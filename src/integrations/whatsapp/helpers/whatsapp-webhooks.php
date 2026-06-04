<?php

namespace Uncanny_Automator\Integrations\WhatsApp;

use Exception;
use Uncanny_Automator\App_Integrations\App_Webhooks;

/**
 * WhatsApp Webhooks
 *
 * @property WhatsApp_Helpers $helpers
 * @property WhatsApp_Api_Caller $api
 */
class WhatsApp_Webhooks extends App_Webhooks {

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set the properties for the webhooks.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override webhook endpoint for legacy compatibility.
		$this->set_webhook_endpoint(
			apply_filters(
				'automator_meta_webhook_endpoint',
				'whatsapp',
				$this->helpers
			)
		);

		// Allow GET requests for validation handshake.
		$this->set_accepts_get_requests( true );
	}

	/**
	 * Get the webhooks enabled status.
	 * - Override for WhatsApp-specific logic.
	 * - WhatsApp webhooks are enabled when connected (no separate toggle needed).
	 *
	 * @return bool
	 */
	public function get_webhooks_enabled_status() {
		return $this->is_connected;
	}

	/**
	 * Validate the webhook request.
	 * - Override for WhatsApp-specific validation logic.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool
	 * @throws Exception If validation fails.
	 */
	protected function validate_webhook_request( $request ) {
		// Validate user agent.
		$user_agent          = $request->get_header( 'user_agent' );
		$allowed_user_agents = array(
			'facebookexternalua',
			'facebookplatform/1.0 (+http://developers.facebook.com)',
			'facebookexternalua X-Middleton/1',
			'facebookplatform/1.0 (+http://developers.facebook.com) X-Middleton/1',
			'Webhooks/1.0 (https://fb.me/webhooks)',
		);

		if ( ! in_array( $user_agent, $allowed_user_agents, true ) ) {
			return false;
		}

		// Handle hub challenge verification handshake (can be GET or POST).
		$challenge = $request->get_param( 'hub_challenge' );
		if ( ! empty( $challenge ) ) {
			if ( ! $this->is_valid_webhook_key( $request->get_param( 'hub_verify_token' ) ) ) {
				throw new Exception( 'Invalid verify token' );
			}
			// Meta requires a plain text response — a JSON-wrapped WP_REST_Response is rejected.
			echo esc_html( $challenge );
			exit;
		}

		return true;
	}

	/**
	 * Process the webhook request during shutdown.
	 *
	 * Overrides the default do_action behavior to handle WhatsApp-specific
	 * recipe completion logic (response extraction, action lookup, status updates).
	 *
	 * @param string $action_name   The action name (unused).
	 * @param array  $action_params The action parameters containing the webhook payload.
	 *
	 * @return void
	 */
	protected function process_webhook_request( $action_name, $action_params ) {
		// Early bail for unsupported webhook types (e.g., message_template_status_update).
		// We only process webhooks with 'messages' (incoming) or 'statuses' (delivery updates).
		if ( ! $this->is_supported_webhook_type( $action_params[0] ) ) {
			return;
		}

		$this->complete_recipe( $action_params[0] );
	}

	////////////////////////////////////////////////////////////
	// Recipe completion
	////////////////////////////////////////////////////////////

	/**
	 * Complete the recipe depending on incoming WhatsApp webhook data.
	 *
	 * @param array $response The incoming webhook data from WhatsApp.
	 *
	 * @return void
	 */
	private function complete_recipe( $response ) {
		try {
			$payload     = $this->extract_webhook_payload( $response );
			$action_data = $this->helpers->get_action_data_by_wamid( $payload['wamid'] );

			// Bail out if meta is empty.
			if ( empty( $action_data['meta'] ) ) {
				automator_log( $action_data, 'WhatsApp: Bailed out. The $action_data["meta"] was empty.', AUTOMATOR_DEBUG_MODE, 'whatsapp' );
				return;
			}

			// Complete the action based on webhook status.
			$this->complete_whatsapp_action( $action_data, $payload );

		} catch ( Exception $e ) {
			automator_log( $e->getMessage(), 'WhatsApp: An Exception has occurred', AUTOMATOR_DEBUG_MODE, 'whatsapp' );
		}
	}

	/**
	 * Complete a WhatsApp action based on webhook data.
	 *
	 * Marks the action and recipe as complete (or failed) in the database
	 * and fires status hooks for trigger consumption.
	 *
	 * @param array $action_data The action data containing recipe and action IDs.
	 * @param array $payload     The extracted webhook payload.
	 *
	 * @return void
	 */
	private function complete_whatsapp_action( $action_data = array(), $payload = array() ) {
		$completed     = 1;
		$error_message = '';
		$status        = $payload['status'] ?? '';
		$action_id     = $action_data['action_id'] ?? '';
		$log_id        = $action_data['recipe_log_id'] ?? '';

		// Handle failed status.
		if ( 'failed' === $status ) {
			$completed = 2;

			// Build error message from webhook payload.
			$errors = $payload['errors'] ?? array();
			if ( ! empty( $errors['code'] ) || ! empty( $errors['message'] ) ) {
				$error_message = sprintf(
					'%s - %s',
					$errors['code'] ?? '',
					$errors['message'] ?? ''
				);
			}

			// Check if there's an existing error message to preserve.
			$existing_error = Automator()->db->action->get_error_message( $log_id );
			if ( ! empty( $existing_error ) && 10 !== intval( $existing_error->completed ) ) {
				// Append existing error message (skip if it was just "awaiting" status).
				if ( ! empty( $existing_error->error_message ) && $error_message !== $existing_error->error_message ) {
					$error_message .= '<br>' . $existing_error->error_message;
				}
			}
		}

		Automator()->recipe_runner->complete_action( absint( $action_id ), absint( $log_id ), $completed, $error_message );
		Automator()->recipe_runner->finalize_recipe_by_log_id( absint( $log_id ) );

		// e.g. `automator_whatsapp_message_delivery_failed`.
		do_action( 'automator_whatsapp_message_delivery_' . $status, $payload );
		do_action( 'automator_whatsapp_message_status', $payload, $status );
	}

	////////////////////////////////////////////////////////////
	// Webhook payload extraction
	////////////////////////////////////////////////////////////

	/**
	 * Get the value array from a webhook response.
	 *
	 * @param array $response The raw webhook response from Meta.
	 *
	 * @return array The value array, or empty array if not found.
	 */
	private function get_webhook_value( $response ) {
		return $response['entry'][0]['changes'][0]['value'] ?? array();
	}

	/**
	 * Check if the webhook is a type we support processing.
	 *
	 * We only process webhooks containing 'messages' (incoming messages)
	 * or 'statuses' (delivery status updates). Other webhook types like
	 * 'message_template_status_update' are ignored.
	 *
	 * @param array $response The raw webhook response from Meta.
	 *
	 * @return bool True if supported, false otherwise.
	 */
	private function is_supported_webhook_type( $response ) {
		$value = $this->get_webhook_value( $response );

		return isset( $value['messages'] ) || isset( $value['statuses'] );
	}

	/**
	 * Validate and extract a structured payload from the raw webhook response.
	 *
	 * Routes to the appropriate extraction method based on whether the
	 * webhook contains an incoming message or an outgoing status update.
	 *
	 * @param array $response The raw webhook response from Meta.
	 *
	 * @return array The extracted payload data.
	 * @throws Exception If the response structure is invalid.
	 */
	private function extract_webhook_payload( $response = array() ) {
		if ( ! $this->is_webhook_response_valid( $response ) ) {
			throw new Exception( 'Malformed data.', 403 );
		}

		$value = $this->get_webhook_value( $response );
		if ( isset( $value['messages'] ) ) {
			return $this->extract_incoming_message_payload( $response );
		}

		return $this->extract_status_update_payload( $response );
	}

	/**
	 * Extract payload data from an incoming message webhook.
	 *
	 * Validates the timestamp to prevent processing stale/duplicate data,
	 * extracts the message body, and fires the message received action hook.
	 *
	 * @param array $response The raw webhook response from Meta.
	 *
	 * @return array The extracted incoming message data.
	 * @throws Exception If the timestamp is stale.
	 */
	private function extract_incoming_message_payload( $response = array() ) {
		$value     = $this->get_webhook_value( $response );
		$message   = $value['messages'][0];
		$timestamp = $message['timestamp'];

		$default = array(
			'from'      => '',
			'wamid'     => 0,
			'body'      => '',
			'timestamp' => '',
		);

		$text_body = $this->extract_message_body( $message );

		$args = array(
			'from'      => $message['from'],
			'wamid'     => $message['id'],
			'body'      => $text_body,
			'timestamp' => $timestamp,
			'_response' => $response, // Send the whole response to the Trigger.
		);

		/**
		 * Action to be triggered when a message is received from WhatsApp.
		 *
		 * Fire this BEFORE timestamp validation so triggers always have a chance to run.
		 * Triggers have their own deduplication via is_unique_webhook_event().
		 *
		 * @param array $args The extracted message data.
		 */
		do_action( 'automator_whatsapp_webhook_message_received', $args );

		/**
		 * Filters the acceptable time interval for incoming message webhook processing.
		 *
		 * Webhooks with timestamps older than this interval are rejected as stale.
		 * This prevents duplicate action processing from Meta's repeated webhook deliveries.
		 * Default is 60 seconds to accommodate typical delivery delays from Meta.
		 *
		 * @param int   $interval The acceptable time interval in seconds. Default 60.
		 * @param array $response The webhook response data.
		 * @param WhatsApp_Helpers $helpers The WhatsApp helpers instance.
		 */
		$interval = apply_filters( 'automator_whatsapp_acceptable_interval', 60, $response, $this->helpers );

		/**
		 * Filters whether to validate webhook timestamps.
		 *
		 * Disable timestamp validation if webhooks are being rejected due to timing issues.
		 *
		 * @param bool  $validate Whether to validate timestamps. Default true.
		 * @param array $response The webhook response data.
		 * @param WhatsApp_Helpers $helpers The WhatsApp helpers instance.
		 */
		$validate = apply_filters( 'automator_whatsapp_timestamp_validation_enabled', true, $response, $this->helpers );

		if ( true === $validate && ! $this->is_timestamp_acceptable( $timestamp, $interval ) ) {
			throw new Exception( 'Stale data: WhatsApp Bug. Do not process.' );
		}

		return wp_parse_args( $args, $default );
	}

	/**
	 * Extract payload data from an outgoing message status update webhook.
	 *
	 * Validates the timestamp and extracts status information including
	 * delivery errors if present.
	 *
	 * @param array $response The raw webhook response from Meta.
	 *
	 * @return array The extracted status update data.
	 * @throws Exception If the timestamp is stale.
	 */
	private function extract_status_update_payload( $response ) {
		$value     = $this->get_webhook_value( $response );
		$statuses  = $value['statuses'][0];
		$metadata  = $value['metadata'] ?? array();
		$timestamp = $statuses['timestamp'];

		/**
		 * Filters the acceptable time interval for status update webhook processing.
		 *
		 * Status updates may arrive with some delay from Meta's servers.
		 * Default is 60 seconds to accommodate typical delivery delays.
		 *
		 * @param int   $interval The acceptable time interval in seconds. Default 60.
		 * @param array $response The webhook response data.
		 * @param WhatsApp_Helpers $helpers The WhatsApp helpers instance.
		 */
		$interval = apply_filters( 'automator_whatsapp_status_acceptable_interval', 60, $response, $this->helpers );

		// Prevent spammy WhatsApp webhook (but allow reasonable delay for status updates).
		if ( ! $this->is_timestamp_acceptable( $timestamp, $interval ) ) {
			throw new Exception( 'Stale data: WhatsApp Bug. Do not process.' );
		}

		$default = array(
			'to'        => '',
			'from'      => '',
			'wamid'     => 0,
			'object'    => 'unknown',
			'status'    => '',
			'errors'    => array(
				'code'  => null,
				'title' => '',
			),
			'entry_id'  => 0,
			'timestamp' => 0,
		);

		$args = array(
			'to'        => $statuses['recipient_id'] ?? '',
			'from'      => $metadata['display_phone_number'] ?? '',
			'object'    => 'whatsapp',
			'status'    => $statuses['status'] ?? '',
			'wamid'     => $statuses['id'] ?? '',
			'entry_id'  => $response['entry'][0]['id'] ?? '',
			'timestamp' => $timestamp,
			'errors'    => $this->extract_status_errors( $response ),
		);

		return wp_parse_args( $args, $default );
	}

	/**
	 * Extract error details from a status update webhook response.
	 *
	 * @param array $response The raw webhook response from Meta.
	 *
	 * @return array The error data with 'code' and 'message' keys.
	 */
	private function extract_status_errors( $response = array() ) {
		$error = array(
			'code'    => null,
			'message' => '',
		);

		$value  = $this->get_webhook_value( $response );
		$errors = $value['statuses'][0]['errors'][0] ?? array();

		if ( ! empty( $errors['code'] ) || ! empty( $errors['title'] ) ) {
			$error['code']    = $errors['code'] ?? null;
			$error['message'] = $errors['title'] ?? '';
		}

		return $error;
	}

	/**
	 * Extract the text body from an incoming WhatsApp message.
	 *
	 * Handles different message types (text, image, button) and returns
	 * a string representation of the message content.
	 *
	 * @param array $message The message data from the webhook payload.
	 *
	 * @return string The extracted message body text.
	 */
	private function extract_message_body( $message ) {
		$type = $message['type'] ?? 'text';

		switch ( $type ) {
			case 'text':
				// Return the text body.
				return $message['text']['body'] ?? '';
			case 'image':
				$caption          = $message['image']['caption'] ?? '';
				$image_id         = $message['image']['id'] ?? '';
				$image_id_caption = sprintf( '(%1$s) %2$s', $image_id, $caption );
				/**
				 * Filters the image caption.
				 *
				 * @param string $image_id_caption The image id + caption.
				 * @param array  $message          The message data.
				 */
				return apply_filters( 'automator_whatsapp_image_caption', $image_id_caption, $message );
			case 'button':
				$button_text = $message['button']['text'] ?? '';
				/**
				 * Filters the button text.
				 *
				 * @param string $button_text The button text.
				 * @param array  $message     The message data.
				 */
				return apply_filters( 'automator_whatsapp_button_text', $button_text, $message );
			default:
				// Otherwise, just return the type and message ID for now.
				$default = sprintf( '(%s) %s', $type, $message['id'] );
				/**
				 * Filters the default type message return.
				 *
				 * @param string $default The default type message return.
				 * @param array  $message The message data.
				 */
				return apply_filters( 'automator_whatsapp_default_type_message_return', $default, $message );
		}
	}

	/**
	 * Check if the incoming webhook response has a valid structure.
	 *
	 * Validates that the response contains the expected Meta webhook
	 * structure for either an incoming message or a status update.
	 *
	 * @param array $response The incoming data parsed as JSON.
	 *
	 * @return bool True if the response structure is valid, false otherwise.
	 */
	private function is_webhook_response_valid( $response = array() ) {
		// Validate base entry structure.
		$entry = $response['entry'][0] ?? array();
		if ( empty( $entry ) || ! isset( $entry['id'], $entry['changes'][0] ) ) {
			return false;
		}

		$value = $this->get_webhook_value( $response );

		// Valid if incoming message.
		if ( isset( $value['messages'] ) ) {
			return true;
		}

		// Valid if status update with required fields.
		return isset( $value['metadata'] ) && isset( $value['statuses'][0] );
	}

	////////////////////////////////////////////////////////////
	// Trigger helpers
	////////////////////////////////////////////////////////////

	/**
	 * Check if a webhook event is unique (not a duplicate).
	 *
	 * Uses a 60-second transient lock to prevent duplicate webhook processing.
	 * WhatsApp may deliver the same webhook payload multiple times, so this
	 * method ensures each event is only processed once.
	 *
	 * @param string $wamid  The WhatsApp message ID.
	 * @param string $suffix A context suffix for the transient key (e.g. 'incoming', 'sent').
	 *
	 * @return bool True if the event is unique and should be processed, false if duplicate.
	 */
	public function is_unique_webhook_event( $wamid, $suffix ) {
		$name = 'automator_whatsapp_' . $wamid . '_' . $suffix;
		if ( false !== get_transient( $name ) ) {
			return false;
		}

		set_transient( $name, 'yes', 60 );

		// Schedule transient cleanup after expiry.
		wp_schedule_single_event( time() + 60, 'automator_whatsapp_flush_transient', array( $name ) );

		return true;
	}
}

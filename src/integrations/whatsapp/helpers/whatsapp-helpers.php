<?php
namespace Uncanny_Automator\Integrations\WhatsApp;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class WhatsApp_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property WhatsApp_Api_Caller $api
 */
class WhatsApp_Helpers extends App_Helpers {

	/**
	 * Phone ID option name.
	 *
	 * @var string
	 */
	const PHONE_ID = 'automator_whatsapp_phone_id';

	/**
	 * Business ID option name.
	 *
	 * @var string
	 */
	const BUSINESS_ID = 'automator_whatsapp_business_account_id';

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set the properties for the WhatsApp integration.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Backward compatibility with old option names.
		$this->set_credentials_option_name( 'automator_whatsapp_access_token' );
		$this->set_account_option_name( 'automator_whatsapp_client' );
	}

	/**
	 * Validate and normalize account info.
	 *
	 * @param mixed $account_info The raw account info from storage.
	 *
	 * @return array The normalized account info.
	 */
	public function validate_account_info( $account_info ) {
		return ! empty( $account_info['data']['data'] ) ? $account_info['data']['data'] : array();
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Get the knowledge base URL
	 *
	 * @param string $medium The UTM medium.
	 * @param string $content The UTM content.
	 * @param string $section The section of the knowledge base URL.
	 *
	 * @return string
	 */
	public function get_knowledgebase_url( $medium = '', $content = '', $section = '' ) {
		$url = "https://automatorplugin.com/knowledge-base/whatsapp";

		// Add section if provided
		if ( ! empty( $section ) ) {
			$url .= "#" . $section;
		}

		if ( empty( $medium ) && empty( $content ) ) {
			return $url;
		}

		return automator_utm_parameters( $url, $medium, $content );
	}

	/**
	 * Get the configured WhatsApp phone number id
	 *
	 * @return int
	 */
	public function get_phone_number_id() {
		return absint( automator_get_option( self::PHONE_ID, 0 ) );
	}

	/**
	 * Get the configured WhatsApp business account id
	 *
	 * @return string
	 */
	public function get_business_account_id() {
		return trim( automator_get_option( self::BUSINESS_ID, '' ) );
	}

	/**
	 * Callback for the `automator_whatsapp_flush_transient` scheduled action hook.
	 *
	 * Used via wp_schedule_single_event() to clean up transients after they expire.
	 *
	 * @param string $name The name of the transient to delete.
	 *
	 * @return void
	 */
	public function flush_transient( $name = '' ) {
		delete_transient( $name );
	}

	/**
	 * Check if the client has all required WhatsApp scopes
	 *
	 * @param array $client - The client data containing scopes.
	 *
	 * @return bool True if any required scope is missing, false if all required scopes are present
	 */
	public function has_missing_scopes( $client = array() ) {
		$required_scopes = array(
			'whatsapp_business_management',
			'whatsapp_business_messaging',
		);

		// Return true if client data is invalid.
		$scopes = $client['scopes'] ?? array();
		if ( empty( $scopes ) ) {
			return true;
		}

		// Check if all required scopes are present
		$granted_scopes = array_intersect( $scopes, $required_scopes );
		return count( $granted_scopes ) !== count( $required_scopes );
	}

	/**
	 * Simplify error message for the recipe editor UI.
	 *
	 * Strips markdown-style links and provides simpler token expiration messages
	 * that are appropriate for the recipe editor UI.
	 *
	 * @param string $message The error message to simplify.
	 *
	 * @return string The simplified error message.
	 */
	private function simplify_error_message( $message ) {
		$message_lower = strtolower( $message );

		// Check for token-related errors and return a simple message.
		$token_patterns = array( 'access token', 'session has expired' );
		foreach ( $token_patterns as $pattern ) {
			if ( false !== strpos( $message_lower, $pattern ) ) {
				return esc_html_x( 'Your WhatsApp connection has expired. Please reconnect in the settings.', 'WhatsApp', 'uncanny-automator' );
			}
		}

		// Strip markdown-style links [text](url) → text.
		$message = preg_replace( '/\[([^\]]+)\]\([^)]+\)/', '$1', $message );

		return $message;
	}

	/**
	 * Return the list of approved message templates as dropdown options.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_message_templates( $request ): array {
		$option_key    = $this->get_option_key( 'templates' );
		$refresh_check = $request->is_refresh() ? 0 : DAY_IN_SECONDS;
		$option_data   = $this->get_app_option( $option_key, $refresh_check );

		if ( ! empty( $option_data['data'] ) && ! $option_data['refresh'] ) {
			return $this->remote_data_success( $option_data['data'] );
		}

		try {
			$response = $this->api->fetch_templates();
			$options  = $this->format_templates_as_dropdown_options( $response );
			$this->save_app_option( $option_key, $options );
			return $this->remote_data_success( $options );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $this->simplify_error_message( $e->getMessage() ) );
		}
	}

	/**
	 * Format the template list API response as dropdown options for the UI.
	 *
	 * @param array $response The WhatsApp API response from fetch_templates().
	 *
	 * @return array The formatted dropdown options.
	 */
	private function format_templates_as_dropdown_options( $response ) {
		$list     = array();
		$response = $response['data']['data'] ?? array();
		if ( ! empty( $response ) ) {
			foreach ( $response as $data ) {
				if ( 'APPROVED' === $data['status'] ) {
					$list[] = array(
						'text'  => sprintf( '%1$s (%2$s)', $data['name'], $data['language'] ),
						'value' => $data['name'] . '|' . $data['language'],
					);
				}
			}
		}

		return $list;
	}

	/**
	 * Return the template repeater rows for the requesting field (HEADER/BODY/BUTTON variables).
	 *
	 * Formatting is delegated to WHATSAPP_SEND_MESSAGE_TEMPLATE::format_repeater_rows().
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_template_repeater_data( $request ): array {
		try {
			$values   = $request->get_values();
			$group_id = $request->get_group_id();
			$field_id = $request->get_field_id();
			$template = isset( $values[ $group_id ] ) ? sanitize_textarea_field( $values[ $group_id ] ) : '';

			if ( empty( $template ) ) {
				throw new Exception(
					esc_html_x( 'Please select a template above.', 'WhatsApp', 'uncanny-automator' )
				);
			}

			if ( false === strpos( $template, '|' ) ) {
				throw new Exception(
					esc_html_x( 'Invalid template selected. Please refresh and try again.', 'WhatsApp', 'uncanny-automator' )
				);
			}

			list( $template_name, $language ) = explode( '|', $template, 2 );

			if ( empty( $template_name ) || empty( $language ) ) {
				throw new Exception(
					esc_html_x( 'Invalid template selected. Please refresh and try again.', 'WhatsApp', 'uncanny-automator' )
				);
			}

			// Fetch template with caching (all 3 repeaters will use same cached data).
			$template_data = $this->get_cached_template( $template_name, $language, $request->is_refresh() );
			$components    = $template_data['components'] ?? array();
			$rows          = WHATSAPP_SEND_MESSAGE_TEMPLATE::format_repeater_rows( $components, $field_id, $values );

			if ( empty( $rows ) ) {
				$name = ucfirst( strtolower( str_replace( '_VARIABLES', '', $field_id ) ) );
				throw new Exception(
					sprintf(
						// translators: %s: The name of the template section (e.g. Header, Body, Buttons).
						esc_html_x( 'No %s variables found in the template.', 'WhatsApp', 'uncanny-automator' ),
						$name
					)
				);
			}

			return $this->remote_data_success( $rows, 'rows' );

		} catch ( Exception $e ) {
			return $this->remote_data_error( $this->simplify_error_message( $e->getMessage() ), 'rows' );
		}
	}

	/**
	 * Get cached template data to avoid multiple API calls.
	 *
	 * All 3 repeaters (HEADER, BODY, BUTTONS) hit this endpoint when a template
	 * is selected. Cached for 1 day to reduce API calls; use refresh to bypass.
	 *
	 * @param string $template_name The template name.
	 * @param string $language The template language.
	 * @param bool   $is_refresh Whether to bypass the cache.
	 *
	 * @return array The template data.
	 *
	 * @throws Exception If template not found.
	 */
	private function get_cached_template( $template_name, $language, $is_refresh = false ) {
		$option_key    = $this->get_option_key( 'template_' . md5( $template_name . '|' . $language ) );
		$refresh_check = $is_refresh ? 0 : DAY_IN_SECONDS;
		$option_data   = $this->get_app_option( $option_key, $refresh_check );

		if ( ! empty( $option_data['data'] ) && ! $option_data['refresh'] ) {
			return $option_data['data'];
		}

		// Fetch fresh template data.
		$template = $this->api->fetch_template( $template_name, $language );
		$this->save_app_option( $option_key, $template );

		return $template;
	}

	////////////////////////////////////////////////////////////
	// Action status and meta filter and action handlers
	////////////////////////////////////////////////////////////

	/**
	 * Filter the error message for WhatsApp actions.
	 *
	 * @param string $message The message.
	 * @param int $user_id The user id.
	 * @param array $action_data The action data.
	 * @param int $recipe_id The recipe id.
	 * @param string $error_message The error message.
	 * @param int $recipe_log_id The recipe log id.
	 * @param array $args The args.
	 *
	 * @return string The message.
	 */
	public function filter_whatsapp_error_message( $message, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args ) {
		$whatsapp_codes = array(
			'WHATSAPP_SEND_MESSAGE_CODE',
			'WHATSAPP_SEND_MESSAGE_TEMPLATE_CODE',
		);

		if ( ! isset( $action_data['meta']['code'] ) || ! in_array( $action_data['meta']['code'], $whatsapp_codes, true ) ) {
			return $message;
		}

		if ( key_exists( 'await', $args ) ) {
			$message = esc_html_x( 'Message sent. Waiting for response. The status will be updated once the response is received.', 'WhatsApp', 'uncanny-automator' );
		}

		return $message;
	}

	/**
	 * Filter the completed status for WhatsApp actions.
	 *
	 * @param int $completed The completed status.
	 * @param int $user_id The user id.
	 * @param array $action_data The action data.
	 * @param int $recipe_id The recipe id.
	 * @param string $error_message The error message.
	 * @param int $recipe_log_id The recipe log id.
	 * @param array $args The args.
	 *
	 * @return int The completed status.
	 */
	public function filter_whatsapp_completed_status( $completed, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args ) {
		$whatsapp_codes = array(
			'WHATSAPP_SEND_MESSAGE_CODE',
			'WHATSAPP_SEND_MESSAGE_TEMPLATE_CODE',
		);

		if ( ! isset( $action_data['meta']['code'] ) || ! in_array( $action_data['meta']['code'], $whatsapp_codes, true ) ) {
			return $completed;
		}

		if ( key_exists( 'await', $args ) ) {
			$completed = 10;
		}

		return $completed;
	}

	/**
	 * Filter the action completed label for WhatsApp actions.
	 *
	 * @param array $labels The labels.
	 *
	 * @return array The labels.
	 */
	public function filter_whatsapp_action_completed_label( $labels = array() ) {
		$labels[10] = esc_html_x( 'Completed, pending response', 'WhatsApp', 'uncanny-automator' );
		return $labels;
	}

	/**
	 * Handle the no response closure for WhatsApp actions.
	 *
	 * @param array $response The response from the API.
	 *
	 * @return void
	 */
	public function handle_whatsapp_noresponse_closure( $response ) {
		if ( empty( $response['data']['messages'][0]['id'] ) ) {
			return;
		}

		$action_data = $this->get_action_data_by_wamid( $response['data']['messages'][0]['id'] );

		if ( empty( $action_data ) ) {
			return;
		}

		$error_message        = esc_html_x( 'No response was received from Meta after 1 minute. Make sure you have set-up your webhook configuration correctly.', 'WhatsApp', 'uncanny-automator' );
		$recipe_error_message = Automator()->db->action->get_error_message( $action_data['recipe_log_id'] );

		if ( ! empty( $recipe_error_message ) && 10 === intval( $recipe_error_message->completed ) ) {
			Automator()->recipe_runner->complete_action( absint( $action_data['action_id'] ), absint( $action_data['recipe_log_id'] ), 1, $error_message );
			Automator()->recipe_runner->finalize_recipe_by_log_id( absint( $action_data['recipe_log_id'] ) );
		}
	}

	/**
	 * Look up action data by WhatsApp message ID (wamid).
	 *
	 * @param string $wamid The WhatsApp message ID.
	 *
	 * @return array The action data, or empty array if not found.
	 */
	public function get_action_data_by_wamid( $wamid = '' ) {
		global $wpdb;

		$wamid_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_action_log_meta WHERE meta_key = %s AND meta_value = %s",
				'whatsapp_wamid',
				$wamid
			)
		);

		if ( empty( $wamid_row ) ) {
			return array();
		}

		$meta = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_action_log_meta WHERE meta_key = %s AND automator_action_log_id = %d AND automator_action_id = %d",
				'whatsapp_meta',
				$wamid_row->automator_action_log_id,
				$wamid_row->automator_action_id
			)
		);

		if ( empty( $meta ) ) {
			return array();
		}

		$action_meta = json_decode( $meta->meta_value );

		return array(
			'action_id'     => $wamid_row->automator_action_id,
			'recipe_log_id' => $action_meta->recipe_log_id,
			'meta'          => $action_meta,
		);
	}

	/**
	 * Persist the WAMID and meta data after action creation.
	 * Used for both send message and send message template actions.
	 *
	 * @param array $action_arguments The action arguments.
	 *
	 * @return void
	 */
	public function persist_whatsapp_action_meta( $action_arguments = array() ) {
		if ( empty( $action_arguments['args']['await'] ) ) {
			return;
		}
		Automator()->db->action->add_meta(
			$action_arguments['user_id'],
			$action_arguments['action_log_id'],
			$action_arguments['action_id'],
			'whatsapp_meta',
			wp_json_encode( $action_arguments['args'] )
		);
		Automator()->db->action->add_meta(
			$action_arguments['user_id'],
			$action_arguments['action_log_id'],
			$action_arguments['action_id'],
			'whatsapp_wamid',
			$action_arguments['args']['await']['whatsapp_response']['data']['messages'][0]['id']
		);
	}
}

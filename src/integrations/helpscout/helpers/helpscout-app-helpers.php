<?php

namespace Uncanny_Automator\Integrations\Helpscout;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Helpscout_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Helpscout_Api_Caller $api
 * @property Helpscout_Webhooks $webhooks
 */
class Helpscout_App_Helpers extends App_Helpers {

	/**
	 * Transient keys
	 */
	const TRANSIENT_MAILBOXES    = 'automator_helpscout_mailboxes_v68';
	const TRANSIENT_EXPIRES_TIME = 3600; // 1 hour in seconds.

	/**
	 * Set properties - NO constructor allowed!
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set custom option name to match existing data.
		$this->set_credentials_option_name( 'automator_helpscout_client' );
	}

	/**
	 * Get account info for settings display - client user
	 *
	 * @return array
	 */
	public function get_account_info() {
		$credentials = $this->get_credentials();
		return is_array( $credentials['user'] )
			? $credentials['user'] ?? array()
			: array();
	}

	/**
	 * Prepare credentials for storage
	 *
	 * @param array $credentials
	 *
	 * @return array
	 */
	public function prepare_credentials_for_storage( $credentials ) {
		// Set token expiration.
		$credentials['expires_on'] = strtotime( current_time( 'mysql' ) ) + $credentials['expires_in'];
		return $credentials;
	}

	/**
	 * AJAX handler - Fetch tags
	 * TODO: REVIEW - This is not used anywhere.
	 *
	 * @return void
	 */
	public function ajax_fetch_tags() {
		Automator()->utilities->ajax_auth_check();

		try {
			$tags = $this->api->get_tags();

			$options = array(
				array(
					'value' => '-1',
					'text'  => esc_html_x( 'Any tag', 'Help Scout', 'uncanny-automator' ),
				),
			);

			if ( ! empty( $tags ) ) {
				foreach ( $tags as $tag ) {
					$options[] = array(
						'value' => $tag['id'],
						'text'  => $tag['name'],
					);
				}
			}
		} catch ( Exception $e ) {
			$options = $this->get_default_error_options( $e );
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * AJAX handler - Fetch conversations
	 *
	 * @return void
	 */
	public function ajax_fetch_conversations() {
		Automator()->utilities->ajax_auth_check();

		// Handle new AJAX format - get mailbox from values array
		$values     = automator_filter_input_array( 'values', INPUT_POST );
		$mailbox_id = isset( $values['MAILBOX'] ) ? intval( $values['MAILBOX'] ) : intval( automator_filter_input( 'value', INPUT_POST ) );

		// Check if request is from an action by checking post type.
		$is_action = $this->is_ajax_request_from_action();

		$options = array();

		// Only add "Any conversation" option for triggers, not actions.
		if ( ! $is_action ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any conversation', 'Help Scout', 'uncanny-automator' ),
			);
		}

		// If "Any conversation" is selected, return empty options.
		if ( intval( '-1' ) === intval( $mailbox_id ) ) {
			$response = array(
				'success' => true,
				'options' => $options,
			);
			wp_send_json( $response );
		}

		try {
			$conversations = $this->api->get_conversations( $mailbox_id );
			if ( ! empty( $conversations ) ) {
				foreach ( $conversations as $conversation ) {
					$options[] = array(
						'value' => $conversation['id'],
						'text'  => sprintf( '#%d %s', $conversation['number'], $conversation['subject'] ),
					);
				}
			}
		} catch ( Exception $e ) {
			$options = $this->get_default_error_options( $e );
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * AJAX handler - Fetch mailbox users
	 *
	 * @return void
	 */
	public function ajax_fetch_mailbox_users() {
		Automator()->utilities->ajax_auth_check();

		// Handle new AJAX format - get mailbox from values array
		$values     = automator_filter_input_array( 'values', INPUT_POST );
		$mailbox_id = isset( $values['HELPSCOUT_CONVERSATION_CREATE_META'] ) ? $values['HELPSCOUT_CONVERSATION_CREATE_META'] :
					( isset( $values['CREATED_BY'] ) ? $values['CREATED_BY'] : automator_filter_input( 'value', INPUT_POST ) );

		$from_created_by_field = false;

		// Check if request is from an action by checking post type
		$is_action = $this->is_ajax_request_from_action();

		$options = array(
			array(
				'text'  => esc_attr_x( 'Customer', 'HelpScout', 'uncanny-automator' ),
				'value' => $mailbox_id . '|_CUSTOMER_',
			),
		);

		// Get mailbox id coming from `Created by` field.
		if ( false !== strpos( $mailbox_id, '|' ) ) {
			$from_created_by_field           = true;
			list( $mailbox_id, $created_by ) = explode( '|', $mailbox_id );
		}

		if ( $from_created_by_field && ! $is_action ) {
			// Only add "Anyone" option for triggers, not actions
			$options[0] = array(
				'text'  => esc_attr_x( 'Anyone', 'HelpScout', 'uncanny-automator' ),
				'value' => '_ANYONE_',
			);
		}

		try {
			$users = $this->api->get_mailbox_users( $mailbox_id );
			if ( ! empty( $users ) ) {
				foreach ( $users as $user ) {
					$options[] = array(
						'text'  => implode( ' ', array( $user['firstName'], $user['lastName'] ) ) . ' - ' . $user['email'] . ' (' . $user['role'] . ')',
						'value' => $mailbox_id . '|' . $user['id'],
					);
				}
			}
		} catch ( Exception $e ) {
			$options = $this->get_default_error_options( $e );
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * AJAX handler - Fetch properties for repeater field.
	 *
	 * @return void
	 */
	public function ajax_fetch_properties() {
		Automator()->utilities->ajax_auth_check();

		try {
			$rows       = array();
			$properties = $this->api->get_properties();
			if ( ! empty( $properties ) ) {
				foreach ( $properties as $property ) {
					$rows[] = array(
						'PROPERTY_SLUG'  => $property['slug'],
						'PROPERTY_NAME'  => $property['name'],
						'PROPERTY_VALUE' => '',
					);
				}
			}

			wp_send_json(
				array(
					'success' => true,
					'rows'    => $rows,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'message' => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * Get mailboxes with caching
	 *
	 * @param bool $any_mailbox
	 * @return array
	 */
	public function get_mailboxes( $any_mailbox = false ) {
		$cache_key = self::TRANSIENT_MAILBOXES . ( $any_mailbox ? '_any' : '_not_any' );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		try {
			$mailboxes = $this->api->get_mailboxes();

			$options = array();

			if ( $any_mailbox ) {
				$options[] = array(
					'value' => '-1',
					'text'  => esc_html_x( 'Any mailbox', 'Help Scout', 'uncanny-automator' ),
				);
			}

			if ( ! empty( $mailboxes ) ) {
				foreach ( $mailboxes as $mailbox ) {
					$options[] = array(
						'value' => $mailbox['id'],
						'text'  => $mailbox['name'],
					);
				}
			}

			set_transient( $cache_key, $options, self::TRANSIENT_EXPIRES_TIME );

			return $options;

		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Format date timestamp
	 *
	 * @param int $timestamp
	 * @return string
	 */
	public function format_date_timestamp( $timestamp ) {
		if ( empty( $timestamp ) ) {
			return '';
		}

		return gmdate( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Get default select options for error.
	 *
	 * @param Exception $e
	 *
	 * @return array
	 */
	private function get_default_error_options( $e ) {
		return array(
			array(
				'text'  => sprintf(
					// translators: %s: error message
					esc_html_x( 'An unexpected error has been encountered. %s', 'Help Scout', 'uncanny-automator' ),
					esc_html( $e->getMessage() )
				),
				'value' => sprintf(
					// translators: %s: error code
					esc_html_x( 'Error: %s', 'Help Scout', 'uncanny-automator' ),
					absint( $e->getCode() )
				),
			),
		);
	}

	/**
	 * Check if the AJAX request is from an action
	 *
	 * @return bool
	 */
	private function is_ajax_request_from_action() {
		$item_id = automator_filter_input( 'item_id', INPUT_POST );
		return $item_id && 'uo-action' === get_post_type( $item_id );
	}
}

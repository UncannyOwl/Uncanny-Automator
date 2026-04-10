<?php

namespace Uncanny_Automator\Integrations\Trello;

use Exception;

/**
 * Class Trello_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Trello_Api_Caller $api
 */
class Trello_App_Helpers extends \Uncanny_Automator\App_Integrations\App_Helpers {

	/**
	 * Meta key constants used across actions.
	 */
	const ACTION_BOARD_META_KEY     = 'BOARD';
	const ACTION_LIST_META_KEY      = 'LIST';
	const ACTION_CARD_META_KEY      = 'CARD';
	const ACTION_CHECKLIST_META_KEY = 'CHECKLIST';

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set properties specific to the Trello integration.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->set_credentials_option_name( 'automator_trello_token' );
	}

	/**
	 * Validate account info.
	 *
	 * If account info is empty, fetch it from the API and store it.
	 * This maintains backward compatibility with the legacy connections that used a transient.
	 *
	 * @param mixed $account_info The account info from the database.
	 *
	 * @return mixed
	 */
	public function validate_account_info( $account_info ) {

		if ( ! empty( $account_info ) ) {
			return $account_info;
		}

		// Only attempt to fetch if we have credentials.
		if ( empty( $this->get_credentials() ) ) {
			return $account_info;
		}

		try {
			return $this->api->fetch_and_store_user();
		} catch ( Exception $e ) {
			return $account_info;
		}
	}

	////////////////////////////////////////////////////////////
	// Option config builders
	////////////////////////////////////////////////////////////

	/**
	 * Get the board option configuration.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_board_option_config( $option_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Board', 'Trello', 'uncanny-automator' ),
			'token_name'            => esc_html_x( 'Board ID', 'Trello', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'placeholder'           => esc_html_x( 'Select a board', 'Trello', 'uncanny-automator' ),
			'default'               => '',
			'options_show_id'       => false,
			'supports_custom_value' => false,
			'relevant_tokens'       => array(),
			'ajax'                  => array(
				'endpoint' => 'automator_trello_get_board_options',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get the list option configuration.
	 *
	 * @param string $option_code    The option code.
	 * @param string $board_meta_key The board meta key to listen to.
	 *
	 * @return array
	 */
	public function get_list_option_config( $option_code, $board_meta_key ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'List', 'Trello', 'uncanny-automator' ),
			'token_name'            => esc_html_x( 'List ID', 'Trello', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'placeholder'           => esc_html_x( 'Select a list', 'Trello', 'uncanny-automator' ),
			'default'               => '',
			'supports_custom_value' => false,
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_board_lists',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $board_meta_key ),
			),
		);
	}

	/**
	 * Get the card option configuration.
	 *
	 * @param string $option_code    The option code.
	 * @param string $list_meta_key  The list meta key to listen to.
	 * @param bool   $supports_custom Whether to support custom values.
	 *
	 * @return array
	 */
	public function get_card_option_config( $option_code, $list_meta_key, $supports_custom = false ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Card', 'Trello', 'uncanny-automator' ),
			'token_name'            => esc_html_x( 'Card ID', 'Trello', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'placeholder'           => esc_html_x( 'Select a card', 'Trello', 'uncanny-automator' ),
			'default'               => '',
			'supports_custom_value' => $supports_custom,
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_list_cards',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $list_meta_key ),
			),
		);
	}

	/**
	 * Get the checklist option configuration.
	 *
	 * @param string $option_code   The option code.
	 * @param string $card_meta_key The card meta key to listen to.
	 *
	 * @return array
	 */
	public function get_checklist_option_config( $option_code, $card_meta_key ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Checklist', 'Trello', 'uncanny-automator' ),
			'token_name'            => esc_html_x( 'Checklist ID', 'Trello', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'placeholder'           => esc_html_x( 'Select a checklist', 'Trello', 'uncanny-automator' ),
			'default'               => '',
			'supports_custom_value' => true,
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_card_checklists',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $card_meta_key ),
			),
		);
	}

	/**
	 * Get the member option configuration.
	 *
	 * @param string $option_code    The option code.
	 * @param string $board_meta_key The board meta key to listen to.
	 * @param bool   $supports_custom Whether to support custom values.
	 * @param bool   $multiple       Whether to support multiple values.
	 *
	 * @return array
	 */
	public function get_member_option_config( $option_code, $board_meta_key, $supports_custom = false, $multiple = false ) {
		$config = array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Member', 'Trello', 'uncanny-automator' ),
			'placeholder'           => esc_html_x( 'No member', 'Trello', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'options'               => array(),
			'supports_custom_value' => $supports_custom,
			'default'               => '',
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_board_members',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $board_meta_key ),
			),
		);

		if ( $multiple ) {
			$config['supports_multiple_values'] = true;
			$config['tokens']                   = true;
			$config['label']                    = esc_html_x( 'Members', 'Trello', 'uncanny-automator' );
		} else {
			$config['token_name']      = esc_html_x( 'Member ID', 'Trello', 'uncanny-automator' );
			$config['options_show_id'] = false;
		}

		return $config;
	}

	/**
	 * Get the label option configuration.
	 *
	 * @param string $option_code    The option code.
	 * @param string $board_meta_key The board meta key to listen to.
	 * @param bool   $supports_custom Whether to support custom values.
	 * @param bool   $multiple       Whether to support multiple values.
	 *
	 * @return array
	 */
	public function get_label_option_config( $option_code, $board_meta_key, $supports_custom = false, $multiple = false ) {
		$config = array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Label', 'Trello', 'uncanny-automator' ),
			'placeholder'           => esc_html_x( 'No label', 'Trello', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'options'               => array(),
			'supports_custom_value' => $supports_custom,
			'default'               => '',
			'options_show_id'       => false,
			'ajax'                  => array(
				'endpoint'      => 'automator_trello_get_board_labels',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $board_meta_key ),
			),
		);

		if ( $multiple ) {
			$config['supports_multiple_values'] = true;
			$config['tokens']                   = true;
			$config['label']                    = esc_html_x( 'Labels', 'Trello', 'uncanny-automator' );
		}

		return $config;
	}

	////////////////////////////////////////////////////////////
	// AJAX handlers
	////////////////////////////////////////////////////////////

	/**
	 * Cache duration for short-lived board-scoped transients.
	 */
	const TRANSIENT_EXPIRY = 15 * MINUTE_IN_SECONDS;

	/**
	 * AJAX handler for board options.
	 *
	 * Uses persistent option cache via get_app_option/save_app_option.
	 *
	 * @return void
	 */
	public function get_board_options_ajax() {

		Automator()->utilities->verify_nonce();

		$option_key = $this->get_option_key( 'boards' );
		$cached     = $this->get_app_option( $option_key, HOUR_IN_SECONDS );

		// Return cached data if available, not expired, and not a refresh request.
		if ( ! empty( $cached['data'] ) && ! $cached['refresh'] && ! $this->is_ajax_refresh() ) {
			$this->ajax_success( $cached['data'] );
		}

		try {
			$options = $this->api->get_boards();

			if ( empty( $options ) ) {
				throw new Exception( esc_html_x( 'No boards were found', 'Trello', 'uncanny-automator' ) );
			}

			$this->save_app_option( $option_key, $options );
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}

		$this->ajax_success( $options );
	}

	/**
	 * AJAX handler for board lists.
	 *
	 * @return void
	 */
	public function get_board_lists_ajax() {

		Automator()->utilities->verify_nonce();

		try {
			$board_id = $this->get_board_from_ajax();
			$options  = $this->get_cached_board_data( 'lists', $board_id, 'get_board_lists' );

			if ( empty( $options ) ) {
				throw new Exception( esc_html_x( 'No lists were found', 'Trello', 'uncanny-automator' ) );
			}
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}

		$this->ajax_success( $options );
	}

	/**
	 * AJAX handler for board members.
	 *
	 * @return void
	 */
	public function get_board_members_ajax() {

		Automator()->utilities->verify_nonce();

		try {
			$board_id = $this->get_board_from_ajax();
			$options  = $this->get_cached_board_data( 'members', $board_id, 'get_board_members' );

			if ( empty( $options ) ) {
				throw new Exception( esc_html_x( 'No members were found in the given board', 'Trello', 'uncanny-automator' ) );
			}
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}

		$this->ajax_success( $options );
	}

	/**
	 * AJAX handler for board labels.
	 *
	 * @return void
	 */
	public function get_board_labels_ajax() {

		Automator()->utilities->verify_nonce();

		try {
			$board_id = $this->get_board_from_ajax();
			$options  = $this->get_cached_board_data( 'labels', $board_id, 'get_board_labels' );

			if ( empty( $options ) ) {
				throw new Exception( esc_html_x( 'No labels were found', 'Trello', 'uncanny-automator' ) );
			}
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}

		$this->ajax_success( $options );
	}

	/**
	 * AJAX handler for list cards.
	 *
	 * No caching — cards change too frequently.
	 *
	 * @return void
	 */
	public function get_list_cards_ajax() {

		Automator()->utilities->verify_nonce();

		try {
			$values = automator_filter_input_array( 'values', INPUT_POST );

			if ( empty( $values['LIST'] ) ) {
				throw new Exception( esc_html_x( 'Please select a list', 'Trello', 'uncanny-automator' ) );
			}

			$options = $this->api->get_list_cards( $values['LIST'] );

			if ( empty( $options ) ) {
				throw new Exception( esc_html_x( 'No cards were found in the given list', 'Trello', 'uncanny-automator' ) );
			}
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}

		$this->ajax_success( $options );
	}

	/**
	 * AJAX handler for card checklists.
	 *
	 * No caching — checklists change too frequently.
	 *
	 * @return void
	 */
	public function get_card_checklists_ajax() {

		Automator()->utilities->verify_nonce();

		try {
			$values = automator_filter_input_array( 'values', INPUT_POST );

			if ( empty( $values['CARD'] ) ) {
				throw new Exception( esc_html_x( 'Please select a card', 'Trello', 'uncanny-automator' ) );
			}

			$options = $this->api->get_card_checklists( $values['CARD'] );

			if ( empty( $options ) ) {
				throw new Exception( esc_html_x( 'No checklists were found on the given card', 'Trello', 'uncanny-automator' ) );
			}
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}

		$this->ajax_success( $options );
	}

	/**
	 * AJAX handler for custom fields repeater.
	 *
	 * @return void
	 */
	public function get_custom_fields_repeater_ajax() {

		Automator()->utilities->verify_nonce();

		try {
			$board_id = $this->get_board_from_ajax();
			$rows     = $this->get_cached_board_data( 'custom_fields', $board_id, 'get_custom_fields' );

			if ( empty( $rows ) ) {
				throw new Exception( esc_html_x( 'No custom fields defined for this board', 'Trello', 'uncanny-automator' ) );
			}

			$this->ajax_success( $rows, 'rows' );
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage(), 'rows' );
		}
	}

	////////////////////////////////////////////////////////////
	// Helper methods
	////////////////////////////////////////////////////////////

	/**
	 * Get cached board-scoped data from a short-lived transient.
	 *
	 * @param string $type       The data type suffix (e.g. 'lists', 'members').
	 * @param string $board_id   The board ID.
	 * @param string $api_method The API caller method name to fetch fresh data.
	 *
	 * @return array
	 * @throws Exception If the API call fails.
	 */
	private function get_cached_board_data( $type, $board_id, $api_method ) {

		$transient_key = $this->get_option_key( $type . '_' . $board_id );
		$cached        = get_transient( $transient_key );

		if ( false !== $cached && ! $this->is_ajax_refresh() ) {
			return $cached;
		}

		$data = $this->api->$api_method( $board_id );

		if ( ! empty( $data ) ) {
			set_transient( $transient_key, $data, self::TRANSIENT_EXPIRY );
		}

		return $data;
	}

	/**
	 * Get the board ID from AJAX POST data.
	 *
	 * @return string
	 * @throws Exception If board is not selected.
	 */
	public function get_board_from_ajax() {
		$values = automator_filter_input_array( 'values', INPUT_POST );

		if ( empty( $values[ self::ACTION_BOARD_META_KEY ] ) ) {
			throw new Exception( esc_html_x( 'Please select a board', 'Trello', 'uncanny-automator' ) );
		}

		return $values[ self::ACTION_BOARD_META_KEY ];
	}

	/**
	 * Convert a JSON array string to a comma-separated string.
	 *
	 * @param string $json_string The JSON string.
	 *
	 * @return string
	 */
	public function comma_separated( $json_string ) {

		$array = json_decode( $json_string, true );

		if ( ! is_array( $array ) ) {
			return $json_string;
		}

		return implode( ',', $array );
	}
}

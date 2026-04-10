<?php

namespace Uncanny_Automator\Integrations\Trello;

use Exception;
use Uncanny_Automator\App_Integrations\Api_Caller;

/**
 * Class Trello_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Trello_App_Helpers $helpers
 */
class Trello_Api_Caller extends Api_Caller {

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->set_credential_request_key( 'api_token' );
	}

	/**
	 * Check for errors in the API response.
	 *
	 * Trello API returns 200 for all successful requests.
	 * Any non-200 status is an error. Uses optional `error_message` arg for context.
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If the response status is not 200.
	 */
	public function check_for_errors( $response, $args = array() ) {

		// Let the parent handle common patterns (400 credential errors etc.)
		parent::check_for_errors( $response, $args );

		$status = $response['statusCode'] ?? 0;

		if ( 200 === absint( $status ) ) {
			return;
		}

		$custom_error_message = $args['error_message'] ?? '';
		$api_message          = $response['data']['message'] ?? '';
		$message_prefix       = ! empty( $custom_error_message ) ? $custom_error_message . ' ' : '';

		if ( ! empty( $api_message ) ) {
			$message = $message_prefix . sprintf(
				// translators: %s is the error message from the Trello API
				esc_html_x( 'Error: %s', 'Trello', 'uncanny-automator' ),
				esc_html( $api_message )
			);
		} else {
			$message = $message_prefix . sprintf(
				// translators: %d is the HTTP status code
				esc_html_x( 'Failed with status code: %d', 'Trello', 'uncanny-automator' ),
				absint( $status )
			);
		}

		throw new Exception( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- All dynamic values are already escaped above.
	}

	////////////////////////////////////////////////////////////
	// API methods
	////////////////////////////////////////////////////////////

	/**
	 * Get the current user info.
	 *
	 * @return array
	 * @throws Exception If the request fails.
	 */
	public function get_user() {

		$response = $this->api_request(
			'user_info',
			null,
			array( 'error_message' => esc_html_x( 'Unable to fetch user info.', 'Trello', 'uncanny-automator' ) )
		);

		return $response['data'];
	}

	/**
	 * Fetch, validate, and store the current user info as account info.
	 *
	 * @return array The user data.
	 * @throws Exception If the user info cannot be fetched or is invalid.
	 */
	public function fetch_and_store_user() {

		$user = $this->get_user();

		if ( empty( $user['id'] ) ) {
			throw new Exception(
				esc_html_x( 'Unable to verify Trello account.', 'Trello', 'uncanny-automator' )
			);
		}

		// Store only the fields we need.
		$account_info = array(
			'id'       => $user['id'],
			'fullName' => $user['fullName'] ?? '',
			'username' => $user['username'] ?? '',
			'initials' => $user['initials'] ?? '',
		);

		$this->helpers->store_account_info( $account_info );

		return $account_info;
	}

	/**
	 * Get the user's boards as select options.
	 *
	 * @return array Formatted as value/text pairs.
	 * @throws Exception If the request fails.
	 */
	public function get_boards() {

		$account = $this->helpers->get_account_info();

		if ( empty( $account['id'] ) ) {
			throw new Exception(
				esc_html_x( 'Trello account info not found. Please reconnect.', 'Trello', 'uncanny-automator' )
			);
		}

		$response = $this->api_request(
			array(
				'action'    => 'user_boards',
				'member_id' => $account['id'],
			),
			null,
			array( 'error_message' => esc_html_x( 'Unable to fetch user boards.', 'Trello', 'uncanny-automator' ) )
		);

		return $this->format_options( $response['data'] );
	}

	/**
	 * Get lists in a board as select options.
	 *
	 * @param string $board_id The board ID.
	 *
	 * @return array Formatted as value/text pairs.
	 * @throws Exception If the request fails.
	 */
	public function get_board_lists( $board_id ) {

		$response = $this->api_request(
			array(
				'action'   => 'board_lists',
				'board_id' => $board_id,
			),
			null,
			array( 'error_message' => esc_html_x( 'Unable to fetch board lists.', 'Trello', 'uncanny-automator' ) )
		);

		return $this->format_options( $response['data'] );
	}

	/**
	 * Get members of a board as select options.
	 *
	 * @param string $board_id The board ID.
	 *
	 * @return array Formatted as value/text pairs.
	 * @throws Exception If the request fails.
	 */
	public function get_board_members( $board_id ) {

		$response = $this->api_request(
			array(
				'action'   => 'board_members',
				'board_id' => $board_id,
			),
			null,
			array( 'error_message' => esc_html_x( 'Unable to fetch board members.', 'Trello', 'uncanny-automator' ) )
		);

		$options = array();

		foreach ( $response['data'] as $member ) {
			$options[] = array(
				'value' => $member['id'],
				'text'  => $member['fullName'] . ' (' . $member['username'] . ')',
			);
		}

		return $options;
	}

	/**
	 * Get labels of a board as select options.
	 *
	 * @param string $board_id The board ID.
	 *
	 * @return array Formatted as value/text pairs.
	 * @throws Exception If the request fails.
	 */
	public function get_board_labels( $board_id ) {

		$response = $this->api_request(
			array(
				'action'   => 'board_labels',
				'board_id' => $board_id,
			),
			null,
			array( 'error_message' => esc_html_x( 'Unable to fetch board labels.', 'Trello', 'uncanny-automator' ) )
		);

		$options = array();

		foreach ( $response['data'] as $label ) {
			$name = $label['color'];

			if ( ! empty( $label['name'] ) ) {
				$name .= ' (' . $label['name'] . ')';
			}

			$options[] = array(
				'value' => $label['id'],
				'text'  => $name,
			);
		}

		return $options;
	}

	/**
	 * Get cards in a list as select options.
	 *
	 * @param string $list_id The list ID.
	 *
	 * @return array Formatted as value/text pairs.
	 * @throws Exception If the request fails.
	 */
	public function get_list_cards( $list_id ) {

		$response = $this->api_request(
			array(
				'action'  => 'list_cards',
				'list_id' => $list_id,
			),
			null,
			array( 'error_message' => esc_html_x( 'Unable to fetch list cards.', 'Trello', 'uncanny-automator' ) )
		);

		return $this->format_options( $response['data'] );
	}

	/**
	 * Get checklists on a card as select options.
	 *
	 * @param string $card_id The card ID.
	 *
	 * @return array Formatted as value/text pairs.
	 * @throws Exception If the request fails.
	 */
	public function get_card_checklists( $card_id ) {

		$response = $this->api_request(
			array(
				'action'  => 'card_checklists',
				'card_id' => $card_id,
			),
			null,
			array( 'error_message' => esc_html_x( 'Unable to fetch checklists.', 'Trello', 'uncanny-automator' ) )
		);

		return $this->format_options( $response['data'] );
	}

	/**
	 * Get custom fields of a board as repeater rows.
	 *
	 * @param string $board_id The board ID.
	 *
	 * @return array Formatted as repeater rows.
	 * @throws Exception If the request fails.
	 */
	public function get_custom_fields( $board_id ) {

		$response = $this->api_request(
			array(
				'action'   => 'get_custom_fields',
				'board_id' => $board_id,
			),
			null,
			array( 'error_message' => esc_html_x( 'Unable to get custom fields.', 'Trello', 'uncanny-automator' ) )
		);

		$rows = array();

		foreach ( $response['data'] as $field ) {
			$rows[] = array(
				'FIELD_NAME'  => $field['name'],
				'FIELD_OBJ'   => wp_json_encode( $field ),
				'FIELD_VALUE' => '',
			);
		}

		return $rows;
	}

	////////////////////////////////////////////////////////////
	// Formatting helpers
	////////////////////////////////////////////////////////////

	/**
	 * Format an array of API items as value/text option pairs.
	 *
	 * Expects each item to have 'id' and 'name' keys.
	 *
	 * @param array $items The API response items.
	 *
	 * @return array Formatted as value/text pairs.
	 */
	private function format_options( $items ) {

		$options = array();

		foreach ( $items as $item ) {
			$options[] = array(
				'value' => $item['id'],
				'text'  => $item['name'],
			);
		}

		return $options;
	}
}

<?php

namespace Uncanny_Automator\Integrations\Trello;

use Exception;
use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

/**
 * Class Trello_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Trello_App_Helpers $helpers
 * @property Trello_Api_Caller  $api
 */
class Trello_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * Validate integration credentials.
	 *
	 * Trello returns credentials as an array with a token property.
	 * Extract and return the token string for storage.
	 *
	 * @param mixed $credentials The credentials.
	 *
	 * @return string
	 * @throws Exception If credentials are invalid.
	 */
	protected function validate_integration_credentials( $credentials ) {

		$token = $credentials['token'] ?? '';

		if ( empty( $token ) ) {
			throw new Exception(
				esc_html_x( 'Missing access token in credentials.', 'Trello', 'uncanny-automator' )
			);
		}

		return $token;
	}

	/**
	 * Authorize account after credentials are stored.
	 *
	 * Fetches the Trello user info and stores it as account info.
	 *
	 * @param array $response    The current response array.
	 * @param mixed $credentials The credentials.
	 *
	 * @return array
	 * @throws Exception If the user info cannot be fetched.
	 */
	protected function authorize_account( $response, $credentials ) {

		$this->api->fetch_and_store_user();

		return $response;
	}

	/**
	 * Get formatted account information for connected user info display.
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {

		$user = $this->helpers->get_account_info();
		if ( empty( $user ) ) {
			return array();
		}

		$initial = ! empty( $user['initials'] ) ? strtoupper( $user['initials'][0] ) : '?';

		return array(
			'avatar_type'    => 'text',
			'avatar_value'   => $initial,
			'main_info'      => esc_html( $user['fullName'] ?? '' ),
			'main_info_icon' => true,
			'additional'     => sprintf(
				/* translators: 1. Username */
				esc_html_x( 'Username: %1$s', 'Trello', 'uncanny-automator' ),
				esc_html( $user['username'] ?? '' )
			),
		);
	}

	/**
	 * Clean up before disconnecting.
	 *
	 * @param array $response The current response array.
	 * @param array $data     The posted data.
	 *
	 * @return array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {

		// Clean up board-scoped transients before removing the boards cache.
		$boards_option = automator_get_option( $this->helpers->get_option_key( 'boards' ), array() );
		$boards        = $boards_option['data'] ?? array();

		foreach ( $boards as $board ) {
			$board_id = $board['value'] ?? '';
			if ( empty( $board_id ) ) {
				continue;
			}
			delete_transient( $this->helpers->get_option_key( 'lists_' . $board_id ) );
			delete_transient( $this->helpers->get_option_key( 'members_' . $board_id ) );
			delete_transient( $this->helpers->get_option_key( 'labels_' . $board_id ) );
			delete_transient( $this->helpers->get_option_key( 'custom_fields_' . $board_id ) );
		}

		automator_delete_option( $this->helpers->get_option_key( 'boards' ) );

		return $response;
	}

	/**
	 * Render the settings page content shown when Trello is not connected.
	 *
	 * Outputs a header with description and the list of available actions.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		// Output the standard disconnected integration header.
		$this->output_disconnected_header(
			esc_html_x( 'Connect Uncanny Automator to Trello to automatically manage your projects from inside your WordPress site. Have form submissions create new checklist items or new comments in a forum discussion automatically update a Trello card; you might even have new group members automatically added to a card.', 'Trello', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions.
		$this->output_available_items();
	}
}

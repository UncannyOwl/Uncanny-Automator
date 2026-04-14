<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Microsoft_Teams_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 */
class Microsoft_Teams_Api_Caller extends Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Set integration-specific properties.
	 *
	 * Registers error messages for Microsoft Graph permission errors that occur
	 * when a migrated user's OAuth token lacks newly required scopes.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->register_error_messages(
			array(
				'insufficient privileges'   => array(
					// translators: %s: Settings page URL.
					'message'   => esc_html_x( 'Your Microsoft Teams account is missing required permissions. Please [reconnect your account](%s) to authorize the additional permissions needed.', 'Microsoft Teams', 'uncanny-automator' ),
					'help_link' => $this->helpers->get_settings_page_url(),
				),
				'missing scope permissions' => array(
					// translators: %s: Settings page URL.
					'message'   => esc_html_x( 'Your Microsoft Teams account is missing required permissions. Please [reconnect your account](%s) to authorize the additional permissions needed.', 'Microsoft Teams', 'uncanny-automator' ),
					'help_link' => $this->helpers->get_settings_page_url(),
				),
			)
		);
	}

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The raw credentials from options to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return string - JSON encoded credentials.
	 * @throws Exception If credentials are invalid.
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		if ( empty( $credentials['microsoft_teams_id'] ) || empty( $credentials['vault_signature'] ) ) {
			throw new Exception( esc_html_x( 'Invalid credentials', 'Microsoft Teams', 'uncanny-automator' ) );
		}

		return wp_json_encode( $credentials );
	}

	////////////////////////////////////////////////////////////
	// Data getters
	////////////////////////////////////////////////////////////

	/**
	 * Fetch user teams.
	 *
	 * @return array The teams option data.
	 * @throws Exception
	 */
	public function get_user_teams() {

		$response = $this->api_request( 'user_teams' );
		$teams    = $response['data']['value'] ?? array();

		if ( empty( $teams ) ) {
			throw new Exception( esc_html_x( 'No teams were found', 'Microsoft Teams', 'uncanny-automator' ) );
		}

		// Filter out archived teams — they appear in joinedTeams but are read-only.
		$teams = array_filter(
			$teams,
			function ( $team ) {
				return empty( $team['isArchived'] );
			}
		);

		if ( empty( $teams ) ) {
			throw new Exception( esc_html_x( 'No active teams were found', 'Microsoft Teams', 'uncanny-automator' ) );
		}

		return $this->format_graph_options( $teams );
	}

	/**
	 * Fetch team members as select options.
	 *
	 * @param string $team_id         The team ID.
	 * @param string $exclude_user_id Optional user ID to exclude from results.
	 *
	 * @return array The team members as value/text option pairs.
	 * @throws Exception If no members are found.
	 */
	public function get_team_members( $team_id, $exclude_user_id = '' ) {

		$body = array(
			'action'  => 'get_team_members',
			'team_id' => $team_id,
		);

		$response = $this->api_request( $body );
		$members  = $response['data']['value'] ?? array();

		if ( empty( $members ) ) {
			throw new Exception( esc_html_x( 'No members were found', 'Microsoft Teams', 'uncanny-automator' ) );
		}

		// Filter out the connected user if specified.
		if ( ! empty( $exclude_user_id ) ) {
			$members = array_filter(
				$members,
				function ( $member ) use ( $exclude_user_id ) {
					return $member['userId'] !== $exclude_user_id;
				}
			);
		}

		return $this->format_graph_options( $members, 'userId' );
	}

	/**
	 * Fetch team tags as select options.
	 *
	 * @param string $team_id The team ID.
	 *
	 * @return array The team tags as value/text option pairs.
	 * @throws Exception If no tags are found.
	 */
	public function get_team_tags( $team_id ) {

		$body = array(
			'action'  => 'team_tags',
			'team_id' => $team_id,
		);

		$response = $this->api_request( $body );
		$tags     = $response['data']['value'] ?? array();

		if ( empty( $tags ) ) {
			throw new Exception( esc_html_x( 'No tags were found', 'Microsoft Teams', 'uncanny-automator' ) );
		}

		return $this->format_graph_options( $tags );
	}

	/**
	 * Fetch team channels as select options.
	 *
	 * @param string $team_id The team ID.
	 *
	 * @return array The team channels as value/text option pairs.
	 * @throws Exception If no channels are found.
	 */
	public function get_team_channels( $team_id ) {

		$body = array(
			'action'  => 'team_channels',
			'team_id' => $team_id,
		);

		$response = $this->api_request( $body );
		$channels = $response['data']['value'] ?? array();

		if ( empty( $channels ) ) {
			throw new Exception( esc_html_x( 'No channels were found', 'Microsoft Teams', 'uncanny-automator' ) );
		}

		return $this->format_graph_options( $channels );
	}

	////////////////////////////////////////////////////////////
	// Formatting helpers
	////////////////////////////////////////////////////////////

	/**
	 * Format Microsoft Graph API items into value/text option pairs.
	 *
	 * Works for any Graph resource that uses 'displayName' (teams, channels, members, etc.).
	 *
	 * @param array  $items    The Graph API items.
	 * @param string $value_key The key to use for the option value.
	 *
	 * @return array The formatted option pairs.
	 */
	private function format_graph_options( $items, $value_key = 'id' ) {

		$options = array();

		foreach ( $items as $item ) {
			$options[] = array(
				'value' => $item[ $value_key ],
				'text'  => $item['displayName'],
			);
		}

		return $options;
	}
}

<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Microsoft_Teams_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_Api_Caller $api
 */
class Microsoft_Teams_App_Helpers extends App_Helpers {

	////////////////////////////////////////////////////////////
	// Common field configurations
	////////////////////////////////////////////////////////////

	/**
	 * Get the team select field configuration.
	 *
	 * @param string $option_code           The option code. Defaults to 'TEAM'.
	 * @param bool   $supports_custom_value Whether to allow custom values. Defaults to false.
	 *
	 * @return array
	 */
	public function get_team_select_option_config( $option_code = 'TEAM', $supports_custom_value = false ) {
		return array(
			'option_code'           => $option_code,
			'input_type'            => 'select',
			'label'                 => esc_html_x( 'Team', 'Microsoft Teams', 'uncanny-automator' ),
			'placeholder'           => esc_html_x( 'Select a team', 'Microsoft Teams', 'uncanny-automator' ),
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => $supports_custom_value,
			'ajax'                  => array(
				'endpoint' => 'automator_microsoft_teams_get_teams',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get the channel select field configuration.
	 *
	 * @param string $option_code           The option code.
	 * @param string $listen_field          The parent field to listen for. Defaults to 'TEAM'.
	 * @param bool   $supports_custom_value Whether to allow custom values. Defaults to true.
	 *
	 * @return array
	 */
	public function get_channel_select_option_config( $option_code, $listen_field = 'TEAM', $supports_custom_value = true ) {
		return array(
			'option_code'           => $option_code,
			'input_type'            => 'select',
			'label'                 => esc_html_x( 'Channel', 'Microsoft Teams', 'uncanny-automator' ),
			'placeholder'           => esc_html_x( 'Select a channel', 'Microsoft Teams', 'uncanny-automator' ),
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => $supports_custom_value,
			'ajax'                  => array(
				'endpoint'      => 'automator_microsoft_teams_get_team_channels',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $listen_field ),
			),
		);
	}

	/**
	 * Get the user identifier (email or AAD Object ID) field configuration.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_user_option_config( $option_code ) {
		return array(
			'option_code' => $option_code,
			'input_type'  => 'text',
			'label'       => esc_html_x( 'User (email or AAD Object ID)', 'Microsoft Teams', 'uncanny-automator' ),
			'description' => esc_html_x( "Enter the user's email address (UPN) or Azure AD Object ID.", 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => true,
		);
	}

	/**
	 * Get the message textarea field configuration.
	 *
	 * @return array
	 */
	public function get_message_option_config() {
		return array(
			'option_code' => 'MESSAGE',
			'input_type'  => 'textarea',
			'label'       => esc_html_x( 'Message', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => true,
		);
	}

	/**
	 * Get the "invite external user as guest" checkbox configuration.
	 *
	 * When enabled, sends create_if_not_found=true in the API request body,
	 * which auto-invites unrecognized emails as B2B guests in Azure AD.
	 *
	 * @param string $option_code The option code. Defaults to 'TEAMS_INVITE_GUEST'.
	 *
	 * @return array
	 */
	public function get_invite_guest_option_config( $option_code = 'TEAMS_INVITE_GUEST' ) {
		return array(
			'option_code'   => $option_code,
			'input_type'    => 'checkbox',
			'is_toggle'     => true,
			'label'         => esc_html_x( 'Invite external users as guests', 'Microsoft Teams', 'uncanny-automator' ),
			'description'   => esc_html_x( 'When enabled, if a user is not found in your Azure AD tenant, they will automatically be invited as a B2B guest.', 'Microsoft Teams', 'uncanny-automator' ) . $this->get_kb_learn_more_link(),
			'required'      => false,
			'default_value' => false,
		);
	}

	/**
	 * Get the description text field configuration.
	 *
	 * @return array
	 */
	public function get_description_option_config() {
		return array(
			'option_code' => 'DESCRIPTION',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Description', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => false,
		);
	}

	/**
	 * Get a "Learn more" link pointing to the Microsoft Teams KB article.
	 *
	 * @return string HTML anchor with external-link icon.
	 */
	public function get_kb_learn_more_link( $utm_medium = 'action' ) {
		return sprintf(
			' <a href="%2$s" target="_blank">%1$s</a>',
			esc_html_x( 'Learn more', 'Microsoft Teams', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon>',
			automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/microsoft-teams/', $utm_medium, 'microsoft-teams-kb_article' )
		);
	}

	////////////////////////////////////////////////////////////
	// AJAX handlers
	////////////////////////////////////////////////////////////

	/**
	 * AJAX handler for teams dropdown.
	 *
	 * @return void
	 */
	public function ajax_get_teams_options() {

		Automator()->utilities->ajax_auth_check();

		$option_key = $this->get_option_key( 'teams' );
		$cached     = $this->get_app_option( $option_key, 2 * HOUR_IN_SECONDS );

		if ( ! empty( $cached['data'] ) && ! $cached['refresh'] && ! $this->is_ajax_refresh() ) {
			$this->ajax_success( $cached['data'] );
		}

		try {
			$teams = $this->api->get_user_teams();
			$this->save_app_option( $option_key, $teams );
			$this->ajax_success( $teams );
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for team members dropdown.
	 *
	 * @return void
	 */
	public function ajax_get_team_members_options() {

		Automator()->utilities->ajax_auth_check();

		try {
			$team_id    = $this->get_team_id_from_ajax();
			$option_key = $this->get_option_key( 'members_' . $team_id );
			$cached     = $this->get_app_option( $option_key, 5 * MINUTE_IN_SECONDS );

			if ( ! empty( $cached['data'] ) && ! $cached['refresh'] && ! $this->is_ajax_refresh() ) {
				$this->ajax_success( $cached['data'] );
			}

			$account_info    = $this->get_account_info();
			$current_user_id = $account_info['microsoft_teams_id'] ?? '';
			$members         = $this->api->get_team_members( $team_id, $current_user_id );
			$this->save_app_option( $option_key, $members );
			$this->ajax_success( $members );
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for team channels dropdown.
	 *
	 * @return void
	 */
	public function ajax_get_team_channels_options() {

		Automator()->utilities->ajax_auth_check();

		try {
			$team_id    = $this->get_team_id_from_ajax();
			$option_key = $this->get_option_key( 'channels_' . $team_id );
			$cached     = $this->get_app_option( $option_key, 30 * MINUTE_IN_SECONDS );

			if ( ! empty( $cached['data'] ) && ! $cached['refresh'] && ! $this->is_ajax_refresh() ) {
				$this->ajax_success( $cached['data'] );
			}

			$channels = $this->api->get_team_channels( $team_id );
			$this->save_app_option( $option_key, $channels );
			$this->ajax_success( $channels );
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for team tags dropdown.
	 *
	 * @return void
	 */
	public function ajax_get_team_tags_options() {

		Automator()->utilities->ajax_auth_check();

		try {
			$team_id    = $this->get_team_id_from_ajax();
			$option_key = $this->get_option_key( 'tags_' . $team_id );
			$cached     = $this->get_app_option( $option_key, 5 * MINUTE_IN_SECONDS );

			if ( ! empty( $cached['data'] ) && ! $cached['refresh'] && ! $this->is_ajax_refresh() ) {
				$this->ajax_success( $cached['data'] );
			}

			$tags = $this->api->get_team_tags( $team_id );
			$this->save_app_option( $option_key, $tags );
			$this->ajax_success( $tags );
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}
	}

	/**
	 * Get the team ID from the AJAX request.
	 *
	 * Checks all AJAX values since the team field option_code varies across actions
	 * (e.g. 'TEAM', 'TEAMS_TAG_MEMBER_TEAM', 'TEAMS_DELETE_CHANNEL_TEAM').
	 *
	 * @return string
	 * @throws Exception
	 */
	private function get_team_id_from_ajax() {

		$values = $this->get_values_from_ajax();

		foreach ( $values as $value ) {
			$value = sanitize_text_field( wp_unslash( $value ) );
			if ( ! empty( $value ) ) {
				return $value;
			}
		}

		throw new Exception( esc_html_x( 'Please select a team', 'Microsoft Teams', 'uncanny-automator' ) );
	}

	////////////////////////////////////////////////////////////
	// Parsed value validators
	////////////////////////////////////////////////////////////

	/**
	 * Get and validate a required text value from parsed meta.
	 *
	 * @param array  $parsed      The parsed meta values.
	 * @param string $meta_key    The meta key to retrieve.
	 * @param string $field_label The human-readable field label used in the error message.
	 *
	 * @return string The sanitized value.
	 * @throws Exception If the value is missing or empty.
	 */
	public function get_text_value_from_parsed( $parsed, $meta_key, $field_label ) {

		if ( ! isset( $parsed[ $meta_key ] ) || '' === trim( $parsed[ $meta_key ] ) ) {
			throw new Exception(
				sprintf(
					// translators: %s is the field label
					esc_html_x( '%s is required.', 'Microsoft Teams', 'uncanny-automator' ),
					esc_html( $field_label )
				)
			);
		}

		return sanitize_text_field( $parsed[ $meta_key ] );
	}

	/**
	 * Get and validate a team ID from parsed meta.
	 *
	 * @param array  $parsed   The parsed meta values.
	 * @param string $meta_key The meta key. Defaults to 'TEAM'.
	 *
	 * @return string The team ID.
	 * @throws Exception If the team ID is missing.
	 */
	public function get_team_id_from_parsed( $parsed, $meta_key = 'TEAM' ) {
		return $this->get_text_value_from_parsed(
			$parsed,
			$meta_key,
			esc_html_x( 'Team', 'Microsoft Teams', 'uncanny-automator' )
		);
	}

	/**
	 * Get and validate a channel ID from parsed meta.
	 *
	 * @param array  $parsed   The parsed meta values.
	 * @param string $meta_key The meta key.
	 *
	 * @return string The channel ID.
	 * @throws Exception If the channel ID is missing.
	 */
	public function get_channel_id_from_parsed( $parsed, $meta_key ) {
		return $this->get_text_value_from_parsed(
			$parsed,
			$meta_key,
			esc_html_x( 'Channel', 'Microsoft Teams', 'uncanny-automator' )
		);
	}

	/**
	 * Get and validate a message from parsed meta.
	 *
	 * @param array  $parsed   The parsed meta values.
	 * @param string $meta_key The meta key. Defaults to 'MESSAGE'.
	 *
	 * @return string The sanitized message.
	 * @throws Exception If the message is missing or empty.
	 */
	public function get_message_from_parsed( $parsed, $meta_key = 'MESSAGE' ) {

		$label = esc_html_x( 'Message', 'Microsoft Teams', 'uncanny-automator' );

		if ( ! isset( $parsed[ $meta_key ] ) || '' === trim( $parsed[ $meta_key ] ) ) {
			throw new Exception(
				sprintf(
					// translators: %s is the field label
					esc_html_x( '%s is required.', 'Microsoft Teams', 'uncanny-automator' ),
					esc_html( $label )
				)
			);
		}

		return sanitize_textarea_field( $parsed[ $meta_key ] );
	}

	/**
	 * Get and validate a user identifier (email or AAD Object ID) from parsed meta.
	 *
	 * Only validates that the value is present and non-empty. Format validation
	 * (email vs GUID) is left to the API which returns more accurate error messages.
	 *
	 * @param array  $parsed   The parsed meta values.
	 * @param string $meta_key The meta key.
	 *
	 * @return string The sanitized user identifier.
	 * @throws Exception If the value is missing or empty.
	 */
	public function get_user_identifier_from_parsed( $parsed, $meta_key ) {
		return $this->get_text_value_from_parsed(
			$parsed,
			$meta_key,
			esc_html_x( 'User (email or AAD Object ID)', 'Microsoft Teams', 'uncanny-automator' )
		);
	}
}

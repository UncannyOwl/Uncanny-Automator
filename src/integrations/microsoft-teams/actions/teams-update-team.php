<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class TEAMS_UPDATE_TEAM
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class TEAMS_UPDATE_TEAM extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'TEAMS_UPDATE_TEAM_CODE' );
		$this->set_action_meta( 'TEAMS_UPDATE_TEAM_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Update settings for {{a team}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the team name
				esc_attr_x( 'Update settings for {{a team:%1$s}}', 'Microsoft Teams', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define the options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_team_select_option_config( $this->get_action_meta(), true ),
			$this->get_display_name_option_config(),
			$this->helpers->get_description_option_config(),
			$this->get_visibility_option_config(),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$team_id      = $this->helpers->get_team_id_from_parsed( $parsed, $this->get_action_meta() );
		$display_name = sanitize_text_field( $parsed['TEAMS_UPDATE_DISPLAY_NAME'] ?? '' );
		$description  = sanitize_text_field( $parsed['DESCRIPTION'] ?? '' );
		$visibility   = sanitize_text_field( $parsed['TEAMS_UPDATE_VISIBILITY'] ?? '' );

		// Build PATCH body — only include fields with values.
		$settings = array();

		if ( ! empty( $display_name ) ) {
			$settings['displayName'] = mb_strimwidth( $display_name, 0, 256 );
		}

		if ( ! empty( $description ) ) {
			$settings['description'] = $description;
		}

		if ( ! empty( $visibility ) ) {
			$settings['visibility'] = $visibility;
		}

		if ( empty( $settings ) ) {
			throw new \Exception( esc_html_x( 'At least one setting must be provided.', 'Microsoft Teams', 'uncanny-automator' ) );
		}

		$body = array(
			'action'   => 'update_team',
			'team_id'  => $team_id,
			'settings' => wp_json_encode( $settings ),
		);

		$this->api->api_request( $body, $action_data );

		// Invalidate the cached teams list so updated name shows fresh.
		automator_delete_option( $this->helpers->get_option_key( 'teams' ) );

		return true;
	}

	////////////////////////////////////////////////////////////
	// Option configurations
	////////////////////////////////////////////////////////////

	/**
	 * Get the display name option configuration.
	 *
	 * @return array
	 */
	private function get_display_name_option_config() {
		return array(
			'option_code' => 'TEAMS_UPDATE_DISPLAY_NAME',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Display name', 'Microsoft Teams', 'uncanny-automator' ),
			'description' => esc_html_x( 'Leave blank to keep the current name. Maximum 256 characters.', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => false,
		);
	}

	/**
	 * Get the visibility option configuration.
	 *
	 * @return array
	 */
	private function get_visibility_option_config() {
		$options = array(
			''        => esc_html_x( 'No change', 'Microsoft Teams', 'uncanny-automator' ),
			'private' => esc_html_x( 'Private', 'Microsoft Teams', 'uncanny-automator' ),
			'public'  => esc_html_x( 'Public', 'Microsoft Teams', 'uncanny-automator' ),
		);
		return array(
			'option_code'           => 'TEAMS_UPDATE_VISIBILITY',
			'label'                 => esc_html_x( 'Visibility', 'Microsoft Teams', 'uncanny-automator' ),
			'description'           => esc_html_x( "Leave as 'No change' to keep the current visibility.", 'Microsoft Teams', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'default_value'         => '',
			'options'               => automator_array_as_options( $options ),
			'supports_custom_value' => false,
		);
	}
}

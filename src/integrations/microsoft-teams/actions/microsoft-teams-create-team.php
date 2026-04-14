<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class MICROSOFT_TEAMS_CREATE_TEAM
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class MICROSOFT_TEAMS_CREATE_TEAM extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'CREATE_TEAM' );
		$this->set_action_meta( 'CHANNEL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create {{a team}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the team name
				esc_attr_x( 'Create {{a team:%1$s}}', 'Microsoft Teams', 'uncanny-automator' ),
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
			$this->get_specialization_option_config(),
			$this->get_team_name_option_config(),
			$this->helpers->get_description_option_config(),
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

		$team = array(
			'displayName'    => $this->helpers->get_text_value_from_parsed( $parsed, $this->get_action_meta(), esc_html_x( 'Team name', 'Microsoft Teams', 'uncanny-automator' ) ),
			'description'    => $this->get_parsed_meta_value( 'DESCRIPTION' ),
			'specialization' => $this->helpers->get_text_value_from_parsed( $parsed, 'SPECIALIZATION', esc_html_x( 'Type', 'Microsoft Teams', 'uncanny-automator' ) ),
		);

		$body = array(
			'action' => 'create_team',
			'team'   => wp_json_encode( $team ),
		);

		$this->api->api_request( $body, $action_data );

		// Invalidate the cached teams list so it loads fresh in the UI.
		automator_delete_option( $this->helpers->get_option_key( 'teams' ) );

		return true;
	}

	////////////////////////////////////////////////////////////
	// Option configurations
	////////////////////////////////////////////////////////////

	/**
	 * Get the specialization option configuration.
	 *
	 * @return array
	 */
	private function get_specialization_option_config() {
		return array(
			'option_code'           => 'SPECIALIZATION',
			'label'                 => esc_html_x( 'Type', 'Microsoft Teams', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => $this->get_specialization_options(),
			'supports_custom_value' => false,
		);
	}

	/**
	 * Get the team name option configuration.
	 *
	 * @return array
	 */
	private function get_team_name_option_config() {
		return array(
			'option_code' => $this->get_action_meta(),
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Team name', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => true,
		);
	}

	/**
	 * Get team specialization options.
	 *
	 * @return array
	 */
	private function get_specialization_options() {

		$specializations = array(
			'standard'                               => esc_html_x( 'Standard', 'Microsoft Teams', 'uncanny-automator' ),
			'educationClass'                         => esc_html_x( 'Education - Class Team', 'Microsoft Teams', 'uncanny-automator' ),
			'educationStaff'                         => esc_html_x( 'Education - Staff Team', 'Microsoft Teams', 'uncanny-automator' ),
			'educationProfessionalLearningCommunity' => esc_html_x( 'Education - Professional Learning Community', 'Microsoft Teams', 'uncanny-automator' ),
		);

		$specializations = apply_filters( 'automator_microsoft_teams_specializations', $specializations );

		return automator_array_as_options( $specializations );
	}
}

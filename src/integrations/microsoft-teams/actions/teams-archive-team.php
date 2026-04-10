<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class TEAMS_ARCHIVE_TEAM
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class TEAMS_ARCHIVE_TEAM extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'TEAMS_ARCHIVE_TEAM_CODE' );
		$this->set_action_meta( 'TEAMS_ARCHIVE_TEAM_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Archive {{a team}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the team name
				esc_attr_x( 'Archive {{a team:%1$s}}', 'Microsoft Teams', 'uncanny-automator' ),
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
			$this->get_spo_readonly_option_config(),
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
		$spo_readonly = $this->get_parsed_meta_value( 'TEAMS_ARCHIVE_SET_SPO_READONLY', false );

		$body = array(
			'action'       => 'archive_team',
			'team_id'      => $this->helpers->get_team_id_from_parsed( $parsed, $this->get_action_meta() ),
			'spo_readonly' => 'true' === $spo_readonly ? 'true' : 'false',
		);

		$this->api->api_request( $body, $action_data );

		// Invalidate the cached teams list so archived team is removed from dropdowns.
		automator_delete_option( $this->helpers->get_option_key( 'teams' ) );

		return true;
	}

	////////////////////////////////////////////////////////////
	// Option configurations
	////////////////////////////////////////////////////////////

	/**
	 * Get the SharePoint read-only checkbox option configuration.
	 *
	 * @return array
	 */
	private function get_spo_readonly_option_config() {
		return array(
			'option_code'   => 'TEAMS_ARCHIVE_SET_SPO_READONLY',
			'input_type'    => 'checkbox',
			'label'         => esc_html_x( 'Set SharePoint site to read-only for members', 'Microsoft Teams', 'uncanny-automator' ),
			'description'   => esc_html_x( "When enabled, the team's associated SharePoint site will become read-only for members after archiving.", 'Microsoft Teams', 'uncanny-automator' ),
			'required'      => false,
			'default_value' => false,
		);
	}
}

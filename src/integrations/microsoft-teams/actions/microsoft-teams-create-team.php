<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class MICROSOFT_TEAMS_CREATE_TEAM
 *
 * @package Uncanny_Automator
 */
class MICROSOFT_TEAMS_CREATE_TEAM extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'CREATE_TEAM' );
		$this->set_action_meta( 'CHANNEL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/microsoft-teams/' ) );
		$this->set_requires_user( false );
		/* translators: channel name */
		$this->set_sentence( sprintf( esc_attr__( 'Create {{a team:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Create {{a team}}', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * options
	 *
	 * @return void
	 */
	public function options() {

		$team_name_field = array(
			'option_code' => $this->action_meta,
			'input_type'  => 'text',
			'label'       => esc_attr__( 'Team name', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => true,
			'tokens'      => true,
			'default'     => '',
		);

		$team_description_field = array(
			'option_code' => 'DESCRIPTION',
			'input_type'  => 'text',
			'label'       => esc_attr__( 'Description', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => false,
			'tokens'      => true,
			'default'     => '',
		);

		$team_specialization_field = array(
			'option_code'           => 'SPECIALIZATION',
			'label'                 => __( 'Type', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'is_ajax'               => false,
			'options'               => $this->helpers->teams_specializations_options(),
			'supports_custom_value' => false,
		);

		return array(
			$team_specialization_field,
			$team_name_field,
			$team_description_field,
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	protected function process_Action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$team = array();

		$team['displayName'] = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );

		$team['description'] = Automator()->parse->text( $action_data['meta']['DESCRIPTION'], $recipe_id, $user_id, $args );

		$team['specialization'] = $action_data['meta']['SPECIALIZATION'];

		$error_msg = '';

		$response = $this->helpers->create_team( $team, $action_data );

		return true;
	}
}

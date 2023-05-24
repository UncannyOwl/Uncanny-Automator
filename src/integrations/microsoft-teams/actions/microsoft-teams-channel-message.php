<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class MICROSOFT_TEAMS_CHANNEL_MESSAGE
 *
 * @package Uncanny_Automator
 */
class MICROSOFT_TEAMS_CHANNEL_MESSAGE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'CHANNEL_MESSAGE' );
		$this->set_action_meta( 'CHANNEL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/microsoft-teams/' ) );
		$this->set_requires_user( false );
		/* translators: channel name */
		$this->set_sentence( sprintf( esc_attr__( 'Send a message to {{a channel:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Send a message to {{a channel}}', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * options
	 *
	 * @return void
	 */
	public function options() {

		$user_teams_field = array(
			'option_code'           => 'TEAM',
			'label'                 => __( 'Team', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => $this->helpers->user_teams_options(),
			'supports_custom_value' => false,
			'is_ajax'               => true,
			'endpoint'              => 'automator_microsoft_teams_get_team_channels',
			'fill_values_in'        => $this->action_meta,
		);

		$channels_field = array(
			'option_code'           => $this->action_meta,
			'label'                 => __( 'Channel', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'is_ajax'               => false,
			'options'               => array(),
			'supports_custom_value' => false,
		);

		$message_field = array(
			'option_code' => 'MESSAGE',
			'input_type'  => 'textarea',
			'label'       => esc_attr__( 'Message', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => true,
			'tokens'      => true,
			'default'     => '',
		);

		return array(
			$user_teams_field,
			$channels_field,
			$message_field,
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$team = $action_data['meta']['TEAM'];

		$channel = $action_data['meta'][ $this->action_meta ];

		$message = Automator()->parse->text( $action_data['meta']['MESSAGE'], $recipe_id, $user_id, $args );

		$error_msg = '';

		$response = $this->helpers->channel_message( $team, $channel, $message, $action_data );

		return true;
	}
}

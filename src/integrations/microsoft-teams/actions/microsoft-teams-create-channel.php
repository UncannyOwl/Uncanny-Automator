<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class MICROSOFT_TEAMS_CREATE_CHANNEL
 *
 * @package Uncanny_Automator
 */
class MICROSOFT_TEAMS_CREATE_CHANNEL extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'CREATE_CHANNEL' );
		$this->set_action_meta( 'CHANNEL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/microsoft-teams/' ) );
		$this->set_requires_user( false );
		/* translators: channel name */
		$this->set_sentence( sprintf( esc_attr__( 'Create {{a channel:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Create {{a channel}}', 'uncanny-automator' ) );

		$this->set_background_processing( true );
		$this->set_action_tokens(
			array(
				'CHANNEL_ID'  => array(
					'name' => __( 'Channel ID', 'uncanny-automator' ),
					'type' => 'text',
				),
				'CHANNEL_URL' => array(
					'name' => __( 'Channel URL', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->action_code
		);
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
			'is_ajax'               => false,
			'options'               => $this->helpers->user_teams_options(),
			'supports_custom_value' => false,
		);

		$channel_name_field = array(
			'option_code' => 'NAME',
			'input_type'  => 'text',
			'label'       => esc_attr__( 'Channel name', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => true,
			'tokens'      => true,
			'default'     => '',
		);

		$channel_description_field = array(
			'option_code' => 'DESCRIPTION',
			'input_type'  => 'text',
			'label'       => esc_attr__( 'Description', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => false,
			'tokens'      => true,
			'default'     => '',
		);

		$channel_type_field = array(
			'option_code'           => 'TYPE',
			'label'                 => __( 'Privacy', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'is_ajax'               => false,
			'options'               => $this->helpers->channel_type_options(),
			'supports_custom_value' => false,
		);

		return array(
			$user_teams_field,
			$channel_name_field,
			$channel_description_field,
			$channel_type_field,
		);
	}

	/**
	 * define_tokens
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'CHANNEL_ID'  => array(
				'name' => __( 'Channel ID', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CHANNEL_URL' => array(
				'name' => __( 'Channel URL', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$channel = array();

		$parsed_channel_name = Automator()->parse->text( $action_data['meta']['NAME'], $recipe_id, $user_id, $args );

		//Shorten the title and remove any special characters from it
		$channel['displayName'] = mb_strimwidth( $parsed_channel_name, 0, 50 );

		$channel['description'] = Automator()->parse->text( $action_data['meta']['DESCRIPTION'], $recipe_id, $user_id, $args );

		$channel['channelMembershipType'] = $action_data['meta']['TYPE'];

		$team_id = $action_data['meta']['TEAM'];

		$response = $this->helpers->create_channel( $channel, $team_id, $action_data );

		if ( ! isset( $response['id'] ) ) {
			// No channel was created but no error
			return;
		}

		$this->hydrate_tokens(
			array(
				'CHANNEL_ID'  => $response['id'],
				'CHANNEL_URL' => $response['webUrl'],
			)
		);

		return true;
	}
}

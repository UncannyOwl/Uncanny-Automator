<?php

namespace Uncanny_Automator;

/**
 * Class HUBSPOT_ADDUSERTOLIST
 *
 * @package Uncanny_Automator
 */
class HUBSPOT_ADDUSERTOLIST {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'HUBSPOT';

	/**
	 *
	 * @var string
	 */
	private $action_code;

	/**
	 *
	 * @var string
	 */
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'HUBSPOTADDUSERTOLIST';
		$this->action_meta = 'HUBSPOTLIST';
		$this->define_action();

	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function define_action() {

		$action = array(
			'author'                => Automator()->get_author_name( $this->action_code ),
			'support_link'          => Automator()->get_author_support_link( $this->action_code, 'integration/hubspot/' ),
			'integration'           => self::$integration,
			'code'                  => $this->action_code,
			'sentence'              => sprintf( esc_html__( "Add the user's HubSpot contact to {{a static list:%1\$s}}", 'uncanny-automator' ), $this->action_meta ),
			'select_option_name'    => esc_html__( "Add the user's HubSpot contact to {{a static list}}", 'uncanny-automator' ),
			'priority'              => 10,
			'accepted_args'         => 1,
			'requires_user'         => true,
			'execution_function'    => array( $this, 'add_contact_to_list' ),
			'options_callback'      => array( $this, 'load_options' ),
			'background_processing' => true,
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		return array(
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->field->select(
						array(
							'option_code'           => $this->action_meta,
							'label'                 => esc_attr__( 'HubSpot List', 'uncanny-automator' ),
							'required'              => true,
							'supports_tokens'       => false,
							'supports_custom_value' => false,
							'options'               => Automator()->helpers->recipe->hubspot->get_lists(),
						)
					),
				),
			),

		);
	}

	/**
	 * Action validation function.
	 *
	 * @return mixed
	 */
	public function add_contact_to_list( $user_id, $action_data, $recipe_id, $args ) {

		$helpers = Automator()->helpers->recipe->hubspot->options;

		$user_data = get_userdata( $user_id );

		$email = $user_data->user_email;

		$list = trim( Automator()->parse->text( $action_data['meta']['HUBSPOTLIST'], $recipe_id, $user_id, $args ) );

		try {

			$response = $helpers->add_contact_to_list( $list, $email, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			$helpers->log_action_error( $e->getMessage(), $user_id, $action_data, $recipe_id );
		}
	}
}

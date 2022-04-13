<?php

namespace Uncanny_Automator;

use WP_User;

/**
 * Class GEN_ADDROLE
 *
 * @package Uncanny_Automator
 */
class WP_ADDROLE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'ADDROLE';
		$this->action_meta = 'WPROLE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/wordpress-core/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - WordPress */
			'sentence'           => sprintf( esc_attr__( "Add {{a new role:%1\$s}} to the user's roles", 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - WordPress */
			'select_option_name' => esc_attr__( "Add {{a new role}} to the user's roles", 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 3,
			'execution_function' => array( $this, 'add_role' ),
			'options_callback'	  => array( $this, 'load_options' ),
			
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		
		Automator()->helpers->recipe->wp->options->load_options = true;

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options'            => array(
					Automator()->helpers->recipe->wp->options->wp_user_roles(),
				),
			)
		);
		return $options;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function add_role( $user_id, $action_data, $recipe_id, $args ) {

		$role = $action_data['meta'][ $this->action_meta ];

		$user_obj = new WP_User( (int) $user_id );
		if ( $user_obj instanceof WP_User ) {
			$user_obj->add_role( $role );
			Automator()->complete->user->action( $user_id, $action_data, $recipe_id );
		}
	}
}

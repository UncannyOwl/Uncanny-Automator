<?php

namespace Uncanny_Automator;

use WP_User;

/**
 * Class GEN_USERROLE
 *
 * @package Uncanny_Automator
 */
class WP_USERROLE {

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
		$this->action_code = 'USERROLE';
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
			'sentence'           => sprintf( esc_attr__( "Change the user's role to {{a new role:%1\$s}}", 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - WordPress */
			'select_option_name' => esc_attr__( "Change the user's role to {{a new role}}", 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 3,
			'execution_function' => array( $this, 'user_role' ),
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
	public function user_role( $user_id, $action_data, $recipe_id, $args ) {

		$role = $action_data['meta'][ $this->action_meta ];

		$user_obj   = new WP_User( (int) $user_id );
		$user_roles = $user_obj->roles;
		if ( ! in_array( 'administrator', $user_roles ) ) {
			$user_obj->set_role( $role );
			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		} else {
			$error_message = esc_attr__( 'For security, the change role action cannot be applied to administrators.', 'uncanny-automator' );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );
		}
	}
}

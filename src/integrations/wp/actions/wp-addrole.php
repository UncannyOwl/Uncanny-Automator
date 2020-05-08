<?php

namespace Uncanny_Automator;

/**
 * Class GEN_ADDROLE
 * @package uncanny_automator
 */
class WP_ADDROLE {

	/**
	 * Integration code
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

		global $uncanny_automator;

		$action = array(
			'author'             => $uncanny_automator->get_author_name( $this->action_code ),
			'support_link'       => $uncanny_automator->get_author_support_link( $this->action_code ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - WordPress */
			'sentence'           => sprintf( __( 'Add {{a new role:%1$s}} to the user\'s roles', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - WordPress */
			'select_option_name' => __( 'Add {{a new role}} to the user\'s roles', 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 3,
			'execution_function' => array( $this, 'add_role' ),
			'options'            => [
				$uncanny_automator->helpers->recipe->wp->options->wp_user_roles(),
			],
		);

		$uncanny_automator->register->action( $action );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function add_role( $user_id, $action_data, $recipe_id ) {

		global $uncanny_automator;

		$role = $action_data['meta'][ $this->action_meta ];

		$user_obj = new \WP_User( (int) $user_id );
		if ( $user_obj instanceof \WP_User ) {
			$user_obj->add_role( $role );
			$uncanny_automator->complete->user->action( $user_id, $action_data, $recipe_id );
		}
	}
}

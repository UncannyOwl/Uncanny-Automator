<?php

namespace Uncanny_Automator;

use PeepSoUserFollower;

/**
 * Class PEEPSO_FOLLOWAUSER
 *
 * @package Uncanny_Automator
 */
class PEEPSO_FOLLOWAUSER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'PP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'PPFOLLOWAUSER';
		$this->action_meta = 'PPFOLLOWUSER';

		if ( is_admin() ) {
			add_action( 'wp_loaded', array( $this, 'plugins_loaded' ), 99 );
		} else {
			$this->define_action();
		}
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {
		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/peepso/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'requires_user'      => true,
			/* translators: Action - WordPress Core */
			'sentence'           => sprintf( esc_attr__( 'Follow {{a user:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - WordPress Core */
			'select_option_name' => esc_attr__( 'Follow a user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'follow_a_user' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	public function plugins_loaded() {
		$this->define_action();
	}

	/**
	 * load_options
	 *
	 */
	public function load_options() {
		$options = array(
			'options' => array(
				Automator()->helpers->recipe->peepso->get_users( __( 'Users', 'uncanny-automator' ), $this->action_meta, array( 'uo_include_any' => false ) ),
			),
		);

		$options = Automator()->utilities->keep_order_of_options( $options );

		return $options;
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function follow_a_user( $user_id, $action_data, $recipe_id, $args ) {

		$follow_user_id = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );
		$userdata       = get_userdata( $follow_user_id );

		if ( ! $userdata ) {
			$error_message = __( "The user doesn't exist", 'uncanny-automator' );
			$recipe_log_id = $action_data['recipe_log_id'];
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );

			return;
		}

		$follow             = 1;
		$PeepSoUserFollower = new PeepSoUserFollower( $follow_user_id, $user_id, true );
		$PeepSoUserFollower->set( 'follow', $follow );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}

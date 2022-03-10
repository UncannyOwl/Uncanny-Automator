<?php

namespace Uncanny_Automator;

/**
 * Class BDB_SETUSERSTATUS
 *
 * @package Uncanny_Automator
 */
class BDB_SETUSERSTATUS {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'BDB';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'BDBUSERSTATUS';
		$this->action_meta = 'BDBPROFILE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/buddyboss/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - BuddyBoss */
			'sentence'           => sprintf( esc_attr__( "Set the user's status to {{a specific status:%1\$s}}", 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - BuddyBoss */
			'select_option_name' => esc_attr__( "Set the user's status to {{a specific status}}", 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'set_user_status' ),
			'options'            => array(
				Automator()->helpers->recipe->field->select( array(
					'option_code' => $this->action_meta,
					'label'       => esc_attr__( 'Status', 'uncanny-automator' ),
					'options'     => array( 'active' => 'Active', 'suspend' => 'Suspend' ),
				) ),
			),
		);

		Automator()->register->action( $action );
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function set_user_status( $user_id, $action_data, $recipe_id, $args ) {

		$set_user_status = $action_data['meta'][ $this->action_meta ];

		if ( bp_is_active( 'moderation' ) ) {
			if ( 'suspend' === $set_user_status ) {
				\BP_Suspend_Member::suspend_user( $user_id );
			} elseif ( bp_moderation_is_user_suspended( $user_id ) ) {
				\BP_Suspend_Member::unsuspend_user( $user_id );
			}
			Automator()->complete->user->action( $user_id, $action_data, $recipe_id );
		} else {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete->user->action( $user_id, $action_data, $recipe_id, __( 'To change members status in your network, please activate the Moderation component.' ) );
		}

	}

}

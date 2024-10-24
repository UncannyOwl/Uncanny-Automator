<?php

namespace Uncanny_Automator;

/**
 * Class WPF_USERGROUP
 *
 * @package Uncanny_Automator
 */
class WPF_USERGROUP {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPFORO';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'ENRLFOROGROUP';
		$this->action_meta = 'FOROGROUP';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/wpforo/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - wpForo */
			'sentence'           => sprintf( esc_attr__( "Set the user's primary group to {{a specific group:%1\$s}}", 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - wpForo */
			'select_option_name' => esc_attr__( "Set the user's primary group to {{a specific group}}", 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'enrol_in_to_group' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {

		$usergroups = WPF()->usergroup->get_usergroups();

		$group_options = array();
		foreach ( $usergroups as $key => $group ) {
			$group_options[ $group['groupid'] ] = $group['name'];
		}

		$option = array(
			'option_code' => 'FOROGROUP',
			'label'       => esc_attr__( 'User groups', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $group_options,
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$option,
				),
			)
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function enrol_in_to_group( $user_id, $action_data, $recipe_id, $args ) {

		$group_id = $action_data['meta'][ $this->action_meta ];

		if ( $this->should_sync_role() ) {
			$this->member_set_user_group_id( $user_id, $group_id );
		} else {
			WPF()->usergroup->set_users_groupid( array( $group_id => array( $user_id ) ) );
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

	/**
	 * Member set the user group id
	 *
	 * @param int $user_id
	 * @param int $group_id
	 */
	private function member_set_user_group_id( $user_id, $group_id ) {

		// Check if new method exists.
		if ( method_exists( WPF()->member, 'set_groupid' ) ) {
			WPF()->member->set_groupid( $user_id, $group_id );
			return;
		}

		return WPF()->member->set_usergroup( $user_id, $group_id );
	}

	/**
	 * Check if the Pro role synch is enabled
	 *
	 * @return bool
	 */
	private function should_sync_role() {
		// Function migrated in wpForo 2.0.3
		if ( function_exists( 'wpforo_feature' ) ) {
			return wpforo_feature( 'role-synch' );
		}
		return wpforo_setting( 'authorization ', 'role_synch' );
	}

}

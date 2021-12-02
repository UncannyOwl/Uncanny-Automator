<?php

namespace Uncanny_Automator;

/**
 * Class BDB_ACCACTIVATE
 *
 * @package Uncanny_Automator
 */
class BDB_ACCACTIVATE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'BDB';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'BDBACCACTIVATE';
		$this->trigger_meta = 'BDBUSERS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/buddyboss/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - BuddyPress */
			'sentence'            => __( "User's account is activated", 'uncanny-automator' ),
			/* translators: Logged-in trigger - BuddyPress */
			'select_option_name'  => __( "User's account is activated", 'uncanny-automator' ),
			'action'              => 'bp_core_activated_user',
			'priority'            => 10,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'bp_core_activated_user' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 *  Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $key
	 * @param $user
	 */
	public function bp_core_activated_user( $user_id, $key, $user ) {

		$args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $user_id,
			'ignore_post_id' => true,
			'is_signed_in'   => true,
		);

		Automator()->maybe_add_trigger_entry( $args );
	}
}

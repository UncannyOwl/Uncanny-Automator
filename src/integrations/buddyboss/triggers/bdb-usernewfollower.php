<?php

namespace Uncanny_Automator;

/**
 * Class BDB_USERNEWFOLLOWER
 * @package Uncanny_Automator
 */
class BDB_USERNEWFOLLOWER {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'BDB';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'BDBUSERNEWFOLLOWER';
		$this->trigger_meta = 'BDBUSERFOLLOWER';
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
			'sentence'            => 'A user gains a new follower',
			/* translators: Logged-in trigger - BuddyPress */
			'select_option_name'  => 'A user gains a new follower',
			'action'              => 'bp_start_following',
			'priority'            => 99,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'bp_start_following_user' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 *  Validation function when the trigger action is hit
	 *
	 * @param $follow
	 */
	public function bp_start_following_user( $follow ) {



		$args = [
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $follow->follower_id,
			'ignore_post_id' => true,
		];

		Automator()->maybe_add_trigger_entry( $args );
	}
}

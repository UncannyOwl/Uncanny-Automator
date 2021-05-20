<?php

namespace Uncanny_Automator;

/**
 * Class BDB_USERACCEPTFRIENDREQUEST
 * @package Uncanny_Automator
 */
class BDB_USERACCEPTFRIENDREQUEST {

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
		$this->trigger_code = 'BDBUSERACCEPTFRIENDREQUEST';
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
			'sentence'            => 'A user accepts a friendship request',
			/* translators: Logged-in trigger - BuddyPress */
			'select_option_name'  => 'A user accepts a friendship request',
			'action'              => 'friends_friendship_accepted',
			'priority'            => 10,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'bp_friends_friendship_accepted' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 *  Validation function when the trigger action is hit
	 *
	 * @param $id
	 * @param $initiator_user_id
	 * @param $friend_user_id
	 * @param $friendship
	 */
	public function bp_friends_friendship_accepted( $id, $initiator_user_id, $friend_user_id, $friendship ) {



		$args = [
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $friend_user_id,
			'ignore_post_id' => true,
			'is_signed_in'   => true,
		];

		Automator()->maybe_add_trigger_entry( $args );
	}
}

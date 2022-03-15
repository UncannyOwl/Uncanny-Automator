<?php

namespace Uncanny_Automator;

/**
 * Class BDB_USERSENDSFRIENDREQUEST
 *
 * @package Uncanny_Automator
 */
class BDB_USERSENDSFRIENDREQUEST {

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
		$this->trigger_code = 'BDBUSERSENDSFRIENDREQUEST';
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
			'sentence'            => 'A user sends a friendship request',
			/* translators: Logged-in trigger - BuddyPress */
			'select_option_name'  => 'A user sends a friendship request',
			'action'              => 'friends_friendship_requested',
			'priority'            => 10,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'bp_friends_friendship_requested' ),
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
	public function bp_friends_friendship_requested( $id, $initiator_user_id, $friend_user_id, $friendship ) {

		$pass_args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $initiator_user_id,
			'ignore_post_id' => true,
			'is_signed_in'   => true,
		);

		$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {

					$trigger_meta = array(
						'user_id'        => $initiator_user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					);

					$friend = get_userdata( $friend_user_id );

					$trigger_meta['meta_key']   = 'FRIEND_ID';
					$trigger_meta['meta_value'] = $friend->ID;
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'FRIEND_EMAIL';
					$trigger_meta['meta_value'] = maybe_serialize( $friend->user_email );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'FRIEND_FIRSTNAME';
					$trigger_meta['meta_value'] = maybe_serialize( $friend->user_firstname );
					Automator()->insert_trigger_meta( $trigger_meta );

					$trigger_meta['meta_key']   = 'FRIEND_LASTNAME';
					$trigger_meta['meta_value'] = maybe_serialize( $friend->user_lastname );
					Automator()->insert_trigger_meta( $trigger_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}

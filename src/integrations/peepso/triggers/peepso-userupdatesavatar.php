<?php

namespace Uncanny_Automator;

use PeepSoUser;
use PeepSoUserFollower;

/**
 * Class PeepSo_USERUPDATESAVATAR
 *
 * @package Uncanny_Automator
 */
class PeepSo_USERUPDATESAVATAR {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'PP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'PPUSERUPDATESAVATAR';
		$this->trigger_meta = 'USERUPDATESAVATAR';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/peepso/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - PeepSo Core */
			'sentence'            => __( 'A user updates their avatar', 'uncanny-automator-pro' ),
			/* translators: Logged-in trigger - PeepSo Core */
			'select_option_name'  => __( 'A user updates their avatar', 'uncanny-automator-pro' ),
			'action'              => 'peepso_user_after_change_avatar',
			'priority'            => 10,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'avatar_update' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * load_options
	 *
	 */
	public function load_options() {
		$options = array(
			'options' => array(),
		);

		$options = Automator()->utilities->keep_order_of_options( $options );

		return $options;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 */
	public function avatar_update( $user_id, $dest_thumb, $dest_full, $dest_orig ) {

		if ( empty( $user_id ) ) {
			return;
		}

		$peepso_user = PeepSoUser::get_instance( $user_id );

		$args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'post_id'        => - 1,
			'ignore_post_id' => true,
			'user_id'        => $user_id,
			'is_signed_in'   => true,
		);

		$args = Automator()->maybe_add_trigger_entry( $args, false );

		// Save trigger meta
		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] && $result['args']['trigger_id'] && $result['args']['get_trigger_id'] ) {
					$run_number = Automator()->get->trigger_run_number( $result['args']['trigger_id'], $result['args']['get_trigger_id'], $result['args']['user_id'] );
					$save_meta  = array(
						'user_id'        => $result['args']['user_id'],
						'trigger_id'     => $result['args']['trigger_id'],
						'run_number'     => $run_number, //get run number
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'ignore_user_id' => true,
					);

					$save_meta['meta_key']   = 'AVATARURL';
					$save_meta['meta_value'] = $peepso_user->get_avatar();
					Automator()->insert_trigger_meta( $save_meta );

					$save_meta['meta_key']   = 'FL_USERNAME';
					$save_meta['meta_value'] = $peepso_user->get_username();
					Automator()->insert_trigger_meta( $save_meta );

					$save_meta['meta_key']   = 'FL_FIRST_NAME';
					$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_name( $peepso_user->get_fullname(), 'first' );
					Automator()->insert_trigger_meta( $save_meta );

					$save_meta['meta_key']   = 'FL_LAST_NAME';
					$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_name( $peepso_user->get_fullname(), 'last' );
					Automator()->insert_trigger_meta( $save_meta );

					$save_meta['meta_key']   = 'FL_GENDER';
					$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_gender( $user_id );
					Automator()->insert_trigger_meta( $save_meta );

					$save_meta['meta_key']   = 'FL_BIRTHDATE';
					$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_birthdate( $user_id );
					Automator()->insert_trigger_meta( $save_meta );

					$save_meta['meta_key']   = 'FL_FOLLOWERS';
					$save_meta['meta_value'] = PeepSoUserFollower::count_followers( $user_id );
					Automator()->insert_trigger_meta( $save_meta );

					$save_meta['meta_key']   = 'FL_FOLLOWING';
					$save_meta['meta_value'] = PeepSoUserFollower::count_following( $user_id );
					Automator()->insert_trigger_meta( $save_meta );

					$save_meta['meta_key']   = 'FL_PROFILEURL';
					$save_meta['meta_value'] = $peepso_user->get_profileurl();
					Automator()->insert_trigger_meta( $save_meta );

					$save_meta['meta_key']   = 'USR_ABOUTME';
					$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_bio( $user_id );
					Automator()->insert_trigger_meta( $save_meta );

					$save_meta['meta_key']   = 'USR_WEBSITE';
					$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_website( $user_id );
					Automator()->insert_trigger_meta( $save_meta );

					$save_meta['meta_key']   = 'USR_USERROLE';
					$save_meta['meta_value'] = $peepso_user->get_user_role();
					Automator()->insert_trigger_meta( $save_meta );

					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}

}

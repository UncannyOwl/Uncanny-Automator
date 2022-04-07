<?php

namespace Uncanny_Automator;

use PeepSoUser;
use PeepSoUserFollower;

/**
 * Class PeepSo_USERFOLLOWSAUSER
 *
 * @package Uncanny_Automator
 */
class PeepSo_USERFOLLOWSAUSER {

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
		$this->trigger_code = 'PPUSERFOLLOWSAUSER';
		$this->trigger_meta = 'USERFOLLOWSAUSER';
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
			'sentence'            => sprintf( esc_attr__( 'A user follows {{another PeepSo member:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - PeepSo Core */
			'select_option_name'  => __( 'A user follows {{another PeepSo member}}', 'uncanny-automator' ),
			'action'              => 'peepso_ajax_start',
			'priority'            => 100,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'user_follows_user' ),
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
			'options' => array(
				Automator()->helpers->recipe->peepso->get_users( __( 'PeepSo member', 'uncanny-automator' ), $this->trigger_meta, array( 'uo_include_any' => true ), true ),
			),
		);

		$options = Automator()->utilities->keep_order_of_options( $options );

		return $options;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 */
	public function user_follows_user( $data ) {

		$recipes       = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );

		if ( ! $recipes ) {
			return;
		}

		if ( ! $required_post ) {
			return;
		}

		$follow_usr_id = 0;
		//Add where option is set to Any post / specific post
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id    = $trigger['ID'];
				$follow_usr_id = intval( $required_post[ $recipe_id ][ $trigger_id ] );
				if ( $follow_usr_id ) {
					continue;
				}
			}
		}

		if ( 'followerajax.set_follow_status' === $data ) {
			//phpcs:ignore PeepSo.Native.Ajax.Methods
			if ( automator_filter_has_var( 'follow', INPUT_POST ) && 0 === automator_filter_input( 'follow', INPUT_POST ) ) {
				return;
			}

			$user_id       = automator_filter_has_var( 'uid', INPUT_POST ) ? absint( automator_filter_input( 'uid', INPUT_POST ) ) : false;
			$follower_id   = automator_filter_has_var( 'user_id', INPUT_POST ) ? absint( automator_filter_input( 'user_id', INPUT_POST ) ) : false;
			$follow_status = automator_filter_has_var( 'follow', INPUT_POST ) ? absint( automator_filter_input( 'follow', INPUT_POST ) ) : false;

			if ( false === $follow_status || false === $follower_id ) {
				return;
			}

			if ( intval( $follow_usr_id ) === intval( '-1' ) ) {
				$follow_usr_id = $follower_id;
			} else {
				if ( $follower_id !== $follow_usr_id ) {
					return;
				}
			}

			$peepso_user   = PeepSoUser::get_instance( $follow_usr_id );
			$peepso_c_user = PeepSoUser::get_instance( $user_id );

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

						$save_meta['meta_key']   = 'FL_USERID';
						$save_meta['meta_value'] = absint( $follow_usr_id );
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
						$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_gender( $follower_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'FL_BIRTHDATE';
						$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_birthdate( $follower_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'FL_FOLLOWERS';
						$save_meta['meta_value'] = PeepSoUserFollower::count_followers( $follower_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'FL_FOLLOWING';
						$save_meta['meta_value'] = PeepSoUserFollower::count_following( $follower_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'FL_PROFILEURL';
						$save_meta['meta_value'] = $peepso_user->get_profileurl();
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'FL_EMAIL';
						$save_meta['meta_value'] = $peepso_user->get_email();
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'FL_ABOUTME';
						$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_bio( $follower_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'FL_WEBSITE';
						$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_website( $follower_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'FL_ROLE';
						$save_meta['meta_value'] = $peepso_user->get_user_role();
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'USR_AVATARURL';
						$save_meta['meta_value'] = $peepso_c_user->get_avatar();
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'USR_GENDER';
						$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_gender( $user_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'USR_BIRTHDATE';
						$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_birthdate( $user_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'USR_FOLLOWERS';
						$save_meta['meta_value'] = PeepSoUserFollower::count_followers( $user_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'USR_FOLLOWING';
						$save_meta['meta_value'] = PeepSoUserFollower::count_following( $user_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'USR_PROFILEURL';
						$save_meta['meta_value'] = $peepso_c_user->get_profileurl();
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'USR_ABOUTME';
						$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_bio( $user_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'USR_WEBSITE';
						$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_website( $user_id );
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'USR_ROLE';
						$save_meta['meta_value'] = $peepso_c_user->get_user_role();
						Automator()->insert_trigger_meta( $save_meta );

						$save_meta['meta_key']   = 'USR_USERROLE';
						$save_meta['meta_value'] = $peepso_c_user->get_user_role();
						Automator()->insert_trigger_meta( $save_meta );

						Automator()->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}
}

<?php

namespace Uncanny_Automator;

use PeepSo;
use PeepSoUser;
use PeepSoUserFollower;

/**
 * Class PeepSo_USERUPDATESPECIFICFIELD
 *
 * @package Uncanny_Automator
 */
class PeepSo_USERUPDATESPECIFICFIELD {

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
		$this->trigger_code = 'PPUSERUPDATESPECIFICFIELD';
		$this->trigger_meta = 'USERUPDATESPECIFICFIELD';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$user_fields = Automator()->helpers->recipe->peepso->get_user_fields( 0 );

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/peepso/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - PeepSo Core */
			'sentence'            => sprintf( esc_attr__( 'A user updates {{a specific field:%1$s}} in their profile', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - PeepSo Core */
			'select_option_name'  => __( 'A user updates {{a specific field}} in their profile', 'uncanny-automator' ),
			'action'              => 'peepso_ajax_start',
			'priority'            => 99,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'profile_update' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * load_options
	 */
	public function load_options() {

		$options = array(
			'options' => array(
				Automator()->helpers->recipe->peepso->get_profile_fields( __( 'Profile field', 'uncanny-automator' ), $this->trigger_meta, array( 'uo_include_any' => true ) ),
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
	public function profile_update( $data ) {

		$recipes       = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$user_id       = automator_filter_input( 'view_user_id', INPUT_POST );

		if ( ! $recipes ) {
			return;
		}

		if ( ! $required_post ) {
			return;
		}
		$ajax_actions = array(
			'profilefieldsajax.savefield',
			'profilefieldsajax.save_acc',
			'profilepreferencesajax.savepreference',
		);

		$restrict_fields = array(
			'peepso_hide_birthday_year',
			'peepso_is_profile_likable',
			'peepso_hide_online_status',
		);

		if ( ! in_array( $data, $ajax_actions ) ) {
			return;
		}
		$user_fields     = Automator()->helpers->recipe->peepso->get_user_fields( 0 );
		$user_fields_ids = array();
		foreach ( $user_fields as $key => $value ) {
			$user_fields_ids[] = $key;
		}

		//Add where option is set to Any field
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( intval( '-1' ) === intval( $required_post[ $recipe_id ][ $trigger_id ] ) || in_array( $required_post[ $recipe_id ][ $trigger_id ], $user_fields_ids, false ) ) {
					$matched_recipe_ids[] = array(
						'recipe_id'     => $recipe_id,
						'trigger_id'    => $trigger_id,
						'user_field_id' => $required_post[ $recipe_id ][ $trigger_id ],
					);
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$pass_args     = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
					'is_signed_in'     => true,
				);
				$user_field_id = $matched_recipe_id['user_field_id'];
				$args          = Automator()->maybe_add_trigger_entry( $pass_args, false );

				$peepso_user = PeepSoUser::get_instance( $user_id );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {

							if ( 'profilefieldsajax.savefield' === $data ) {
								$pp_field_id = automator_filter_input( 'id', INPUT_POST );
								$field_value = automator_filter_input( 'value', INPUT_POST );
								if ( 'm' === (string) $field_value ) {
									$field_value = __( 'Male', 'uncanny-automator' );
								}
								if ( 'f' === (string) $field_value ) {
									$field_value = __( 'Female', 'uncanny-automator' );
								}
								if ( intval( $user_field_id ) === intval( '-1' ) ) {
									$user_field_id = $pp_field_id;
								}
							} elseif ( 'profilefieldsajax.save_acc' === $data ) {
								$pp_field_id = automator_filter_input( 'id', INPUT_POST );
								$field_value = automator_filter_input( 'acc', INPUT_POST );
								$field_value = $this->get_privacy_status( $field_value );
								if ( intval( $user_field_id ) === intval( '-1' ) ) {
									$user_field_id = $pp_field_id;
								}
							} elseif ( 'profilepreferencesajax.savepreference' === $data ) {
								$pp_field_id = automator_filter_input( 'meta_key', INPUT_POST );
								if ( 'usr_profile_acc' === $pp_field_id || 'peepso_profile_post_acc' === $pp_field_id ) {
									$field_value = automator_filter_input( 'value', INPUT_POST );
									$field_value = $this->get_privacy_status( $field_value );
								} elseif ( 'peepso_gmt_offset' === $pp_field_id ) {
									$field_value = Automator()->helpers->recipe->peepso->get_gmt_value( automator_filter_input( 'value', INPUT_POST ) );
								} else {
									$field_value = ( 1 === absint( automator_filter_input( 'value', INPUT_POST ) ) ) ? __( 'Enabled', 'uncanny-automator' ) : __( 'Disabled', 'uncanny-automator' );
								}
								if ( intval( $user_field_id ) === intval( '-1' ) ) {
									$user_field_id = $pp_field_id;
								}
							} elseif ( 'profilepreferencesajax.save_notifications' === $data ) {
								$pp_field_id = automator_filter_input( 'fieldname', INPUT_POST );
								if ( in_array( $pp_field_id, $restrict_fields ) ) {
									return;
								}
								if ( 'email_intensity' === (string) $pp_field_id ) {
									$field_value = Automator()->helpers->recipe->peepso->get_email_intensity( automator_filter_input( 'value', INPUT_POST ) );
								} else {
									$field_value = ( 1 === absint( automator_filter_input( 'value', INPUT_POST ) ) ) ? __( 'Enabled', 'uncanny-automator' ) : __( 'Disabled', 'uncanny-automator' );
								}
								if ( intval( $user_field_id ) === intval( '-1' ) ) {
									$user_field_id = $pp_field_id;
								}
							}

							if ( $user_field_id === $pp_field_id || intval( $user_field_id ) === intval( '-1' ) ) {
								$save_meta = array(
									'user_id'        => $result['args']['user_id'],
									'trigger_id'     => $result['args']['trigger_id'],
									'run_number'     => $run_number,
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'ignore_user_id' => true,
								);

								$save_meta['meta_key']   = 'USR_AVATARURL';
								$save_meta['meta_value'] = $peepso_user->get_avatar();
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
								$save_meta['meta_value'] = $peepso_user->get_profileurl();
								Automator()->insert_trigger_meta( $save_meta );

								$save_meta['meta_key']   = 'USR_ABOUTME';
								$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_bio( $user_id );
								Automator()->insert_trigger_meta( $save_meta );

								$save_meta['meta_key']   = 'USR_WEBSITE';
								$save_meta['meta_value'] = Automator()->helpers->recipe->peepso->get_website( $user_id );
								Automator()->insert_trigger_meta( $save_meta );

								$save_meta['meta_key']   = 'USR_ROLE';
								$save_meta['meta_value'] = $peepso_user->get_user_role();
								Automator()->insert_trigger_meta( $save_meta );

								$save_meta['meta_key']   = 'USR_USERROLE';
								$save_meta['meta_value'] = $peepso_user->get_user_role();
								Automator()->insert_trigger_meta( $save_meta );

								$save_meta['meta_key']   = 'PPFIELD_NAME';
								$save_meta['meta_value'] = $user_fields[ $user_field_id ];
								Automator()->insert_trigger_meta( $save_meta );

								$save_meta['meta_key']   = 'PPFIELD_VALUE';
								$save_meta['meta_value'] = $field_value;
								Automator()->insert_trigger_meta( $save_meta );

								Automator()->maybe_trigger_complete( $result['args'] );
							}
						}
					}
				}
			}
		}
	}

	public function get_privacy_status( $merit ) {
		if ( $merit == PeepSo::ACCESS_PUBLIC ) {
			$field_value = __( 'Public', 'uncanny-automator' );
		}
		if ( $merit == PeepSo::ACCESS_MEMBERS ) {
			$field_value = __( 'Site Members', 'uncanny-automator' );
		}
		if ( $merit == PeepSo::ACCESS_PRIVATE ) {
			$field_value = __( 'Only Me', 'uncanny-automator' );
		}

		return $field_value;
	}

}

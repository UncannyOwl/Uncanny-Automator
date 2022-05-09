<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Recipe_Process_User
 * @package Uncanny_Automator
 */
class Automator_Recipe_Process_User {
	/**
	 * Automator_Recipe_Process_User constructor.
	 */
	public function __construct() {

	}

	/**
	 *
	 * Matches recipes against trigger meta/code. If a recipe is found and not completed,
	 * add a trigger entry in to the DB and matches number of times.
	 *
	 * @param      $args
	 * @param bool $mark_trigger_complete
	 *
	 * @return array|bool|int|null
	 */
	public function maybe_add_trigger_entry( $args, $mark_trigger_complete = true ) {
		$is_signed_in       = Automator()->is_user_signed_in( $args );
		$check_trigger_code = key_exists( 'code', $args ) ? $args['code'] : null;
		$trigger_meta       = key_exists( 'meta', $args ) ? $args['meta'] : null;
		$post_id            = key_exists( 'post_id', $args ) ? $args['post_id'] : 0;
		$user_id            = key_exists( 'user_id', $args ) ? $args['user_id'] : wp_get_current_user()->ID;
		$matched_recipe_id  = key_exists( 'recipe_to_match', $args ) ? (int) $args['recipe_to_match'] : null;
		$matched_trigger_id = key_exists( 'trigger_to_match', $args ) ? (int) $args['trigger_to_match'] : null;
		$ignore_post_id     = key_exists( 'ignore_post_id', $args ) ? true : false;
		$is_webhook         = key_exists( 'is_webhook', $args ) ? true : false;
		$webhook_recipe     = key_exists( 'webhook_recipe', $args ) ? (int) $args['webhook_recipe'] : null;
		$get_trigger_log_id = null;
		$result             = array();

		if ( is_null( $check_trigger_code ) ) {
			return null;
		}

		$args = [
			'code'             => $check_trigger_code,
			'meta'             => $trigger_meta,
			'post_id'          => $post_id,
			'user_id'          => $user_id,
			'recipe_to_match'  => $matched_recipe_id,
			'trigger_to_match' => $matched_trigger_id,
			'ignore_post_id'   => $ignore_post_id,
			'is_signed_in'     => $is_signed_in,
		];

		if ( $is_webhook ) {
			$recipes = Automator()->get->recipes_from_trigger_code( $check_trigger_code, $webhook_recipe );
		} else {
			$recipes = Automator()->get->recipes_from_trigger_code( $check_trigger_code );
		}

		foreach ( $recipes as $recipe ) {
			//loop only published
			if ( 'publish' !== $recipe['post_status'] ) {
				continue;
			}

			if ( 'user' === (string) $recipe['recipe_type'] && ! $is_signed_in ) {
				//If it's user recipe & user is not logged in.. skip recipe
				continue;
			}

			$recipe_id = absint( $recipe['ID'] );

			/**
			 * if recipe is already completed, bail early
			 * @version 2.5.1
			 * @author  Saad
			 */
			if ( Automator()->is_recipe_completed( $recipe_id, $user_id ) ) {
				continue;
			}

			$maybe_recipe_log    = $this->maybe_create_recipe_log_entry( $recipe_id, $user_id, true, $args, true );
			$maybe_recipe_log_id = (int) $maybe_recipe_log['recipe_log_id'];
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( ! empty( $matched_trigger_id ) && is_numeric( $matched_trigger_id ) && (int) $matched_trigger_id !== (int) $trigger['ID'] ) {
					continue;
				}

				$trigger_id          = absint( $trigger['ID'] );
				$trigger_post_status = $trigger['post_status'];

				if ( 'publish' !== $trigger_post_status ) {
					continue;
				}

				$get_trigger_log_id = $this->get_trigger_id( $args, $trigger, $recipe_id, $maybe_recipe_log_id, $ignore_post_id );

				if ( is_array( $get_trigger_log_id ) && false === $get_trigger_log_id['result'] ) {
					$result[] = $get_trigger_log_id;
					continue;
				}

				if ( ! $maybe_recipe_log['existing'] ) {
					//trigger validated.. add recipe log ID now!
					$recipe_log_details = $this->maybe_create_recipe_log_entry( $recipe_id, $user_id, true, $args );
					$recipe_log_id      = (int) $recipe_log_details['recipe_log_id'];
					//running again--after $recipe_log_id
					$get_trigger_log_id = $this->get_trigger_id( $args, $trigger, $recipe_id, $maybe_recipe_log_id, $ignore_post_id );
				} else {
					$recipe_log_id = $maybe_recipe_log_id;
				}

				$get_trigger_log_id = $get_trigger_log_id['trigger_log_id'];

				$numtimes_arg = [
					'recipe_id'      => $recipe_id,
					'trigger_id'     => $trigger_id,
					'trigger'        => $trigger,
					'user_id'        => $user_id,
					'recipe_log_id'  => $recipe_log_id,
					'trigger_log_id' => $get_trigger_log_id,
					'is_signed_in'   => $is_signed_in,
				];

				$trigger_steps_completed = $this->maybe_trigger_num_times_completed( $numtimes_arg );

				//If -1 / Any option is used, save it's entry for tokens
				if ( ( isset( $trigger['meta'][ $trigger_meta ] ) && intval( '-1' ) === intval( $trigger['meta'][ $trigger_meta ] ) ) && true === $trigger_steps_completed['result'] ) {
					$meta_arg = [
						'recipe_id'      => $recipe_id,
						'trigger_id'     => $trigger_id,
						'user_id'        => $user_id,
						'recipe_log_id'  => $recipe_log_id,
						'trigger_log_id' => $get_trigger_log_id,
						'post_id'        => $post_id,
						'trigger'        => $trigger,
						'is_signed_in'   => $is_signed_in,
						'meta'           => $trigger_meta,
						'run_number'     => Automator()->get->next_run_number( $recipe_id, $user_id, true ),
					];

					// Fix to avoid saving value as 0 when Any option is selected
					if ( 0 !== absint( $post_id ) ) {
						$meta_results = $this->maybe_trigger_add_any_option_meta( $meta_arg, $trigger_meta );
						if ( isset( $meta_results['result'] ) && false === $meta_results['result'] ) {
							Automator()->error->add_error( 'uap_maybe_add_meta_entry', 'ERROR: You are trying to add entry ' . $trigger['meta'][ $trigger_meta ] . ' and post_id = ' . $post_id . '.', $this );
						}
					}
				}

				do_action_deprecated(
					'uap_after_trigger_run',
					array(
						$check_trigger_code,
						$post_id,
						$user_id,
						$trigger_meta,
					),
					'3.0',
					'automator_after_trigger_run'
				);
				do_action( 'automator_after_trigger_run', $check_trigger_code, $post_id, $user_id, $trigger_meta );

				if ( true === $trigger_steps_completed['result'] ) {
					/**
					 * @deprecated  $args['trigger_log_id'] Use $args['trigger_log_id'].
					 * @version 3.0
					 */
					$args['get_trigger_id'] = $get_trigger_log_id;
					$args['trigger_log_id'] = $get_trigger_log_id;
					$args['recipe_id']      = $recipe_id;
					$args['trigger_id']     = $trigger_id;
					$args['recipe_log_id']  = $recipe_log_id;
					$args['post_id']        = $post_id;
					$args['is_signed_in']   = $is_signed_in;
					$args['run_number']     = Automator()->get->next_run_number( $recipe_id, $user_id, true );

					if ( 1 === + $mark_trigger_complete ) {
						$this->maybe_trigger_complete( $args );
					} else {
						$result[] = array(
							'result' => true,
							'args'   => $args,
						); //phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @param int $recipe_id
	 * @param int $user_id
	 * @param bool $create_recipe
	 * @param array $args
	 * @param bool $maybe_simulate
	 * @param null $maybe_add_log_id
	 *
	 * @return array
	 * @since  2.0
	 * @author Saad S. on Nov 15th, 2019
	 *
	 * Added $maybe_simulate in order to avoid unnecessary recipe logs in database.
	 * It'll return existing $recipe_log_id if there's one for a user & recipe, or
	 * simulate an ID for the next run.. The reason for simulate is to avoid unnecessary
	 * recipe_logs in the database since we insert recipe log first & check if trigger
	 * is valid after which means, recipe log is added and not used in this run.
	 * Once trigger is validated.. I pass $maybe_simulate ID to $maybe_add_log_id
	 * and insert recipe log at this point.
	 *
	 */
	public function maybe_create_recipe_log_entry( $recipe_id, $user_id, $create_recipe = true, $args = array(), $maybe_simulate = false, $maybe_add_log_id = null ) {
		global $wpdb;

		$recipe_log_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID
						FROM {$wpdb->prefix}uap_recipe_log
						WHERE completed NOT IN (1,2,5,9)
						AND automator_recipe_id = %d
						AND user_id = %d",
				$recipe_id,
				$user_id
			)
		);
		if ( $recipe_log_id && 0 !== absint( $user_id ) ) {
			return array(
				'existing'      => true,
				'recipe_log_id' => $recipe_log_id,
			);
		} elseif ( true === $maybe_simulate ) {
			/*
			 * @since 2.0
			 * @author Saad S.
			 */
			if ( ! is_null( $maybe_add_log_id ) ) {
				return array(
					'existing'      => false,
					'recipe_log_id' => $this->insert_recipe_log( $recipe_id, $user_id, $maybe_add_log_id ),
				);
			} else {

				/**
				 * Query changed from Table schema to Max(ID) to support wider MySQL settings
				 * Next Auto_Increment in certain environments returned last inserted ID instead of
				 * next one. Manually add 1 to get next insert ID
				 *
				 * @version 2.6.3
				 *
				 * Query changed back to AUTO_INCREMENT but added another query to reset cache
				 * @version 2.9
				 * @author  Saad S.
				 */

				//Check if it's MySQL 8+
				$check_mysql8 = $wpdb->get_results( "SHOW VARIABLES LIKE 'information_schema_stats_expiry'" );
				if ( ! empty( $check_mysql8 ) ) {
					$wpdb->query( 'SET information_schema_stats_expiry = 0;' );
				}

				$recipe_log_id = $wpdb->get_var( "SELECT `AUTO_INCREMENT` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->prefix}uap_recipe_log';" );

				return array(
					'existing'      => false,
					'recipe_log_id' => $recipe_log_id,
				);
			}
		} elseif ( true === $create_recipe ) {
			return array(
				'existing'      => false,
				'recipe_log_id' => $this->insert_recipe_log( $recipe_id, $user_id, null ),
			);
		}

		return array(
			'existing'      => false,
			'recipe_log_id' => null,
		);
	}

	/**
	 * @param      $recipe_id
	 * @param      $user_id
	 * @param null $maybe_add_log_id
	 *
	 * @return int
	 */
	public function insert_recipe_log( $recipe_id, $user_id, $maybe_add_log_id = null ) {

		global $wpdb;
		$table_name = $wpdb->prefix . 'uap_recipe_log';

		$results = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(completed) FROM {$wpdb->prefix}uap_recipe_log WHERE  completed = 1 AND user_id = %d AND automator_recipe_id = %d", $user_id, $recipe_id ) );

		if ( 0 !== absint( $user_id ) ) {
			$num_times_recipe_run = Automator()->utilities->recipe_number_times_completed( $recipe_id, $results );
		} else {
			$num_times_recipe_run = false;
		}


		if ( ! $num_times_recipe_run ) {
			$run_number = Automator()->get->next_run_number( $recipe_id, $user_id );

			$insert = array(
				'date_time'           => '0000-00-00 00:00:00',
				'user_id'             => $user_id,
				'automator_recipe_id' => $recipe_id,
				'completed'           => - 1,
				'run_number'          => $run_number,
			);

			$format = array(
				'%s',
				'%d',
				'%d',
				'%d',
			);

			/*
			 * Force new ID
			 * if ( ! is_null( $maybe_add_log_id ) ) {
				$insert['ID'] = $maybe_add_log_id;
				$format[]     = '%d';
			}*/

			$wpdb->insert( $table_name, $insert, $format );
			$recipe_log_id = (int) $wpdb->insert_id;

			return $recipe_log_id;
		}

		return null;
	}

	/**
	 * @param $args
	 * @param $trigger
	 * @param $recipe_id
	 * @param $maybe_recipe_log_id
	 * @param $ignore_post_id
	 *
	 * @return array
	 */
	public function get_trigger_id( $args, $trigger, $recipe_id, $maybe_recipe_log_id, $ignore_post_id ) {

		if ( $ignore_post_id ) {
			$get_trigger_id = $this->maybe_validate_trigger_without_postid( $args, $trigger, $recipe_id, $maybe_recipe_log_id );
		} else {
			$get_trigger_id = $this->maybe_validate_trigger( $args, $trigger, $recipe_id, $maybe_recipe_log_id );
		}

		return $get_trigger_id;
	}

	/**
	 *
	 * Validate recipe post ID when ignore post id is passed.
	 * This is mostly going to be used when user/dev done validation in trigger
	 * and passes recipe IDs for this to be validated and added to trigger log DB.
	 *
	 * @param array $args
	 * @param null $trigger
	 * @param null $recipe_id
	 * @param null $recipe_log_id
	 *
	 * @return array
	 */
	public function maybe_validate_trigger_without_postid( $args = array(), $trigger = null, $recipe_id = null, $recipe_log_id = null ) {

		if ( empty( $args ) || null === $trigger || null === $recipe_id ) {
			return [
				'result' => false,
				'error'  => esc_html__( 'One of the required field is missing.', 'uncanny-automator' ),
			];
		}


		$check_trigger_code  = $args['code'];
		$trigger_meta        = $args['meta'];
		$user_id             = $args['user_id'];
		$matched_recipe_id   = $args['recipe_to_match'];
		$matched_trigger_id  = $args['trigger_to_match'];
		$trigger_id          = is_numeric( $matched_trigger_id ) ? (int) $matched_trigger_id : $trigger['ID'];
		$trigger_code        = $trigger['meta']['code'];
		$trigger_integration = $trigger['meta']['integration'];

		// Skip completion if the plugin is not active
		if ( 0 === Automator()->plugin_status->get( $trigger_integration ) ) {
			// The plugin for this trigger is NOT active
			Automator()->error->add_error( 'uap_do_trigger_log', 'ERROR: You are trying to complete ' . $trigger['meta']['code'] . ' and the plugin ' . $trigger_integration . ' is not active. ', $this );

			return [
				'result' => false,
				'error'  => esc_html__( 'Plugin is not active.', 'uncanny-automator' ),
			];
		}

		/*if ( is_null( $recipe_log_id ) || ! is_numeric( $recipe_log_id ) ) {
			$recipe_log_id = $this->maybe_create_recipe_log_entry( $recipe_id, $user_id, true );
		}*/

		// Stop here if the trigger was already completed
		$is_trigger_completed = $this->is_trigger_completed( $user_id, $trigger_id, $recipe_id, $recipe_log_id, $args );

		if ( $is_trigger_completed ) {
			return [
				'result' => false,
				'error'  => esc_html__( 'Trigger is completed.', 'uncanny-automator' ),
			];
		}
		// Skip if the executed trigger doesn't match
		if ( (string) $check_trigger_code !== (string) $trigger_code ) {
			return [
				'result' => false,
				'error'  => sprintf( '%s AND %s triggers not matched.', $check_trigger_code, $trigger_code ),
			];
		}

		if ( 0 !== (int) $matched_recipe_id && (int) $recipe_id !== (int) $matched_recipe_id ) {
			return [
				'result' => false,
				'error'  => esc_html__( 'Recipe not matched.', 'uncanny-automator' ),
			];
		} elseif ( (int) $recipe_id === (int) $matched_recipe_id ) {
			/**
			 * Added second part of code to check for MAGICBUTTON
			 * since trigger meta of MAGICBUTTON is saved by
			 * `code` instead of `meta`
			 *
			 * @version 2.1.6
			 * @author  Saad
			 */
			if ( ! isset( $trigger['meta'][ $trigger_meta ] ) && ! isset( $trigger['meta'][ $args['code'] ] ) ) {
				return [
					'result' => false,
					'error'  => esc_html__( 'Trigger meta not found.', 'uncanny-automator' ),
				];
			}
		}

		return $this->maybe_get_trigger_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id );
	}

	/**
	 * Check if the trigger is completed
	 *
	 * @param       $user_id       null
	 * @param       $trigger_id    null
	 * @param       $recipe_id     null
	 * @param       $recipe_log_id null
	 * @param array $args
	 * @param bool $process_recipe
	 *
	 * @return null|bool
	 * @deprecated 3.0
	 */
	public function is_trigger_completed( $user_id = null, $trigger_id = null, $recipe_id = null, $recipe_log_id = null, $args = array(), $process_recipe = false ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'Automator()->process->user->is_trigger_completed( ...$args )', 'Use Automator()->db->trigger->is_completed( ...$args ) instead.', '3.0' );
		}

		return Automator()->db->trigger->is_completed( $user_id, $trigger_id, $recipe_id, $recipe_log_id, $process_recipe, $args );
	}

	/**
	 *
	 * Record an entry in to DB against a trigger
	 *
	 * @param      $user_id
	 * @param      $trigger_id
	 * @param      $recipe_id
	 * @param null $recipe_log_id
	 *
	 * @return array
	 */
	public function maybe_get_trigger_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id = null ) {
		if ( null === $trigger_id || null === $recipe_id || null === $user_id ) {
			return array(
				'result' => false,
				'error'  => esc_html__( 'One of the required field is missing.', 'uncanny-automator' ),
			);
		}

		$get_trigger_id = Automator()->get->trigger_log_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id );

		if ( is_null( $get_trigger_id ) && is_numeric( $recipe_log_id ) ) {
			//Nothing found! Insert
			$get_trigger_id = $this->insert_trigger( $user_id, $trigger_id, $recipe_id, false, $recipe_log_id );
		}

		return array(
			'result'         => true,
			'trigger_log_id' => $get_trigger_id,
		);
	}

	/**
	 * Insert trigger for the user
	 *
	 * @param $user_id
	 * @param $trigger_id
	 * @param $recipe_id
	 * @param $completed
	 * @param $recipe_log_id
	 *
	 * @return int|null
	 * @deprecated 3.0
	 */
	public function insert_trigger( $user_id = null, $trigger_id = null, $recipe_id = null, $completed = false, $recipe_log_id = null ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'Automator()->insert_trigger()', 'User Automator()->db->trigger->add() instead', '3.0' );
		}

		return Automator()->db->trigger->add( $user_id, $trigger_id, $recipe_id, $completed, $recipe_log_id );
	}

	/**
	 *
	 * Validate (int) values trigger v/s (int) trigger['meta'].
	 * If matched add value to trigger log table
	 *
	 * @param array $args
	 * @param null $trigger
	 * @param null $recipe_id
	 * @param null $recipe_log_id
	 *
	 * @return array
	 */
	public function maybe_validate_trigger( $args = array(), $trigger = null, $recipe_id = null, $recipe_log_id = null ) {

		if ( empty( $args ) || null === $trigger || null === $recipe_id ) {
			return [
				'result' => false,
				'error'  => esc_html__( 'One of the required field is missing.', 'uncanny-automator' ),
			];
		}

		$check_trigger_code  = $args['code'];
		$trigger_meta        = $args['meta'];
		$post_id             = $args['post_id'];
		$user_id             = $args['user_id'];
		$trigger_id          = $trigger['ID'];
		$trigger_code        = $trigger['meta']['code'];
		$trigger_integration = $trigger['meta']['integration'];

		// Skip completion if the plugin is not active
		if ( 0 === Automator()->plugin_status->get( $trigger_integration ) ) {
			// The plugin for this trigger is NOT active
			Automator()->error->add_error( 'uap_do_trigger_log', 'ERROR: You are trying to complete ' . $trigger['meta']['code'] . ' and the plugin ' . $trigger_integration . ' is not active. ', $this );

			return [
				'result' => false,
				'error'  => esc_html__( 'Plugin is not active.', 'uncanny-automator' ),
			];
		}

		// Stop here if the trigger was already completed
		$is_trigger_completed = $this->is_trigger_completed( $user_id, $trigger_id, $recipe_id, $recipe_log_id, $args );

		if ( $is_trigger_completed ) {
			return [
				'result' => false,
				'error'  => esc_html__( 'Trigger is completed.', 'uncanny-automator' ),
			];
		}

		// Skip if the executed trigger doesn't match
		if ( $check_trigger_code !== $trigger_code ) {
			return [
				'result' => false,
				'error'  => esc_html__( 'Trigger isn\'t matched.', 'uncanny-automator' ),
			];
		}

		// The post ID the current user needs to visit
		if ( key_exists( $trigger_meta, $trigger['meta'] ) ) {
			$trigger_post_id = intval( $trigger['meta'][ $trigger_meta ] );
		} else {
			$trigger_post_id = 0;
		}

		if ( intval( '-1' ) !== intval( $trigger_post_id ) ) {
			if ( is_numeric( $trigger_post_id ) && is_numeric( $post_id ) && absint( $trigger_post_id ) !== absint( $post_id ) ) {
				return [
					'result' => false,
					'error'  => esc_html__( 'Trigger not matched.', 'uncanny-automator' ),
				];
			} elseif ( (string) $trigger_post_id != (string) $post_id ) {
				return [
					'result' => false,
					'error'  => esc_html__( 'Trigger not matched.', 'uncanny-automator' ),
				];
			}
		}

		return $this->maybe_get_trigger_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id );
	}

	/**
	 * Validate if the number of times of a trigger condition met
	 *
	 * @param $times_args
	 *
	 * @return array
	 */
	public function maybe_trigger_num_times_completed( $times_args ) {

		$recipe_id      = key_exists( 'recipe_id', $times_args ) ? $times_args['recipe_id'] : null;
		$trigger_id     = key_exists( 'trigger_id', $times_args ) ? $times_args['trigger_id'] : null;
		$trigger        = key_exists( 'trigger', $times_args ) ? $times_args['trigger'] : null;
		$user_id        = key_exists( 'user_id', $times_args ) ? $times_args['user_id'] : null;
		$recipe_log_id  = key_exists( 'recipe_log_id', $times_args ) ? $times_args['recipe_log_id'] : null;
		$trigger_log_id = key_exists( 'trigger_log_id', $times_args ) ? $times_args['trigger_log_id'] : null;

		if ( null === $trigger_id || null === $trigger || null === $user_id ) {
			return array(
				'result' => false,
				'error'  => esc_html__( 'One of the required field is missing.', 'uncanny-automator' ),
			);
		}

		// The number of times the current user needs to visit the post/page
		$num_times = key_exists( 'NUMTIMES', $trigger['meta'] ) ? absint( $trigger['meta']['NUMTIMES'] ) : 1;

		// Get max run number from trigger logs
		$run_number = Automator()->get->trigger_run_number( $trigger_id, $trigger_log_id, $user_id );

		// How many times has this user triggered this trigger
		$user_num_times = Automator()->get->trigger_meta( $user_id, $trigger['ID'], 'NUMTIMES', $trigger_log_id );

		$args = [
			'user_id'        => $user_id,
			'trigger_id'     => $trigger_id,
			'meta_key'       => 'NUMTIMES',
			'run_number'     => $run_number,
			'trigger_log_id' => $trigger_log_id,
		];

		if ( empty( $user_num_times ) ) {
			//This is first time user visited
			$args['meta_value'] = 1;
			$user_num_times     = 1;
		} else {

			$user_num_times ++;
			$run_number         = $run_number + 1;
			$args['run_number'] = $run_number;
			$args['meta_value'] = 1;
		}

		$this->insert_trigger_meta( $args );

		/**  Moved this from Completed to run number code */

		/**
		 * Provide hook to developers to hook in to and
		 * do what they want to do with it
		 *
		 * @version 2.5.1
		 * @author  Saad
		 *
		 */
		$trigger_data = Automator()->get->trigger_sentence( $trigger_id, 'trigger_detail' );
		do_action( 'automator_complete_trigger_detail', $trigger_data, $args );

		$sentence_human_readable = Automator()->get->trigger_sentence( $trigger_id, 'sentence_human_readable' );

		// Store trigger sentence details for the completion
		if ( ! empty( $sentence_human_readable ) ) {
			$save_meta = array(
				'user_id'        => $user_id,
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_id,
				'run_number'     => $run_number,
				'meta_key'       => 'sentence_human_readable',
				'meta_value'     => $sentence_human_readable,
			);

			Automator()->process->user->insert_trigger_meta( $save_meta );
		}
		/**  */

		//change completed from -1 to 0
		$this->maybe_change_recipe_log_to_zero( $recipe_id, $user_id, $recipe_log_id, true );

		// Move on if the user didn't trigger the trigger enough times
		if ( $user_num_times < $num_times ) {
			return [
				'result' => false,
				'error'  => 'Number of times condition is not completed.',
			];
		}

		// If the trigger was hit the enough times then complete the trigger
		if ( $user_num_times >= $num_times ) {
			return array(
				'result'     => true,
				'error'      => 'Number of times condition met.',
				'run_number' => $args['run_number'],
			);
		}

		return array(
			'result' => false,
			'error'  => 'Default return. Something is wrong.',
		);
	}

	/**
	 * Insert the trigger for the user
	 *
	 * @param $args
	 *
	 * @return null
	 * @deprecated 3.0
	 */
	public function insert_trigger_meta( $args ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'Automator()->process->user->insert_trigger_meta( $args )', 'Use Automator()->db->trigger->insert_meta( $trigger_id, $trigger_log_id, $run_number, $args ) instead.', '3.0' );
		}
		$trigger_id     = absint( $args['trigger_id'] );
		$trigger_log_id = absint( $args['trigger_log_id'] );
		$run_number     = absint( $args['run_number'] );

		return Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );
	}

	/**
	 * @param      $recipe_id
	 * @param      $user_id
	 * @param      $recipe_log_id
	 * @param bool $change_to_zero
	 */
	public function maybe_change_recipe_log_to_zero( $recipe_id, $user_id, $recipe_log_id, $change_to_zero = false ) {
		$if_exists = Automator()->db->recipe->log_run_pre_exists( $recipe_id, $user_id );
		if ( ! empty( $if_exists ) && (int) $if_exists === (int) $recipe_log_id && true === $change_to_zero ) {
			Automator()->db->recipe->mark_incomplete( $recipe_id, $recipe_log_id );
		}
	}

	/**
	 * Validate if the number of times of a trigger condition met
	 *
	 * @param      $option_meta
	 * @param null $save_for_option
	 *
	 * @return array
	 */
	public function maybe_trigger_add_any_option_meta( $option_meta, $save_for_option = null ) {
		if ( is_null( $save_for_option ) ) {
			return array(
				'result' => false,
				'error'  => esc_html__( 'Option meta not defined.', 'uncanny-automator' ),
			);
		}

		$trigger_id     = key_exists( 'trigger_id', $option_meta ) ? absint( $option_meta['trigger_id'] ) : null;
		$user_id        = key_exists( 'user_id', $option_meta ) ? absint( $option_meta['user_id'] ) : null;
		$trigger_log_id = key_exists( 'trigger_log_id', $option_meta ) ? absint( $option_meta['trigger_log_id'] ) : null;
		$post_id        = key_exists( 'post_id', $option_meta ) ? $option_meta['post_id'] : null;
		$run_number     = Automator()->get->next_run_number( $option_meta['recipe_id'], $user_id, true );
		$trigger        = key_exists( 'trigger', $option_meta ) ? $option_meta['trigger'] : null;
		$trigger_meta   = ! empty( $save_for_option ) ? $save_for_option : null;

		if ( null === $trigger_id || null === $trigger || null === $user_id ) {
			return array(
				'result' => false,
				'error'  => 'One of the required field is missing.',
			);
		}

		$args = array(
			'user_id'        => $user_id,
			'trigger_id'     => $trigger_id,
			'meta_key'       => $trigger_meta,
			'meta_value'     => $post_id,
			'run_number'     => $run_number,
			'trigger_log_id' => $trigger_log_id,
		);

		$meta_already_saved = Automator()->get->maybe_get_meta_id_from_trigger_log( $run_number, $trigger_id, $trigger_log_id, $trigger_meta, $user_id );

		if ( ! $meta_already_saved ) {
			return array(
				'result' => Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args ),
				'error'  => esc_html__( 'Meta entry added.', 'uncanny-automator' ),
			);
		}
		if ( is_numeric( $meta_already_saved ) ) {
			$args['trigger_log_meta_id'] = $meta_already_saved;

			return array(
				'result' => $this->update_trigger_meta( $user_id, $trigger_id, $trigger_meta, $post_id, $trigger_log_id ),
				'error'  => esc_html__( 'Meta entry updated.', 'uncanny-automator' ),
			);
		}

		return array(
			'result' => false,
			'error'  => esc_html__( 'No action happened.', 'uncanny-automator' ),
		);

	}

	/**
	 * Update the trigger for the user
	 *
	 * @param $user_id       null
	 * @param $trigger_id    null
	 * @param $meta_key      null
	 * @param $meta_value    string
	 * @param $trigger_log_id
	 *
	 * @return null
	 * @deprecated 3.0
	 */
	public function update_trigger_meta( $user_id = null, $trigger_id = null, $meta_key = null, $meta_value = '', $trigger_log_id = null ) {

		// Set user ID
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		// No user id is aviable.
		if ( 0 === $user_id ) {
			Automator()->error->add_error( 'update_trigger_meta', 'ERROR: You are trying to update trigger meta when a there is no logged in user.', $this );

			return null;
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Automator()->error->add_error( 'update_trigger_meta', 'ERROR: You are trying to update trigger meta without providing a trigger_id', $this );

			return null;
		}

		if ( null === $meta_key || ! is_string( $meta_key ) ) {
			Automator()->error->add_error( 'update_trigger_meta', 'ERROR: You are trying to update trigger meta without providing a meta_key', $this );

			return null;
		}

		$update = array(
			'meta_value' => $meta_value,
			'run_time'   => current_time( 'mysql' ),
		);

		$where = array(
			'user_id'              => $user_id,
			'automator_trigger_id' => $trigger_id,
			'meta_key'             => $meta_key,
		);

		if ( ! empty( $trigger_log_id ) && is_numeric( $trigger_log_id ) ) {
			$where['automator_trigger_log_id'] = (int) $trigger_log_id;
		}

		$update_format = array(
			'%d',
			'%s',
		);

		$where_format = array(
			'%d',
			'%d',
			'%s',
		);

		if ( ! empty( $trigger_log_id ) && is_numeric( $trigger_log_id ) ) {
			$where_format[] = '%d';
		}

		return Automator()->db->trigger->update_meta(
			$update,
			$where,
			$update_format,
			$where_format
		);
	}

	/**
	 *
	 * Complete a trigger once all validation & trigger entry added
	 * and number of times met, complete the trigger
	 *
	 * @param $args
	 *
	 * @return bool|void
	 */
	public function maybe_trigger_complete( $args ) {
		$is_signed_in = Automator()->is_user_signed_in( $args );

		if ( empty( $args ) && false === $is_signed_in ) {
			return false;
		}

		Automator()->complete->trigger( $args );
	}

	/**
	 * Get the trigger for the user
	 *
	 * @param null $user_id
	 * @param null $trigger_id
	 * @param null $meta_key
	 * @param null $recipe_log_id
	 *
	 * @return null|int
	 */
	public function trigger_meta_id( $user_id = null, $trigger_id = null, $meta_key = null, $recipe_log_id = null ) {

		// Set user ID
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		// No user id is aviable.
		if ( 0 === $user_id ) {
			Automator()->error->add_error( 'get_trigger_meta_id', 'ERROR: You are trying to get trigger meta ID when a there is no logged in user.', $this );

			return null;
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Automator()->error->add_error( 'get_trigger_meta_id', 'ERROR: You are trying to get trigger meta ID without providing a trigger_id', $this );

			return null;
		}

		if ( null === $meta_key || ! is_string( $meta_key ) ) {
			Automator()->error->add_error( 'get_trigger_meta_id', 'ERROR: You are trying to get trigger meta ID without providing a meta_key', $this );

			return null;
		}

		global $wpdb;
		$results = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}uap_trigger_log_meta WHERE user_id = %d AND meta_key LIKE %s AND automator_trigger_id = %d", $user_id, $meta_key, $trigger_id ) );

		if ( null !== $results ) {
			return (int) $results;
		}

		return $results;
	}


	/**
	 * Update the trigger for the user
	 *
	 * @param $user_id    null
	 * @param $trigger_id null
	 * @param $recipe_id  null
	 * @param $ID         null
	 *
	 * @return null|void
	 */
	public function update_trigger( $user_id = null, $trigger_id = null, $recipe_id = null, $ID = null ) {

		// Set user ID
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		// No user id is aviable.
		if ( 0 === $user_id ) {
			Automator()->error->add_error( 'update_trigger', 'ERROR: You are trying to update a trigger when a there is no logged in user.', $this );

			return null;
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Automator()->error->add_error( 'update_trigger', 'ERROR: You are trying to update a trigge without providing a trigger_id', $this );

			return null;
		}

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Automator()->error->add_error( 'update_trigger', 'ERROR: You are trying to update a trigge without providing a recipe_id', $this );

			return null;
		}
	}
}

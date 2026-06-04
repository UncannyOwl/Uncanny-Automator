<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Pipeline_Context;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;
use Uncanny_Automator\App\Recipe_Runner\Services\Trigger_Validator as Api_Trigger_Validator;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Log_Manager as Api_Recipe_Log_Manager;
use Uncanny_Automator\App\Recipe_Runner\Services\Trigger_Numtimes as Api_Trigger_Numtimes;
use Uncanny_Automator\App\Recipe_Runner\Stages\Trigger_Entry_Stage as Api_Trigger_Entry;

/**
 * Class Automator_Recipe_Process_User
 *
 * Facade that delegates to Core API services for trigger entry processing.
 * Pro extends this class via inheritance — all public methods must remain.
 *
 * @package Uncanny_Automator
 */
class Automator_Recipe_Process_User {

	/**
	 * @var Api_Trigger_Validator
	 */
	protected $validator;

	/**
	 * @var Api_Recipe_Log_Manager
	 */
	protected $log_manager;

	/**
	 * @var Api_Trigger_Numtimes
	 */
	protected $numtimes;

	/**
	 * @var Api_Trigger_Entry
	 */
	protected $trigger_entry;

	/**
	 * Automator_Recipe_Process_User constructor.
	 *
	 * Services are lazy-initialized via getters because Pro subclasses
	 * override this constructor without calling parent::__construct().
	 */
	public function __construct() {
		// Intentionally empty — services are lazy-loaded in getters.
	}

	/**
	 * Get the Recipe_Runner singleton if available.
	 *
	 * @return \Uncanny_Automator\App\Recipe_Runner\Recipe_Runner|null
	 */
	protected function get_recipe_runner() {
		if ( function_exists( 'Automator' ) && null !== Automator()->recipe_runner ) {
			return Automator()->recipe_runner;
		}
		return null;
	}

	/**
	 * @return Api_Trigger_Validator
	 */
	protected function get_validator() {
		if ( null === $this->validator ) {
			$runner          = $this->get_recipe_runner();
			$this->validator = null !== $runner ? $runner->validator() : new Api_Trigger_Validator();
		}
		return $this->validator;
	}

	/**
	 * @return Api_Recipe_Log_Manager
	 */
	protected function get_log_manager() {
		if ( null === $this->log_manager ) {
			$runner            = $this->get_recipe_runner();
			$this->log_manager = null !== $runner ? $runner->log_manager() : new Api_Recipe_Log_Manager();
		}
		return $this->log_manager;
	}

	/**
	 * @return Api_Trigger_Numtimes
	 */
	protected function get_numtimes() {
		if ( null === $this->numtimes ) {
			$runner         = $this->get_recipe_runner();
			$this->numtimes = null !== $runner ? $runner->numtimes() : new Api_Trigger_Numtimes( $this->get_log_manager() );
		}
		return $this->numtimes;
	}

	/**
	 * @return Api_Trigger_Entry
	 */
	protected function get_trigger_entry() {
		if ( null === $this->trigger_entry ) {
			$runner              = $this->get_recipe_runner();
			$this->trigger_entry = null !== $runner ? $runner->trigger_entry() : new Api_Trigger_Entry( $this->get_validator(), $this->get_log_manager(), $this->get_numtimes() );
		}
		return $this->trigger_entry;
	}

	/**
	 * Matches recipes against trigger meta/code. If a recipe is found and not completed,
	 * add a trigger entry in to the DB and matches number of times.
	 *
	 * Normalizes legacy args, builds Pipeline_Context, runs the trigger entry
	 * stage, and converts Pipeline_Result back to the legacy return format.
	 *
	 * @param      $args
	 * @param bool $mark_trigger_complete
	 * @param array $trigger_args
	 *
	 * @return array|null
	 */
	public function maybe_add_trigger_entry( $args, $mark_trigger_complete = true, $trigger_args = array() ) {

		$args = (array) $args;

		// Guard: no trigger code → null (legacy contract).
		if ( ! key_exists( 'code', $args ) || null === $args['code'] ) {
			return null;
		}

		// Normalize fields that Pipeline_Context expects.
		$args = $this->normalize_trigger_args( $args );

		$context = new Pipeline_Context( $args, (bool) $mark_trigger_complete );
		$result  = new Pipeline_Result();

		$result = $this->get_trigger_entry()->execute( $context, $result );

		return $this->convert_pipeline_result( $result, (bool) $mark_trigger_complete );
	}

	/**
	 * Normalize raw trigger args before building Pipeline_Context.
	 *
	 * Computes is_signed_in, resolves user_id, and maps webhook_recipe
	 * to recipe_to_match so Pipeline_Context can read them.
	 *
	 * @param array $args Raw trigger args from caller.
	 *
	 * @return array Normalized args.
	 */
	private function normalize_trigger_args( array $args ): array {

		if ( ! isset( $args['is_signed_in'] ) ) {
			$args['is_signed_in'] = Automator()->is_user_signed_in( $args );
		}

		if ( ! isset( $args['user_id'] ) ) {
			$args['user_id'] = wp_get_current_user()->ID;
		}

		// Note: webhook_recipe is NOT mapped to recipe_to_match.
		// Legacy keeps them separate: webhook_recipe is for recipe filtering only,
		// recipe_to_match is for validation. Pipeline_Context reads webhook_recipe directly.

		return $args;
	}

	/**
	 * Convert Pipeline_Result to the legacy return format.
	 *
	 * When mark_trigger_complete is true: complete each successful entry
	 * and return only failures. When false: return all entries as-is.
	 *
	 * @param Pipeline_Result $result                Pipeline result from execute().
	 * @param bool            $mark_trigger_complete Whether triggers should be completed.
	 *
	 * @return array
	 */
	private function convert_pipeline_result( Pipeline_Result $result, bool $mark_trigger_complete ): array {

		$entries = $result->get_trigger_entries();

		if ( ! $mark_trigger_complete ) {
			// Legacy contract: return only successful entries when not auto-completing.
			return array_values(
				array_filter(
					$entries,
					static function ( $entry ) {
						return ! empty( $entry['result'] );
					}
				)
			);
		}

		// Complete successful triggers, collect failures for return.
		$failures = array();

		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['result'] ) && isset( $entry['args'] ) ) {
				$this->maybe_trigger_complete( $entry['args'] );
			} else {
				$failures[] = $entry;
			}
		}

		return $failures;
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
	 * simulate an ID for the next run. The reason for simulate is to avoid unnecessary
	 * recipe_logs in the database since we insert recipe log first & check if trigger
	 * is valid after which means, recipe log is added and not used in this run.
	 * Once trigger is validated. I pass $maybe_simulate to $maybe_add_log_id
	 * and insert recipe log at this point.
	 *
	 */
	/**
	 * Maybe create recipe log entry.
	 *
	 * @param mixed $recipe_id The ID.
	 * @param mixed $user_id The user ID.
	 * @param mixed $create_recipe The create recipe.
	 * @param mixed $args The arguments.
	 * @param mixed $maybe_simulate The maybe simulate.
	 * @param mixed $maybe_add_log_id The ID.
	 * @return mixed
	 */
	public function maybe_create_recipe_log_entry( $recipe_id, $user_id, $create_recipe = true, $args = array(), $maybe_simulate = false, $maybe_add_log_id = null ) {
		return $this->get_log_manager()->maybe_create_recipe_log_entry( (int) $recipe_id, (int) $user_id, (bool) $create_recipe, $args, (bool) $maybe_simulate );
	}

	/**
	 * @param      $recipe_id
	 * @param      $user_id
	 * @param null $maybe_add_log_id
	 *
	 * @return int
	 *
	 * @since 6.0.2 Fixed race condition in log_number assignment. Bug identified by BugBot:
	 *              https://github.com/UncannyOwl/Automator/pull/5357#pullrequestreview-3025989372
	 *              WHAT: Race condition allowing duplicate log_number values and potential lock leaks.
	 *              WHY: Multiple processes could retrieve same log count and MySQL locks weren't properly released on errors.
	 *              HOW: Added MySQL named locks with try/finally for guaranteed release, fallback to original behavior when lock fails.
	 */
	public function insert_recipe_log( $recipe_id, $user_id, $maybe_add_log_id = null ) {
		return $this->get_log_manager()->insert_recipe_log( (int) $recipe_id, (int) $user_id );
	}

	/**
	 * Get recipe log count for a specific recipe ID.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return int
	 */
	public function get_recipe_log_count( int $recipe_id ) {
		return $this->get_log_manager()->get_recipe_log_count( $recipe_id );
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
		return $this->get_validator()->get_trigger_id( $args, $trigger, (int) $recipe_id, $maybe_recipe_log_id, (bool) $ignore_post_id );
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
		return $this->get_validator()->maybe_validate_trigger_without_postid( $args, $trigger, $recipe_id, $recipe_log_id );
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
		return $this->get_validator()->maybe_get_trigger_id( (int) $user_id, (int) $trigger_id, (int) $recipe_id, $recipe_log_id );
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
		return $this->get_validator()->maybe_validate_trigger( $args, $trigger, $recipe_id, $recipe_log_id );
	}

	/**
	 * Validate if the number of times of a trigger condition met
	 *
	 * @param $times_args
	 *
	 * @return array
	 */
	public function maybe_trigger_num_times_completed( $times_args ) {
		return $this->get_numtimes()->maybe_trigger_num_times_completed( $times_args );
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
			_doing_it_wrong( 'Automator()->process->user->insert_trigger_meta( $args )', 'Use Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args ) instead.', '3.0' );
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
		$this->get_log_manager()->maybe_change_recipe_log_to_zero( (int) $recipe_id, (int) $user_id, (int) $recipe_log_id, (bool) $change_to_zero );
	}

	/**
	 * Validate if the number of times of a trigger condition met
	 *
	 * @param      $option_meta
	 * @param null $save_for_option
	 *
	 * @return array
	 *
	 * @since 6.0.2 Fixed undefined variables in update_trigger_meta() call. Bug identified by BugBot:
	 *              https://github.com/UncannyOwl/Automator/pull/5357#pullrequestreview-3025989372
	 *              WHAT: PHP undefined variable errors for $meta_key and $meta_value.
	 *              WHY: Variables were used without being defined in function scope.
	 *              HOW: Replaced with correctly scoped variables $trigger_meta and $post_id.
	 */
	public function maybe_trigger_add_any_option_meta( $option_meta, $save_for_option = null ) {
		return $this->get_numtimes()->maybe_trigger_add_any_option_meta( $option_meta, $save_for_option );
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
			Automator()->wp_error->add_error( 'update_trigger_meta', 'ERROR: You are trying to update trigger meta when a there is no logged in user.', $this );

			return null;
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Automator()->wp_error->add_error( 'update_trigger_meta', 'ERROR: You are trying to update trigger meta without providing a trigger_id', $this );

			return null;
		}

		if ( null === $meta_key || ! is_string( $meta_key ) ) {
			Automator()->wp_error->add_error( 'update_trigger_meta', 'ERROR: You are trying to update trigger meta without providing a meta_key', $this );

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

		// No user id is available.
		if ( 0 === $user_id ) {
			Automator()->wp_error->add_error( 'get_trigger_meta_id', 'ERROR: You are trying to get trigger meta ID when a there is no logged in user.', $this );

			return null;
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Automator()->wp_error->add_error( 'get_trigger_meta_id', 'ERROR: You are trying to get trigger meta ID without providing a trigger_id', $this );

			return null;
		}

		if ( null === $meta_key || ! is_string( $meta_key ) ) {
			Automator()->wp_error->add_error( 'get_trigger_meta_id', 'ERROR: You are trying to get trigger meta ID without providing a meta_key', $this );

			return null;
		}

		global $wpdb;
		$results = $this->wpdb_get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}uap_trigger_log_meta WHERE user_id = %d AND meta_key LIKE %s AND automator_trigger_id = %d", $user_id, $meta_key, $trigger_id ) );

		if ( null !== $results ) {
			return (int) $results;
		}

		return null;
	}


	/**
	 * wpdb_get_var
	 *
	 * @param string $query
	 *
	 * @return mixed
	 */
	public function wpdb_get_var( $query ) {

		global $wpdb;

		return $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * get_plugin_status
	 *
	 * @param string $integration
	 *
	 * @return bool
	 */
	public function get_plugin_status( $integration ) {
		return Automator()->plugin_status->get( $integration );
	}

	/**
	 * recipe_number_times_completed
	 *
	 * @param mixed $recipe_id
	 * @param mixed $results
	 *
	 * @return mixed
	 */
	public function recipe_number_times_completed( $recipe_id, $results ) {
		return Automator()->utilities->recipe_number_times_completed( $recipe_id, $results );
	}

	/**
	 * is_recipe_completed
	 *
	 * @param mixed $recipe_id
	 * @param mixed $user_id
	 *
	 * @return mixed
	 */
	public function is_recipe_completed( $recipe_id, $user_id ) {
		return Automator()->is_recipe_completed( $recipe_id, $user_id );
	}

	/**
	 * recipes_from_trigger_code
	 *
	 * @param mixed $check_trigger_code
	 * @param mixed $webhook_recipe
	 *
	 * @return mixed
	 */
	public function recipes_from_trigger_code( $check_trigger_code, $webhook_recipe = null ) {
		return Automator()->get->recipes_from_trigger_code( $check_trigger_code, $webhook_recipe );
	}

	/**
	 * get_trigger_meta
	 *
	 * @param mixed $user_id
	 * @param mixed $trigger_id
	 * @param mixed $meta_key
	 * @param mixed $trigger_log_id
	 *
	 * @return mixed
	 */
	public function get_trigger_meta( $user_id, $trigger_id, $meta_key, $trigger_log_id ) {
		return Automator()->get->trigger_meta( $user_id, $trigger_id, $meta_key, $trigger_log_id );
	}

	/**
	 * get_trigger_sentence
	 *
	 * @param mixed $trigger_id
	 *
	 * @return mixed
	 */
	public function get_trigger_sentence( $trigger_id ) {
		return Automator()->get->trigger_sentence( $trigger_id, 'sentence_human_readable' );
	}

	/**
	 * maybe_get_meta_id_from_trigger_log
	 *
	 * @param mixed $run_number
	 * @param mixed $trigger_id
	 * @param mixed $trigger_log_id
	 * @param mixed $trigger_meta
	 * @param mixed $user_id
	 *
	 * @return mixed
	 */
	public function maybe_get_meta_id_from_trigger_log( $run_number, $trigger_id, $trigger_log_id, $trigger_meta, $user_id ) {
		return Automator()->get->maybe_get_meta_id_from_trigger_log( $run_number, $trigger_id, $trigger_log_id, $trigger_meta, $user_id );
	}
}

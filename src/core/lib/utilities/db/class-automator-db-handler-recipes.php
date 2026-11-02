<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;

/**
 * Class Automator_DB_Handler_Recipes
 *
 * @package Uncanny_Automator
 */
class Automator_DB_Handler_Recipes {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @return Automator_DB_Handler_Recipes
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Inserts a new recipe run in to Recipe logs table.
	 *
	 * @param $user_id
	 * @param $recipe_id
	 * @param $completed
	 * @param $run_number
	 *
	 * @return int
	 *
	 * @since 3.0
	 */
	public function add( $user_id, $recipe_id, $completed, $run_number ) {

		if ( isset( Automator()->recipe_runner ) ) {
			return Automator()->recipe_runner->log_store()->add_recipe_log( (int) $user_id, (int) $recipe_id, (int) $completed, (int) $run_number );
		}

		global $wpdb;

		$table_name = isset( Automator()->db->tables->recipe ) ? Automator()->db->tables->recipe : 'uap_recipe_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write to Automator's own table.
		$wpdb->insert(
			$wpdb->prefix . $table_name,
			array(
				'date_time'           => current_time( 'mysql' ),
				'user_id'             => $user_id,
				'automator_recipe_id' => $recipe_id,
				'completed'           => $completed,
				'run_number'          => $run_number,
			),
			array(
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
			)
		);

		return $wpdb->insert_id;
	}

	/**
	 * Update recipe log table. Check $wpdb->update() to see how to pass values.
	 *
	 * @param $to_update
	 * @param $where
	 * @param $update_format
	 * @param $where_format
	 *
	 * @return bool|int
	 * @since 3.0
	 */
	public function update( $to_update, $where, $update_format, $where_format ) {
		global $wpdb;
		$table_name = isset( Automator()->db->tables->recipe ) ? Automator()->db->tables->recipe : 'uap_recipe_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		return $wpdb->update(
			$wpdb->prefix . $table_name,
			$to_update,
			$where,
			$update_format,
			$where_format
		);
	}


	/**
	 * @param $recipe_id
	 * @param $recipe_log_id
	 */
	public function mark_incomplete( $recipe_id, $recipe_log_id ) {

		if ( isset( Automator()->recipe_runner ) ) {
			Automator()->recipe_runner->log_store()->mark_recipe_incomplete( (int) $recipe_id, (int) $recipe_log_id );
			return;
		}

		$this->update(
			array(
				'completed' => 0,
			),
			array(
				'ID'                  => $recipe_log_id,
				'automator_recipe_id' => $recipe_id,
			),
			array(
				'%d',
			),
			array(
				'%d',
				'%d',
			)
		);
	}

	/**
	 * Marks a recipe as complete in the recipe log.
	 *
	 * Updates the recipe log entry with completion status and timestamp.
	 * Fires the 'automator_recipe_marked_complete' hook if the update is successful.
	 *
	 * Meaning of each number:
	 * 0 = not completed
	 * 1 = completed
	 * 2 = completed with errors, error message provided
	 * 5 = scheduled
	 * 9 = completed, do nothing
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 * @param int $completed     The completion status.
	 *
	 * @return void
	 */
	public function mark_complete( $recipe_log_id, $completed ) {

		if ( isset( Automator()->recipe_runner ) ) {
			Automator()->recipe_runner->log_store()->mark_recipe_complete( (int) $recipe_log_id, (int) $completed );
			return;
		}

		$updated = $this->update(
			array(
				'date_time' => current_time( 'mysql' ),
				'completed' => $completed,
			),
			array(
				'ID' => $recipe_log_id,
			),
			array(
				'%s',
				'%d',
			),
			array(
				'%d',
			)
		);

		if ( $updated ) {
			/**
			 * Fires after a recipe has been successfully marked as complete in the database.
			 *
			 * This hook allows developers to perform additional operations when a recipe
			 * is marked complete, such as logging, notifications, or triggering other processes.
			 *
			 * @since 6.7.0
			 *
			 * @param int $recipe_log_id The recipe log ID that was marked complete.
			 * @param int $completed     The completion status.
			 *
			 * @example
			 * // Hook into the recipe completion event
			 * add_action( 'automator_recipe_marked_complete', 'my_custom_recipe_complete_handler', 10, 2 );
			 *
			 * function my_custom_recipe_complete_handler( $recipe_log_id, $completed ) {
			 *     if ( 1 === $completed ) {
			 *         // Recipe completed successfully
			 *         error_log( "Recipe log {$recipe_log_id} completed successfully" );
			 *     } elseif ( 2 === $completed ) {
			 *         // Recipe completed with errors
			 *         error_log( "Recipe log {$recipe_log_id} completed with errors" );
			 *     }
			 * }
			 */
			do_action( 'automator_recipe_marked_complete', $recipe_log_id, $completed );
		}
	}

	/**
	 * Marks a recipe as complete with error status in the recipe log.
	 *
	 * Updates the recipe log entry with completion status for error scenarios.
	 * Fires the 'automator_recipe_marked_complete_with_error' hook if the update is successful.
	 *
	 * Meaning of each number:
	 * 0 = not completed
	 * 1 = completed
	 * 2 = completed with errors, error message is provided
	 * 9 = completed, do nothing
	 *
	 * @param int $recipe_id     The recipe ID.
	 * @param int $recipe_log_id The recipe log ID.
	 * @param int $complete      The completion status.
	 *
	 * @return void
	 */
	public function mark_complete_with_error( $recipe_id, $recipe_log_id, $complete ) {

		if ( isset( Automator()->recipe_runner ) ) {
			Automator()->recipe_runner->log_store()->mark_recipe_complete_with_error( (int) $recipe_id, (int) $recipe_log_id, (int) $complete );
			return;
		}

		$updated = $this->update(
			array(
				'completed' => $complete,
			),
			array(
				'ID'                  => $recipe_log_id,
				'automator_recipe_id' => $recipe_id,
			),
			array(
				'%d',
			),
			array(
				'%d',
				'%d',
			)
		);

		if ( $updated ) {
			/**
			 * Fires after a recipe has been successfully marked as complete with error in the database.
			 *
			 * This hook allows developers to perform additional operations when a recipe
			 * is marked complete with error status, such as error logging, notifications, or cleanup.
			 *
			 * @since 6.7.0
			 *
			 * @param int $recipe_id     The recipe ID that was marked complete with error.
			 * @param int $recipe_log_id The recipe log ID that was marked complete with error.
			 * @param int $complete      The completion status.
			 *
			 * @example
			 * // Hook into the recipe completion with error event
			 * add_action( 'automator_recipe_marked_complete_with_error', 'my_custom_recipe_error_handler', 10, 3 );
			 *
			 * function my_custom_recipe_error_handler( $recipe_id, $recipe_log_id, $complete ) {
			 *     if ( 2 === $complete ) {
			 *         // Recipe completed with errors
			 *         error_log( "Recipe {$recipe_id} (log {$recipe_log_id}) completed with errors" );
			 *     }
			 * }
			 */
			do_action( 'automator_recipe_marked_complete_with_error', $recipe_id, $recipe_log_id, $complete );
		}
	}

	/**
	 * @param $recipe_id
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public function log_run_pre_exists( $recipe_id, $user_id ) {

		if ( isset( Automator()->recipe_runner ) ) {
			return Automator()->recipe_runner->log_store()->recipe_log_pre_exists( (int) $recipe_id, (int) $user_id );
		}

		global $wpdb;
		$tbl = Automator()->db->tables->recipe;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		return $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM `' . esc_sql( $wpdb->prefix . $tbl ) . '` WHERE completed = %d AND automator_recipe_id = %d AND user_id = %d', '-1', $recipe_id, $user_id ) );
	}

	/**
	 * Get recipe_id and user_id from a recipe log row.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return object|null Object with automator_recipe_id and user_id, or null.
	 */
	public function get_log_row( int $recipe_log_id ) {

		if ( isset( Automator()->recipe_runner ) ) {
			return Automator()->recipe_runner->log_store()->get_recipe_log_row( $recipe_log_id );
		}

		global $wpdb;
		$tbl = Automator()->db->tables->recipe;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT automator_recipe_id, user_id FROM `' . esc_sql( $wpdb->prefix . $tbl ) . '` WHERE ID = %d',
				$recipe_log_id
			)
		);
	}

	/**
	 * get_scheduled_actions_count
	 *
	 * @param mixed $recipe_log_id
	 * @param mixed $args
	 *
	 * @return void
	 */
	public function get_scheduled_actions_count( $recipe_log_id, $args ) {

		if ( isset( Automator()->recipe_runner ) ) {
			return Automator()->recipe_runner->log_store()->get_scheduled_actions_count( (int) $recipe_log_id, (array) $args );
		}

		global $wpdb;

		$tbl = Automator()->db->tables->action;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$results = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . esc_sql( $wpdb->prefix . $tbl ) . '` WHERE completed = 5 AND automator_recipe_log_id = %d', $recipe_log_id ) );

		return apply_filters( 'automator_has_scheduled_actions', absint( $results ), $recipe_log_id, $args );
	}

	/**
	 * @param $recipe_id
	 */
	public function delete( $recipe_id ) {
		global $wpdb;

		// delete from uap_recipe_log
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->recipe,
			array( 'automator_recipe_id' => $recipe_id )
		);
	}

	/**
	 * Deletes one run: its uap_error_log, uap_recipe_log_meta, uap_tokens_log and uap_run_snapshots rows, then its
	 * uap_recipe_log row. Every single-run deletion comes here through automator_purge_recipe_logs(): the run's delete
	 * link, log pruning, a deleted user's runs, "Delete recipe records on completion" and Pro's auto-purge.
	 *
	 * @param $recipe_id
	 * @param $automator_recipe_log_id
	 */
	public function delete_logs( $recipe_id, $automator_recipe_log_id ) {

		global $wpdb;

		// Delete from uap_error_log.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->delete(
			$wpdb->prefix . 'uap_error_log',
			array( 'recipe_log_id' => $automator_recipe_log_id )
		);

		// Delete the run's finished uap_async_jobs rows and processed uap_platform_inbox rows; an open job stays, so its retry still runs.
		$this->delete_finished_background_rows_of_run( absint( $automator_recipe_log_id ) );

		// Delete from uap_recipe_log_meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->recipe_meta,
			array(
				'recipe_id'     => $recipe_id,
				'recipe_log_id' => $automator_recipe_log_id,
			)
		);

		// Delete from uap_tokens_log
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->tokens_logs,
			array(
				'recipe_id'     => $recipe_id,
				'recipe_log_id' => $automator_recipe_log_id,
			)
		);

		// Delete from uap_run_snapshots: the trigger data the run kept so it can run again. A deleted run can't be replayed.
		try {
			Database::get_run_snapshot_store()->purge_run( absint( $automator_recipe_log_id ) );
		} catch ( Store_Exception $e ) {
			automator_log( $e->getMessage(), 'delete_logs', true );
		}

		// Delete from uap_recipe_log.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->recipe,
			array(
				'automator_recipe_id' => $recipe_id,
				'ID'                  => $automator_recipe_log_id,
			)
		);
	}

	/**
	 * Deletes every run of a recipe ("Clear activity").
	 *
	 * Covers the run log tables: uap_recipe_log and its meta, uap_tokens_log, uap_trigger_log and its meta,
	 * uap_action_log and its meta, uap_error_log, uap_api_log, uap_api_log_response and uap_run_snapshots, and deletes
	 * the recipe's uap_recipe_count row.
	 *
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function clear_activity_log_by_recipe_id( $recipe_id ) {
		global $wpdb;

		// Delete from error log (by recipe_log_ids for this recipe).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE e FROM {$wpdb->prefix}uap_error_log e INNER JOIN {$wpdb->prefix}uap_recipe_log r ON e.recipe_log_id = r.ID WHERE r.automator_recipe_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_id
			)
		);

		// Delete from uap_api_log and uap_api_log_response; their rows are found through the run, action and trigger logs.
		Automator()->db->api->delete_by_recipe_id( $recipe_id );
		// Delete the finished uap_async_jobs rows and processed uap_platform_inbox rows; the inbox rows are found through the run logs.
		$this->delete_finished_background_rows_of_recipe( absint( $recipe_id ) );
		// Delete from actions
		Automator()->db->action->delete_by_recipe_id( $recipe_id );
		// Delete from triggers
		Automator()->db->trigger->delete_by_recipe_id( $recipe_id );

		// Delete from uap_recipe_log_meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->recipe_meta,
			array( 'recipe_id' => $recipe_id ),
			array( '%d' )
		);

		// Delete from uap_tokens_log: the parsed token values, which can hold personal data.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->tokens_logs,
			array( 'recipe_id' => $recipe_id ),
			array( '%d' )
		);

		// Delete from uap_run_snapshots: the trigger data each run kept so it can run again.
		try {
			Database::get_run_snapshot_store()->purge_recipe( absint( $recipe_id ) );
		} catch ( Store_Exception $e ) {
			automator_log( $e->getMessage(), 'clear_activity_log_by_recipe_id', true );
		}

		// delete from uap_recipe_log
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->recipe,
			array(
				'automator_recipe_id' => $recipe_id,
			)
		);

		// delete from uap_recipe_log
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->delete(
			"{$wpdb->prefix}uap_recipe_count",
			array(
				'recipe_id' => $recipe_id,
			)
		);
	}

	/**
	 * Delete a recipe's finished background jobs and processed platform inbox rows.
	 *
	 * Open and processing jobs, and inbox rows the drain has still to apply or retry, stay: a site job's retry or a
	 * completion still runs, and finds the run's log gone. Call it before the recipe's run logs are deleted, since
	 * the inbox rows are found through them. A failed delete is logged and the clear goes on.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id The recipe being cleared.
	 *
	 * @return void
	 */
	private function delete_finished_background_rows_of_recipe( $recipe_id ) {
		try {
			Database::get_background_job_store()->delete_finished_for_recipe( $recipe_id );
		} catch ( Store_Exception $e ) {
			automator_log( $e->getMessage(), 'clear_activity_log_by_recipe_id', true );
		}

		try {
			Database::get_platform_inbox_store()->delete_processed_for_recipe( $recipe_id, \Uncanny_Automator\App\Infrastructure\Async_Jobs\Inbox_Drainer::MAX_ATTEMPTS );
		} catch ( Store_Exception $e ) {
			automator_log( $e->getMessage(), 'clear_activity_log_by_recipe_id', true );
		}
	}

	/**
	 * Delete one run's finished background jobs and processed platform inbox rows (see
	 * delete_finished_background_rows_of_recipe()). A failed delete is logged and the run's other rows still go.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The run being deleted.
	 *
	 * @return void
	 */
	private function delete_finished_background_rows_of_run( $recipe_log_id ) {
		try {
			Database::get_background_job_store()->delete_finished_for_run( $recipe_log_id );
		} catch ( Store_Exception $e ) {
			automator_log( $e->getMessage(), 'delete_logs', true );
		}

		try {
			Database::get_platform_inbox_store()->delete_processed_for_run( $recipe_log_id, \Uncanny_Automator\App\Infrastructure\Async_Jobs\Inbox_Drainer::MAX_ATTEMPTS );
		} catch ( Store_Exception $e ) {
			automator_log( $e->getMessage(), 'delete_logs', true );
		}
	}

	/**
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function update_count( $recipe_id ) {

		if ( isset( Automator()->recipe_runner ) ) {
			Automator()->recipe_runner->log_store()->update_recipe_count( (int) $recipe_id );
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}uap_recipe_count SET runs = runs + 1 WHERE recipe_id = %d", $recipe_id ) );
	}

	/**
	 * Fetches all recipes with zero status.
	 *
	 * @return mixed[]
	 */
	public function retrieve_failed_recipes() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$results = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, completed, automator_recipe_id FROM {$wpdb->prefix}uap_recipe_log WHERE completed = %d",
				Automator_Status::NOT_COMPLETED
			),
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Retrieve recipe current triggers via recipe log ID.
	 *
	 * @param int $recipe_log_id
	 *
	 * @return false|int[] Returns false if there are no records, or if JSON from database is invalid. Otherwise, returns a set of integers representing the Trigger IDs.
	 */
	public function retrieve_recipe_current_triggers( $recipe_log_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$result = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT automator_trigger_id
					FROM {$wpdb->prefix}uap_trigger_log
					WHERE
						completed = %d AND
						automator_recipe_log_id = %d", // Make sure to fetch the latest current recipes.
				1,
				$recipe_log_id
			)
		);

		return $result;
	}

	/**
	 * Adds meta to the recipe log.
	 *
	 * @param string $meta_key
	 * @param string $meta_value
	 * @param array{user_id:int,recipe_id:int,recipe_log_id:int} $args
	 * @param bool $upsert Whether to upsert the value or not.
	 *
	 * @return int|false
	 */
	public function add_meta( $meta_key = '', $meta_value = '', $args = array(), $upsert = true ) {

		if ( empty( $meta_key ) ) {
			return false;
		}

		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'user_id'       => 0,
				'recipe_id'     => 0,
				'recipe_log_id' => 0,
			)
		);

		$has_record = ! empty( $this->get_meta( $meta_key, $args ) );

		if ( $has_record && true === $upsert ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
			$updated = $wpdb->update(
				$wpdb->prefix . 'uap_recipe_log_meta',
				array(
					'meta_value' => wp_json_encode( $meta_value ),
				),
				array(
					'user_id'       => $args['user_id'],
					'recipe_id'     => $args['recipe_id'],
					'recipe_log_id' => $args['recipe_log_id'],
					'meta_key'      => $meta_key,
				),
				array( '%s' ),
				array( '%d', '%d', '%d', '%s' )
			);

			// get_meta() cached the value it read; drop it so the next read returns the value just stored.
			wp_cache_delete( $this->meta_cache_key( $meta_key, $args ), 'automator_recipe' );

			return $updated;
		}

		$meta_value = wp_json_encode( $meta_value );

		$has_existing_record = false;

		if ( is_string( $meta_value ) ) {
			$has_existing_record = $this->fetch_existing_data( $args, $meta_key, $meta_value );
		}

		if ( false === $has_existing_record ) {

			$column_val = array(
				'user_id'       => $args['user_id'],
				'recipe_id'     => $args['recipe_id'],
				'recipe_log_id' => $args['recipe_log_id'],
				'meta_key'      => $meta_key,
				'meta_value'    => $meta_value,
			);

			$serialized = maybe_serialize( $column_val );

			if ( is_string( $serialized ) ) {
				$hashed = md5( $serialized );
			}

			if ( isset( $hashed ) && ! empty( Automator()->cache->get( $hashed, 'automator_recipe', true ) ) ) {
				return false;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write to Automator's own table.
			$inserted = $wpdb->insert(
				$wpdb->prefix . 'uap_recipe_log_meta',
				$column_val,
				array( '%d', '%d', '%d', '%s', '%s' )
			);

			if ( isset( $hashed ) ) {
				Automator()->cache->set( $hashed, true, 'automator_recipe' );
			}

			return $inserted;

		}

		return false;
	}

	/**
	 * Returns the meta value from a selected key.
	 *
	 * @param string $meta_key
	 * @param array{user_id:int,recipe_id:int,recipe_log_id:int} $args
	 *
	 * @return string
	 */
	public function get_meta( $meta_key, $args = array() ) {

		$group = 'automator_recipe';

		$key = $this->meta_cache_key( $meta_key, $args );

		$meta_val_cached = Automator()->cache->get( $key, $group, true );

		if ( ! empty( $meta_val_cached ) ) {
			return is_string( $meta_val_cached ) ? $meta_val_cached : '';
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$meta_val = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value
					FROM {$wpdb->prefix}uap_recipe_log_meta
						WHERE recipe_id = %d
							AND recipe_log_id = %d
							AND user_id = %d
							AND meta_key = %s",
				$args['recipe_id'],
				$args['recipe_log_id'],
				$args['user_id'],
				$meta_key
			)
		);

		if ( ! empty( $meta_val ) ) {
			Automator()->cache->set( $key, $meta_val, $group );
		}

		return $meta_val;
	}

	/**
	 * The object cache key get_meta() keeps a run's meta value under, in the automator_recipe group.
	 *
	 * @since 8.0.0
	 *
	 * @param string                                             $meta_key The meta key.
	 * @param array{user_id:int,recipe_id:int,recipe_log_id:int} $args     The run.
	 *
	 * @return string
	 */
	private function meta_cache_key( $meta_key, $args ) {
		return sprintf(
			'automator_recipe_%d_%d_%d_%s',
			$args['user_id'],
			$args['recipe_id'],
			$args['recipe_log_id'],
			$meta_key
		);
	}

	/**
	 * Get a recipe log record by field and value
	 *
	 * @param string $field The field to search by (e.g., 'ID', 'user_id', 'automator_recipe_id')
	 * @param mixed $value The value to search for
	 *
	 * @return object|null The recipe log record or null if not found
	 */
	public function get_by( $field, $value ) {
		global $wpdb;

		// Sanitize field name to prevent SQL injection
		$allowed_fields = array(
			'ID'                  => 'ID',
			'user_id'             => 'user_id',
			'automator_recipe_id' => 'automator_recipe_id',
			'completed'           => 'completed',
			'run_number'          => 'run_number',
			'date_time'           => 'date_time',
		);
		if ( ! array_key_exists( $field, $allowed_fields ) ) {
			return null;
		}
		$safe_field = $allowed_fields[ $field ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . esc_sql( $wpdb->uap_recipe_log ) . '` WHERE `' . esc_sql( $safe_field ) . '` = %s', $value ) );
	}

	/**
	 * @param int[] $args
	 * @param string $meta_key
	 * @param string $meta_value The value as stored: already JSON-encoded by add_meta().
	 *
	 * @return bool True if existing data already exists. Otherwise, false.
	 */
	protected function fetch_existing_data( $args, $meta_key, $meta_value ) {

		$has_record = 'no';

		$key = 'automator_recipe_objects_logger_' . maybe_serialize( $args ) . '_' . $meta_key . '_' . maybe_serialize( $meta_value );

		$has_record_cached = Automator()->cache->get( $key, 'automator_recipe', true );

		if ( ! empty( $has_record_cached ) ) {
			return 'yes' === $has_record_cached;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->prefix}uap_recipe_log_meta
				WHERE user_id = %d
				AND recipe_id = %d
				AND recipe_log_id = %d
				AND meta_key = %s
				AND meta_value = %s
				",
				$args['user_id'],
				$args['recipe_id'],
				$args['recipe_log_id'],
				$meta_key,
				$meta_value
			)
		);

		if ( ! empty( $results ) ) {
			$has_record = 'yes';
		}

		Automator()->cache->set( $key, $has_record, 'automator_recipe' );

		return 'yes' === $has_record;
	}
}

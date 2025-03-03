<?php

namespace Uncanny_Automator;

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
		global $wpdb;

		$table_name = isset( Automator()->db->tables->recipe ) ? Automator()->db->tables->recipe : 'uap_recipe_log';
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
	 * Meaning of each number
	 *
	 * 0 = not completed
	 * 1 = completed
	 * 2 = completed with errors, error message provided
	 * 5 = scheduled
	 * 9 = completed, do nothing
	 *
	 * @param $recipe_log_id
	 * @param $completed
	 */
	public function mark_complete( $recipe_log_id, $completed ) {
		$this->update(
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
	}

	/**
	 * Meaning of each number
	 *
	 * 0 = not completed
	 * 1 = completed
	 * 2 = completed with errors, error message is provided
	 * 9 = completed, do nothing
	 *
	 * @param $recipe_id
	 * @param $recipe_log_id
	 * @param $complete
	 */
	public function mark_complete_with_error( $recipe_id, $recipe_log_id, $complete ) {
		$this->update(
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
	}

	/**
	 * @param $recipe_id
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public function log_run_pre_exists( $recipe_id, $user_id ) {
		global $wpdb;
		$tbl = Automator()->db->tables->recipe;

		return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}{$tbl} WHERE completed = %d AND automator_recipe_id = %d AND user_id = %d", '-1', $recipe_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		global $wpdb;

		$tbl = Automator()->db->tables->action;

		$results = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}{$tbl} WHERE completed = 5 AND automator_recipe_log_id = $recipe_log_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return apply_filters( 'automator_has_scheduled_actions', absint( $results ), $recipe_log_id, $args );
	}

	/**
	 * @param $recipe_id
	 */
	public function delete( $recipe_id ) {
		global $wpdb;

		// delete from uap_recipe_log
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->recipe,
			array( 'automator_recipe_id' => $recipe_id )
		);
	}

	/**
	 * @param $recipe_id
	 * @param $automator_recipe_log_id
	 */
	public function delete_logs( $recipe_id, $automator_recipe_log_id ) {

		global $wpdb;

		// Delete from uap_recipe_log_meta.
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->recipe_meta,
			array(
				'recipe_id'     => $recipe_id,
				'recipe_log_id' => $automator_recipe_log_id,
			)
		);

		// Delete from uap_tokens_log
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->tokens_logs,
			array(
				'recipe_id'     => $recipe_id,
				'recipe_log_id' => $automator_recipe_log_id,
			)
		);

		// Delete from uap_recipe_log.
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->recipe,
			array(
				'automator_recipe_id' => $recipe_id,
				'ID'                  => $automator_recipe_log_id,
			)
		);
	}

	/**
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function clear_activity_log_by_recipe_id( $recipe_id ) {
		global $wpdb;

		// Delete from closures
		Automator()->db->closure->delete_by_recipe_id( $recipe_id );
		// Delete from actions
		Automator()->db->action->delete_by_recipe_id( $recipe_id );
		// Delete from triggers
		Automator()->db->trigger->delete_by_recipe_id( $recipe_id );
		// delete from uap_recipe_log
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->recipe,
			array(
				'automator_recipe_id' => $recipe_id,
			)
		);

		// delete from uap_recipe_log
		$wpdb->delete(
			"{$wpdb->prefix}uap_recipe_count",
			array(
				'recipe_id' => $recipe_id,
			)
		);

	}

	/**
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function update_count( $recipe_id ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}uap_recipe_count SET runs = runs + 1 WHERE recipe_id = %d", $recipe_id ) );
	}

	/**
	 * Fetches all recipes with zero status.
	 *
	 * @return mixed[]
	 */
	public function retrieve_failed_recipes() {

		global $wpdb;

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
			return $wpdb->update(
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

		$key = sprintf(
			'%s_%d_%d_%d_%s',
			$group,
			$args['user_id'],
			$args['recipe_id'],
			$args['recipe_log_id'],
			$meta_key
		);

		$meta_val_cached = Automator()->cache->get( $key, $group, true );

		if ( ! empty( $meta_val_cached ) ) {
			return is_string( $meta_val_cached ) ? $meta_val_cached : '';
		}

		global $wpdb;

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
	 * @param int[] $args
	 * @param string $meta_key
	 * @param string $meta_value
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
				wp_json_encode( $meta_value )
			)
		);

		if ( ! empty( $results ) ) {
			$has_record = 'yes';
		}

		Automator()->cache->set( $key, $has_record, 'automator_recipe' );

		return 'yes' === $has_record;
	}

}

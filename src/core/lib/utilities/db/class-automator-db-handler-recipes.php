<?php


namespace Uncanny_Automator;

/**
 * Class Automator_DB_Handler_Recipes
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
	 * @param int $user_id
	 * @param int $recipe_id
	 * @param int $completed
	 * @param int $run_number
	 *
	 * @return int
	 *
	 * @since 3.0
	 */
	public function add( int $user_id, int $recipe_id, int $completed, int $run_number ) {
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
	 * @param array $to_update
	 * @param array $where
	 * @param array $update_format
	 * @param array $where_format
	 *
	 * @return bool|int
	 * @since 3.0
	 */
	public function update( array $to_update, array $where, array $update_format, array $where_format ) {
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
	 *
	 */
	public function insert_recipe_log_meta() {

	}

	/**
	 * @param int $recipe_id
	 * @param int $recipe_log_id
	 */
	public function mark_incomplete( int $recipe_id, int $recipe_log_id ) {
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
	 * @param int $recipe_log_id
	 * @param int $completed
	 */
	public function mark_complete( int $recipe_log_id, int $completed ) {
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
				'%d',
			),
			array(
				'%d',
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
	 * 2 = completed with errors, error message is provided
	 * 9 = completed, do nothing
	 *
	 * @param int $recipe_id
	 * @param int $recipe_log_id
	 * @param int $complete
	 */
	public function mark_complete_with_error( int $recipe_id, int $recipe_log_id, int $complete ) {
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
	 * @param int $recipe_id
	 * @param int $user_id
	 *
	 * @return string|null
	 */
	public function log_run_pre_exists( int $recipe_id, int $user_id ) {
		global $wpdb;
		$tbl = Automator()->db->tables->recipe;

		return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}{$tbl} WHERE completed = %d AND automator_recipe_id = %d AND user_id = %d", '-1', $recipe_id, $user_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$results = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}{$tbl} WHERE completed NOT IN (1,2,7,9) AND automator_recipe_log_id = $recipe_log_id" ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return apply_filters( 'automator_has_scheduled_actions', absint( $results ), $recipe_log_id, $args );
	}

	/**
	 * @param int $recipe_id
	 */
	public function delete( int $recipe_id ) {
		global $wpdb;

		// delete from uap_recipe_log
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->recipe,
			array( 'automator_recipe_id' => $recipe_id )
		);
	}
}

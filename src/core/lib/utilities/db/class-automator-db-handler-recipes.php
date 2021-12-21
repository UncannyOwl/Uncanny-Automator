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
	 *
	 */
	public function insert_recipe_log_meta() {

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

		$results = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}{$tbl} WHERE completed = 5 AND automator_recipe_log_id = $recipe_log_id" ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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

		// delete from uap_recipe_log
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
	}
}

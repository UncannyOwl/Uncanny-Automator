<?php


namespace Uncanny_Automator;

/**
 * Class Automator_DB_Handler_Actions
 * @package Uncanny_Automator
 */
class Automator_DB_Handler_Actions {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @return Automator_DB_Handler_Actions
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param array $args
	 *
	 * @return bool|int
	 */
	public function add( array $args ) {
		$user_id       = absint( $args['user_id'] );
		$action_id     = absint( $args['action_id'] );
		$recipe_id     = absint( $args['recipe_id'] );
		$recipe_log_id = absint( $args['recipe_log_id'] );
		$completed     = esc_attr( $args['completed'] );
		$error_message = sanitize_text_field( $args['error_message'] );
		$date_time     = $args['date_time'];

		global $wpdb;
		$table_name = $wpdb->prefix . Automator()->db->tables->action;

		$date_time = null !== $date_time ? $date_time : current_time( 'mysql' );

		$wpdb->insert(
			$table_name,
			array(
				'date_time'               => $date_time,
				'user_id'                 => $user_id,
				'automator_action_id'     => $action_id,
				'automator_recipe_id'     => $recipe_id,
				'automator_recipe_log_id' => $recipe_log_id,
				'completed'               => $completed,
				'error_message'           => ! empty( $error_message ) ? $error_message : '',
			),
			array(
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
				'%s',
			)
		);

		return $wpdb->insert_id;
	}

	/**
	 * @param $data
	 * @param $where
	 *
	 * @return bool|int
	 */
	public function update( $data, $where ) {
		global $wpdb;
		$table_name = $wpdb->prefix . Automator()->db->tables->action;

		return $wpdb->update( $table_name, $data, $where );
	}

	/**
	 * @param int $user_id
	 * @param int $action_log_id
	 * @param int $action_id
	 * @param string $meta_key
	 * @param string|mixed $meta_value
	 *
	 * @return bool|int
	 */
	public function add_meta( int $user_id, int $action_log_id, int $action_id, string $meta_key, $meta_value ) {
		global $wpdb;
		$table_name = $wpdb->prefix . Automator()->db->tables->action_meta;

		return $wpdb->insert(
			$table_name,
			array(
				'user_id'                 => $user_id,
				'automator_action_log_id' => $action_log_id,
				'automator_action_id'     => $action_id,
				'meta_key'                => $meta_key,
				'meta_value'              => $meta_value,
			),
			array(
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * @param int $action_log_id
	 * @param string $meta_key
	 *
	 * @return string
	 */
	public function get_meta( int $action_log_id, string $meta_key ) {
		global $wpdb;
		$table_name = $wpdb->prefix . Automator()->db->tables->action_meta;
		$result     = $wpdb->get_row( $wpdb->prepare( "SELECT meta_value FROM $table_name WHERE meta_key =%s AND automator_action_log_id =%d", $meta_key, $action_log_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $result ) {
			return $result;
		}

		return $result->meta_value;
	}

	/**
	 * @param $action_id
	 * @param $recipe_log_id
	 * @param int $completed
	 * @param string $error_message
	 */
	public function mark_complete( $action_id, $recipe_log_id, int $completed = 1, string $error_message = '' ) {
		$data = array(
			'completed'     => $completed,
			'date_time'     => current_time( 'mysql' ),
			'error_message' => $error_message,
		);

		$where = array(
			'automator_action_id'     => $action_id,
			'automator_recipe_log_id' => $recipe_log_id,
		);

		Automator()->db->action->update( $data, $where );

	}

	/**
	 * @param $recipe_log_id
	 *
	 * @return array|object|void|null
	 */
	public function get_error_message( $recipe_log_id ) {
		global $wpdb;
		$tbl = Automator()->db->tables->action;

		return $wpdb->get_row( $wpdb->prepare( "SELECT error_message, completed FROM {$wpdb->prefix}{$tbl} WHERE error_message != '' AND automator_recipe_log_id =%d", $recipe_log_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param int $action_id
	 */
	public function delete( int $action_id ) {
		global $wpdb;

		// delete from uap_action_log
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->action,
			array( 'automator_action_id' => $action_id )
		);

		// delete from uap_action_log_meta
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->action_meta,
			array( 'automator_action_id' => $action_id )
		);
	}
}

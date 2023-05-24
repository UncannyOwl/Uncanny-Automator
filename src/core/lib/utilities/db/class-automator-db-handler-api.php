<?php


namespace Uncanny_Automator;

/**
 * Class Automator_DB_Handler_Api
 *
 * @package Uncanny_Automator
 */
class Automator_DB_Handler_Api {

	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @return Automator_DB_Handler_Api
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param $args
	 *
	 * @return bool|int
	 */
	public function add( $args ) {

		global $wpdb;
		$table_name = $wpdb->prefix . Automator()->db->tables->api;

		$date_time = isset( $args['date_time'] ) ? $args['date_time'] : current_time( 'mysql' );

		$wpdb->insert(
			$table_name,
			array(
				'date_time'     => $date_time,
				'type'          => $args['type'],
				'recipe_log_id' => absint( $args['recipe_log_id'] ),
				'item_log_id'   => absint( $args['item_log_id'] ),
				'endpoint'      => isset( $args['endpoint'] ) ? $args['endpoint'] : null,
				'params'        => isset( $args['params'] ) ? $args['params'] : null,
				'request'       => isset( $args['request'] ) ? $args['request'] : null,
				'response'      => isset( $args['response'] ) ? $args['response'] : null,
				'status'        => isset( $args['status'] ) ? $args['status'] : null,
				'price'         => isset( $args['price'] ) ? absint( $args['price'] ) : null,
				'balance'       => isset( $args['balance'] ) ? absint( $args['balance'] ) : null,
				'time_spent'    => isset( $args['time_spent'] ) ? absint( $args['time_spent'] ) : null,
			),
			array(
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
			)
		);

		return $wpdb->insert_id;

	}

	/**
	 * Logs the API retry response.
	 */
	protected function log_api_retry_response( $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'item_log_id' => 0,
				'api_log_id'  => 0,
				'result'      => '',
				'message'     => '',
			)
		);

		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'uap_api_log_response',
			array(
				'item_log_id' => $args['item_log_id'],
				'api_log_id'  => $args['api_log_id'],
				'result'      => $args['result'],
				'message'     => $args['message'],
			),
			array(
				'%d',
				'%s',
				'%s',
			)
		);

		return $wpdb->insert_id;

	}

	/**
	 * @param $action_log_id
	 * @param $meta_key
	 *
	 * @return object
	 */
	public function get_by_id( $id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . Automator()->db->tables->api;
		$result     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE ID =%s", $id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $result;
	}

	/**
	 * @param $action_log_id
	 * @param $meta_key
	 *
	 * @return array|object|null|void
	 */
	public function get_by_log_id( $type, $item_log_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . Automator()->db->tables->api;
		$result     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE type=%s AND item_log_id =%d", $type, $item_log_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $result;
	}

	/**
	 * @param $recipe_id
	 * @param $automator_recipe_log_id
	 */
	public function delete_logs( $recipe_id, $automator_recipe_log_id ) {

		global $wpdb;

		$api_tbl = $wpdb->prefix . Automator()->db->tables->api;

		$api_response_tbl = $wpdb->prefix . Automator()->db->tables->api_response_logs;

		$action_tbl = $wpdb->prefix . Automator()->db->tables->action;

		$triggers_tbl = $wpdb->prefix . Automator()->db->tables->trigger;

		// Delete the corresponding response.
		$item_log_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT item_log_id FROM $api_tbl WHERE recipe_log_id = %d", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$automator_recipe_log_id
			)
		);

		if ( $item_log_ids ) {
			foreach ( $item_log_ids as $item_log_id ) {
				$wpdb->delete(
					$api_response_tbl,
					array(
						'item_log_id' => $item_log_id,
					),
					array( '%d' )
				);
			}
		}

		// Delete the corresponding actions.
		$actions = $wpdb->get_col( $wpdb->prepare( "SELECT `ID` FROM $action_tbl WHERE automator_recipe_id=%d AND automator_recipe_log_id=%d", $recipe_id, $automator_recipe_log_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $actions ) {
			foreach ( $actions as $item_log_id ) {
				$wpdb->delete(
					$api_tbl,
					array(
						'type'        => 'action',
						'item_log_id' => $item_log_id,
					)
				);
			}
		}

		// -- Delete the corresponding trigger entries.

		$triggers = $wpdb->get_col( $wpdb->prepare( "SELECT `ID` FROM $triggers_tbl WHERE automator_recipe_id=%d AND automator_recipe_log_id=%d", $recipe_id, $automator_recipe_log_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $triggers ) {
			foreach ( $triggers as $item_log_id ) {
				$wpdb->delete(
					$api_tbl,
					array(
						'type'        => 'trigger',
						'item_log_id' => $item_log_id,
					)
				);
			}
		}

	}

}

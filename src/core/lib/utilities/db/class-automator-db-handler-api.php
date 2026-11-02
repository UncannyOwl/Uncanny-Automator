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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write to Automator's own table.
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
			// One format per column, in column order. `status` is a varchar: a
			// transport failure's status is its WP_Error code, not a number.
			array(
				'%s', // date_time.
				'%s', // type.
				'%d', // recipe_log_id.
				'%d', // item_log_id.
				'%s', // endpoint.
				'%s', // params.
				'%s', // request.
				'%s', // response.
				'%s', // status.
				'%d', // price.
				'%d', // balance.
				'%d', // time_spent.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write to Automator's own table.
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$result = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . esc_sql( $table_name ) . '` WHERE ID =%s', $id ) );

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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$result = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . esc_sql( $table_name ) . '` WHERE type=%s AND item_log_id =%d', $type, $item_log_id ) );

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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$item_log_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT item_log_id FROM `' . esc_sql( $api_tbl ) . '` WHERE recipe_log_id = %d',
				$automator_recipe_log_id
			)
		);

		if ( $item_log_ids ) {
			foreach ( $item_log_ids as $item_log_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$actions = $wpdb->get_col( $wpdb->prepare( 'SELECT `ID` FROM `' . esc_sql( $action_tbl ) . '` WHERE automator_recipe_id=%d AND automator_recipe_log_id=%d', $recipe_id, $automator_recipe_log_id ) );

		if ( $actions ) {
			foreach ( $actions as $item_log_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$triggers = $wpdb->get_col( $wpdb->prepare( 'SELECT `ID` FROM `' . esc_sql( $triggers_tbl ) . '` WHERE automator_recipe_id=%d AND automator_recipe_log_id=%d', $recipe_id, $automator_recipe_log_id ) );

		if ( $triggers ) {
			foreach ( $triggers as $item_log_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
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

	/**
	 * Deletes the API log of every run of a recipe, with the resend results recorded for its actions.
	 *
	 * Each row is found through the recipe's run, action and trigger logs, so this runs before those are deleted.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return void
	 */
	public function delete_by_recipe_id( $recipe_id ) {

		global $wpdb;

		// Resend results, by the action log each one was recorded for.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE response FROM {$wpdb->prefix}uap_api_log_response response INNER JOIN {$wpdb->prefix}uap_action_log a ON response.item_log_id = a.ID WHERE a.automator_recipe_id = %d",
				$recipe_id
			)
		);

		// The API calls of the recipe's actions and triggers, found as a single run's purge finds them.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE api FROM {$wpdb->prefix}uap_api_log api INNER JOIN {$wpdb->prefix}uap_action_log a ON api.item_log_id = a.ID WHERE api.type = 'action' AND a.automator_recipe_id = %d",
				$recipe_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE api FROM {$wpdb->prefix}uap_api_log api INNER JOIN {$wpdb->prefix}uap_trigger_log t ON api.item_log_id = t.ID WHERE api.type = 'trigger' AND t.automator_recipe_id = %d",
				$recipe_id
			)
		);

		// Any other API call recorded on one of the recipe's runs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE api FROM {$wpdb->prefix}uap_api_log api INNER JOIN {$wpdb->prefix}uap_recipe_log r ON api.recipe_log_id = r.ID WHERE r.automator_recipe_id = %d",
				$recipe_id
			)
		);
	}
}

<?php

namespace Uncanny_Automator\Logger\Db;

use Uncanny_Automator\Automator_Status;

/**
 * Specific DB operations related to log. All logs related db operations should be added here.
 *
 * @since 5.4
 *
 * @package Uncanny_Automator\Logger\Db
 */
class Data_Access {

	/**
	 * Finds a specific recipe log by ID.
	 *
	 * @param int $id
	 *
	 * @return null|array{ID:string,date_time:string,user_id:string,automator_recipe_id:string,completed:string,run_number:string}
	 */
	public static function find_recipe_log_by_id( $id ) {

		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}uap_recipe_log WHERE ID = %d", $id ),
			ARRAY_A
		);

	}

	/**
	 * Determines whether the recipe has in-progress action.
	 *
	 * @param int $recipe_log_id
	 *
	 * @return bool Returns true if there are any in-progress actions.
	 */
	public static function action_log_has_in_progress( $recipe_log_id ) {

		global $wpdb;

		$result = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `completed` as `status` 
					FROM {$wpdb->prefix}uap_action_log 
						WHERE automator_recipe_log_id = %d
				",
				$recipe_log_id
			),
			ARRAY_A
		);

		$result_set_int = array_map(
			function( $val ) {
				return intval( $val );
			},
			array_column( $result, 'status' )
		);

		$removable = Automator_Status::get_removable_statuses();

		return count( array_diff( $result_set_int, $removable ) ) >= 1;

	}

	/**
	 * @param int $recipe_log_id
	 *
	 * @return bool Returns true if a specific recipe log has in-progress or queued status. Returns false, otherwise.
	 */
	public static function loop_entries_has_in_progress( $recipe_log_id ) {

		global $wpdb;

		$results = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_loop_entries WHERE status IN('in-progress','queued')",
				$recipe_log_id
			),
			ARRAY_A
		);

		return ! empty( $results );

	}
}

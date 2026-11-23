<?php
namespace Uncanny_Automator\Logger;

/**
 * Internal class use for logging tokens.
 *
 * @since 4.12
 */
class Tokens_Logger {

	/**
	 * Action log meta naming where the action stands among the run's token records: the record
	 * it wrote, or the latest one before it when it wrote none (0 when the run had none yet).
	 * The run log shows each action the records up to there (QA D103). An action that never
	 * ran, such as a skipped one, notes nothing; Run_Data_Bundle::token_place_for() places it.
	 */
	const RECORD_META_KEY = 'tokens_record_id';

	/**
	 * Logs the field values in action meta log table.
	 *
	 * @param mixed[] $args Accepts ['tokens_record','recipe_id','recipe_log_id', 'run_number'].
	 *
	* @todo Move the query to the query class.
	*
	 * @return bool|int
	 */
	public function log( $args = array() ) {

		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'tokens_record' => 0,
				'recipe_id'     => 0,
				'recipe_log_id' => 0,
				'run_number'    => 0,
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write to Automator's own table.
		return $wpdb->insert(
			$wpdb->prefix . 'uap_tokens_log',
			array(
				'tokens_record' => wp_json_encode( $args['tokens_record'] ),
				'recipe_id'     => $args['recipe_id'],
				'recipe_log_id' => $args['recipe_log_id'],
				'run_number'    => $args['run_number'],
			),
			array(
				'%s',
				'%d',
				'%d',
				'%d',
			)
		);
	}
}

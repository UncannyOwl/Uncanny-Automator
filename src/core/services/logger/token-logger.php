<?php
namespace Uncanny_Automator\Logger;

/**
 * Internal class use for logging tokens.
 *
 * @since 4.12
 */
class Tokens_Logger {

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

<?php
namespace Uncanny_Automator\Logger;

/**
 * Internal class use for logging fields.
 *
 * @since 4.12
 */
class Trigger_Fields_Logger {

	const META_KEY = 'trigger_fields';

	/**
	 * Logs the field values in trigger meta log table.
	 *
	 * @param int[] $args Accepts ['trigger_id','recipe_id','trigger_log_id','run_number', 'user_id']
	 * @param mixed[] $fields Accepts the array result from resolver.
	 *
	 * @return bool|int|null
	 */
	public function log( $args = array(), $fields = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'trigger_id'     => 0,
				'recipe_id'      => 0,
				'trigger_log_id' => 0,
				'run_number'     => 0,
				'user_id'        => 0,
			)
		);

		return Automator()->db->trigger->add_meta(
			$args['trigger_id'],
			$args['trigger_log_id'],
			$args['run_number'],
			array(
				'user_id'    => $args['user_id'],
				'meta_key'   => self::META_KEY,
				'meta_value' => wp_json_encode( array_merge( (array) $fields['options'], (array) $fields['options_group'] ) ),
			)
		);

	}

}

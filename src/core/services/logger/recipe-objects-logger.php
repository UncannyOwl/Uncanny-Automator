<?php

namespace Uncanny_Automator\Logger;

/**
 * Internal class use for logging fields.
 *
 * @since 4.12
 */
class Recipe_Objects_Logger {

	/**
	 * The meta key.
	 *
	 * @var string $key
	 */
	protected $key = 'recipe_current_triggers';

	/**
	 * Sets the key property
	 *
	 * @param string $key
	 *
	 * @return void
	 */
	public function set_key( $key ) {
		$this->key = $key;
	}

	/**
	 * Logs the trigger ids for the current recipe run.
	 *
	 * @param int[] $args
	 * @param string $trigger_ids The json encoded trigger_ids "[1,2,3]"
	 *
	 * @return bool|int|null
	 */
	public function log_triggers( $args = array(), $trigger_ids = '' ) {

		$args = wp_parse_args(
			$args,
			array(
				'user_id'       => 0,
				'recipe_id'     => 0,
				'recipe_log_id' => 0,
			)
		);

		return Automator()->db->trigger->add_meta(
			$args['trigger_id'],
			$args['trigger_log_id'],
			$args['run_number'],
			array(
				'user_id'    => $args['user_id'],
				'meta_key'   => $this->key,
				'meta_value' => $trigger_ids,
			)
		);

	}

	/**
	 * Logs the actions conditions.
	 *
	 * @param int[] $args
	 * @param mixed[] $action_conditions_result
	 *
	 * @return int|false
	 */
	public function log_actions_conditions( $args = array(), $action_conditions_result = array() ) {

		return $this->add_meta( $args, 'actions_conditions', $action_conditions_result );

	}

	/**
	 * Logs the actions flow.
	 *
	 * @param int[] $args
	 * @param mixed[] $flow
	 *
	 * @return int|false
	 */
	public function log_actions_flow( $args = array(), $flow = array() ) {

		return $this->add_meta(
			$args,
			'actions_flow',
			array(
				'actions_conditions' => get_post_meta( $args['recipe_id'], 'actions_conditions', true ),
				'flow'               => $flow,
			)
		);

	}

	/**
	 * Adds a meta to the _uap_recipe_log_meta table.
	 *
	 * @param int[] $args
	 * @param string $meta_key
	 * @param mixed $meta_value
	 * @param bool $upsert
	 *
	 * @return int|false
	 * @todo Move queries to the query class.
	 *
	 */
	public function add_meta( $args = array(), $meta_key = '', $meta_value = '', $upsert = true ) {
		return Automator()->db->recipe->add_meta( $meta_key, $meta_value, $args, $upsert );
	}

	/**
	 * @param int[] $args
	 * @param string $meta_key
	 *
	 * @return string
	 */
	public function get_meta( $args, $meta_key ) {
		return Automator()->db->recipe->get_meta( $meta_key, $args );
	}

}

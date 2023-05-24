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
	 * @param int[] $args
	 * @param string $meta_key
	 * @param string $meta_value
	 *
	 * @return bool True if existing data already exists. Otherwise, false.
	 */
	protected function fetch_existing_data( $args, $meta_key, $meta_value ) {

		$has_record = 'no';

		$key = 'automator_recipe_objects_logger_' . maybe_serialize( $args ) . '_' . $meta_key . '_' . maybe_serialize( $meta_value );

		$has_record_cached = wp_cache_get( $key );

		if ( false !== $has_record_cached ) {
			return 'yes' === $has_record_cached ? true : false;
		}

		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->prefix}uap_recipe_log_meta 
				WHERE user_id = %d
				AND recipe_id = %d
				AND recipe_log_id = %d
				AND meta_key = %s
				AND meta_value = %s
				",
				$args['user_id'],
				$args['recipe_id'],
				$args['recipe_log_id'],
				$meta_key,
				wp_json_encode( $meta_value )
			)
		);

		if ( ! empty( $results ) ) {
			$has_record = 'yes';
		}

		wp_cache_set( $key, $has_record );

		return 'yes' === $has_record ? true : false;
	}

	/**
	 * Adds a meta to the _uap_recipe_log_meta table.
	 *
	 * @todo Move queries to the query class.
	 *
	 * @param int[] $args
	 * @param string $meta_key
	 * @param mixed $meta_value
	 * @param bool $upsert
	 *
	 * @return int|false
	 */
	public function add_meta( $args = array(), $meta_key = '', $meta_value = '', $upsert = true ) {

		if ( empty( $meta_key ) ) {
			return false;
		}

		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'user_id'       => 0,
				'recipe_id'     => 0,
				'recipe_log_id' => 0,
			)
		);

		$has_record = ! empty( $this->get_meta( $args, $meta_key ) );

		if ( $has_record && true === $upsert ) {
			return $wpdb->update(
				$wpdb->prefix . 'uap_recipe_log_meta',
				array(
					'meta_value' => wp_json_encode( $meta_value ),
				),
				array(
					'user_id'       => $args['user_id'],
					'recipe_id'     => $args['recipe_id'],
					'recipe_log_id' => $args['recipe_log_id'],
					'meta_key'      => $meta_key,
				),
				array( '%s' ),
				array( '%d', '%d', '%d', '%s' )
			);
		}

		$meta_value = wp_json_encode( $meta_value );

		$has_existing_record = false;

		if ( is_string( $meta_value ) ) {
			$has_existing_record = $this->fetch_existing_data( $args, $meta_key, $meta_value );
		}

		if ( false === $has_existing_record ) {

			$column_val = array(
				'user_id'       => $args['user_id'],
				'recipe_id'     => $args['recipe_id'],
				'recipe_log_id' => $args['recipe_log_id'],
				'meta_key'      => $meta_key,
				'meta_value'    => $meta_value,
			);

			$serialized = maybe_serialize( $column_val );

			if ( is_string( $serialized ) ) {
				$hashed = md5( $serialized );
			}

			if ( isset( $hashed ) && false !== wp_cache_get( $hashed ) ) {
				return false;
			}

			$inserted = $wpdb->insert(
				$wpdb->prefix . 'uap_recipe_log_meta',
				$column_val,
				array( '%d', '%d', '%d', '%s', '%s' )
			);

			if ( isset( $hashed ) ) {
				wp_cache_set( $hashed, true );
			}

			return $inserted;

		}

		return false;

	}

	/**
	 * @param int[] $args
	 * @param string $meta_key
	 *
	 * @return string
	 */
	public function get_meta( $args, $meta_key ) {

		$group = 'automator_recipe_objects_logger_get_meta';

		$key = sprintf(
			'%s_%d_%d_%d_%s',
			$group,
			$args['user_id'],
			$args['recipe_id'],
			$args['recipe_log_id'],
			$meta_key
		);

		$meta_val_cached = wp_cache_get( $key, $group, false );

		if ( false !== $meta_val_cached ) {
			return is_string( $meta_val_cached ) ? $meta_val_cached : '';
		}

		global $wpdb;

		$meta_val = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value 
					FROM {$wpdb->prefix}uap_recipe_log_meta 
						WHERE recipe_id = %d
							AND recipe_log_id = %d
							AND user_id = %d
							AND meta_key = %s",
				$args['recipe_id'],
				$args['recipe_log_id'],
				$args['user_id'],
				$meta_key
			)
		);

		wp_cache_set( $key, $meta_val, $group );

		return $meta_val;

	}

}

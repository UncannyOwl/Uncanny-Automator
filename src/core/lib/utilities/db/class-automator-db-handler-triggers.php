<?php


namespace Uncanny_Automator;

/**
 * Class Automator_DB_Handler_Triggers
 *
 * @package Uncanny_Automator
 */
class Automator_DB_Handler_Triggers {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @return Automator_DB_Handler_Triggers
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param int|null $user_id
	 * @param int|null $trigger_id
	 * @param int|null $recipe_id
	 * @param $completed
	 * @param int|null $recipe_log_id
	 *
	 * @return int|null
	 */
	public function add( $user_id = null, $trigger_id = null, $recipe_id = null, $completed = false, $recipe_log_id = null ) {
		if ( null === $trigger_id ) {
			return null;
		}
		if ( null === $user_id ) {
			return null;
		}
		if ( null === $recipe_id ) {
			return null;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . Automator()->db->tables->trigger;

		$wpdb->insert(
			$table_name,
			array(
				'date_time'               => current_time( 'mysql' ),
				'user_id'                 => $user_id,
				'automator_trigger_id'    => $trigger_id,
				'automator_recipe_id'     => $recipe_id,
				'completed'               => $completed,
				'automator_recipe_log_id' => $recipe_log_id,
			),
			array(
				'%s',
				'%d',
				'%d',
				'%d',
				'%s',
				'%d',
			)
		);

		return $wpdb->insert_id;
	}

	/**
	 * @param $to_update
	 * @param $where
	 * @param $update_format
	 * @param $where_format
	 *
	 * @return bool|int
	 */
	public function update( $to_update, $where, $update_format, $where_format ) {
		global $wpdb;
		$table_name = isset( Automator()->db->tables->trigger ) ? Automator()->db->tables->trigger : 'uap_trigger_log';

		return $wpdb->update(
			$wpdb->prefix . $table_name,
			$to_update,
			$where,
			$update_format,
			$where_format
		);
	}

	/**
	 * @param $trigger_id
	 * @param $trigger_log_id
	 * @param $run_number
	 * @param $args
	 *
	 * @return bool|int|null
	 */
	public function add_meta( $trigger_id, $trigger_log_id, $run_number, $args ) {
		$user_id    = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
		$meta_key   = isset( $args['meta_key'] ) ? esc_attr( $args['meta_key'] ) : '';
		$meta_value = isset( $args['meta_value'] ) ? $args['meta_value'] : '';
		$run_time   = isset( $args['run_time'] ) ? $args['run_time'] : current_time( 'mysql' );
		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( ! is_numeric( $trigger_log_id ) ) {
			Automator()->error->add_error( 'insert_trigger_meta', 'ERROR: You are trying to insert trigger meta without providing valid trigger_log_id', $this );

			return null;
		}

		if ( null === $meta_key || ! is_string( $meta_key ) ) {
			Automator()->error->add_error( 'insert_trigger_meta', 'ERROR: You are trying to insert trigger meta without providing a meta_key', $this );

			return null;
		}
//      // Disabling this check to avoid unnecessary recipe issues
//		if ( null === $meta_value ) {
//			Automator()->error->add_error( 'insert_trigger_meta', 'ERROR: You are trying to insert trigger meta without providing a meta_value', $this );
//
//			return null;
//		}

		if ( 'sentence_human_readable' === $meta_key ) {
			if ( ! empty( $this->get_sentence( $user_id, $trigger_log_id, $run_number, $meta_key ) ) ) {
				// sentence already added!
				return null;
			}
		}

		global $wpdb;
		$table_name = isset( Automator()->db->tables->trigger_meta ) ? Automator()->db->tables->trigger_meta : 'uap_trigger_log_meta';

		return $wpdb->insert(
			$wpdb->prefix . $table_name,
			array(
				'user_id'                  => $user_id,
				'automator_trigger_log_id' => $trigger_log_id,
				'automator_trigger_id'     => $trigger_id,
				'run_number'               => $run_number,
				'meta_key'                 => $meta_key,
				'meta_value'               => $meta_value,
				'run_time'                 => $run_time,
			),
			array(
				'%d',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * @param $args
	 * @param $meta_key
	 * @param $meta_value
	 *
	 * @return bool|int|null
	 */
	public function add_token_meta( $meta_key, $meta_value, $args ) {
		$trigger_id     = isset( $args['trigger_id'] ) ? absint( $args['trigger_id'] ) : 0;
		$trigger_log_id = isset( $args['trigger_log_id'] ) ? absint( $args['trigger_log_id'] ) : null;
		$run_number     = isset( $args['run_number'] ) ? absint( $args['run_number'] ) : 0;
		$user_id        = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( null === $trigger_log_id || ! is_numeric( $trigger_log_id ) ) {
			Automator()->error->add_error( 'insert_trigger_token_meta', 'ERROR: You are trying to insert trigger meta without providing valid trigger_log_id', $this );

			return null;
		}

		if ( empty( $meta_key ) ) {
			Automator()->error->add_error( 'insert_trigger_token_meta', 'ERROR: You are trying to insert trigger meta without providing a meta_key', $this );

			return null;
		}

		if ( empty( $meta_value ) ) {
			Automator()->error->add_error( 'insert_trigger_token_meta', 'ERROR: You are trying to insert trigger meta without providing a meta_value', $this );

			return null;
		}
		$token_args = array(
			'user_id'    => $user_id,
			'meta_key'   => $meta_key,
			'meta_value' => $meta_value,
		);

		return $this->add_meta( $trigger_id, $trigger_log_id, $run_number, $token_args );
	}

	/**
	 * @param $update
	 * @param $where
	 * @param $update_format
	 * @param $where_format
	 *
	 * @return bool|int
	 */
	public function update_meta( $update, $where, $update_format, $where_format ) {
		global $wpdb;
		$table_name = isset( Automator()->db->tables->trigger_meta ) ? Automator()->db->tables->trigger_meta : 'uap_trigger_log_meta';

		return $wpdb->update(
			$wpdb->prefix . $table_name,
			$update,
			$where,
			$update_format,
			$where_format
		);
	}

	/**
	 * @param $user_id
	 * @param $trigger_log_id
	 * @param $run_number
	 * @param $meta_key
	 *
	 * @return string|null
	 */
	public function get_sentence( $user_id, $trigger_log_id, $run_number, $meta_key ) {
		global $wpdb;
		$tbl = Automator()->db->tables->trigger_meta;

		return $wpdb->get_var(
			$wpdb->prepare(
			//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT meta_value FROM {$wpdb->prefix}{$tbl}
						WHERE 1=1
						AND user_id = %d
						AND automator_trigger_log_id = %d
						AND run_number = %d
						AND meta_key LIKE %s",
				$user_id,
				$trigger_log_id,
				$run_number,
				$meta_key
			)
		);
	}

	/**
	 * @param $meta_key
	 * @param $trigger_id
	 * @param $trigger_log_id
	 * @param int|null $user_id
	 *
	 * @return mixed|string
	 */
	public function get_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id = null ) {
		if ( empty( $meta_key ) || empty( $trigger_id ) || empty( $trigger_log_id ) ) {
			return '';
		}

		global $wpdb;
		$tbl        = Automator()->db->tables->trigger_meta;
		$meta_value = $wpdb->get_var(
			$wpdb->prepare(
			//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT meta_value FROM {$wpdb->prefix}$tbl
						WHERE 1=1
						AND user_id = %d
						AND meta_key = %s
						AND automator_trigger_id = %d
						AND automator_trigger_log_id = %d
						LIMIT 0,1",
				$user_id,
				$meta_key,
				$trigger_id,
				$trigger_log_id
			)
		);

		if ( ! empty( $meta_value ) ) {
			return maybe_unserialize( $meta_value );
		}

		return '';
	}

	/**
	 * @param $meta_key
	 * @param $args
	 *
	 * @return mixed
	 */
	public function get_token_meta( $meta_key, $args = array() ) {
		$trigger_id     = absint( $args['trigger_id'] );
		$trigger_log_id = absint( $args['trigger_log_id'] );
		$user_id        = absint( $args['user_id'] );

		return $this->get_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
	}

	/**
	 * @param $trigger_id
	 * @param $user_id
	 * @param $recipe_id
	 * @param $recipe_log_id
	 * @param $trigger_log_id
	 *
	 * @return bool|int
	 */
	public function mark_complete( $trigger_id, $user_id, $recipe_id, $recipe_log_id, $trigger_log_id ) {
		$update = array(
			'completed' => true,
			'date_time' => current_time( 'mysql' ),
		);

		$where = array(
			'user_id'              => $user_id,
			'automator_trigger_id' => $trigger_id,
			'automator_recipe_id'  => $recipe_id,
		);

		$update_format = array(
			'%d',
			'%s',
		);

		$where_format = array(
			'%d',
			'%d',
			'%d',
		);

		if ( null !== $trigger_log_id && is_int( $trigger_log_id ) ) {
			$where['ID']    = absint( $trigger_log_id );
			$where_format[] = '%d';
		}

		if ( null !== $recipe_log_id && is_int( $recipe_log_id ) ) {
			$where['automator_recipe_log_id'] = absint( $recipe_log_id );
			$where_format[]                   = '%d';
		}

		return $this->update( $update, $where, $update_format, $where_format );
	}

	/**
	 * @param int|null $user_id
	 * @param int|null $trigger_id
	 * @param int|null $recipe_id
	 * @param int|null $recipe_log_id
	 * @param $process_recipe
	 * @param $args
	 *
	 * @return bool|null
	 */
	public function is_completed( $user_id = null, $trigger_id = null, $recipe_id = null, $recipe_log_id = null, $process_recipe = false, $args = array() ) {
		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( null === $trigger_id || ! is_numeric( $trigger_id ) ) {
			Automator()->error->add_error( 'is_trigger_completed', 'ERROR: You are trying to check if a trigger is completed without providing a trigger_id', $this );

			return null;
		}

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Automator()->error->add_error( 'is_trigger_completed', 'ERROR: You are trying to check if a trigger is completed without providing a recipe_id', $this );

			return null;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . Automator()->db->tables->trigger;
		if ( $process_recipe ) {
			$q = "SELECT completed FROM $table_name
						WHERE user_id = %d
						AND automator_trigger_id = %d
						AND automator_recipe_id = %d
						AND automator_recipe_log_id = %d";
		} else {
			$q = "SELECT t.completed AS trigger_completed
							FROM $table_name t
							LEFT JOIN {$wpdb->prefix}uap_recipe_log r
							ON t.automator_recipe_log_id = r.ID
							LEFT JOIN {$wpdb->prefix}uap_action_log a
							ON t.automator_recipe_log_id = a.automator_recipe_log_id
							WHERE 1=1
							AND t.user_id = %d
							AND t.automator_trigger_id = %d
							AND t.automator_recipe_id = %d
							AND t.automator_recipe_log_id = %d
							AND r.completed = 1
							AND a.completed = 1";
		}
		$results = $wpdb->get_var( $wpdb->prepare( $q, $user_id, $trigger_id, $recipe_id, $recipe_log_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $results ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param $trigger_id
	 */
	public function delete( $trigger_id ) {
		global $wpdb;

		// delete from uap_trigger_log
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->trigger,
			array( 'automator_trigger_id' => $trigger_id )
		);

		// delete from uap_trigger_log_meta
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->trigger_meta,
			array( 'automator_trigger_id' => $trigger_id )
		);
	}

	/**
	 * @param $recipe_id
	 * @param $automator_recipe_log_id
	 */
	public function delete_logs( $recipe_id, $automator_recipe_log_id ) {
		global $wpdb;
		$trigger_tbl      = $wpdb->prefix . Automator()->db->tables->trigger;
		$trigger_meta_tbl = $wpdb->prefix . Automator()->db->tables->trigger_meta;
		$triggers         = $wpdb->get_col( $wpdb->prepare( "SELECT `ID` FROM $trigger_tbl WHERE automator_recipe_id=%d AND automator_recipe_log_id=%d", $recipe_id, $automator_recipe_log_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $triggers ) {
			foreach ( $triggers as $automator_trigger_log_id ) {
				// delete from uap_trigger_log_meta
				$wpdb->delete(
					$trigger_meta_tbl,
					array( 'automator_trigger_log_id' => $automator_trigger_log_id )
				);
			}
		}

		// delete from uap_trigger_log
		$wpdb->delete(
			$trigger_tbl,
			array(
				'automator_recipe_id'     => $recipe_id,
				'automator_recipe_log_id' => $automator_recipe_log_id,
			)
		);
	}

	/**
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function delete_by_recipe_id( $recipe_id ) {
		global $wpdb;
		$trigger_tbl      = $wpdb->prefix . Automator()->db->tables->trigger;
		$trigger_meta_tbl = $wpdb->prefix . Automator()->db->tables->trigger_meta;
		$triggers         = $wpdb->get_col( $wpdb->prepare( "SELECT `ID` FROM $trigger_tbl WHERE automator_recipe_id=%d", $recipe_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $triggers ) {
			foreach ( $triggers as $automator_trigger_log_id ) {
				// delete from uap_trigger_log_meta
				$wpdb->delete(
					$trigger_meta_tbl,
					array( 'automator_trigger_log_id' => $automator_trigger_log_id )
				);
			}
		}

		// delete from uap_trigger_log
		$wpdb->delete(
			$trigger_tbl,
			array(
				'automator_recipe_id' => $recipe_id,
			)
		);
	}
}

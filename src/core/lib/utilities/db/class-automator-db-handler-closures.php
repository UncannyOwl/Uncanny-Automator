<?php


namespace Uncanny_Automator;

/**
 * Class Automator_DB_Handler_Closures
 *
 * @package Uncanny_Automator
 */
class Automator_DB_Handler_Closures {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @return Automator_DB_Handler_Closures
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @return array
	 */
	public function get_all() {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT cp.ID FROM $wpdb->posts cp
	                    LEFT JOIN $wpdb->posts rp ON rp.ID = cp.post_parent
						WHERE cp.post_type LIKE %s
						  AND cp.post_status LIKE %s
						  AND rp.post_status LIKE %s LIMIT 1",
				'uo-closure',
				'publish',
				'publish'
			)
		);
	}

	/**
	 * @param $closure_id
	 */
	public function delete( $closure_id ) {
		global $wpdb;

		// delete from uap_closure_log
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->closure,
			array( 'automator_closure_id' => $closure_id )
		);

		// delete from uap_closure_log_meta
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->closure_meta,
			array( 'automator_closure_id' => $closure_id )
		);
	}

	/**
	 * @param $recipe_id
	 * @param $automator_recipe_log_id
	 */
	public function delete_logs( $recipe_id, $automator_recipe_log_id ) {
		global $wpdb;
		$closure_tbl      = $wpdb->prefix . Automator()->db->tables->closure;
		$closure_meta_tbl = $wpdb->prefix . Automator()->db->tables->closure_meta;
		$closure          = $wpdb->get_col( $wpdb->prepare( "SELECT `ID` FROM $closure_tbl WHERE automator_recipe_id=%d AND automator_recipe_log_id=%d", $recipe_id, $automator_recipe_log_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $closure ) {
			foreach ( $closure as $automator_closure_log_id ) {
				// delete from uap_closure_log_meta
				$wpdb->delete(
					$closure_meta_tbl,
					array( 'automator_closure_log_id' => $automator_closure_log_id )
				);
			}
		}

		// delete from uap_closure_log
		$wpdb->delete(
			$closure_tbl,
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
		$closure_tbl      = $wpdb->prefix . Automator()->db->tables->closure;
		$closure_meta_tbl = $wpdb->prefix . Automator()->db->tables->closure_meta;
		$closure          = $wpdb->get_col( $wpdb->prepare( "SELECT `ID` FROM $closure_tbl WHERE automator_recipe_id=%d", $recipe_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $closure ) {
			foreach ( $closure as $automator_closure_log_id ) {
				// delete from uap_closure_log_meta
				$wpdb->delete(
					$closure_meta_tbl,
					array( 'automator_closure_log_id' => $automator_closure_log_id )
				);
			}
		}

		// delete from uap_closure_log
		$wpdb->delete(
			$closure_tbl,
			array(
				'automator_recipe_id' => $recipe_id,
			)
		);
	}

	/**
	 * Adds an entry to the closure log.
	 *
	 * @param mixed[] $args See wp_parse_args func inside for possible arguments.
	 * -- `$args` Accepts\
	 * `int 'user_id'` - Defaults to `NULL` \
	 * `int 'automator_closure_id'` - Defaults to `NULL`\
	 * `int 'automator_recipe_id` - Defaults to `NULL`\
	 * `int 'automator_recipe_log_id` - Defaults to `NULL`\
	 * `int 'completed` - Defaults to `<int> 0`
	 *
	 * @return int|false The log ID on success. Returns boolean false otherwise.
	 */
	public function add_entry( $args = array() ) {

		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'user_id'                 => null,
				'automator_closure_id'    => null,
				'automator_recipe_id'     => null,
				'automator_recipe_log_id' => null,
				'completed'               => 0,
			)
		);

		$closure_tbl = $wpdb->prefix . Automator()->db->tables->closure;

		$inserted = $wpdb->insert(
			$closure_tbl,
			array(
				'user_id'                 => $args['user_id'],
				'automator_closure_id'    => $args['automator_closure_id'],
				'automator_recipe_id'     => $args['automator_recipe_id'],
				'automator_recipe_log_id' => $args['automator_recipe_log_id'],
				'completed'               => $args['completed'],
			),
			array(
				'%d',
				'%d',
				'%d',
				'%d',
				'%d',
			)
		);

		if ( false !== $inserted ) {
			return $wpdb->insert_id;
		}

		return false;

	}

	/**
	 * Adds an entry to the closures log table.
	 *
	 * @param int[] $args
	 * -- `$args` Accepts\
	 * `int 'user_id'` - Defaults to `NULL` \
	 * `int 'automator_closure_id'` - Defaults to `NULL`\
	 * `int 'automator_closure_log_id` - Defaults to `NULL`
	 *
	 * @param string $meta_key
	 * @param string $meta_value
	 *
	 * @return int|false
	 */
	public function add_entry_meta( $args = array(), $meta_key = '', $meta_value = '' ) {

		if ( empty( $meta_key ) ) {
			_doing_it_wrong( esc_html( self::class . '::add_entry_meta' ), 'The parameter $meta_key must not be empty.', 4.12 );
			return false;
		}

		global $wpdb;

		$closure_meta_tbl = $wpdb->prefix . Automator()->db->tables->closure_meta;

		$args = wp_parse_args(
			$args,
			array(
				'user_id'                  => null,
				'automator_closure_id'     => null,
				'automator_closure_log_id' => null,
			)
		);

		return $wpdb->insert(
			$closure_meta_tbl,
			array(
				'user_id'                  => $args['user_id'],
				'automator_closure_id'     => $args['automator_closure_id'],
				'automator_closure_log_id' => $args['automator_closure_log_id'],
				'meta_key'                 => $meta_key,
				'meta_value'               => $meta_value,
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
	 * Retrieve an entry meta value from the closures log meta table.
	 *
	 * @param int[] $args
	 * -- `$args` Accepts\
	 * `int 'user_id'` - Defaults to `NULL` \
	 * `int 'automator_closure_id'` - Defaults to `NULL`\
	 * `int 'automator_closure_log_id` - Defaults to `NULL`
	 *
	 * @param string $meta_key
	 *
	 * @return string|false Returns the meta value as string. Otherwise, returns boolean false if meta value is falsy or if meta key is empty.
	 */
	public function get_entry_meta( $args = array(), $meta_key = '' ) {

		if ( empty( $meta_key ) ) {
			_doing_it_wrong( esc_html( self::class . '::get_entry_meta' ), 'The parameter $meta_key must not be empty.', 4.12 );
			return false;
		}

		$args = wp_parse_args(
			$args,
			array(
				'user_id'                  => null,
				'automator_closure_id'     => null,
				'automator_closure_log_id' => null,
			)
		);

		global $wpdb;

		$closure_meta_tbl = $wpdb->prefix . Automator()->db->tables->closure_meta;

		$meta_value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT meta_value FROM ' . esc_sql( $closure_meta_tbl ) . '
				WHERE user_id = %d
				AND automator_closure_id = %d
				AND automator_closure_log_id = %d
				AND meta_key = %s
				',
				$args['user_id'],
				$args['automator_closure_id'],
				$args['automator_closure_log_id'],
				$meta_key
			)
		);

		if ( empty( $meta_value ) ) {
			return false;
		}

		return $meta_value;

	}

}

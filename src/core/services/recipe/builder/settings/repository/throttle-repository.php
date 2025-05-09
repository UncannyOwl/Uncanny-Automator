<?php
//phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

namespace Uncanny_Automator\Services\Recipe\Builder\Settings\Repository;

/**
 * Class Throttle_Repository
 *
 * Handles database operations for recipe throttling
 *
 * @package Uncanny_Automator\Services\Recipe\Builder\Settings\Repository
 */
class Throttle_Repository implements Throttle_Repository_Interface {

	/**
	 * @var \wpdb WordPress database instance
	 */
	private $wpdb;

	/**
	 * @var string The table name without prefix
	 */
	private $table = 'uap_recipe_throttle_log';

	/**
	 * @var string The meta key for the recipe throttle
	 */
	private $meta_key = 'field_recipe_throttle';

	/**
	 * Constructor
	 *
	 * @param \wpdb $wpdb
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Gets the last run timestamp for a recipe/user combination
	 *
	 * @param int $recipe_id
	 * @param int $user_id
	 * @param string $meta_key
	 *
	 * @return int|null Timestamp of last run or null if not found
	 */
	public function get_last_run( $recipe_id, $user_id ) {

		$table = $this->wpdb->prefix . $this->table;

		// All good, we are using prepare.
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT last_run 
                    FROM {$table} 
                    WHERE recipe_id = %d 
                    AND user_id = %d 
                    AND meta_key = %s",
				$recipe_id,
				$user_id,
				$this->meta_key
			)
		);

		return null === $result ? null : (int) $result;
	}

	/**
	 * Updates or inserts the last run timestamp
	 *
	 * @param int $recipe_id
	 * @param int $user_id
	 * @param string $meta_key
	 * @param int $timestamp
	 *
	 * @return bool True on success, false on failure
	 */
	public function update_last_run( $recipe_id, $user_id, $timestamp ) {

		$table = $this->wpdb->prefix . $this->table;

		$result = $this->wpdb->replace(
			$table,
			array(
				'recipe_id' => $recipe_id,
				'user_id'   => $user_id,
				'meta_key'  => $this->meta_key,
				'last_run'  => $timestamp,
				'date_time' => current_time( 'mysql' ),
			),
			array(
				'%d', // recipe_id
				'%d', // user_id
				'%s', // meta_key
				'%d', // last_run
				'%s',  // date_time
			)
		);

		return false !== $result;
	}
}

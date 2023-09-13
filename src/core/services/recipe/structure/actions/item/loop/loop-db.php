<?php

namespace Uncanny_Automator\Services\Structure\Actions\Item\Loop;

use Uncanny_Automator\Utilities;
use Uncanny_Automator_Pro\Loops_Process_Registry;

/**
 * Handles loops database transactions from the base plugin.
 *
 * @since 5.0
 * @package Uncanny_Automator\Services\Structure\Actions\Item\Loop
 */
class Loop_Db {

	protected $db;

	public function __construct() {
		global $wpdb;
		$this->db = $wpdb;
	}

	/**
	 * Given a recipe ID, find all loops that are under it.
	 *
	 * @param int $recipe_id
	 *
	 * @return mixed[]
	 */
	public function find_recipe_loops( $recipe_id ) {

		$loops = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->db->posts} WHERE post_parent = %d AND post_type = %s",
				absint( $recipe_id ),
				'uo-loop'
			),
			ARRAY_A
		);

		return (array) $loops;
	}

	/**
	 * This is a method built to support legacy object. It will be deprecated soon.
	 *
	 * @return mixed[]
	 */
	public function fetch_all_recipes_loops() {

		$cache_key = 'automator_loop_db_fetch_all_recipes_loops';

		$cached_recipes_loops = wp_cache_get( $cache_key, $cache_key . '_group' );

		if ( false !== $cached_recipes_loops ) {
			return (array) $cached_recipes_loops;
		}

		$recipes_loops = (array) $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->db->posts}
					WHERE post_parent IN (
						SELECT ID FROM {$this->db->posts} WHERE post_type = '%s'
					)
					AND post_type = %s
				",
				'uo-recipe',
				'uo-loop'
			),
			ARRAY_A
		);

		$all_loops = array();
		foreach ( $recipes_loops as $recipe_loop ) {
			$all_loops[ $recipe_loop['post_parent'] ][] = $recipe_loop;
		}

		wp_cache_set( $cache_key, $all_loops, $cache_key . '_group' );

		return $all_loops;

	}

	/**
	 * Call the method "fetch_all_recipes_loops" and supply as Parameter#3.
	 *
	 * @param int $recipe_id
	 * @param mixed[] $recipes_loops
	 *
	 * @return mixed[]
	 */
	public function find_recipe_loops_from_recipes_loops( $recipe_id, $recipes_loops ) {

		return isset( $recipes_loops[ $recipe_id ] ) ? $recipes_loops[ $recipe_id ] : array();

	}

	/**
	 * Generates an array of actions that is compatible with old recipe_object.
	 *
	 * @param int $recipe_id
	 * @param bool $render_meta
	 * @param bool $render_action_tokens
	 * @param mixed[] $recipes_loops
	 *
	 * @return mixed[]
	 */
	public function find_recipe_loops_actions( $recipe_id = null, $render_meta = false, $render_action_tokens = false, $recipes_loops = array() ) {

		$recipe_loops = $this->find_recipe_loops_from_recipes_loops( $recipe_id, $recipes_loops );

		$loops_actions = array();

		foreach ( (array) $recipe_loops as $recipe_loop ) {

			$recipe_loop_actions = $this->find_loop_actions( absint( $recipe_loop['ID'] ) );

			foreach ( $recipe_loop_actions as $loop_action ) {

				$action_id             = absint( $loop_action['ID'] );
				$key                   = $action_id;
				$loops_actions[ $key ] = array(
					'ID'          => absint( $action_id ),
					'post_status' => $loop_action['post_status'],
					'menu_order'  => absint( $loop_action['menu_order'] ),
				);

				$action_meta = Utilities::flatten_post_meta( (array) get_post_meta( $action_id ) );

				if ( true === $render_meta ) {
					$loops_actions[ $key ]['meta'] = $action_meta;
				}

				if ( true === $render_action_tokens ) {
					$loops_actions[ $key ]['tokens'] = apply_filters( 'automator_action_' . $action_meta['code'] . '_tokens_renderable', array(), $action_id, $recipe_id );
				}
			}
		}

		return array_values( (array) $loops_actions );
	}

	/**
	 * Given a loop ID, find all filters that are under it.
	 *
	 * @param int $loop_id
	 *
	 * @return mixed[] array
	 */
	public function find_loop_filters( $loop_id ) {

		$filters = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->db->posts} WHERE post_parent = %d AND post_type = %s ORDER BY menu_order ASC",
				absint( $loop_id ),
				'uo-loop-filter'
			),
			ARRAY_A
		);

		return (array) $filters;

	}

	/**
	 * Given a loop ID, find all actions that are under it.
	 *
	 * @param int $loop_id
	 *
	 * @return mixed[] array
	 */
	public function find_loop_actions( $loop_id ) {

		$actions = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->db->posts} WHERE post_parent = %d AND post_type = %s ORDER BY menu_order ASC",
				absint( $loop_id ),
				'uo-action'
			),
			ARRAY_A
		);

		return (array) $actions;

	}

	/**
	 * Retrieves total number of completed iteration by distinct user in a
	 * given loop ID relative to recipe log and run number.
	 *
	 * @param int $loop_id The loop ID
	 * @param mixed[] $params The parameters
	 *
	 * @return int
	 */
	public function find_loop_items_completed_count( $loop_id, $params ) {

		$process_id = Loops_Process_Registry::generate_process_id_manual(
			$loop_id,
			$params['recipe_id'],
			$params['recipe_log_id'],
			$params['run_number']
		);

		$num_processed_users = $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(DISTINCT entity_id)
					FROM {$this->db->prefix}uap_loop_entries_items
					WHERE filter_id = %s
						AND recipe_id = %d
						AND recipe_log_id = %d
						AND recipe_run_number = %d",
				$process_id,
				$params['recipe_id'],
				$params['recipe_log_id'],
				$params['run_number']
			)
		);

		return absint( $num_processed_users );

	}

	/**
	 * Determines whether the specific loop process has in-progress entry.
	 *
	 * @param int $loop_id
	 * @param int $recipe_id
	 * @param int $recipe_log_id
	 * @param int $run_number
	 *
	 * @return bool
	 */
	public function loop_has_in_progress_item( $loop_id, $recipe_id, $recipe_log_id, $run_number ) {

		$process_id = Loops_Process_Registry::generate_process_id_manual( $loop_id, $recipe_id, $recipe_log_id, $run_number );

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}uap_loop_entries_items
					WHERE filter_id  = %s
						AND `status`= %s
						AND recipe_id = %d
						AND recipe_log_id = %d
						AND recipe_run_number = %d",
				$process_id,
				'in-progress',
				$recipe_id,
				$recipe_log_id,
				$run_number
			)
		);

		return ! empty( $results );

	}

}

<?php
/**
 * Trigger Index
 *
 * Lightweight, event-driven cache mapping trigger codes to recipe/trigger metadata.
 * Eliminates the need for get_recipes_data() in the trigger hot path.
 *
 * Zero queries at runtime — reads from uap_options (already object-cached).
 * Rebuilt via the same WordPress hooks as Recipe_Manifest.
 *
 * @package Uncanny_Automator
 * @since   7.2
 */

namespace Uncanny_Automator;

class Trigger_Index {

	const OPTION_KEY = 'automator_trigger_index';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Cached index: trigger_code => array of entries.
	 *
	 * @var array|null
	 */
	private $index = null;

	/**
	 * Re-entrancy guard for rebuild.
	 *
	 * @var bool
	 */
	private $is_rebuilding = false;

	/**
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get the full index. Lazy-builds from DB on first access.
	 *
	 * @return array Trigger code => array of entry arrays.
	 */
	public function get() {

		if ( null !== $this->index ) {
			return $this->index;
		}

		$raw = automator_get_option( self::OPTION_KEY, false );

		if ( false !== $raw && is_array( $raw ) ) {
			$this->index = $raw;
			return $this->index;
		}

		// First access or invalidated — rebuild.
		$this->index = $this->build();
		$this->save( $this->index );

		return $this->index;
	}

	/**
	 * O(1) lookup by trigger code. Returns entries compatible with recipes_from_trigger_code() format.
	 *
	 * @param string   $trigger_code The trigger code (e.g. WP_LOGIN).
	 * @param int|null $recipe_id    Optional recipe ID filter.
	 *
	 * @return array Recipe-keyed array compatible with recipes_from_trigger_code().
	 */
	public function get_by_code( $trigger_code, $recipe_id = null ) {

		$index   = $this->get();
		$entries = isset( $index[ $trigger_code ] ) ? $index[ $trigger_code ] : array();

		if ( null !== $recipe_id ) {
			$recipe_id = absint( $recipe_id );
			$entries   = array_filter(
				$entries,
				function ( $entry ) use ( $recipe_id ) {
					return absint( $entry['recipe_id'] ) === $recipe_id;
				}
			);
		}

		return $this->format_as_recipes( $entries );
	}

	/**
	 * Transform flat index entries into the recipe-grouped structure
	 * expected by maybe_add_trigger_entry().
	 *
	 * Output format matches recipes_from_trigger_code():
	 * [
	 *   recipe_id => [
	 *     'ID'          => recipe_id,
	 *     'post_status' => 'publish',
	 *     'recipe_type' => 'user',
	 *     'triggers'    => [
	 *       [ 'ID' => trigger_id, 'post_status' => 'publish', 'meta' => [...] ],
	 *     ],
	 *   ],
	 * ]
	 *
	 * @param array $entries Flat array of index entries.
	 *
	 * @return array Recipe-keyed array.
	 */
	public function format_as_recipes( $entries ) {

		$recipes = array();

		foreach ( $entries as $entry ) {
			$rid = absint( $entry['recipe_id'] );

			if ( ! isset( $recipes[ $rid ] ) ) {
				$recipes[ $rid ] = array(
					'ID'          => $rid,
					'post_status' => $entry['recipe_status'],
					'recipe_type' => isset( $entry['recipe_type'] ) ? $entry['recipe_type'] : '',
					'triggers'    => array(),
				);
			}

			$recipes[ $rid ]['triggers'][] = array(
				'ID'          => absint( $entry['trigger_id'] ),
				'post_status' => $entry['trigger_status'],
				'post_parent' => $rid,
				'meta'        => $entry['trigger_meta'],
			);
		}

		return $recipes;
	}

	/**
	 * Build the index from a single SQL query.
	 *
	 * @return array Trigger code => array of entry arrays.
	 */
	private function build() {

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					recipe.ID AS recipe_id,
					recipe.post_status AS recipe_status,
					recipe_type_meta.meta_value AS recipe_type,
					trigger_post.ID AS trigger_id,
					trigger_post.post_status AS trigger_status,
					trigger_meta.meta_key,
					trigger_meta.meta_value
				FROM {$wpdb->posts} recipe
				INNER JOIN {$wpdb->posts} trigger_post
					ON trigger_post.post_parent = recipe.ID
					AND trigger_post.post_type = %s
					AND trigger_post.post_status = 'publish'
				INNER JOIN {$wpdb->postmeta} trigger_meta
					ON trigger_meta.post_id = trigger_post.ID
				LEFT JOIN {$wpdb->postmeta} recipe_type_meta
					ON recipe_type_meta.post_id = recipe.ID
					AND recipe_type_meta.meta_key = 'uap_recipe_type'
				WHERE recipe.post_type = %s
					AND recipe.post_status = 'publish'
				ORDER BY recipe.ID, trigger_post.ID",
				AUTOMATOR_POST_TYPE_TRIGGER,
				AUTOMATOR_POST_TYPE_RECIPE
			),
			OBJECT
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $this->group_rows( $rows );
	}

	/**
	 * Group raw DB rows into the index structure.
	 *
	 * @param array $rows DB result rows.
	 *
	 * @return array Trigger code => array of entry arrays.
	 */
	private function group_rows( $rows ) {

		$index = array();

		if ( empty( $rows ) ) {
			return $index;
		}

		// First pass: collect meta per trigger.
		$trigger_data = array();

		foreach ( $rows as $row ) {
			$tid = absint( $row->trigger_id );

			if ( ! isset( $trigger_data[ $tid ] ) ) {
				$trigger_data[ $tid ] = array(
					'recipe_id'      => absint( $row->recipe_id ),
					'recipe_status'  => $row->recipe_status,
					'recipe_type'    => $row->recipe_type,
					'trigger_id'     => $tid,
					'trigger_status' => $row->trigger_status,
					'trigger_meta'   => array(),
				);
			}

			$trigger_data[ $tid ]['trigger_meta'][ $row->meta_key ] = $row->meta_value;
		}

		// Second pass: index by trigger code.
		foreach ( $trigger_data as $entry ) {
			$code = isset( $entry['trigger_meta']['code'] ) ? $entry['trigger_meta']['code'] : '';

			if ( '' === $code ) {
				continue;
			}

			$index[ $code ][] = $entry;
		}

		return $index;
	}

	/**
	 * Rebuild the index and persist.
	 *
	 * @return void
	 */
	public function rebuild() {

		if ( $this->is_rebuilding ) {
			return;
		}

		$this->is_rebuilding = true;
		$this->index         = $this->build();
		$this->save( $this->index );
		$this->is_rebuilding = false;
	}

	/**
	 * Invalidate the index (delete from DB). Will lazy-rebuild on next get().
	 *
	 * @return void
	 */
	public function invalidate() {
		automator_delete_option( self::OPTION_KEY );
		$this->index = null;
	}

	/**
	 * Persist the index to uap_options.
	 *
	 * @param array $index The index data.
	 *
	 * @return void
	 */
	private function save( $index ) {
		automator_update_option( self::OPTION_KEY, $index );
	}
}

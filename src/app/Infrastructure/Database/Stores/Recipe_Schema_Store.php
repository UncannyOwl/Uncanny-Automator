<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Throwable;

/**
 * Defensive CRUD around recipe post-meta `_automator_recipe_schema`.
 *
 * The canonical recipe-schema store — persists a hand-shaped tree built by
 * `Recipe_Tree_Builder`, read O(1) by every projection consumer (frontend
 * wire serializer now; runner DAG, MCP, CLI, agent later). Current shape
 * is `SCHEMA_VERSION`; bump on every output-shape change.
 *
 * Corrupt JSON, non-array payload, or `_schema_version` mismatch returns
 * `null` AND auto-deletes the offending entry so the next read rebuilds
 * cleanly. Writers call `invalidate()`, which also bumps the per-recipe
 * write generation the reader guards on.
 *
 * **Bump `SCHEMA_VERSION` any time `Recipe_Tree_Builder::build`'s output
 * shape changes.** Stale entries lazy-rebuild on read; no manual purge
 * required.
 *
 * `get()` strips the internal `_schema_version` and `generated_at` keys
 * from the returned array so callers see only the canonical tree.
 */
final class Recipe_Schema_Store {

	/**
	 * Post-meta key the canonical tree is persisted under.
	 *
	 * @var string
	 */
	public const META_KEY = '_automator_recipe_schema';

	/**
	 * Shape version of the persisted tree. A stored tree written at an older version is treated as a miss and rebuilt, so bump this on every output-shape change.
	 *
	 * @var int
	 */
	public const SCHEMA_VERSION = 3;

	/**
	 * Post-meta key of the recipe's write generation.
	 *
	 * Bumped by every `invalidate()`. `Recipe_Schema_Reader` compares it before and after a rebuild walk and refuses to persist a tree when a write landed in between.
	 *
	 * @var string
	 */
	public const GENERATION_META_KEY = '_automator_recipe_schema_generation';

	/**
	 * Shared instance handed out by `instance()`.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Shared singleton accessor.
	 *
	 * Holds no per-request state; reused so callers share one instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Read and decode the persisted tree array.
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return array<string, mixed>|null Tree array, or null on miss / corrupt / stale.
	 */
	public function get( int $recipe_id ): ?array {
		$raw = get_post_meta( $recipe_id, self::META_KEY, true );
		if ( '' === $raw || ! is_string( $raw ) ) {
			return null;
		}

		try {
			// Depth matches wp_json_encode's default, which save() relies on.
			// A shallower limit here would let a deep tree write successfully
			// and then fail every read: decode throws, the row is deleted, the
			// tree rebuilds, saves at 512 again, and the cycle repeats.
			$decoded = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
		} catch ( Throwable $e ) {
			automator_log(
				'Recipe_Schema_Store::get failed to decode for recipe ' . $recipe_id . ': ' . $e->getMessage(),
				'Recipe_Schema_Store'
			);
			$this->delete( $recipe_id );
			return null;
		}

		if ( ! is_array( $decoded ) ) {
			automator_log(
				'Recipe_Schema_Store::get got non-array payload for recipe ' . $recipe_id,
				'Recipe_Schema_Store'
			);
			$this->delete( $recipe_id );
			return null;
		}

		if ( (int) ( $decoded['_schema_version'] ?? 0 ) !== self::SCHEMA_VERSION ) {
			// Stale entry from an older serializer shape — silent rebuild on
			// next read.
			$this->delete( $recipe_id );
			return null;
		}

		unset( $decoded['_schema_version'], $decoded['generated_at'] );
		return $decoded;
	}

	/**
	 * Persist the tree array, stamped with the current schema version.
	 *
	 * @param int                  $recipe_id Recipe post id.
	 * @param array<string, mixed> $tree      Canonical tree array (typically Recipe_Tree_Builder::build output).
	 * @return string The payload as persisted, for `delete_own()`.
	 */
	public function save( int $recipe_id, array $tree ): string {
		// `_schema_version` and `generated_at` lead the persisted shape so
		// raw inspection (SQL, log dumps, debug tools) can read both at the
		// top of the JSON before scanning the recipe body. PHP arrays
		// preserve insertion order through `wp_json_encode`, so unsetting
		// then prepending guarantees these keys appear first regardless of
		// what `Recipe_Tree_Builder` produced.
		unset( $tree['_schema_version'], $tree['generated_at'] );
		$tree = array(
			'_schema_version' => self::SCHEMA_VERSION,
			'generated_at'    => current_time( 'c' ),
		) + $tree;

		// wp_slash() counter-balances update_post_meta()'s internal
		// wp_unslash() — without it, JSON escape sequences like \" inside
		// HTML attribute values (sentence_html) get stripped on store and
		// the persisted value fails json_decode() with a syntax error.
		$payload = wp_slash( wp_json_encode( $tree ) );
		update_post_meta( $recipe_id, self::META_KEY, $payload );

		return $payload;
	}

	/**
	 * Delete the persisted tree only while it is still the given payload.
	 *
	 * One value-matched DELETE, so a tree another reader persisted in the meantime is left alone.
	 *
	 * @param int    $recipe_id Recipe post id.
	 * @param string $payload   The value `save()` returned.
	 */
	public function delete_own( int $recipe_id, string $payload ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- delete_metadata() selects then deletes by id; this must be one statement.
		$wpdb->delete(
			$wpdb->postmeta,
			array(
				'post_id'    => $recipe_id,
				'meta_key'   => self::META_KEY,
				'meta_value' => wp_unslash( $payload ),
			)
		);
		wp_cache_delete( $recipe_id, 'post_meta' );
	}

	/**
	 * Remove the persisted tree for a recipe.
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return void
	 */
	public function delete( int $recipe_id ): void {
		delete_post_meta( $recipe_id, self::META_KEY );
	}

	/**
	 * Invalidate the cached tree after a write to the recipe or one of its children.
	 *
	 * Bumps the write generation, then deletes the persisted tree. Stores call this for writes no WordPress hook covers — meta-only writes, every `uo-recipe` write, and any method whose LAST write is meta (the hook fired earlier, against pre-edit values). The next read rebuilds from final storage through `Recipe_Schema_Reader`.
	 *
	 * @param int $recipe_id Recipe post id. Non-positive ids are ignored.
	 *
	 * @return void
	 */
	public function invalidate( int $recipe_id ): void {
		if ( $recipe_id <= 0 ) {
			return;
		}

		// Bump first: the reader's post-save re-check must see it before the delete lands.
		update_post_meta( $recipe_id, self::GENERATION_META_KEY, $this->generation( $recipe_id ) + 1 );
		$this->delete( $recipe_id );
	}

	/**
	 * The recipe's current write generation, read from the table rather than the object cache so a concurrent request's bump is visible.
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return int Zero for a recipe that has never been invalidated.
	 */
	public function generation( int $recipe_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The object cache is what this read must bypass.
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$recipe_id,
				self::GENERATION_META_KEY
			)
		);

		return (int) $value;
	}
}

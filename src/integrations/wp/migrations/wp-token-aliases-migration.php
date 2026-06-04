<?php

namespace Uncanny_Automator\Integrations\Wp\Migrations;

use Uncanny_Automator\Integrations\Wp\Wp_Token_Aliases;

/**
 * Postmeta + options token-alias migration.
 *
 * Rewrites every reference to a legacy WP token ID (`WPPAGE_ID`,
 * `WPPOSTTYPES_ID`, etc.) to its canonical form (`POSTID`, …) in stored
 * recipe data so the parse-time `Wp_Token_Aliases` resolver eventually
 * becomes a no-op for migrated rows.
 *
 * Scope:
 *
 *   - `wp_postmeta` rows attached to recipe-structure post types
 *     (`uo-action`, `uo-action-condition`, `uo-recipe`, `uo-trigger`,
 *     `uo-closure`). Run logs (`uap_action_log_meta`) are intentionally
 *     out of scope — those captured token values at trigger-fire time
 *     and rewriting them would mutate audit history.
 *   - `wp_options` rows whose value matches the scheduled-action
 *     payload shape (`a:%user_id%action_data%`) AND contains any token
 *     reference.
 *   - `wp_uap_options` rows matching the same payload shape.
 *
 * Two-mode rewrite:
 *
 *   - Plain text gets direct regex substitution.
 *   - PHP-serialized values are `unserialize()`-ed, walked recursively,
 *     rewritten leaf-by-leaf, and re-serialized so length prefixes
 *     (`s:N:"..."`) regenerate correctly. A naive regex would corrupt
 *     the row.
 *
 * Versioned via `OPTION_KEY` in Automator's internal `uap_options` table
 * (via `automator_get_option` / `automator_update_option`). Wired to
 * `admin_init` from `Wp_Integration::setup()`, so the migration only
 * runs on admin requests and never blocks user-facing page loads.
 *
 * @since 7.5
 *
 * @package Uncanny_Automator\Integrations\Wp\Migrations
 */
class WP_Token_Aliases_Migration {

	/**
	 * Migration schema version. Bump when the alias map gains entries
	 * the previous run could not have known about so the migration
	 * re-executes.
	 */
	const VERSION = '7.5.0';

	/**
	 * Internal uap_options key holding the last-completed version.
	 */
	const OPTION_KEY = 'wp_token_aliases_migrated';

	/**
	 * Number of postmeta rows scanned per query.
	 */
	const BATCH_SIZE = 500;

	/**
	 * Post types whose postmeta carries recipe-structure data.
	 *
	 * Order is irrelevant — used as an IN(...) list.
	 *
	 * @var string[]
	 */
	private const MIGRATABLE_POST_TYPES = array(
		'uo-action',
		'uo-action-condition',
		'uo-recipe',
		'uo-trigger',
		'uo-closure',
	);

	/**
	 * Run once per `VERSION`. No-op when the version option already
	 * matches.
	 *
	 * @return void
	 */
	public function maybe_run(): void {

		if ( self::VERSION === automator_get_option( self::OPTION_KEY ) ) {
			return;
		}

		$this->run();

		automator_update_option( self::OPTION_KEY, self::VERSION );
	}

	/**
	 * Subscribe the targeted variant to the shared importer-driven action.
	 *
	 * Called from Wp_Integration::setup(). The contract is documented in
	 * Import_Recipe::import_recipe_json() — fired once per import with the
	 * list of newly-created recipe + child post IDs.
	 *
	 * @return void
	 */
	public static function register_listeners(): void {
		add_action(
			'automator_migrate_recipe_part_meta',
			static function ( $post_ids ) {
				( new self() )->migrate_for_post_ids( (array) $post_ids );
			}
		);
	}

	/**
	 * Targeted postmeta rewrite scoped to specific post IDs.
	 *
	 * Bypasses the version flag — imports need patching regardless of
	 * whether the global pass has completed. Skips wp_options / uap_options
	 * (those are scheduled-action queues that imports don't repopulate).
	 *
	 * @param int[] $post_ids Recipe + child post IDs.
	 *
	 * @return int Number of postmeta rows rewritten.
	 */
	public function migrate_for_post_ids( array $post_ids ): int {

		$post_ids = $this->sanitize_ids( $post_ids );
		if ( empty( $post_ids ) ) {
			return 0;
		}

		$aliases = Wp_Token_Aliases::all();
		if ( empty( $aliases ) ) {
			return 0;
		}

		$updated = $this->migrate_postmeta( $aliases, $post_ids );

		if ( $updated > 0 && function_exists( 'automator_log' ) ) {
			automator_log(
				sprintf(
					'WP token aliases migration (targeted): rewrote %d postmeta rows across %d post IDs',
					$updated,
					count( $post_ids )
				),
				'WP_Token_Aliases_Migration'
			);
		}

		return $updated;
	}

	/**
	 * Coerce the action payload to a clean array of positive integers.
	 *
	 * @param int[] $post_ids
	 *
	 * @return int[]
	 */
	private function sanitize_ids( array $post_ids ): array {
		$out = array();
		foreach ( $post_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$out[ $id ] = $id;
			}
		}
		return array_values( $out );
	}

	/**
	 * Execute the migration against all three data sources.
	 *
	 * @return array{postmeta:int,wp_options:int,uap_options:int}
	 */
	public function run(): array {

		$aliases = Wp_Token_Aliases::all();
		if ( empty( $aliases ) ) {
			return array(
				'postmeta'    => 0,
				'wp_options'  => 0,
				'uap_options' => 0,
			);
		}

		$stats = array(
			'postmeta'    => $this->migrate_postmeta( $aliases ),
			'wp_options'  => $this->migrate_wp_options( $aliases ),
			'uap_options' => $this->migrate_uap_options( $aliases ),
		);

		if ( function_exists( 'automator_log' ) ) {
			automator_log(
				sprintf(
					'WP token aliases migration: postmeta=%d, wp_options=%d, uap_options=%d',
					$stats['postmeta'],
					$stats['wp_options'],
					$stats['uap_options']
				),
				'WP_Token_Aliases_Migration'
			);
		}

		return $stats;
	}

	/**
	 * Two-mode rewrite. Serialized values get the walker; everything else
	 * gets the plain-text regex.
	 *
	 * @param mixed $value   Raw column value (string, or non-string passthrough).
	 * @param array $aliases Legacy => canonical map.
	 *
	 * @return mixed
	 */
	public function rewrite_value( $value, array $aliases ) {

		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( $this->is_php_serialized( $value ) ) {
			return $this->rewrite_serialized( $value, $aliases );
		}

		return $this->rewrite_plain( $value, $aliases );
	}

	/**
	 * Plain-text alias rewrite. Iterates the alias map and replaces each
	 * occurrence of `{{<digits>:<code>:<alias>}}` with the canonical form.
	 *
	 * The regex is intentionally narrow:
	 *
	 *   - `\d+` for trigger_id — recipe trigger IDs are always numeric.
	 *     Outer wrapper tokens (`{{UT:ADVANCED:POSTMETA:...}}`) start
	 *     with letters and don't carry aliases at their third position,
	 *     so they're safely ignored.
	 *   - `[A-Za-z0-9_]+` for the trigger code (handles both canonical
	 *     uppercase codes and rare lowercase legacy ones).
	 *   - The literal alias name (`preg_quote`-d) is required between
	 *     the second `:` and the closing `}}`.
	 *
	 * @param string $text    Raw text.
	 * @param array  $aliases Legacy => canonical map.
	 *
	 * @return string
	 */
	public function rewrite_plain( string $text, array $aliases ): string {

		if ( '' === $text || empty( $aliases ) ) {
			return $text;
		}

		foreach ( $aliases as $old => $new ) {
			$pattern = '/\{\{(\d+):([A-Za-z0-9_]+):' . preg_quote( $old, '/' ) . '\}\}/';
			$text    = preg_replace( $pattern, '{{$1:$2:' . $new . '}}', $text );
		}

		return $text;
	}

	/**
	 * Serialize-aware rewrite. Unserializes, walks every leaf, rewrites
	 * string leaves via `rewrite_plain`, re-serializes.
	 *
	 * If `unserialize()` fails (truncated or otherwise malformed data),
	 * returns the original input rather than corrupting the row.
	 *
	 * @param string $serialized PHP-serialized value.
	 * @param array  $aliases    Legacy => canonical map.
	 *
	 * @return string
	 */
	public function rewrite_serialized( string $serialized, array $aliases ): string {

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		$data = @unserialize( $serialized );

		if ( false === $data && 'b:0;' !== $serialized ) {
			// Malformed serialized data — leave untouched.
			return $serialized;
		}

		$rewritten = $this->walk_and_rewrite( $data, $aliases );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		return serialize( $rewritten );
	}

	/**
	 * Recursively walk arrays + objects, rewriting alias references in
	 * string leaves. Non-string leaves (int/bool/null) pass through
	 * unchanged.
	 *
	 * @param mixed $value   Any unserialized scalar/array/object.
	 * @param array $aliases Legacy => canonical map.
	 *
	 * @return mixed
	 */
	private function walk_and_rewrite( $value, array $aliases ) {

		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $this->walk_and_rewrite( $v, $aliases );
			}
			return $value;
		}

		if ( is_object( $value ) ) {
			foreach ( get_object_vars( $value ) as $k => $v ) {
				$value->$k = $this->walk_and_rewrite( $v, $aliases );
			}
			return $value;
		}

		if ( is_string( $value ) ) {
			// A string leaf may itself be a PHP-serialized blob — e.g. when
			// `add_post_meta()` is handed an already-serialized string it
			// double-serializes the row as `s:N:"a:M:{…}"`. Recursing keeps
			// the length prefix of the outer wrapper intact (we never edit
			// it directly — `serialize()` regenerates it).
			if ( $this->is_php_serialized( $value ) ) {
				return $this->rewrite_serialized( $value, $aliases );
			}
			return $this->rewrite_plain( $value, $aliases );
		}

		return $value;
	}

	/**
	 * Heuristic for "is this a PHP-serialized string?". Delegates to WP's
	 * `is_serialized()` when available (handles every shape — arrays,
	 * strings, objects, primitives) and falls back to a minimal check
	 * otherwise.
	 *
	 * @param string $value Raw column value.
	 *
	 * @return bool
	 */
	private function is_php_serialized( string $value ): bool {

		if ( function_exists( 'is_serialized' ) ) {
			return is_serialized( $value );
		}

		return (bool) preg_match( '/^(a|s|O|i|d|b|N|C):/', $value );
	}

	/**
	 * Batched postmeta migration over recipe-structure post types.
	 *
	 * Selects rows containing any `{{...}}` reference, rewrites each, and
	 * persists changed rows via `update_metadata_by_mid()`. Returns the
	 * count of rows actually modified (rewrites that left the value
	 * unchanged are excluded).
	 *
	 * @param array<string,string> $aliases  Legacy => canonical map.
	 * @param int[]|null           $post_ids Optional scope. When provided,
	 *                                       restricts the rewrite to these
	 *                                       post IDs (no batching needed —
	 *                                       a single import is small).
	 *
	 * @return int
	 */
	public function migrate_postmeta( array $aliases, ?array $post_ids = null ): int {

		global $wpdb;

		$updated = 0;
		$offset  = 0;
		$types   = "'" . implode( "','", self::MIGRATABLE_POST_TYPES ) . "'";

		// Scoped mode: skip batching and the post_type join — the importer
		// only hands us recipe-structure posts to begin with.
		if ( null !== $post_ids ) {
			$ids = array_filter( array_map( 'absint', $post_ids ) );
			if ( empty( $ids ) ) {
				return 0;
			}
			$ids_csv = implode( ',', $ids );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id, post_id, meta_value
					 FROM {$wpdb->postmeta}
					 WHERE meta_value LIKE %s
					   AND post_id IN ({$ids_csv})",
					'%{{%}}%'
				)
			);
			return $this->apply_rewrites( (array) $rows, $aliases );
		}

		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT pm.meta_id, pm.post_id, pm.meta_value
					 FROM {$wpdb->postmeta} pm
					 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					 WHERE p.post_type IN ({$types})
					   AND pm.meta_value LIKE %s
					 ORDER BY pm.meta_id ASC
					 LIMIT %d OFFSET %d",
					'%{{%}}%',
					self::BATCH_SIZE,
					$offset
				)
			);

			if ( empty( $rows ) ) {
				break;
			}

			$updated += $this->apply_rewrites( $rows, $aliases );

			$offset += self::BATCH_SIZE;
		} while ( count( $rows ) === self::BATCH_SIZE );

		return $updated;
	}

	/**
	 * Apply alias rewrites to a batch of postmeta rows.
	 *
	 * Direct $wpdb->update — `update_metadata_by_mid()` runs the value
	 * through `maybe_serialize()` which re-wraps already-serialized strings
	 * (`a:N:{…}` becomes `s:N:"a:M:{…}"`). Direct SQL writes the migrated
	 * value byte-for-byte. The `post_meta` cache (keyed by post_id) is
	 * busted so the rest of the request sees the rewritten value.
	 *
	 * @param array                $rows    Rows with meta_id / post_id / meta_value.
	 * @param array<string,string> $aliases Legacy => canonical map.
	 *
	 * @return int Number of rows actually rewritten.
	 */
	private function apply_rewrites( array $rows, array $aliases ): int {

		global $wpdb;

		$updated = 0;
		foreach ( $rows as $row ) {
			$rewritten = $this->rewrite_value( $row->meta_value, $aliases );
			if ( $rewritten === $row->meta_value ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->postmeta,
				array( 'meta_value' => $rewritten ),
				array( 'meta_id' => (int) $row->meta_id ),
				array( '%s' ),
				array( '%d' )
			);
			wp_cache_delete( (int) $row->post_id, 'post_meta' );
			$updated++;
		}

		return $updated;
	}

	/**
	 * Migrate wp_options scheduled-action payloads (PHP-serialized arrays
	 * carrying `user_id` + `action_data` keys).
	 *
	 * @param array<string,string> $aliases Legacy => canonical map.
	 *
	 * @return int
	 */
	public function migrate_wp_options( array $aliases ): int {

		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			"SELECT option_id, option_name, option_value
			 FROM {$wpdb->options}
			 WHERE option_value LIKE 'a:%user_id%action_data%'
			   AND option_value LIKE '%{{%}}%'"
		);

		$updated = 0;
		foreach ( (array) $rows as $row ) {
			$rewritten = $this->rewrite_value( $row->option_value, $aliases );
			if ( $rewritten !== $row->option_value ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->options,
					array( 'option_value' => $rewritten ),
					array( 'option_id' => (int) $row->option_id ),
					array( '%s' ),
					array( '%d' )
				);
				// Bust the options cache so subsequent get_option() returns
				// the migrated value, not the in-memory copy from this request.
				wp_cache_delete( $row->option_name, 'options' );
				$updated++;
			}
		}

		// Also flush the alloptions bucket — autoloaded options are cached
		// as a single serialized blob, so a per-key delete isn't enough.
		wp_cache_delete( 'alloptions', 'options' );

		return $updated;
	}

	/**
	 * Migrate Automator's internal uap_options scheduled-action payloads.
	 *
	 * @param array<string,string> $aliases Legacy => canonical map.
	 *
	 * @return int
	 */
	public function migrate_uap_options( array $aliases ): int {

		global $wpdb;

		$table = $wpdb->prefix . 'uap_options';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT option_id, option_value
			 FROM {$table}
			 WHERE option_value LIKE 'a:%user_id%action_data%'
			   AND option_value LIKE '%{{%}}%'"
		);

		$updated = 0;
		foreach ( (array) $rows as $row ) {
			$rewritten = $this->rewrite_value( $row->option_value, $aliases );
			if ( $rewritten !== $row->option_value ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array( 'option_value' => $rewritten ),
					array( 'option_id' => (int) $row->option_id ),
					array( '%s' ),
					array( '%d' )
				);
				$updated++;
			}
		}

		return $updated;
	}
}

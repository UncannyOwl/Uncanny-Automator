<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Migration;

use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * The atomic loop fold-in upgrade migration: one site-wide pass folds every
 * legacy `uo-loop` / `uo-loop-filter` row into unified block storage via the
 * per-recipe {@see Loop_To_Block_Migrator}.
 *
 * Runs once per site (option-gated, so multisite folds per-site on each
 * site's own first request) and is safe to re-enter: discovery only matches
 * still-legacy rows and the per-recipe migrator is idempotent, so a crashed
 * or concurrent run simply resumes/no-ops. The pass is recorded as done only
 * when every recipe folded; a recipe that could not keeps its legacy rows and
 * the pass is tried again after a wait that grows with each failed pass (see
 * RETRY_WAITS), so a fold that keeps failing does not run on every request.
 * A database upgrade forgets the record and the wait, so rows written while
 * the site ran 7.x again are folded on the next `init`. The whole pass is
 * synchronous — WooCommerce-style required-to-function, sized by the reality
 * that no site carries thousands of loop recipes; a loop-less site pays two
 * indexed post_type lookups once.
 */
final class Loop_Fold_Bulk_Migration {

	/**
	 * Completion flag option. Per-site (automator options are site-scoped).
	 *
	 * @var string
	 */
	const COMPLETED_OPTION = 'automator_loop_fold_migration_completed';

	/**
	 * Option holding what a pass that did not finish left behind: the plugin version that wrote
	 * it, how many passes have failed, when the next one may run and which recipes failed.
	 * Per-site, and absent once the pass completes.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const RETRY_OPTION = 'automator_loop_fold_retry';

	/**
	 * Seconds to wait after the first, second, third and later failed pass. The last wait holds
	 * for every pass after: a recipe whose loop has not folded is missing its loop, so the pass is
	 * never given up on.
	 *
	 * @since 8.0.0
	 *
	 * @var int[]
	 */
	const RETRY_WAITS = array( 60, 300, 900, 3600 );

	/**
	 * Per-recipe fold migrator.
	 *
	 * @var Loop_To_Block_Migrator
	 */
	private Loop_To_Block_Migrator $migrator;

	/**
	 * Lazy-default constructor injection (Rule 9c).
	 *
	 * @param Loop_To_Block_Migrator|null $migrator Per-recipe migrator.
	 */
	public function __construct( ?Loop_To_Block_Migrator $migrator = null ) {
		$this->migrator = $migrator ?? new Loop_To_Block_Migrator(
			Database::get_block_store(),
			Database::get_block_modifier_store()
		);
	}

	/**
	 * Forget that the pass completed, and any wait a failed pass left, so the next `init` runs it again.
	 *
	 * The database upgrade calls this: a site that went back to 7.x and upgraded again holds
	 * loops 7.x wrote after the first pass, and the new files may have fixed what failed.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public static function rearm(): void {
		automator_delete_option( self::COMPLETED_OPTION );
		automator_delete_option( self::RETRY_OPTION );
	}

	/**
	 * The recipes the last failed pass could not fold, whose loops are not in the recipe until a pass folds them.
	 *
	 * @since 8.0.0
	 *
	 * @return int[]
	 */
	public static function failed_recipe_ids(): array {
		return self::retry_state()['failed'];
	}

	/**
	 * Run the migration unless it has already completed on this site, or a failed pass is still waiting out its backoff.
	 *
	 * @return void
	 */
	public function maybe_run(): void {
		if ( automator_get_option( self::COMPLETED_OPTION, false ) ) {
			return;
		}

		if ( time() < self::retry_state()['retry_after'] ) {
			return;
		}

		$this->run();
	}

	/**
	 * Fold every recipe that still owns legacy loop storage, then record
	 * completion when every one of them folded.
	 *
	 * A recipe whose fold throws is logged and left for the next pass; the
	 * others still fold. The wait before that pass is recorded before the first
	 * fold starts, so a request that dies mid-fold (a timeout, a fatal) still
	 * holds the next requests off, and again when the pass ends, so the wait
	 * counts from the end of a long pass.
	 *
	 * @return array<int,int[]> Map of recipe_id => folded post ids.
	 */
	public function run(): array {
		$migrated = array();
		$failed   = array();
		$state    = self::retry_state();
		$recipes  = $this->find_recipe_ids_with_legacy_rows();
		if ( null === $recipes ) {
			self::record_failed_pass( $state['attempts'] + 1, $state['failed'] );

			return array();
		}

		if ( array() === $recipes ) {
			$this->record_completion();

			return array();
		}

		self::record_failed_pass( $state['attempts'] + 1, $state['failed'] );

		foreach ( $recipes as $recipe_id ) {
			try {
				$folded = $this->migrator->migrate( (int) $recipe_id );
			} catch ( \Throwable $e ) {
				$log_key = 'automator_loop_fold_log_' . (int) $recipe_id;
				if ( false === get_transient( $log_key ) ) {
					automator_log( sprintf( 'Loop fold failed for recipe %d; it is tried again after a wait that grows to an hour: %s', (int) $recipe_id, $e->getMessage() ), 'Loop fold migration', true, 'loop-fold-migration' );
					set_transient( $log_key, true, DAY_IN_SECONDS );
				}
				$failed[] = (int) $recipe_id;
				continue;
			}
			if ( array() !== $folded ) {
				$migrated[ (int) $recipe_id ] = $folded;
			}
		}

		// The store-level adopt flips bypass the CRUD-side popularity
		// invalidation, so one explicit refresh re-aggregates the folded
		// scopes (shutdown-dirty: stored counts keep serving meanwhile).
		if ( array() !== $migrated ) {
			automator_refresh_entity_popularity_cache();
		}

		if ( array() === $failed ) {
			$this->record_completion();
		} else {
			self::record_failed_pass( $state['attempts'] + 1, $failed );
		}

		return $migrated;
	}

	/**
	 * Record the pass as done and forget the wait: the wait goes first, so a request that stops
	 * between the two writes finds neither and runs a pass that has nothing left to fold.
	 *
	 * @return void
	 */
	private function record_completion(): void {
		automator_delete_option( self::RETRY_OPTION );
		automator_update_option( self::COMPLETED_OPTION, gmdate( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Record a pass that did not finish, and when the next one may run.
	 *
	 * @param int   $attempts How many passes have failed, this one included.
	 * @param int[] $failed   The recipes that could not fold, empty when the pass could not tell.
	 *
	 * @return void
	 */
	private static function record_failed_pass( int $attempts, array $failed ): void {
		$wait = self::RETRY_WAITS[ min( $attempts, count( self::RETRY_WAITS ) ) - 1 ];

		automator_update_option(
			self::RETRY_OPTION,
			array(
				'version'     => AUTOMATOR_PLUGIN_VERSION,
				'attempts'    => $attempts,
				'retry_after' => time() + $wait,
				'failed'      => array_values( $failed ),
			),
			false
		);
	}

	/**
	 * What a failed pass left, as the plugin version running now reads it.
	 *
	 * A record another version wrote is ignored: that version may have fixed what failed, and the
	 * pass should not wait on a verdict it passed.
	 *
	 * @return array{attempts: int, retry_after: int, failed: int[]}
	 */
	private static function retry_state(): array {
		$state = automator_get_option( self::RETRY_OPTION, array() );

		if ( ! is_array( $state ) || AUTOMATOR_PLUGIN_VERSION !== ( $state['version'] ?? '' ) ) {
			return array(
				'attempts'    => 0,
				'retry_after' => 0,
				'failed'      => array(),
			);
		}

		return array(
			'attempts'    => (int) ( $state['attempts'] ?? 0 ),
			'retry_after' => (int) ( $state['retry_after'] ?? 0 ),
			'failed'      => array_map( 'intval', (array) ( $state['failed'] ?? array() ) ),
		);
	}

	/**
	 * Find every recipe that still owns a legacy row, in either shape:
	 *
	 *  - a `uo-loop` parented directly to the recipe (legacy storage could
	 *    not nest loops, so direct parentage is exhaustive), or
	 *  - a `uo-loop-filter` whose parent loop — folded or not — sits under
	 *    the recipe (the mid-state straggler the per-recipe migrator's
	 *    order-independent Pass 2 exists for).
	 *
	 * Parents that are not `uo-recipe` posts are excluded: orphaned rows
	 * have no owning recipe to stamp and stay untouched. Direct SQL is the
	 * established posture for `Migration/` classes (short-lived legacy
	 * readers).
	 *
	 * @return int[]|null Recipe post ids, or null when discovery failed.
	 */
	private function find_recipe_ids_with_legacy_rows(): ?array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			"SELECT DISTINCT l.post_parent
			 FROM {$wpdb->posts} l
			 INNER JOIN {$wpdb->posts} r ON l.post_parent = r.ID AND r.post_type = 'uo-recipe'
			 WHERE l.post_type = 'uo-loop'
			 UNION
			 SELECT DISTINCT p.post_parent
			 FROM {$wpdb->posts} f
			 INNER JOIN {$wpdb->posts} p ON f.post_parent = p.ID
			 INNER JOIN {$wpdb->posts} r ON p.post_parent = r.ID AND r.post_type = 'uo-recipe'
			 WHERE f.post_type = 'uo-loop-filter'"
		);
		// phpcs:enable
		if ( ! is_array( $ids ) || '' !== $wpdb->last_error ) {
			return null;
		}

		return array_map( 'intval', $ids );
	}
}

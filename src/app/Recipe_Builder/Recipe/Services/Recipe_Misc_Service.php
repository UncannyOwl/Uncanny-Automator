<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;

/**
 * Projection-time helper for `total_runs`, the recipe's created/modified
 * dates, and the Pro-writable recipe-state flags.
 *
 * Reads runtime state — the execution-log table and post fields. Used by the
 * wire serializer at projection time, NOT by the cache or the Recipe domain.
 *
 * **State flags** (`can_edit`, `has_run_now`, `latest_recipe_log_id`,
 * `latest_run_number`) come from `state_flags()`, which fires the focused
 * `automator_recipe_state_flags` filter with safe free defaults; a Pro
 * integration sets the live values. The serializer lifts `can_edit` to the
 * top level and routes the rest into `miscellaneous`.
 *
 * Lazy-default DI per Rule 9c: constructor takes optional `Execution_Log_Store`,
 * defaulting to `Database::get_execution_log_store()` so production callers
 * can `Recipe_Misc_Service::instance()` without DI wiring.
 */
final class Recipe_Misc_Service {

	/**
	 * Shared instance handed out by `instance()`.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Reads run history, which is where the miscellaneous values are derived from.
	 *
	 * @var Execution_Log_Store
	 */
	private $log_store;

	/**
	 * Lazy-default DI per Rule 9c: production resolves the execution-log store
	 * from the database factory; tests may inject a fake.
	 *
	 * @param Execution_Log_Store|null $log_store
	 */
	public function __construct( ?Execution_Log_Store $log_store = null ) {
		$this->log_store = $log_store ?? Database::get_execution_log_store();
	}

	/**
	 * Shared singleton accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * The Pro-writable recipe-state flags, defaulting to the free baseline.
	 *
	 * One focused filter — `automator_recipe_state_flags` — lets a Pro
	 * integration (run-now / import / a future loop trigger) flip the lock and
	 * supply the latest-run pointers. The wire serializer lifts `can_edit` to
	 * the top level and routes the remaining three into `miscellaneous`.
	 *
	 * Handoff contract (Run Now / Pro stream):
	 *   apply_filters( 'automator_recipe_state_flags', array $flags, int $recipe_id )
	 *   $flags = {
	 *     'can_edit'             => bool   (default true; false while a loop runs),
	 *     'has_run_now'          => bool   (default false),
	 *     'latest_recipe_log_id' => ?int   (default null; newest run's recipe-log id),
	 *     'latest_run_number'    => ?int   (default null; that run's run number),
	 *   }
	 *
	 * @param int $recipe_id
	 * @return array{can_edit:bool, has_run_now:bool, latest_recipe_log_id:int|null, latest_run_number:int|null}
	 */
	public function state_flags( int $recipe_id ): array {
		$defaults = array(
			'can_edit'             => true,
			'has_run_now'          => false,
			'latest_recipe_log_id' => null,
			'latest_run_number'    => null,
		);

		$flags = Dispatcher::filter( 'automator_recipe_state_flags', $defaults, $recipe_id );
		if ( ! is_array( $flags ) ) {
			return $defaults;
		}

		return array(
			'can_edit'             => (bool) ( $flags['can_edit'] ?? true ),
			'has_run_now'          => (bool) ( $flags['has_run_now'] ?? false ),
			'latest_recipe_log_id' => isset( $flags['latest_recipe_log_id'] ) ? (int) $flags['latest_recipe_log_id'] : null,
			'latest_run_number'    => isset( $flags['latest_run_number'] ) ? (int) $flags['latest_run_number'] : null,
		);
	}

	/**
	 * Total number of times this recipe has run.
	 *
	 * Reads from `uap_recipe_log` via the existing log store. Returns 0 when
	 * the recipe has no log rows.
	 */
	public function total_runs( int $recipe_id ): int {
		return $this->log_store->get_max_recipe_log_number( $recipe_id );
	}

	/**
	 * Display string for when the recipe was created — `get_the_date` output.
	 */
	public function created_on_date( int $recipe_id ): string {
		$date = get_the_date( '', $recipe_id );
		return false === $date ? '' : (string) $date;
	}

	/**
	 * Unix timestamp of the recipe's last modification.
	 */
	public function last_modified_date_timestamp( int $recipe_id ): int {
		$ts = get_post_modified_time( 'U', false, $recipe_id );
		return false === $ts ? 0 : (int) $ts;
	}
}

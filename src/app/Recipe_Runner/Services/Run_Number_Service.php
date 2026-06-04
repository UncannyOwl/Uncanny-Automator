<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\Automator_Status;

/**
 * Centralized run number lookups for the recipe runner.
 *
 * Replaces scattered calls to \Automator()->get->next_run_number()
 * with a single, cacheable service.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.2
 */
class Run_Number_Service {

	/**
	 * Statuses to exclude from run number lookups.
	 *
	 * These terminal statuses indicate a completed-but-errored, did-nothing,
	 * or awaiting run — they should not count toward the current run number.
	 */
	private const EXCLUDED_STATUSES = array(
		Automator_Status::COMPLETED_WITH_ERRORS,
		Automator_Status::DID_NOTHING,
		Automator_Status::COMPLETED_AWAITING,
	);

	/**
	 * Per-request cache keyed by "recipe_id:user_id".
	 *
	 * @var array<string, int>
	 */
	private array $cache = array();

	/**
	 * @var Execution_Log_Store
	 */
	private Execution_Log_Store $log_store;

	/**
	 * @param Execution_Log_Store|null $log_store Optional log store.
	 */
	public function __construct( ?Execution_Log_Store $log_store = null ) {
		$this->log_store = $log_store ?? Database::get_execution_log_store();
	}

	/**
	 * Get the current (max) run number for a recipe+user pair.
	 *
	 * Returns 1 when user_id is 0 (anonymous / Everyone recipes).
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return int
	 */
	public function get_current( int $recipe_id, int $user_id ): int {

		if ( 0 === $user_id ) {
			return 1;
		}

		$key = "{$recipe_id}:{$user_id}";

		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$run_number = $this->log_store->get_max_run_number( $recipe_id, $user_id, self::EXCLUDED_STATUSES );

		$result = $run_number ?? 1;

		$this->cache[ $key ] = $result;

		return $result;
	}

	/**
	 * Get the run number to use for the next inserted recipe log row.
	 *
	 * Mirrors the historical Automator()->get->next_run_number(..., false)
	 * semantics used at insert time: 1 for the first row (MAX is null) or
	 * for anonymous users, MAX + 1 otherwise. Reads MAX directly so the
	 * value is not affected by the get_current() default and does not
	 * pollute the get_current() cache before the insert lands.
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return int
	 */
	public function get_next( int $recipe_id, int $user_id ): int {

		if ( 0 === $user_id ) {
			return 1;
		}

		$max = $this->log_store->get_max_run_number( $recipe_id, $user_id, self::EXCLUDED_STATUSES );

		return null === $max ? 1 : $max + 1;
	}

	/**
	 * Prime the per-request cache with a known-current value.
	 *
	 * Call after inserting a recipe log row so subsequent get_current()
	 * lookups in the same request reflect the just-written run_number
	 * without re-reading MAX or returning a stale cached entry.
	 *
	 * No-op for anonymous users since get_current() short-circuits there.
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 * @param int $current   The run_number that was just written.
	 *
	 * @return void
	 */
	public function prime( int $recipe_id, int $user_id, int $current ): void {

		if ( 0 === $user_id ) {
			return;
		}

		$this->cache[ "{$recipe_id}:{$user_id}" ] = $current;
	}

	/**
	 * Clear the instance cache (useful in tests and between recipe runs).
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cache = array();
	}
}

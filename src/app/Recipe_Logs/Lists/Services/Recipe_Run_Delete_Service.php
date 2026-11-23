<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Services;

use Uncanny_Automator\App\Bridge\Automator_Log_Purge_Bridge;
use Uncanny_Automator\App\Bridge\Log_Purge_Bridge;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Log_List_Store;

/**
 * Deletes recipe runs from the Logs page: `DELETE logs/recipe-runs`.
 *
 * Each run goes the way the legacy Logs page deleted one:
 * `automator_before_recipe_log_deleted` (Pro cancels the run's scheduled
 * actions and purges its loops on it), the four legacy purges, then
 * `automator_recipe_log_deleted`. A run that no longer exists, or that the
 * current user may not delete, is left out rather than failing the rest.
 *
 * @since 8.1.0
 */
final class Recipe_Run_Delete_Service {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/** @var Log_List_Store */
	private $store;

	/** @var Log_Purge_Bridge */
	private $purge;

	/** @var Recipe_Run_Delete_Policy */
	private $policy;

	/**
	 * @param Log_List_Store|null           $store  The list store.
	 * @param Log_Purge_Bridge|null         $purge  The legacy purge.
	 * @param Recipe_Run_Delete_Policy|null $policy Who may delete runs.
	 */
	private function __construct( ?Log_List_Store $store = null, ?Log_Purge_Bridge $purge = null, ?Recipe_Run_Delete_Policy $policy = null ) {
		$this->store  = $store ?? Database::get_log_list_store();
		$this->purge  = $purge ?? new Automator_Log_Purge_Bridge();
		$this->policy = $policy ?? new Recipe_Run_Delete_Policy();
	}

	/**
	 * The shared instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * An instance with its dependencies given, for tests.
	 *
	 * @param Log_List_Store|null           $store  The list store.
	 * @param Log_Purge_Bridge|null         $purge  The legacy purge.
	 * @param Recipe_Run_Delete_Policy|null $policy Who may delete runs.
	 *
	 * @return self
	 */
	public static function create_with_dependencies( ?Log_List_Store $store = null, ?Log_Purge_Bridge $purge = null, ?Recipe_Run_Delete_Policy $policy = null ): self {
		return new self( $store, $purge, $policy );
	}

	/**
	 * Deletes the runs the current user may delete.
	 *
	 * @param int[] $recipe_log_ids Run ids.
	 *
	 * @return int[] The runs that were deleted, in the order given.
	 */
	public function delete( array $recipe_log_ids ): array {
		$ids     = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $recipe_log_ids ),
					static function ( int $id ): bool {
						return $id > 0;
					}
				)
			)
		);
		$runs    = $this->store->get_runs( $ids );
		$deleted = array();

		foreach ( $ids as $recipe_log_id ) {
			if ( ! isset( $runs[ $recipe_log_id ] ) || ! $this->policy->can_delete( $runs[ $recipe_log_id ]['recipe_id'] ) ) {
				continue;
			}

			$recipe_id  = $runs[ $recipe_log_id ]['recipe_id'];
			$run_number = $runs[ $recipe_log_id ]['run_number'];

			Dispatcher::action( 'automator_before_recipe_log_deleted', $recipe_id, $recipe_log_id, $run_number );

			$this->purge->purge_run( $recipe_id, $recipe_log_id );

			Dispatcher::action( 'automator_recipe_log_deleted', $recipe_id, $recipe_log_id, $run_number );

			$deleted[] = $recipe_log_id;
		}

		return $deleted;
	}
}

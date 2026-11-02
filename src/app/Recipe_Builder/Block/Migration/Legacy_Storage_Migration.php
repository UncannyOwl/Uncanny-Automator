<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Block\Migration;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\Utilities;

/**
 * Migrates a legacy recipe's filter and delay storage into blocks once, at the tree request.
 * Gated on the recipe vintage, serialised per recipe by a MySQL advisory lock, and the
 * vintage is bumped only after both migrators complete, so a failed pass retries next time.
 * Each pass first deletes the blocks an interrupted one left unfinished, so a request that ended
 * in the middle of a create leaves no block the tree cannot read.
 * Loops are the init-time bulk fold's job and are not touched here.
 */
class Legacy_Storage_Migration {

	/**
	 * Recipes at this vintage or later already store filters and delays as blocks.
	 *
	 * @var string
	 */
	public const BLOCK_STORAGE_SINCE = '8.0';

	/**
	 * Seconds to wait for another request's pass on the same recipe.
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT = 10;

	/**
	 * Vintage reads and the bump.
	 *
	 * @var Recipe_Store
	 */
	private Recipe_Store $recipe_store;

	/**
	 * Owner of the advisory-lock primitive.
	 *
	 * @var Execution_Log_Store
	 */
	private Execution_Log_Store $log_store;

	/**
	 * Legacy `actions_conditions` to filter blocks.
	 *
	 * @var Action_Conditions_Meta_Migrator
	 */
	private Action_Conditions_Meta_Migrator $conditions_migrator;

	/**
	 * Legacy `async_*` action meta to delay blocks.
	 *
	 * @var Delay_Schedule_Meta_Migrator
	 */
	private Delay_Schedule_Meta_Migrator $delay_migrator;

	/**
	 * Deletes the blocks an interrupted pass left unfinished.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * Wire the migration to its stores and migrators.
	 *
	 * @param Recipe_Store|null                    $recipe_store        Recipe store; defaults to the shared one.
	 * @param Execution_Log_Store|null             $log_store           Log store; defaults to the shared one.
	 * @param Action_Conditions_Meta_Migrator|null $conditions_migrator Conditions migrator; defaults to a fresh one.
	 * @param Delay_Schedule_Meta_Migrator|null    $delay_migrator      Delay migrator; defaults to a fresh one.
	 * @param Block_Store|null                     $block_store         Block store; defaults to the shared one.
	 */
	public function __construct(
		?Recipe_Store $recipe_store = null,
		?Execution_Log_Store $log_store = null,
		?Action_Conditions_Meta_Migrator $conditions_migrator = null,
		?Delay_Schedule_Meta_Migrator $delay_migrator = null,
		?Block_Store $block_store = null
	) {
		$this->recipe_store        = $recipe_store ?? Database::get_recipe_store();
		$this->log_store           = $log_store ?? Database::get_execution_log_store();
		$this->conditions_migrator = $conditions_migrator ?? new Action_Conditions_Meta_Migrator( Database::get_block_store(), Database::get_block_modifier_store() );
		$this->delay_migrator      = $delay_migrator ?? new Delay_Schedule_Meta_Migrator( Database::get_block_store() );
		$this->block_store         = $block_store ?? Database::get_block_store();
	}

	/**
	 * Migrate the recipe if its vintage predates block storage.
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return void
	 * @throws Legacy_Storage_Migration_Exception When the lock is not acquired or a migrator throws.
	 */
	public function ensure_migrated( int $recipe_id ): void {
		if ( $recipe_id <= 0 || null === $this->recipe_store->get_wp_post( $recipe_id ) ) {
			return;
		}

		if ( $this->at_block_vintage( $this->recipe_store->get_version( $recipe_id ) ) ) {
			return;
		}

		$lock = 'automator_recipe_migrate_' . $recipe_id;

		if ( ! $this->log_store->acquire_lock( $lock, self::LOCK_TIMEOUT ) ) {
			throw new Legacy_Storage_Migration_Exception(
				esc_html( sprintf( 'Recipe %d: the legacy storage migration lock was not acquired within %d seconds.', $recipe_id, self::LOCK_TIMEOUT ) )
			);
		}

		try {
			// Another request may have finished the pass while we waited.
			if ( $this->at_block_vintage( $this->recipe_store->get_version_uncached( $recipe_id ) ) ) {
				return;
			}

			$this->block_store->delete_unfinished( $recipe_id );
			$this->conditions_migrator->maybe_migrate( $recipe_id );
			$this->delay_migrator->maybe_migrate( $recipe_id );
			$this->recipe_store->set_version( $recipe_id, Utilities::automator_get_version() );
		} catch ( \Throwable $e ) {
			throw new Legacy_Storage_Migration_Exception(
				esc_html( sprintf( 'Recipe %d: the legacy storage migration failed: %s', $recipe_id, $e->getMessage() ) )
			);
		} finally {
			$this->log_store->release_lock( $lock );
		}
	}

	/**
	 * Whether a vintage string is at or past block storage.
	 *
	 * @param string $vintage Stored recipe version, '' when none.
	 *
	 * @return bool
	 */
	private function at_block_vintage( string $vintage ): bool {
		return version_compare( $vintage, self::BLOCK_STORAGE_SINCE, '>=' );
	}
}

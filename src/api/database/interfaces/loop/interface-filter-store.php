<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Interfaces\Loop;

use Uncanny_Automator\Api\Components\Loop\Filter\Filter;

/**
 * Filter Store Interface.
 *
 * Contract for persisting Filter entities within the Loop bounded context.
 * Database-agnostic interface with WordPress implementation.
 *
 * @since 7.0.0
 */
interface Filter_Store {

	/**
	 * Persist a Filter (insert or update).
	 *
	 * @param int    $loop_id    Parent loop ID.
	 * @param Filter $filter     Filter to save.
	 * @param int    $menu_order Optional menu order for sorting.
	 * @return Filter The saved Filter with ID and all persisted values.
	 */
	public function save( int $loop_id, Filter $filter, int $menu_order = 0 ): Filter;

	/**
	 * Load a Filter by its ID.
	 *
	 * @param int $id Filter ID.
	 * @return Filter|null Filter instance or null if not found.
	 */
	public function get( int $id ): ?Filter;

	/**
	 * Delete a Filter.
	 *
	 * @param Filter $filter Filter to delete.
	 * @return void
	 * @throws \Exception If delete fails or filter is not persisted.
	 */
	public function delete( Filter $filter ): void;

	/**
	 * Delete a Filter by ID.
	 *
	 * @param int $id Filter ID.
	 * @return void
	 */
	public function delete_by_id( int $id ): void;

	/**
	 * Get all Filters for a Loop.
	 *
	 * @param int $loop_id Loop ID.
	 * @return Filter[] Array of Filter instances.
	 */
	public function get_loop_filters( int $loop_id ): array;

	/**
	 * Get Filter data arrays for a Loop (for hydration).
	 *
	 * @param int $loop_id Loop ID.
	 * @return array Array of filter data arrays.
	 */
	public function get_loop_filter_data( int $loop_id ): array;

	/**
	 * Delete all Filters for a Loop.
	 *
	 * @param int $loop_id Loop ID.
	 * @return void
	 */
	public function delete_loop_filters( int $loop_id ): void;

	/**
	 * Sync Filters for a Loop (delete removed, update existing, create new).
	 *
	 * @param int      $loop_id Loop ID.
	 * @param Filter[] $filters Array of Filter entities to sync.
	 * @return void
	 */
	public function sync( int $loop_id, array $filters ): void;

	/**
	 * Check if a filter exists.
	 *
	 * @param int $id Filter ID.
	 * @return bool True if exists.
	 */
	public function exists( int $id ): bool;
}

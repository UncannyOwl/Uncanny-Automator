<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema;

use Uncanny_Automator\App\Infrastructure\Database\Stores\Recipe_Schema_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration;

/**
 * The one read seam for the canonical recipe tree.
 *
 * A hit returns the persisted tree. On a miss the tree is rebuilt from
 * storage and persisted only if the recipe's write generation is unchanged
 * across the walk, and dropped again if a write landed during the persist
 * itself, so a rebuild that raced a write never leaves a stale tree behind;
 * the caller still receives the fresh tree either way.
 *
 * Every tree consumer (builder wire, run-log, runner, census folds) reads here, and a legacy
 * recipe migrates to block storage on its first read, before the cache is consulted, so the
 * generation noted for the rebuild already includes the migration's own writes.
 * The builder is anything with `build( int ): ?array`; production passes
 * Recipe_Tree_Builder, tests pass doubles.
 */
final class Recipe_Schema_Reader {

	/**
	 * Shared instance handed out by `instance()`.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Where trees are cached and where the write generation lives.
	 *
	 * @var Recipe_Schema_Store
	 */
	private Recipe_Schema_Store $store;

	/**
	 * Storage walker with `build( int ): ?array`.
	 *
	 * @var object
	 */
	private object $builder;

	/**
	 * One-shot legacy filter and delay migration, gated on the recipe vintage.
	 *
	 * @var Legacy_Storage_Migration
	 */
	private Legacy_Storage_Migration $migration;

	/**
	 * Wire the seam to the cache it reads, the walker it rebuilds through and the migration it runs first.
	 *
	 * @param Recipe_Schema_Store|null      $store     Schema store; defaults to the shared instance.
	 * @param object|null                   $builder   Anything with `build( int ): ?array`; defaults to Recipe_Tree_Builder.
	 * @param Legacy_Storage_Migration|null $migration Legacy migration; defaults to a production-wired one.
	 */
	public function __construct( ?Recipe_Schema_Store $store = null, ?object $builder = null, ?Legacy_Storage_Migration $migration = null ) {
		$this->store     = $store ?? Recipe_Schema_Store::instance();
		$this->builder   = $builder ?? Recipe_Tree_Builder::instance();
		$this->migration = $migration ?? new Legacy_Storage_Migration();
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
	 * Return the cached tree, or rebuild it from storage, migrating legacy storage first.
	 *
	 * @param int $recipe_id Recipe post id.
	 * @return array<string, mixed>|null The tree, or null when the id is not a recipe.
	 * @throws \Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception When the legacy migration fails; the caller fails closed.
	 */
	public function get_or_build( int $recipe_id ): ?array {
		$this->migration->ensure_migrated( $recipe_id );

		$tree = $this->store->get( $recipe_id );
		if ( null !== $tree ) {
			return $tree;
		}

		$generation = $this->store->generation( $recipe_id );
		$tree       = $this->builder->build( $recipe_id );
		if ( null === $tree ) {
			return null;
		}

		if ( $this->store->generation( $recipe_id ) === $generation ) {
			$payload = $this->store->save( $recipe_id, $tree );

			// A write landed during the save: drop our tree, and only ours.
			if ( $this->store->generation( $recipe_id ) !== $generation ) {
				$this->store->delete_own( $recipe_id, $payload );
			}
		}

		return $tree;
	}
}

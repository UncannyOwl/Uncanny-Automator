<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Portability;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Action_Conditions_Meta_Migrator;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Delay_Schedule_Meta_Migrator;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Tree_Builder;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Services\User_Selector_Service;
use WP_Error;

/**
 * Recipe → versioned portable document.
 *
 * The portable document IS the canonical tree plus a small
 * envelope: `{ schema_version, exported_from, exported_at, user_selector,
 * recipe }`. Node ids intentionally survive the export — the import-side
 * Pass-2 reference rewrite keys on them. Stripped as runtime-only: the
 * root's site-local `recipe_id` and the lossy `run_as` projection (the FULL
 * user selector rides the envelope instead, mirroring the duplication
 * service's service-to-service selector copy).
 *
 * The same migrate-before-read guarantee as `Recipe_Duplication_Service`
 * applies: `Recipe_Tree_Builder::build()` is a pure read, so a source whose
 * conditions/delays still live in legacy meta would export an incomplete
 * tree. Both migrators run explicitly and idempotently first.
 */
final class Recipe_Exporter {

	/**
	 * Portable-document schema version this exporter emits and the importer
	 * accepts. Bump when the tree contract changes incompatibly.
	 *
	 * @var int
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Projects recipe storage into the canonical tree (pure read).
	 *
	 * @var Recipe_Tree_Builder
	 */
	private Recipe_Tree_Builder $tree_builder;

	/**
	 * Supplies the full-fidelity user selector for the envelope (the tree's
	 * `run_as` projection is intentionally lossy).
	 *
	 * @var User_Selector_Service
	 */
	private User_Selector_Service $user_selector;

	/**
	 * Migrates legacy `actions_conditions` post-meta to `uo-block-modifier`
	 * rows before the tree read (part of the migration guarantee).
	 *
	 * @var Action_Conditions_Meta_Migrator
	 */
	private Action_Conditions_Meta_Migrator $conditions_migrator;

	/**
	 * Migrates legacy `async_*` action meta to delay/schedule blocks before
	 * the tree read (part of the migration guarantee).
	 *
	 * @var Delay_Schedule_Meta_Migrator
	 */
	private Delay_Schedule_Meta_Migrator $delay_migrator;

	/**
	 * Lazy-default constructor injection (Rule 9c): tests inject fakes,
	 * production gets the singletons / `new` defaults.
	 *
	 * @param Recipe_Tree_Builder|null             $tree_builder
	 * @param User_Selector_Service|null           $user_selector
	 * @param Action_Conditions_Meta_Migrator|null $conditions_migrator
	 * @param Delay_Schedule_Meta_Migrator|null    $delay_migrator
	 */
	public function __construct(
		?Recipe_Tree_Builder $tree_builder = null,
		?User_Selector_Service $user_selector = null,
		?Action_Conditions_Meta_Migrator $conditions_migrator = null,
		?Delay_Schedule_Meta_Migrator $delay_migrator = null
	) {
		$this->tree_builder  = $tree_builder ?? Recipe_Tree_Builder::instance();
		$this->user_selector = $user_selector ?? User_Selector_Service::instance();

		if ( null === $conditions_migrator || null === $delay_migrator ) {
			$block_store    = Database::get_block_store();
			$modifier_store = Database::get_block_modifier_store();

			$conditions_migrator = $conditions_migrator
				?? new Action_Conditions_Meta_Migrator( $block_store, $modifier_store );
			$delay_migrator      = $delay_migrator
				?? new Delay_Schedule_Meta_Migrator( $block_store );
		}

		$this->conditions_migrator = $conditions_migrator;
		$this->delay_migrator      = $delay_migrator;
	}

	/**
	 * Export one recipe as a portable document.
	 *
	 * @param int $recipe_id Recipe to export.
	 * @return array<string,mixed>|WP_Error The portable document, or the failure.
	 */
	public function export( int $recipe_id ) {
		// 1. Migration guarantee: canonicalize the source before the pure read.
		$this->conditions_migrator->maybe_migrate( $recipe_id );
		$this->delay_migrator->maybe_migrate( $recipe_id );

		// 2. Build the canonical tree.
		$tree = $this->tree_builder->build( $recipe_id );
		if ( null === $tree ) {
			return new WP_Error(
				'export_recipe_not_found',
				/* translators: %d Recipe ID. */
				sprintf( esc_html_x( 'Recipe not found with ID: %d', 'Recipe export error', 'uncanny-automator' ), $recipe_id )
			);
		}

		// 3. Runtime-strip: only the site-local root id and the lossy run_as
		//    projection are dropped; node ids stay for the import-side rewrite.
		unset( $tree['recipe_id'], $tree['run_as'] );

		// 4. Full-fidelity user selector for the envelope.
		$selector = $this->user_selector->get_user_selector( $recipe_id );
		$selector = is_array( $selector ) && is_array( $selector['data'] ?? null ) ? $selector['data'] : null;
		if ( is_array( $selector ) ) {
			unset( $selector['id'], $selector['recipe_id'] );
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'exported_from'  => get_site_url(),
			'exported_at'    => gmdate( 'c' ),
			'user_selector'  => $selector,
			'recipe'         => $tree,
		);
	}
}

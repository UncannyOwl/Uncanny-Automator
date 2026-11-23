<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Portability;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Post_Content_Edit_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Legacy_Meta_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Legacy_Meta_Store;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Action_Conditions_Meta_Migrator;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Delay_Schedule_Meta_Migrator;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Tree_Builder;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects\Materialization_Result;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Reference_Rewriter;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use Uncanny_Automator\Json_String_Repair;
use WP_Error;

/**
 * Portable document → new recipe.
 *
 * The new-format import pipeline: envelope validation → cross-site URL
 * document-transform (when the document's `exported_from` origin differs
 * from this site) → the legacy import status policy applied to the tree
 * BEFORE replay (draft-by-default for triggers/actions; codes from the
 * `published_trigger_codes` filter forced ON — both legacy policy filters
 * preserved) → replay via
 * {@see Recipe_Duplication_Service::replay_tree()} (recipe shell always
 * DRAFT on import) → triggers without an on / off switch through the
 * item-on check → the `automator_migrate_recipe_part_meta` integration
 * hook with every fresh id.
 *
 * The imported-recipe warning meta, admin notices, and the
 * `automator_recipe_imported` action remain core-side concerns
 * (`Import_Recipe` fires them around this pipeline).
 *
 * `import_into()` and `import_legacy_into()` run the same pipeline into an
 * existing blank draft instead of a new recipe, inside one database transaction:
 * a fill that fails at any point, a PHP fatal included, leaves the draft as it was.
 */
final class Recipe_Importer {

	/**
	 * Portable-document schema version this importer accepts. Mirrors
	 * {@see Recipe_Exporter::SCHEMA_VERSION}.
	 *
	 * @var int
	 */
	const SCHEMA_VERSION = Recipe_Exporter::SCHEMA_VERSION;

	/**
	 * The recipe to fill doesn't exist, or isn't a recipe.
	 *
	 * @var string
	 */
	const ERROR_RECIPE_NOT_FOUND = 'recipe_not_found';

	/**
	 * The recipe to fill has a type, a trigger or a step.
	 *
	 * @var string
	 */
	const ERROR_RECIPE_NOT_BLANK = 'recipe_not_blank';

	/**
	 * The recipe to fill is live or in the trash.
	 *
	 * @var string
	 */
	const ERROR_RECIPE_NOT_DRAFT = 'recipe_not_draft';

	/**
	 * The site's posts storage can't roll a fill back, so no fill runs.
	 *
	 * @var string
	 */
	const ERROR_FILL_STORAGE_UNAVAILABLE = 'recipe_fill_storage_unavailable';

	/**
	 * The database didn't confirm the fill's commit.
	 *
	 * @var string
	 */
	const ERROR_FILL_UNVERIFIED = 'recipe_fill_unverified';

	/**
	 * The refusals a fill answers in its own words, which reach the person as they are.
	 *
	 * @var string[]
	 */
	const FILL_REFUSAL_CODES = array(
		self::ERROR_RECIPE_NOT_FOUND,
		self::ERROR_RECIPE_NOT_BLANK,
		self::ERROR_RECIPE_NOT_DRAFT,
		self::ERROR_FILL_STORAGE_UNAVAILABLE,
		self::ERROR_FILL_UNVERIFIED,
	);

	/**
	 * Replays the document tree as a new recipe (shared with duplication).
	 *
	 * @var Recipe_Duplication_Service
	 */
	private Recipe_Duplication_Service $duplication;

	/**
	 * Translates old-format export files into portable documents.
	 *
	 * @var Legacy_Export_Adapter
	 */
	private Legacy_Export_Adapter $adapter;

	/**
	 * Stashes old dumps' pre-canonical legacy meta on the new recipe so the
	 * existing migrators/stores can canonicalize it.
	 *
	 * @var Recipe_Legacy_Meta_Store
	 */
	private Recipe_Legacy_Meta_Store $legacy_meta;

	/**
	 * Canonicalizes stashed `actions_conditions` into filter blocks.
	 *
	 * @var Action_Conditions_Meta_Migrator
	 */
	private Action_Conditions_Meta_Migrator $conditions_migrator;

	/**
	 * Canonicalizes old dumps' `async_*` action meta — stashed on the new
	 * actions from the `_legacy` side-channel — into delay/schedule blocks.
	 *
	 * @var Delay_Schedule_Meta_Migrator
	 */
	private Delay_Schedule_Meta_Migrator $delay_migrator;

	/**
	 * Rewrites token ids inside old dumps' selector fields JSON.
	 *
	 * @var Reference_Rewriter
	 */
	private Reference_Rewriter $rewriter;

	/**
	 * Holds a fill's writes in one transaction, with the recipe's row locked.
	 *
	 * @var Post_Content_Edit_Store
	 */
	private Post_Content_Edit_Store $lock;

	/**
	 * Reads the recipe a fill targets, in the shape the builder decides "blank" from.
	 *
	 * @var Recipe_Tree_Builder
	 */
	private Recipe_Tree_Builder $tree_builder;

	/**
	 * Lazy-default constructor injection (Rule 9c): tests inject fakes,
	 * production gets the singletons / `new` defaults.
	 *
	 * @param Recipe_Duplication_Service|null      $duplication
	 * @param Legacy_Export_Adapter|null           $adapter
	 * @param Recipe_Legacy_Meta_Store|null       $legacy_meta
	 * @param Action_Conditions_Meta_Migrator|null $conditions_migrator
	 * @param Reference_Rewriter|null              $rewriter
	 * @param Delay_Schedule_Meta_Migrator|null    $delay_migrator
	 * @param Post_Content_Edit_Store|null         $lock
	 * @param Recipe_Tree_Builder|null             $tree_builder
	 */
	public function __construct(
		?Recipe_Duplication_Service $duplication = null,
		?Legacy_Export_Adapter $adapter = null,
		?Recipe_Legacy_Meta_Store $legacy_meta = null,
		?Action_Conditions_Meta_Migrator $conditions_migrator = null,
		?Reference_Rewriter $rewriter = null,
		?Delay_Schedule_Meta_Migrator $delay_migrator = null,
		?Post_Content_Edit_Store $lock = null,
		?Recipe_Tree_Builder $tree_builder = null
	) {
		$this->duplication         = $duplication ?? new Recipe_Duplication_Service();
		$this->adapter             = $adapter ?? new Legacy_Export_Adapter();
		$this->legacy_meta         = $legacy_meta ?? new WP_Recipe_Legacy_Meta_Store();
		$this->conditions_migrator = $conditions_migrator
			?? new Action_Conditions_Meta_Migrator( Database::get_block_store(), Database::get_block_modifier_store() );
		$this->rewriter            = $rewriter ?? new Reference_Rewriter();
		$this->delay_migrator      = $delay_migrator ?? new Delay_Schedule_Meta_Migrator( Database::get_block_store() );
		$this->lock                = $lock ?? Database::get_post_content_edit_store();
		$this->tree_builder        = $tree_builder ?? Recipe_Tree_Builder::instance();
	}

	/**
	 * Import one portable document as a new (draft) recipe.
	 *
	 * @param array<string,mixed> $document Decoded portable document.
	 * @return Materialization_Result|WP_Error Result (root = the new recipe id) or the first failure.
	 */
	public function import( array $document ) {
		return Recipe_Change_Events::operation(
			function () use ( $document ) {
				$refusal = $this->check( $document );
				if ( null !== $refusal ) {
					return $refusal;
				}

				return $this->run( $document );
			}
		);
	}

	/**
	 * Fill an existing blank draft from a portable document.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string,mixed> $document  Decoded portable document.
	 * @param int                 $recipe_id The recipe to fill.
	 * @return Materialization_Result|WP_Error Result (root = the filled recipe's id), or the failure with the recipe unchanged.
	 */
	public function import_into( array $document, int $recipe_id ) {
		return Recipe_Change_Events::operation(
			function () use ( $document, $recipe_id ) {
				$refusal = $this->check( $document );
				if ( null !== $refusal ) {
					return $refusal;
				}

				return $this->fill( $document, $recipe_id );
			}
		);
	}

	/**
	 * Fill an existing blank draft from an OLD-FORMAT export file.
	 *
	 * @since 8.1.0
	 *
	 * @param object $old_json  Decoded legacy export JSON.
	 * @param int    $recipe_id The recipe to fill.
	 * @return Materialization_Result|WP_Error Result (root = the filled recipe's id), or the failure with the recipe unchanged.
	 */
	public function import_legacy_into( object $old_json, int $recipe_id ) {
		return Recipe_Change_Events::operation(
			function () use ( $old_json, $recipe_id ) {
				$document = $this->adapter->adapt( $old_json );
				if ( $document instanceof WP_Error ) {
					return $document;
				}

				return $this->fill( $document, $recipe_id );
			}
		);
	}

	/**
	 * Run a fill in one transaction with the recipe's row locked, and report it once it is final.
	 *
	 * The lock makes the blank check and the writes one step: a second request for the same draft waits here, then finds it filled and is refused.
	 *
	 * A failed fill clears the recipe's caches after the rollback. The rollback restores the database but not a persistent object cache, and replay_document_into() keeps most values out of that cache. A refusal wrote nothing, so it leaves the caches alone.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string,mixed> $document  Validated or adapted portable document.
	 * @param int                 $recipe_id The recipe to fill.
	 * @return Materialization_Result|WP_Error
	 */
	private function fill( array $document, int $recipe_id ) {
		$wrote  = false;
		$filled = false;

		try {
			$result = $this->lock->with_post_lock(
				$recipe_id,
				function () use ( $document, $recipe_id, &$wrote ) {
					$refusal = $this->fill_refusal( $recipe_id );
					if ( null !== $refusal ) {
						return $refusal;
					}

					$wrote = true;

					return $this->replay_uncached( $document, $recipe_id );
				}
			);

			$filled = $result instanceof Materialization_Result;
		} finally {
			if ( $wrote && ! $filled ) {
				Recipe_Cache::invalidate( $recipe_id );
			}
		}

		if ( $result instanceof WP_Error ) {
			return $this->fill_error( $result );
		}

		Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );

		return $result;
	}

	/**
	 * Run the fill's pipeline with additions to the object cache suspended.
	 *
	 * Reads during a fill see writes that may still roll back, and a fatal or a killed process runs no cleanup. A persistent cache would keep those values after the database dropped them, so the fill doesn't add them.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string,mixed> $document  Validated or adapted portable document.
	 * @param int                 $recipe_id The recipe to fill.
	 * @return Materialization_Result|WP_Error
	 */
	private function replay_uncached( array $document, int $recipe_id ) {
		$suspended = wp_suspend_cache_addition();
		wp_suspend_cache_addition( true );

		try {
			return $this->replay_document_into( $document, $recipe_id );
		} finally {
			wp_suspend_cache_addition( $suspended );
		}
	}

	/**
	 * Why a recipe can't be filled: it has to be a blank draft.
	 *
	 * Blank is the frontend's isRecipeBlank(): no type, no triggers, nothing in the flow. Status is a rule of its own, because an import always lands on a draft: filling a live recipe would run whatever the document switches on.
	 *
	 * A caller whose fill the lock refused before this rule ran asks it directly, so it answers as a fill would. It writes nothing.
	 *
	 * @since 8.1.0
	 *
	 * @param int $recipe_id The recipe to fill.
	 * @return WP_Error|null The refusal, or null when the recipe can be filled.
	 */
	public function fill_refusal( int $recipe_id ): ?WP_Error {
		$target = $this->tree_builder->build( $recipe_id );

		if ( null === $target ) {
			return $this->recipe_not_found();
		}

		if ( Recipe_Status::DRAFT !== $target['status'] ) {
			return new WP_Error(
				self::ERROR_RECIPE_NOT_DRAFT,
				esc_html_x( 'Only a draft recipe can be filled from a template.', 'Recipe import error', 'uncanny-automator' )
			);
		}

		if ( null !== $target['recipe_type'] || array() !== $target['triggers'] || array() !== $target['content'] ) {
			return new WP_Error(
				self::ERROR_RECIPE_NOT_BLANK,
				esc_html_x( 'Only a blank recipe can be filled from a template. This one already has a type, triggers or actions.', 'Recipe import error', 'uncanny-automator' )
			);
		}

		return null;
	}

	/**
	 * The refusal for an id that names no recipe.
	 *
	 * @since 8.1.0
	 *
	 * @return WP_Error
	 */
	private function recipe_not_found(): WP_Error {
		return new WP_Error(
			self::ERROR_RECIPE_NOT_FOUND,
			esc_html_x( 'The recipe to fill could not be found.', 'Recipe import error', 'uncanny-automator' )
		);
	}

	/**
	 * Say the lock's failures in the fill's words. Every other failure passes through.
	 *
	 * @since 8.1.0
	 *
	 * @param WP_Error $error The fill's failure.
	 * @return WP_Error
	 */
	private function fill_error( WP_Error $error ): WP_Error {
		switch ( $error->get_error_code() ) {
			case 'rest_post_invalid_id':
				return $this->recipe_not_found();
			case Post_Content_Edit_Store::ERROR_STORAGE_UNAVAILABLE:
				return new WP_Error(
					self::ERROR_FILL_STORAGE_UNAVAILABLE,
					esc_html_x( 'Filling a recipe from a template needs transactional WordPress posts storage. Nothing was saved.', 'Recipe import error', 'uncanny-automator' )
				);
			case Post_Content_Edit_Store::ERROR_UNVERIFIED:
				return new WP_Error(
					self::ERROR_FILL_UNVERIFIED,
					esc_html_x( 'The template could not be confirmed as saved. Reload the recipe before you try again.', 'Recipe import error', 'uncanny-automator' )
				);
			default:
				return $error;
		}
	}

	/**
	 * Check a portable document the way import() does before it writes anything.
	 *
	 * A bulk import checks every document first, so a file with one it refuses imports nothing.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string,mixed> $document Decoded portable document.
	 * @return WP_Error|null The refusal import() answers for the document, or null when it passes.
	 */
	public function check( array $document ): ?WP_Error {
		if ( array() === $document ) {
			return new WP_Error(
				'invalid_import_document',
				esc_html_x( 'A non-empty recipe document is required.', 'Recipe helper error', 'uncanny-automator' )
			);
		}

		$version = (int) ( $document['schema_version'] ?? 0 );

		// Version gate: we only block documents from a NEWER Automator than
		// this site runs (we cannot understand a future format). When the
		// schema version bumps, OLDER documents are translated forward HERE
		// via a document adapter before run() — the Legacy_Export_Adapter
		// pattern — so a lower-but-known version must never hard-fail. With
		// only v1 in existence, anything below it is a malformed file.
		if ( $version > self::SCHEMA_VERSION ) {
			return new WP_Error(
				'import_unsupported_schema_version',
				esc_html_x( 'This recipe was exported by a newer version of Uncanny Automator. Please update Uncanny Automator on this site, then try the import again.', 'Import Recipe', 'uncanny-automator' )
			);
		}

		$tree = $document['recipe'] ?? null;
		if ( $version < self::SCHEMA_VERSION || ! is_array( $tree ) || ! array_key_exists( 'title', $tree ) ) {
			return new WP_Error(
				'import_invalid_document',
				esc_html_x( 'The uploaded file is not a valid recipe export.', 'Import Recipe', 'uncanny-automator' )
			);
		}

		return null;
	}

	/**
	 * Check an OLD-FORMAT export file the way import_legacy() does before it writes anything.
	 *
	 * @since 8.0.0
	 *
	 * @param object $old_json Decoded legacy export JSON.
	 * @return WP_Error|null The refusal import_legacy() answers for the file, or null when it passes.
	 */
	public function check_legacy( object $old_json ): ?WP_Error {
		$document = $this->adapter->adapt( $old_json );

		return $document instanceof WP_Error ? $document : null;
	}

	/**
	 * Import one OLD-FORMAT export file (raw post+meta dump) as a new draft
	 * recipe: adapt to a portable document, run the normal pipeline, then
	 * stash + canonicalize the dump's pre-canonical legacy meta.
	 *
	 * @param object $old_json Decoded legacy export JSON.
	 * @return Materialization_Result|WP_Error Result (root = the new recipe id) or the first failure.
	 */
	public function import_legacy( object $old_json ) {
		return Recipe_Change_Events::operation(
			function () use ( $old_json ) {
				$document = $this->adapter->adapt( $old_json );
				if ( $document instanceof WP_Error ) {
					return $document;
				}

				return $this->run( $document );
			}
		);
	}

	/**
	 * The shared import pipeline: cross-site URL transform → status policy on
	 * the tree (draft-by-default) → replay (recipe shell always DRAFT) →
	 * old-dump legacy-meta canonicalization (when present) → switch-less
	 * triggers through the item-on check → integration hook.
	 *
	 * @param array<string,mixed> $document Validated/adapted portable document.
	 * @return Materialization_Result|WP_Error Result or the first failure.
	 */
	private function run( array $document ) {
		$document = $this->transform_cross_site_urls( $document );
		$tree     = $document['recipe'];
		$selector = is_array( $document['user_selector'] ?? null ) ? $document['user_selector'] : null;

		// Imported recipes always land draft for safety. Item live-flags are
		// neutralized BEFORE replay (not demoted after): the materializer's
		// `_status` carrier runs required-field validation on publish, and a
		// document item that no longer validates on this site must not abort
		// the whole import — legacy import force-drafted without validating.
		$tree = $this->neutralize_item_status( $tree );

		// Same msgid the legacy importer used, so existing translations carry over.
		/* translators: %1$s Original recipe title */
		$title = sprintf( esc_html__( '%1$s (Imported)', 'uncanny-automator' ), (string) $tree['title'] );

		// The replay and the switch-ons are one import: only the imported recipe fires an event.
		$result = $this->duplication->replay_tree( $tree, $title, Recipe_Status::DRAFT, $selector );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$root_id      = (int) $result->get_root_id();
		$legacy_error = $this->canonicalize_legacy_sidecar( (array) ( $document['_legacy'] ?? array() ), $result );
		if ( $legacy_error instanceof WP_Error ) {
			// The recipe landed; only its legacy meta did not.
			Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::CREATED, Recipe_Changed::RECIPE, $root_id, $root_id ) );
			return $legacy_error;
		}

		$this->switch_on_switchless_triggers( (array) ( $tree['triggers'] ?? array() ), $result );
		Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::CREATED, Recipe_Changed::RECIPE, $root_id, $root_id ) );

		$this->announce_new_parts( $result );

		return $result;
	}

	/**
	 * The fill's pipeline, the same steps as run() on an existing recipe. The recipe keeps the document's title as is, because the owner never named the draft. It runs inside the fill's transaction, so a failure at any step, the integration hook included, undoes every step.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string,mixed> $document  Validated or adapted portable document.
	 * @param int                 $recipe_id The recipe to fill.
	 * @return Materialization_Result|WP_Error Result or the first failure.
	 */
	private function replay_document_into( array $document, int $recipe_id ) {
		$document = $this->transform_cross_site_urls( $document );
		$tree     = $this->neutralize_item_status( $document['recipe'] );
		$selector = is_array( $document['user_selector'] ?? null ) ? $document['user_selector'] : null;

		$result = $this->duplication->replay_tree_into( $tree, $recipe_id, (string) $tree['title'], $selector );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$legacy_error = $this->canonicalize_legacy_sidecar( (array) ( $document['_legacy'] ?? array() ), $result );
		if ( $legacy_error instanceof WP_Error ) {
			return $legacy_error;
		}

		$this->switch_on_switchless_triggers( (array) ( $tree['triggers'] ?? array() ), $result );
		$this->announce_new_parts( $result );

		return $result;
	}

	/**
	 * Tell integrations which posts an import just wrote, so they can migrate their meta.
	 *
	 * @since 8.1.0
	 *
	 * @param Materialization_Result $result Replay result.
	 * @return void
	 */
	private function announce_new_parts( Materialization_Result $result ): void {
		/** This hook is documented in src/core/admin/class-import-recipe.php (legacy firing site). */
		Dispatcher::action(
			'automator_migrate_recipe_part_meta',
			array_values( array_unique( array_merge( array( $result->get_root_id() ), array_map( 'intval', $result->get_id_map() ) ) ) ),
			$result->get_root_id()
		);
	}

	/**
	 * Canonicalize an old dump's `_legacy` side-channel on the NEW recipe:
	 * conditions JSON (action ids remapped structurally, then stashed for the
	 * EXISTING conditions migrator) and the anonymous-selector meta pair
	 * (token ids rewritten, stashed for the selector store to read natively).
	 *
	 * @param array<string,mixed>    $legacy Side-channel from the adapter ([] for new-format docs).
	 * @param Materialization_Result $result Replay result (old→new id map).
	 * @return WP_Error|null Error to abort, or null on success/no-op.
	 */
	private function canonicalize_legacy_sidecar( array $legacy, Materialization_Result $result ): ?WP_Error {
		if ( empty( $legacy ) ) {
			return null;
		}

		$recipe_id = $result->get_root_id();
		$id_map    = $result->get_id_map();

		$conditions_json  = (string) ( $legacy['actions_conditions'] ?? '' );
		$source_recipe_id = (int) ( $legacy['source_recipe_id'] ?? 0 );
		if ( '' !== $conditions_json ) {
			$groups = json_decode( Json_String_Repair::looks_like_structured_json( $conditions_json ) ? Json_String_Repair::repair( $conditions_json ) : $conditions_json, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error(
					'automator_import_conditions_unreadable',
					esc_html_x( 'The recipe was imported as a draft without its action conditions, because they could not be read. Add them again before setting the recipe live.', 'Recipe import error', 'uncanny-automator' )
				);
			}
			if ( is_array( $groups ) ) {
				foreach ( $groups as &$group ) {
					if ( ! is_array( $group ) ) {
						continue;
					}
					if ( is_array( $group['actions'] ?? null ) ) {
						$group['actions'] = array_values(
							array_map(
								static function ( $action_id ) use ( $id_map ) {
									return $id_map[ (int) $action_id ] ?? (int) $action_id;
								},
								$group['actions']
							)
						);
					}
					// Groups parent at the source loop (in the item map) or
					// the source recipe (never in the item map) — both must
					// land on this site's ids or the migrator parents its
					// filter blocks at a stale foreign id. A group an older export
					// saved with no parent belongs to the recipe: the 7.x builder
					// treated it so and saved that when the draft was opened.
					$old_parent         = (int) ( $group['parent_id'] ?? 0 );
					$group['parent_id'] = 0 === $old_parent
						? $recipe_id
						: ( $id_map[ $old_parent ] ?? ( $old_parent === $source_recipe_id ? $recipe_id : $old_parent ) );
				}
				unset( $group );

				$this->legacy_meta->stash_actions_conditions( $recipe_id, (string) wp_json_encode( $groups ) );
				$this->conditions_migrator->maybe_migrate( $recipe_id );
			}
		}

		// Old dumps' delay config rides the side-channel by source action id; a token delay can name source ids, so rewrite before the stash.
		foreach ( (array) ( $legacy['action_async'] ?? array() ) as $source_action_id => $async ) {
			$new_action_id = (int) ( $id_map[ (int) $source_action_id ] ?? 0 );
			if ( $new_action_id > 0 && is_array( $async ) ) {
				$this->legacy_meta->stash_action_async_meta( $recipe_id, $new_action_id, (array) $this->rewriter->rewrite( $async, $id_map ) );
			}
		}
		$this->delay_migrator->maybe_migrate( $recipe_id );

		$selector_source = (string) ( $legacy['selector_source'] ?? '' );
		if ( '' !== $selector_source ) {
			$fields = (array) $this->rewriter->rewrite( (array) ( $legacy['selector_fields'] ?? array() ), $id_map );
			$this->legacy_meta->stash_user_selector_meta( $recipe_id, $selector_source, $fields );
		}

		return null;
	}

	/**
	 * Rewrite the exporting site's origin to this site's origin across every
	 * string value in the document (legacy `maybe_adjust_hardcoded_meta_urls`
	 * semantics, applied as a document transform). No-op when origins match.
	 *
	 * @param array<string,mixed> $document Portable document.
	 * @return array<string,mixed> Document with origins rewritten.
	 */
	private function transform_cross_site_urls( array $document ): array {
		$from = $this->origin_of( (string) ( $document['exported_from'] ?? '' ) );
		$to   = $this->origin_of( get_site_url() );

		if ( '' === $from || $from === $to ) {
			return $document;
		}

		$document['recipe'] = $this->replace_strings( $document['recipe'], $from, $to );
		if ( is_array( $document['user_selector'] ?? null ) ) {
			$document['user_selector'] = $this->replace_strings( $document['user_selector'], $from, $to );
		}

		return $document;
	}

	/**
	 * Normalize a URL to its `scheme://host[:port]` origin.
	 *
	 * The port is part of the origin: a site on :8080 must not be rewritten as
	 * if it were on the default port.
	 *
	 * @param string $url Any URL.
	 * @return string Origin, or '' when the URL has no scheme/host.
	 */
	private function origin_of( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		return $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	}

	/**
	 * Recursively replace a needle in every string value of a branch.
	 *
	 * @param mixed  $value  Document branch.
	 * @param string $search Needle.
	 * @param string $place  Replacement.
	 * @return mixed Same shape, strings replaced.
	 */
	private function replace_strings( $value, string $search, string $place ) {
		if ( is_string( $value ) ) {
			return str_replace( $search, $place, $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $this->replace_strings( $v, $search, $place );
			}
		}
		return $value;
	}

	/**
	 * Neutralize the document items' live-flags so the materializer's
	 * `_status` carrier never attempts a validating publish during import
	 * (legacy parity: parts in `$draft_post_types` were force-drafted).
	 * Honors the `automator_recipe_import_draft_post_types` filter — a post
	 * type filtered OUT of the list keeps its exported flag.
	 *
	 * @param array<string,mixed> $tree Document tree.
	 * @return array<string,mixed> Tree with item live-flags neutralized.
	 */
	private function neutralize_item_status( array $tree ): array {
		$draft_post_types = (array) Dispatcher::filter(
			'automator_recipe_import_draft_post_types',
			array( AUTOMATOR_POST_TYPE_RECIPE, AUTOMATOR_POST_TYPE_TRIGGER, AUTOMATOR_POST_TYPE_ACTION )
		);

		// Legacy policy filter, preserved: codes listed here land live as
		// exported. Switch-less triggers are handled after replay instead.
		$published_codes = (array) Dispatcher::filter(
			'automator_recipe_import_published_trigger_codes',
			array()
		);

		$neutralizer = new Live_Flag_Neutralizer();
		$triggers    = (array) ( $tree['triggers'] ?? array() );

		$tree['triggers'] = in_array( AUTOMATOR_POST_TYPE_TRIGGER, $draft_post_types, true )
			? $neutralizer->neutralize_triggers( $triggers, $published_codes )
			: $neutralizer->force_live_triggers( $triggers, $published_codes );

		if ( in_array( AUTOMATOR_POST_TYPE_ACTION, $draft_post_types, true ) ) {
			$tree['content'] = $neutralizer->neutralize_actions( (array) ( $tree['content'] ?? array() ) );
		}

		return $tree;
	}

	/**
	 * Switch on the imported triggers that have no on / off switch, through
	 * the same item-on check as the builder: a configured one lands live, an
	 * unconfigured one stays draft until its first valid save. Runs after
	 * replay, so a refusal never aborts the import. A code this site does not
	 * register (Pro inactive) reads as switchable and stays draft.
	 *
	 * @param array<int,mixed>       $triggers Document tree triggers, keyed by their exported ids.
	 * @param Materialization_Result $result   Replay result (old→new id map).
	 * @return void
	 */
	private function switch_on_switchless_triggers( array $triggers, Materialization_Result $result ): void {
		$id_map   = $result->get_id_map();
		$registry = Trigger_Registry_Service::instance();
		$crud     = Trigger_CRUD_Service::instance();

		foreach ( $triggers as $trigger ) {
			if ( ! is_array( $trigger ) ) {
				continue;
			}

			$code   = (string) ( $trigger['code'] ?? '' );
			$new_id = (int) ( $id_map[ $trigger['id'] ?? 0 ] ?? 0 );
			if ( 0 === $new_id || '' === $code || $registry->has_status_toggle( $code ) ) {
				continue;
			}

			$crud->set_status( $new_id, $result->get_root_id(), true );
		}
	}
}

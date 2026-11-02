<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Trigger_Store;
use LogicException;
use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects\Action_Tree_Entry;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects\Block_Modifier_Tree_Entry;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects\Block_Tree_Entry;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects\Delay_Schedule_Tree_Entry;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects\Filter_Tree_Entry;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects\Loop_Tree_Entry;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects\Trigger_Tree_Entry;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Details_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Taxonomy_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Services\Recipe_Settings_Service;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Token_Dependency_Extractor;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Services\User_Selector_Service;
use WP_Post;

/**
 * Walks recipe storage and emits the canonical schema-v3 tree array.
 *
 * **Pure read.** No domain-object reconstruction, no mutations, no caching
 * inside the walk. `Recipe_Schema_Reader` is the caller, and it decides
 * whether to persist the output.
 *
 * Composition is a thin orchestration — every per-row projection lives on
 * a typed VO; this class is the dispatch surface that walks storage and
 * routes each row to the matching VO:
 *
 *   - `Block_Store` / `Block_Modifier_Store` for structural traversal.
 *   - `Recipe_Trigger_Store` / `Action_Store` for item
 *     hydration.
 *   - `Recipe_Settings_Service` for the recipe-level policy strip.
 *   - `{Filter,Loop,Delay_Schedule}_Tree_Entry` VOs for block projection
 *     (own their type-specific computation — `delay_seconds`,
 *     `schedule_at`, `iterable_expression` translation, etc.).
 *   - `{Action,Trigger}_Tree_Entry` VOs for item projection.
 *   - `User_Selector_Service` for `flow.run_as`.
 *
 * **Triggers always live in `triggers[]`, never in `content[]`** — wire
 * contract puts them in `flow.entry`. `content[]` is the heterogeneous
 * actions / blocks list.
 *
 * Field-level enrichment (resolving integration display names, deriving
 * published `tokens`, availability) happens at PROJECTION time in the
 * wire serializer and its decorators — never on the cached tree. The
 * tree carries declarative domain values; the wire serializer projects
 * to `FieldsOutput` envelopes at emit time.
 */
final class Recipe_Tree_Builder {

	/**
	 * Map of `block_type` discriminator → `Block_Tree_Entry` subclass that
	 * projects it. Single dispatch table consumed by every per-block
	 * lookup (modifier discriminator, default path code, projection) so
	 * adding a new block type is one entry plus its VO class.
	 *
	 * @var array<string, class-string<Block_Tree_Entry>>
	 */
	private const BLOCK_TREE_ENTRY_CLASS = array(
		Block_Type::FILTER         => Filter_Tree_Entry::class,
		Block_Type::LOOP           => Loop_Tree_Entry::class,
		Block_Type::DELAY_SCHEDULE => Delay_Schedule_Tree_Entry::class,
	);

	/**
	 * Shared instance handed out by `instance()`.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Reads the recipe's filter / loop / delay block rows and their nesting,
	 * which is what the structural half of the walk traverses.
	 *
	 * @var Block_Store
	 */
	private $block_store;

	/**
	 * Reads the modifiers attached to each block — filter conditions and loop
	 * filters — so a block projects together with what qualifies it.
	 *
	 * @var Block_Modifier_Store
	 */
	private $modifier_store;

	/**
	 * Resolves who the recipe runs as, projected onto `flow.run_as`.
	 *
	 * @var User_Selector_Service
	 */
	private $user_selector;

	/**
	 * Supplies the recipe-level policy strip — trigger logic, run limits and
	 * the rest of the settings the tree carries above its items.
	 *
	 * @var Recipe_Settings_Service
	 */
	private $recipe_settings;

	/**
	 * Supplies the author-facing recipe details, such as title and notes.
	 *
	 * @var Recipe_Details_Service
	 */
	private $details;

	/**
	 * Supplies the recipe's taxonomy terms for the tree's classification keys.
	 *
	 * @var Recipe_Taxonomy_Service
	 */
	private $taxonomy;

	/**
	 * Reads and hydrates the recipe's trigger posts. Triggers are walked into
	 * `triggers[]`, never into `content[]`.
	 *
	 * @var Recipe_Trigger_Store
	 */
	private $trigger_store;

	/**
	 * Reads and hydrates the recipe's action posts for the `content[]` walk.
	 *
	 * @var Action_Store
	 */
	private $action_store;

	/**
	 * Reads the recipe post itself, so the walk can confirm the recipe exists
	 * before projecting anything beneath it.
	 *
	 * @var Recipe_Store
	 */
	private $recipe_store;

	/**
	 * Finds the tokens a stored field value depends on, recorded on the tree so
	 * later passes know what each item consumes.
	 *
	 * @var Token_Dependency_Extractor
	 */
	private $token_extractor;

	/**
	 * Legacy-registry seam used to confirm an item's integration is present.
	 * Display-name resolution belongs to the wire serializer, not the tree.
	 *
	 * @var Integration_Registry_Bridge
	 */
	private $integration_registry;

	/**
	 * Lazy-default constructor injection (Rule 9c): tests inject fakes,
	 * production gets the store/service defaults.
	 *
	 * @param Block_Store|null                 $block_store          Block store.
	 * @param Block_Modifier_Store|null        $modifier_store       Block-modifier store.
	 * @param User_Selector_Service|null       $user_selector        User-selector service.
	 * @param Recipe_Settings_Service|null     $recipe_settings      Recipe settings service.
	 * @param Recipe_Trigger_Store|null        $trigger_store        Trigger store.
	 * @param Action_Store|null                $action_store         Action store.
	 * @param Recipe_Store|null                $recipe_store         Recipe store.
	 * @param Token_Dependency_Extractor|null  $token_extractor      Token dependency extractor.
	 * @param Integration_Registry_Bridge|null $integration_registry Integration registry bridge.
	 * @param Recipe_Details_Service|null      $details              Recipe details service.
	 * @param Recipe_Taxonomy_Service|null     $taxonomy             Recipe taxonomy service.
	 */
	public function __construct(
		?Block_Store $block_store = null,
		?Block_Modifier_Store $modifier_store = null,
		?User_Selector_Service $user_selector = null,
		?Recipe_Settings_Service $recipe_settings = null,
		?Recipe_Trigger_Store $trigger_store = null,
		?Action_Store $action_store = null,
		?Recipe_Store $recipe_store = null,
		?Token_Dependency_Extractor $token_extractor = null,
		?Integration_Registry_Bridge $integration_registry = null,
		?Recipe_Details_Service $details = null,
		?Recipe_Taxonomy_Service $taxonomy = null
	) {
		$this->block_store          = $block_store ?? Database::get_block_store();
		$this->modifier_store       = $modifier_store ?? Database::get_block_modifier_store();
		$this->user_selector        = $user_selector ?? User_Selector_Service::instance();
		$this->recipe_settings      = $recipe_settings ?? new Recipe_Settings_Service();
		$this->trigger_store        = $trigger_store ?? Database::get_recipe_trigger_store();
		$this->action_store         = $action_store ?? Database::get_action_store();
		$this->recipe_store         = $recipe_store ?? Database::get_recipe_store();
		$this->token_extractor      = $token_extractor ?? new Token_Dependency_Extractor();
		$this->integration_registry = $integration_registry ?? new Automator_Integration_Registry_Bridge();
		$this->details              = $details ?? new Recipe_Details_Service();
		$this->taxonomy             = $taxonomy ?? new Recipe_Taxonomy_Service();
	}

	/**
	 * Shared singleton accessor.
	 *
	 * Holds no per-request state; reused so callers share one instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Build the canonical tree for a recipe.
	 *
	 * Returns null when the post id doesn't resolve to a `uo-recipe`.
	 *
	 * @param int $recipe_id Recipe post id.
	 * @return array|null
	 */
	public function build( int $recipe_id ): ?array {
		$recipe = $this->recipe_store->get_wp_post( $recipe_id );
		if ( ! $recipe instanceof WP_Post ) {
			return null;
		}

		return array(
			'recipe_id'      => $recipe_id,
			'title'          => $recipe->post_title,
			'notes'          => $this->details->get_notes( $recipe_id ),
			'categories'     => $this->taxonomy->get_assigned_terms_wire( $recipe_id, 'recipe_category' ),
			'tags'           => $this->taxonomy->get_assigned_terms_wire( $recipe_id, 'recipe_tag' ),
			'status'         => $recipe->post_status,
			'recipe_type'    => $this->recipe_settings->get_recipe_type_or_null( $recipe_id ),
			'trigger_logic'  => $this->recipe_settings->get_trigger_logic( $recipe_id ),
			'throttle'       => $this->recipe_settings->get_throttle( $recipe_id ),
			'total_times'    => $this->recipe_settings->get_total_times( $recipe_id ),
			'times_per_user' => $this->recipe_settings->get_times_per_user( $recipe_id ),
			'triggers'       => $this->build_triggers( $recipe_id ),
			'content'        => $this->build_content( $recipe_id ),
			'run_as'         => $this->user_selector->project_for_recipe( $recipe_id ),
		);
	}

	/**
	 * Project every trigger on the recipe into the tree's `triggers[]` list.
	 *
	 * A trigger post the aggregate refuses (it has no hook, say) still emits
	 * what it stores, via `Trigger_Tree_Entry::unhydrated()`, so the builder
	 * shows the recipe as authored. A post with no code is no trigger and stays
	 * out: the builder's schema requires one, and a blank code fails its parse
	 * of the whole recipe.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return list<array> One tree entry per trigger, in storage order.
	 */
	private function build_triggers( int $recipe_id ): array {
		$out = array();
		foreach ( $this->trigger_store->get_recipe_trigger_posts( $recipe_id ) as $post ) {
			$trigger = $this->trigger_store->hydrate_trigger_from_post( $post );
			if ( null !== $trigger ) {
				$out[] = Trigger_Tree_Entry::from_aggregate( $trigger, $post )->to_array();
				continue;
			}

			$stored = $this->trigger_store->parse_trigger_meta( (int) $post->ID );
			if ( null !== $stored ) {
				$out[] = Trigger_Tree_Entry::unhydrated( $post, $stored )->to_array();
			}
		}
		return $out;
	}

	/**
	 * Build the recipe-root content list (heterogeneous: actions / blocks).
	 *
	 * Triggers excluded — they live in `triggers[]`. The top-level
	 * uo-block / uo-action direct children of the recipe
	 * become content entries.
	 *
	 * @return list<array>
	 */
	private function build_content( int $recipe_id ): array {
		$out = array();

		// Top-level uo-block children (canonical loops + filters + delay_schedule).
		foreach ( $this->block_store->get_by_parent( $recipe_id ) as $block_row ) {
			if ( $this->is_unfinished_block( $block_row ) ) {
				continue;
			}
			$out[] = $this->build_block_entry( $block_row );
		}

		// Top-level uo-action children via the action store + Action_Tree_Entry.
		foreach ( $this->action_store->get_top_level_recipe_actions( $recipe_id ) as $action ) {
			$post = $this->action_store->get_wp_post( $action->get_action_id()->get_value() );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$out[] = Action_Tree_Entry::from_aggregate(
				$action,
				$post,
				$this->token_extractor,
				$this->integration_registry
			)->to_array();
		}

		// The passes above append type-grouped, but menu_order is a single
		// sequence stamped across every top-level child (block / action /
		// legacy loop) by the reorder service. Merge them back into that order;
		// id is the tie-break so the result is deterministic on PHP 7.4, where
		// usort is not stable.
		usort(
			$out,
			static function ( array $a, array $b ): int {
				return array( $a['menu_order'] ?? 0, $a['id'] ?? 0 )
					<=> array( $b['menu_order'] ?? 0, $b['id'] ?? 0 );
			}
		);

		return $out;
	}

	/**
	 * Build a block tree entry by dispatching to the typed VO for the
	 * row's block_type. Modifier discriminator and default path code are
	 * sourced from the matching VO's static metadata
	 * ({@see Block_Tree_Entry::modifier_type()},
	 * {@see Block_Tree_Entry::default_path_code()}) so per-type domain
	 * knowledge lives on the type, not in the walker.
	 *
	 * @param array $block_row Row from Block_Store::get_by_parent / get_children.
	 * @return array
	 * @throws LogicException When `block_row['block_type']` has no registered VO.
	 */
	private function build_block_entry( array $block_row ): array {
		$block_id    = (int) ( $block_row['id'] ?? 0 );
		$block_type  = (string) ( $block_row['block_type'] ?? '' );
		$entry_class = $this->tree_entry_class_for( $block_type );

		$modifiers = $this->build_block_modifiers( $block_id, $entry_class );
		$paths     = $this->build_block_paths( $block_id, $block_type );

		switch ( $entry_class ) {
			case Filter_Tree_Entry::class:
				return Filter_Tree_Entry::from_block_row(
					$block_row,
					$modifiers,
					$paths,
					$this->token_extractor
				)->to_array();

			case Loop_Tree_Entry::class:
				return Loop_Tree_Entry::from_block_row(
					$block_row,
					$modifiers,
					$paths
				)->to_array();

			case Delay_Schedule_Tree_Entry::class:
				return Delay_Schedule_Tree_Entry::from_block_row(
					$block_row,
					$modifiers,
					$paths,
					$this->token_extractor,
					$this->block_store
				)->to_array();
		}

		// Unreachable — tree_entry_class_for would have already thrown.
		throw new LogicException( sprintf( 'No factory wired for "%s".', esc_html( $entry_class ) ) );
	}

	/**
	 * Whether a block row is a create that never finished, which the tree leaves out.
	 *
	 * `Block_Store::create()` writes `block_type` as the block's last row, and nothing is placed in a
	 * block before its create returns, so a block with no type is one whose request ended part-way.
	 * Leaving it out, and logging it, keeps the rest of the recipe readable and runnable; the row
	 * stays on disk, since a read never writes. A block whose type is set but unknown is not this
	 * case and still throws in {@see self::tree_entry_class_for()}.
	 *
	 * @since 8.0.0
	 *
	 * @param array $block_row Row from Block_Store::get_by_parent / get_children.
	 *
	 * @return bool
	 */
	private function is_unfinished_block( array $block_row ): bool {
		if ( '' !== (string) ( $block_row['block_type'] ?? '' ) ) {
			return false;
		}

		automator_log(
			sprintf(
				'Block %d (parent %d) has no block_type: its create never finished. It is left out of the recipe tree.',
				(int) ( $block_row['id'] ?? 0 ),
				(int) ( $block_row['parent_id'] ?? 0 )
			),
			'Recipe_Tree_Builder'
		);

		return true;
	}

	/**
	 * Resolve the `Block_Tree_Entry` subclass for a given block_type.
	 * Single dispatch surface; throws for unknown types so missing VO
	 * registration is loud (no silent fallback per Rule 0.8).
	 *
	 * @return class-string<Block_Tree_Entry>
	 * @throws LogicException When `$block_type` has no registered VO.
	 */
	private function tree_entry_class_for( string $block_type ): string {
		if ( ! isset( self::BLOCK_TREE_ENTRY_CLASS[ $block_type ] ) ) {
			throw new LogicException(
				sprintf( 'Unknown block_type "%s" — no tree-entry VO registered.', esc_html( $block_type ) )
			);
		}
		return self::BLOCK_TREE_ENTRY_CLASS[ $block_type ];
	}

	/**
	 * Build the modifier list for a block. Pulls the discriminator from
	 * the block's tree-entry VO ({@see Block_Tree_Entry::modifier_type()})
	 * so each block type owns the knowledge of which modifier kind it
	 * carries. Returns `[]` for block types with no modifiers (e.g.
	 * delay_schedule).
	 *
	 * @param class-string<Block_Tree_Entry> $entry_class
	 * @return list<array>
	 */
	private function build_block_modifiers( int $block_id, string $entry_class ): array {
		$discriminator = $entry_class::modifier_type();
		if ( null === $discriminator ) {
			return array();
		}

		$rows = $this->modifier_store->get_by_block( $block_id, $discriminator );
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = Block_Modifier_Tree_Entry::from_row( $row )->to_array();
		}
		return $out;
	}

	/**
	 * Build path entries for a block. Walks `Block_Store::get_children`
	 * and buckets each child by its stamped `path_code`. Children with
	 * an empty `path_code` fall back to the block type's first declared
	 * path ({@see Block_Path_Code::default_for_block_type()}).
	 *
	 * Every catalog-declared path for the block type is always emitted,
	 * even when its `items` bucket is empty — the wire serializer and
	 * recipe-builder FE rely on exhaustive `paths[]` for optimistic
	 * inserts into empty branches.
	 *
	 * @param string $block_type One of the `Block_Type::*` constants.
	 * @return list<array{path_code: string, items: list<array>}>
	 */
	private function build_block_paths( int $block_id, string $block_type ): array {
		$default_path   = Block_Path_Code::default_for_block_type( $block_type );
		$declared_paths = Block_Path_Code::for_block_type( $block_type );
		$by_path        = array();

		foreach ( $this->block_store->get_children( $block_id ) as $child ) {
			$entity_type = (string) ( $child['entity_type'] ?? '' );
			$path_code   = (string) ( $child['path_code'] ?? '' );
			// An empty stamp, or one this block type does not declare (a stale stamp from a previous
			// parent), reads as the default path, as it does for a keep-delete landing and a placement check.
			if ( '' === $path_code || ( ! empty( $declared_paths ) && ! in_array( $path_code, $declared_paths, true ) ) ) {
				$path_code = $default_path;
			}

			if ( 'block' === $entity_type ) {
				if ( ! $this->is_unfinished_block( $child ) ) {
					$by_path[ $path_code ][] = $this->build_block_entry( $child );
				}
				continue;
			}

			// 'item' entity_type — currently always a uo-action child since
			// Block_Store::get_children queries uo-block + uo-action only.
			$post_id = (int) ( $child['ID'] ?? 0 );
			if ( $post_id <= 0 ) {
				continue;
			}

			$action = $this->action_store->get( $post_id );
			$post   = $this->action_store->get_wp_post( $post_id );
			if ( null === $action || ! $post instanceof WP_Post ) {
				continue;
			}

			$by_path[ $path_code ][] = Action_Tree_Entry::from_aggregate(
				$action,
				$post,
				$this->token_extractor,
				$this->integration_registry
			)->to_array();
		}

		if ( ! empty( $declared_paths ) ) {
			$out = array();
			foreach ( $declared_paths as $path_code ) {
				$out[] = array(
					'path_code' => $path_code,
					'items'     => $by_path[ $path_code ] ?? array(),
				);
			}
			return $out;
		}

		// Unknown block types: emit only buckets that have children.
		$out = array();
		foreach ( $by_path as $path_code => $items ) {
			$out[] = array(
				'path_code' => $path_code,
				'items'     => $items,
			);
		}
		return $out;
	}
}

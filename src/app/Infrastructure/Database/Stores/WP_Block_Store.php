<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Exception;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Events\Recipe_Change_Record;
use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Field_Translator;
use Uncanny_Automator\App\Recipe_Builder\Shared\Enums\Post_Status;
use WP_Post;

/**
 * WordPress Block Store.
 *
 * WordPress implementation of block store using uo-block post type.
 * Stores block instances as posts with meta for block_type and fields.
 */
class WP_Block_Store extends WP_Post_Store implements Block_Store {

	/**
	 * Post type for blocks.
	 *
	 * @var string
	 */
	const POST_TYPE = AUTOMATOR_POST_TYPE_BLOCK;

	/**
	 * Meta key for block type.
	 *
	 * @var string
	 */
	const META_BLOCK_TYPE = 'block_type';

	/**
	 * Meta key for block fields (JSON).
	 *
	 * @var string
	 */
	const META_FIELDS = 'fields';

	/**
	 * Meta key for a loop block's iteration config.
	 *
	 * @var string
	 */
	const META_ITERABLE_EXPRESSION = 'iterable_expression';

	/**
	 * Meta key for a loop block's run-on selection.
	 *
	 * @var string
	 */
	const META_RUN_ON = 'run_on';

	/**
	 * Meta key for path assignment on child posts.
	 *
	 * Blocks can define multiple execution paths that their children belong to.
	 * Each child (action or nested block) stores a path_code to identify which
	 * branch it runs under. For example, a filter block may have:
	 *
	 *   - FILTER-CONDITIONS-MET     → actions that run when conditions pass
	 *   - FILTER-CONDITIONS-NOT-MET → actions that run when conditions fail
	 *
	 * Currently only the "conditions met" path is active in the UI. The
	 * path_code infrastructure is in place to support multi-branch flows
	 * (if/else) once the frontend flow UI is ready.
	 *
	 * @var string
	 */
	const META_PATH_CODE = 'path_code';

	/**
	 * Cross-recipe lookup post-meta key — indexed by WordPress core's
	 * `wp_postmeta(meta_key, meta_value)` index. Stamped on every uo-block
	 * so a recipe-scoped query is one indexed lookup, not a tree walk.
	 *
	 * @var string
	 */
	const META_RECIPE_ID = 'recipe_id';

	/**
	 * Legacy `actions_conditions[i].id` string-hash post-meta key. Stamped on
	 * filter blocks so the legacy frontend translator can emit a stable
	 * group `id` across edits and round-trips.
	 *
	 * @var string
	 */
	const META_LEGACY_GROUP_ID = '_legacy_group_id';

	/**
	 * Universal backup breadcrumb post-meta key. Colon-delimited path
	 * (e.g. `recipe_388284:filter:morissneqvjc7i4qnj9`) captured at
	 * migration time for manual debug + rollback.
	 *
	 * @var string
	 */
	const META_SYNTHESIS_KEY = '_synthesis_key';

	/**
	 * Block-modifier store — type-agnostic cascade delete on block teardown.
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Constructor.
	 *
	 * @param Block_Modifier_Store $modifier_store Block-modifier store instance.
	 */
	public function __construct( Block_Modifier_Store $modifier_store ) {
		$this->modifier_store = $modifier_store;
	}

	/**
	 * Create a new block, whole or not at all.
	 *
	 * The post and every meta row ride one insert, and `block_type` is the last row written: a
	 * `uo-block` without it is a create that never finished, which {@see self::delete_unfinished()}
	 * removes. When a row is refused, the post is deleted again and the create throws.
	 *
	 * @param int      $parent_id        Parent ID (recipe or parent block).
	 * @param string   $block_type       Block type (filter, delay_schedule, loop).
	 * @param array    $fields           Block fields.
	 * @param int      $menu_order       Execution order within parent.
	 * @param int|null $recipe_id        Owning recipe id. Stamped as `recipe_id` post-meta for indexed cross-recipe lookup. Null for top-level recipes (resolved from `$parent_id`).
	 * @param string|null $legacy_group_id Legacy `actions_conditions[i].id` string-hash. Stamped only on filter blocks.
	 * @param string|null $synthesis_key  Universal backup breadcrumb (`recipe_<id>:<block_type>:<legacy_id_or_anchor>`).
	 * @param string|null $path_code      Target path of the parent block, stamped at insert.
	 *
	 * @return int New block post ID.
	 * @throws Exception If creation fails.
	 */
	public function create(
		int $parent_id,
		string $block_type,
		array $fields = array(),
		int $menu_order = 0,
		?int $recipe_id = null,
		?string $legacy_group_id = null,
		?string $synthesis_key = null,
		?string $path_code = null
	): int {

		$meta_input = array();

		// The requested path rides the insert so a create is one write.
		if ( null !== $path_code && '' !== $path_code ) {
			$meta_input[ self::META_PATH_CODE ] = $path_code;
		}

		if ( ! empty( $fields ) ) {
			// wp_slash: wp_insert_post runs wp_unslash on the entire input including meta_input.
			$meta_input[ self::META_FIELDS ] = wp_slash( wp_json_encode( $fields ) );

			// Loop config is a coherent PAIR: wire-shape fields + the derived
			// `iterable_expression` the runner/reconcile read. Deriving here
			// keeps fields-only writers (replay/import) coherent by
			// construction.
			$expression = Block_Type::LOOP === $block_type ? Loop_Field_Translator::from_fields( $fields ) : array();
			if ( array() !== $expression ) {
				// wp_slash for the same reason as the fields write above, and it
				// matters just as much here: the expression is an ARRAY holding a
				// JSON string, wp_unslash is stripslashes_deep and recurses into
				// it, so a loop token containing a quote or backslash comes back
				// as undecodable JSON and the loop silently loses its token.
				$meta_input[ self::META_ITERABLE_EXPRESSION ] = wp_slash( $expression );
			}
		}

		// Stamp recipe_id for indexed cross-recipe lookup. When the caller
		// hasn't provided one, fall back to walking up parent_id so legacy
		// callers (without recipe_id wiring) still backfill correctly.
		$resolved_recipe_id = $recipe_id ?? $this->resolve_recipe_id_from_parent( $parent_id );
		if ( $resolved_recipe_id > 0 ) {
			$meta_input[ self::META_RECIPE_ID ] = $resolved_recipe_id;
		}

		if ( null !== $legacy_group_id && '' !== $legacy_group_id ) {
			$meta_input[ self::META_LEGACY_GROUP_ID ] = $legacy_group_id;
		}

		if ( null !== $synthesis_key && '' !== $synthesis_key ) {
			$meta_input[ self::META_SYNTHESIS_KEY ] = $synthesis_key;
		}

		// Last: wp_insert_post() writes meta_input in order, so a typed block carries every row above.
		$meta_input[ self::META_BLOCK_TYPE ] = $block_type;

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $this->generate_title( $block_type ),
				'post_parent' => $parent_id,
				'post_status' => Post_Status::PUBLISH,
				'menu_order'  => $menu_order,
				'meta_input'  => $meta_input,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to create block: %s', 'Block store creation error', 'uncanny-automator' ), esc_html( $post_id->get_error_message() ) ) );
		}

		if ( ! $post_id ) {
			throw new Exception( esc_html_x( 'Failed to create block: unknown error', 'Block store creation error', 'uncanny-automator' ) );
		}

		// wp_insert_post() does not report a refused meta row; a block missing one is not created.
		$missing = array_diff( array_keys( $meta_input ), array_keys( (array) get_post_meta( $post_id ) ) );
		if ( array() !== $missing ) {
			wp_delete_post( $post_id, true );
			// translators: %s is a comma-separated list of meta keys.
			throw new Exception( sprintf( esc_html_x( 'Failed to create block: %s could not be stored', 'Block store creation error', 'uncanny-automator' ), esc_html( implode( ', ', $missing ) ) ) );
		}

		// Maintain path code consistency for nested blocks.
		self::maybe_reconcile_item_path_code( $post_id, $parent_id );

		return $post_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_unfinished( int $recipe_id ): array {
		$deleted = array();

		foreach ( $this->find_tree_block_ids( $recipe_id ) as $block_id ) {
			// A typed block is finished, with or without its `recipe_id` row: the type is a create's last row,
			// and a typed block reads and runs, so deleting one would take what its owner configured.
			if ( '' !== (string) get_post_meta( $block_id, self::META_BLOCK_TYPE, true ) || $this->holds_posts( $block_id ) ) {
				continue;
			}

			if ( ! wp_delete_post( $block_id, true ) ) {
				// translators: %d is the block ID.
				throw new Exception( sprintf( esc_html_x( 'Failed to delete the unfinished block with ID: %d', 'Block store delete error', 'uncanny-automator' ), absint( $block_id ) ) );
			}
			$deleted[] = $block_id;
		}

		return $deleted;
	}

	/**
	 * Every `uo-block` post under a recipe at any depth, found through `post_parent` alone.
	 *
	 * A create stopped before its `recipe_id` row carries no stamp, so the walk follows parents: the
	 * recipe, its blocks, and any `uo-loop` the loop fold has not renamed yet. Each post is visited once.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return int[] Block post IDs, nearest the recipe first.
	 */
	private function find_tree_block_ids( int $recipe_id ): array {
		global $wpdb;

		$blocks  = array();
		$seen    = array( $recipe_id => true );
		$parents = $recipe_id > 0 ? array( $recipe_id ) : array();

		while ( array() !== $parents ) {
			$placeholders = implode( ',', array_fill( 0, count( $parents ), '%d' ) );
			$rows         = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A parent walk over every container type, read live: the rows it looks for are ones no cache or meta query can find.
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is one %d per parent id; the spread supplies the ids, then the two post types.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a run of %d built above.
					"SELECT ID, post_type FROM {$wpdb->posts} WHERE post_parent IN ({$placeholders}) AND post_type IN (%s, %s) ORDER BY ID ASC",
					...array_merge( $parents, array( self::POST_TYPE, AUTOMATOR_POST_TYPE_LOOP ) )
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
				// translators: %d is the recipe ID.
				throw new Exception( sprintf( esc_html_x( 'Failed to read unfinished blocks for recipe %d.', 'Block store delete error', 'uncanny-automator' ), absint( $recipe_id ) ) );
			}

			$parents = array();
			foreach ( (array) $rows as $row ) {
				$id = (int) $row['ID'];
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$parents[]   = $id;
				if ( self::POST_TYPE === $row['post_type'] ) {
					$blocks[] = $id;
				}
			}
		}

		return $blocks;
	}

	/**
	 * Whether any post, of any type, sits directly under the given one.
	 *
	 * @since 8.0.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool
	 */
	private function holds_posts( int $post_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Any child of any type and status counts, read live; no WordPress API answers that in one query.
		$child = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d LIMIT 1", $post_id ) );
		if ( '' !== $wpdb->last_error ) {
			// translators: %d is the block ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to inspect unfinished block %d.', 'Block store delete error', 'uncanny-automator' ), absint( $post_id ) ) );
		}
		return null !== $child;
	}

	/**
	 * Resolve the owning recipe id by walking up `post_parent`. Returns
	 * the parent id when it points at a `uo-recipe` post, or recurses up
	 * one level when the parent is another `uo-block`. Returns 0 if no
	 * recipe ancestor is found (defensive — never throws).
	 *
	 * @param int $parent_id Direct parent post id.
	 *
	 * @return int Recipe post id or 0.
	 */
	private function resolve_recipe_id_from_parent( int $parent_id ): int {
		if ( $parent_id <= 0 ) {
			return 0;
		}

		$parent = get_post( $parent_id );
		if ( ! $parent ) {
			return 0;
		}

		if ( AUTOMATOR_POST_TYPE_RECIPE === $parent->post_type ) {
			return (int) $parent->ID;
		}

		if ( $this->is_owned_post( $parent ) ) {
			$grandparent_recipe = (int) get_post_meta( $parent->ID, self::META_RECIPE_ID, true );
			if ( $grandparent_recipe > 0 ) {
				return $grandparent_recipe;
			}
			return $this->resolve_recipe_id_from_parent( (int) $parent->post_parent );
		}

		return 0;
	}

	/**
	 * Owning recipe of a block or of a child item under a block.
	 *
	 * Blocks carry the `recipe_id` stamp; an action child does not, so it resolves through its parent chain.
	 *
	 * @param int $post_id Block or child post id.
	 * @return int Recipe post id or 0.
	 */
	private function owning_recipe_id( int $post_id ): int {
		$stamped = (int) get_post_meta( $post_id, self::META_RECIPE_ID, true );
		if ( $stamped > 0 ) {
			return $stamped;
		}

		return $this->resolve_recipe_id_from_parent( (int) get_post_field( 'post_parent', $post_id ) );
	}

	/**
	 * Get a block by ID.
	 *
	 * @param int $block_id Block post ID.
	 *
	 * @return array|null Block data array or null if not found.
	 */
	public function get( int $block_id ): ?array {
		$post = $this->get_wp_post( $block_id );

		if ( ! $post ) {
			return null;
		}

		return $this->build_data_from_post( $post );
	}

	/**
	 * Get all blocks for a given parent, ordered by menu_order.
	 *
	 * @param int $parent_id Parent ID (recipe or parent block).
	 *
	 * @return array Array of block data arrays.
	 */
	public function get_by_parent( int $parent_id ): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_parent'    => $parent_id,
				'post_status'    => Post_Status::get_all(),
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		$blocks = array();
		foreach ( $posts as $post ) {
			$blocks[] = $this->build_data_from_post( $post );
		}

		return $blocks;
	}

	/**
	 * Update a block.
	 *
	 * @param int   $block_id Block post ID.
	 * @param array $data     Data to update (fields, menu_order).
	 *
	 * @return bool True on success.
	 * @throws Exception If update fails.
	 */
	public function update( int $block_id, array $data ): bool {
		if ( ! $this->exists( $block_id ) ) {
			throw new Exception( esc_html_x( 'Block not found', 'Block store update error', 'uncanny-automator' ) );
		}

		$post_data = array( 'ID' => $block_id );

		if ( isset( $data['menu_order'] ) ) {
			$post_data['menu_order'] = (int) $data['menu_order'];
		}

		if ( isset( $data['status'] ) ) {
			$post_data['post_status'] = $data['status'];
		}

		$fields_changed = false;
		if ( isset( $data['fields'] ) ) {
			// Write-time invalidation (spec Stage 3): a whole-bag write has no
			// field-by-field update_post_meta() return to read, so the stored
			// bag is decoded and strict-compared against the incoming one —
			// same representation (this store's own JSON), before the write.
			// The bag's first materialization is configuration, not an edit —
			// create() skips META_FIELDS when empty, so key absence means no
			// prior values existed (spec Stage 3 first-save rule, AMENDED).
			$had_stored_bag = metadata_exists( 'post', $block_id, self::META_FIELDS );
			$stored_fields  = json_decode( (string) get_post_meta( $block_id, self::META_FIELDS, true ), true );
			$fields_changed = $had_stored_bag && ( ! is_array( $stored_fields ) || $stored_fields !== $data['fields'] );

			Recipe_Change_Record::note_bag_fields( Wire_Node_Type::BLOCK, $block_id, $this->owning_recipe_id( $block_id ), is_array( $stored_fields ) ? $stored_fields : array(), (array) $data['fields'] );

			// wp_slash: counteracts wp_unslash inside update_post_meta.
			update_post_meta( $block_id, self::META_FIELDS, wp_slash( wp_json_encode( $data['fields'] ) ) );

			// Keep the loop fields/iterable_expression pair coherent for
			// fields-only writers. An explicit expression in $data wins (the
			// loop CRUD service merges with legacy-parity rules); an empty
			// derivation (unconfigured fields) must not clobber.
			$incoming_type = (string) ( $data[ self::META_BLOCK_TYPE ] ?? get_post_meta( $block_id, self::META_BLOCK_TYPE, true ) );
			if ( ! isset( $data['iterable_expression'] ) && Block_Type::LOOP === $incoming_type ) {
				$expression = Loop_Field_Translator::from_fields( (array) $data['fields'] );
				if ( array() !== $expression ) {
					update_post_meta( $block_id, self::META_ITERABLE_EXPRESSION, wp_slash( $expression ) );
				}
			}
		}

		if ( isset( $data[ self::META_BLOCK_TYPE ] ) ) {
			update_post_meta( $block_id, self::META_BLOCK_TYPE, $data[ self::META_BLOCK_TYPE ] );
		}

		// Loop blocks persist their iteration config in `iterable_expression`
		// post-meta — the one loop-config storage both the tree and the
		// runner read.
		if ( isset( $data['iterable_expression'] ) && is_array( $data['iterable_expression'] ) ) {
			update_post_meta( $block_id, self::META_ITERABLE_EXPRESSION, wp_slash( $data['iterable_expression'] ) );
		}

		if ( isset( $data['run_on'] ) ) {
			update_post_meta( $block_id, self::META_RUN_ON, wp_slash( $data['run_on'] ) );
		}

		// Request WordPress errors: a refused update otherwise answers 0 and passes as saved.
		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to update block: %s', 'Block store update error', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}

		// Maintain path code consistency for nested blocks.
		$parent_id = (int) get_post_field( 'post_parent', $block_id );
		self::maybe_reconcile_item_path_code( $block_id, $parent_id );

		if ( $fields_changed ) {
			Dispatcher::action( 'automator_node_fields_changed', 'block', $block_id );
		}

		return true;
	}

	/**
	 * Delete a block and cascade delete all children.
	 *
	 * Deletes child filter conditions and nested child blocks recursively.
	 *
	 * @param int $block_id Block post ID.
	 *
	 * @return bool True on success.
	 * @throws Exception If delete fails.
	 */
	public function delete( int $block_id ): bool {
		if ( ! $this->exists( $block_id ) ) {
			throw new Exception( esc_html_x( 'Block not found', 'Block store delete error', 'uncanny-automator' ) );
		}

		// Delete every modifier child (filter conditions, loop filters, future kinds).
		$this->modifier_store->delete_by_block( $block_id );

		// Recursively delete nested child blocks.
		$child_blocks = $this->get_by_parent( $block_id );

		foreach ( $child_blocks as $child ) {
			$this->delete( $child['id'] );
		}

		// Delete the block post itself.
		$result = wp_delete_post( $block_id, true );

		if ( ! $result ) {
			// translators: %s is the block ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to delete block with ID: %s', 'Block store delete error', 'uncanny-automator' ), absint( $block_id ) ) );
		}

		return true;
	}

	/**
	 * Check if a block exists.
	 *
	 * @param int $block_id Block post ID.
	 *
	 * @return bool True if exists.
	 */
	public function exists( int $block_id ): bool {
		return null !== $this->get_wp_post( $block_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function adopt_post( int $post_id, string $block_type, ?int $recipe_id = null, ?string $synthesis_key = null ): void {
		if ( $post_id <= 0 || '' === $block_type ) {
			return;
		}

		$existing = get_post( $post_id );
		if ( ! $existing ) {
			return;
		}

		$rows = array( self::META_BLOCK_TYPE => $block_type );

		if ( null !== $recipe_id && $recipe_id > 0 ) {
			$rows[ self::META_RECIPE_ID ] = (string) $recipe_id;
		}

		if ( null !== $synthesis_key && '' !== $synthesis_key ) {
			$rows[ self::META_SYNTHESIS_KEY ] = $synthesis_key;
		}

		$this->adopt_rows( $existing, $rows );

		Recipe_Cache::invalidate( $recipe_id ?? $this->owning_recipe_id( $post_id ) );
	}

	/**
	 * Get all child posts of a parent, ordered by menu_order.
	 *
	 * Retrieves both block children (uo-block) and item children (uo-action)
	 * in a single query, returning each with an `entity_type` discriminator.
	 *
	 * @param int $parent_id Parent post ID (recipe or block).
	 *
	 * @return array Ordered array of child data arrays, each with `entity_type`.
	 */
	public function get_children( int $parent_id ): array {
		$posts = get_posts(
			array(
				'post_type'      => array( self::POST_TYPE, WP_Action_Store::POST_TYPE ),
				'post_parent'    => $parent_id,
				'post_status'    => Post_Status::get_all(),
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		$children = array();
		foreach ( $posts as $post ) {
			if ( $this->is_owned_post( $post ) ) {
				$children[] = $this->build_data_from_post( $post );
			} else {
				$children[] = $this->build_item_data_from_post( $post );
			}
		}

		return $children;
	}

	/**
	 * @inheritDoc
	 */
	public function collect_inner_action_ids( int $block_id ): array {
		$ids = array();
		foreach ( $this->get_children( $block_id ) as $child ) {
			if ( 'block' === ( $child['entity_type'] ?? '' ) ) {
				continue;
			}
			$id = (int) ( $child['ID'] ?? 0 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * @inheritDoc
	 */
	public function collect_descendant_action_ids( int $block_id ): array {
		$ids = array();
		foreach ( $this->get_children( $block_id ) as $child ) {
			if ( 'block' === ( $child['entity_type'] ?? '' ) ) {
				$ids = array_merge( $ids, $this->collect_descendant_action_ids( (int) ( $child['id'] ?? 0 ) ) );
				continue;
			}
			$id = (int) ( $child['ID'] ?? 0 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Set the path_code meta on a child post.
	 *
	 * @param int    $post_id   Child post ID (action or block).
	 * @param string $path_code Path code (e.g. FILTER-CONDITIONS-MET).
	 *
	 * @return void
	 */
	public function set_path_code( int $post_id, string $path_code ): void {
		update_post_meta( $post_id, self::META_PATH_CODE, $path_code );

		Recipe_Cache::invalidate( $this->owning_recipe_id( $post_id ) );
	}

	/**
	 * Reparent a child post to a new parent.
	 *
	 * Changes the child's post_parent without modifying path_code.
	 *
	 * @param int $child_id      Child post ID (action or block).
	 * @param int $new_parent_id New parent post ID (recipe or block).
	 *
	 * @return void
	 * @throws Exception If the parent update fails.
	 */
	public function reparent( int $child_id, int $new_parent_id ): void {
		$result = wp_update_post(
			array(
				'ID'          => $child_id,
				'post_parent' => $new_parent_id,
			),
			true
		);
		if ( is_wp_error( $result ) || ! $result ) {
			throw new Exception( is_wp_error( $result ) ? esc_html( $result->get_error_message() ) : esc_html_x( 'Failed to move the child to its new parent.', 'Block store update error', 'uncanny-automator' ) );
		}
	}

	/**
	 * Reparent a child post into a block and assign it to a path.
	 *
	 * @param int    $child_id  Child post ID (action or block).
	 * @param int    $block_id  Target block post ID.
	 * @param string $path_code Path code (e.g. FILTER-CONDITIONS-MET, DELAY-COMPLETED).
	 *
	 * @return void
	 * @throws Exception If the parent update fails.
	 */
	public function reparent_to_path( int $child_id, int $block_id, string $path_code ): void {
		// A refused move must not assign the destination's path to a child still under its original parent.
		$this->reparent( $child_id, $block_id );
		$this->set_path_code( $child_id, $path_code );
	}

	/**
	 * Remove the path_code meta from a child post.
	 *
	 * @param int $post_id Child post ID (action or block).
	 *
	 * @return void
	 */
	public function delete_path_code( int $post_id ): void {
		delete_post_meta( $post_id, self::META_PATH_CODE );

		Recipe_Cache::invalidate( $this->owning_recipe_id( $post_id ) );
	}

	/**
	 * All action-container ids for a recipe: every block at any nesting
	 * depth (recipe_id meta link).
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return array<int> Container post IDs.
	 */
	public function get_recipe_container_ids( int $recipe_id ): array {
		$blocks = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Blocks per recipe are bounded by the builder UI.
				'fields'         => 'ids',
				'meta_key'       => self::META_RECIPE_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $recipe_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return array_map( 'absint', $blocks );
	}

	/**
	 * {@inheritDoc}
	 */
	public function rows_in_published_recipes( string $block_type ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT b.ID            AS block_id,
				        rid.meta_value  AS recipe_id,
				        lg.meta_value   AS legacy_group_id,
				        f.meta_value    AS fields,
				        ie.meta_value   AS iterable_expression
				 FROM {$wpdb->posts} b
				 INNER JOIN {$wpdb->postmeta} bt  ON bt.post_id  = b.ID AND bt.meta_key  = %s
				 INNER JOIN {$wpdb->postmeta} rid ON rid.post_id = b.ID AND rid.meta_key = %s
				 INNER JOIN {$wpdb->posts} r      ON r.ID = rid.meta_value
				 LEFT  JOIN {$wpdb->postmeta} f   ON f.post_id   = b.ID AND f.meta_key   = %s
				 LEFT  JOIN {$wpdb->postmeta} ie  ON ie.post_id  = b.ID AND ie.meta_key  = %s
				 LEFT  JOIN {$wpdb->postmeta} lg  ON lg.post_id  = b.ID AND lg.meta_key  = %s
				 WHERE b.post_type   = %s
				   AND b.post_status = %s
				   AND bt.meta_value = %s
				   AND r.post_type   = %s
				   AND r.post_status = %s
				 ORDER BY r.ID ASC, b.ID ASC, f.meta_id ASC, ie.meta_id ASC, lg.meta_id ASC",
				self::META_BLOCK_TYPE,
				self::META_RECIPE_ID,
				self::META_FIELDS,
				self::META_ITERABLE_EXPRESSION,
				self::META_LEGACY_GROUP_ID,
				self::POST_TYPE,
				Post_Status::PUBLISH,
				$block_type,
				AUTOMATOR_POST_TYPE_RECIPE,
				Post_Status::PUBLISH
			),
			ARRAY_A
		);

		// One row per block: the first joined meta row by meta_id is the stored value.
		$output = array();
		$seen   = array();
		foreach ( (array) $rows as $row ) {
			$block_id = (int) $row['block_id'];
			if ( isset( $seen[ $block_id ] ) ) {
				continue;
			}
			$seen[ $block_id ] = true;

			$fields     = json_decode( (string) $row['fields'], true );
			$expression = automator_safe_unserialize( (string) $row['iterable_expression'] );
			$expression = is_object( $expression ) ? get_object_vars( $expression ) : $expression;

			$output[] = array(
				'block_id'            => $block_id,
				'recipe_id'           => (int) $row['recipe_id'],
				'legacy_group_id'     => (string) $row['legacy_group_id'],
				'fields'              => is_array( $fields ) ? $fields : array(),
				'iterable_expression' => is_array( $expression ) ? $expression : array(),
			);
		}

		return $output;
	}

	/**
	 * Build block data array from a WP_Post.
	 *
	 * @param WP_Post $post WordPress post.
	 *
	 * @return array Block data.
	 */
	private function build_data_from_post( WP_Post $post ): array {
		$fields_raw = get_post_meta( $post->ID, self::META_FIELDS, true );
		$fields     = is_string( $fields_raw ) ? json_decode( $fields_raw, true ) : array();

		$legacy_group_id = (string) get_post_meta( $post->ID, self::META_LEGACY_GROUP_ID, true );
		$synthesis_key   = (string) get_post_meta( $post->ID, self::META_SYNTHESIS_KEY, true );

		// `iterable_expression` lives as its own post-meta (adopt_post leaves
		// it in place; create/update keep it coherent with the fields).
		// Surface it on the row so the tree builder doesn't need a Rule-3
		// read for the LOOP case.
		$iterable_expression = get_post_meta( $post->ID, self::META_ITERABLE_EXPRESSION, true );

		return array(
			'entity_type'         => 'block',
			'id'                  => (int) $post->ID,
			'parent_id'           => (int) $post->post_parent,
			'recipe_id'           => (int) get_post_meta( $post->ID, self::META_RECIPE_ID, true ),
			'block_type'          => (string) get_post_meta( $post->ID, self::META_BLOCK_TYPE, true ),
			'path_code'           => (string) get_post_meta( $post->ID, self::META_PATH_CODE, true ),
			'fields'              => is_array( $fields ) ? $fields : array(),
			'menu_order'          => (int) $post->menu_order,
			'post_status'         => $post->post_status,
			'legacy_group_id'     => '' === $legacy_group_id ? null : $legacy_group_id,
			'synthesis_key'       => '' === $synthesis_key ? null : $synthesis_key,
			'post_type'           => $post->post_type,
			'iterable_expression' => is_array( $iterable_expression ) ? $iterable_expression : array(),
		);
	}

	/**
	 * Build item data array from a WP_Post (uo-action).
	 *
	 * Returns the flattened meta format expected by the Structure layer's
	 * Item\Action::hydrate_from() method, with an entity_type discriminator.
	 *
	 * @param WP_Post $post WordPress post.
	 *
	 * @return array Item data with entity_type 'item'.
	 */
	private function build_item_data_from_post( WP_Post $post ): array {
		$all_meta = get_post_meta( $post->ID );
		$meta     = array();

		foreach ( (array) $all_meta as $key => $value ) {
			$meta[ $key ] = reset( $value );
		}

		return array(
			'entity_type' => 'item',
			'ID'          => (int) $post->ID,
			'post_status' => $post->post_status,
			'post_parent' => (int) $post->post_parent,
			'menu_order'  => (int) $post->menu_order,
			'meta'        => $meta,
			'tokens'      => array(),
			'path_code'   => isset( $meta[ self::META_PATH_CODE ] ) ? (string) $meta[ self::META_PATH_CODE ] : '',
			'post_type'   => $post->post_type,
		);
	}

	/**
	 * Generate post title for a block.
	 *
	 * @param string $block_type Block type.
	 *
	 * @return string Generated title.
	 */
	private function generate_title( string $block_type ): string {
		return sprintf( 'Block: %s', ucfirst( str_replace( '_', ' ', $block_type ) ) );
	}

	/**
	 * Reconcile path_code meta for a child post based on its parent.
	 *
	 * Ensures path_code stays consistent with the child's current parent:
	 *
	 * - Parent is a block (uo-block): validates the existing path_code belongs
	 *   to this block type. Sets the default (first path) when missing or invalid.
	 * - Parent is not a block: removes stale path_code meta.
	 *
	 * Explicit path_code assignments (via set_path_code) that occur after
	 * this call will overwrite the default set here.
	 *
	 * @param int $child_id  Child post ID (action or nested block).
	 * @param int $parent_id Parent post ID.
	 *
	 * @return void
	 */
	public static function maybe_reconcile_item_path_code( int $child_id, int $parent_id, ?Block_Registry $registry = null ): void {
		$parent_post_type = get_post_type( $parent_id );

		if ( self::POST_TYPE === $parent_post_type ) {
			$valid_paths = self::get_valid_path_codes( $parent_id, $registry );
			if ( empty( $valid_paths ) ) {
				return;
			}

			$existing = get_post_meta( $child_id, self::META_PATH_CODE, true );
			if ( ! empty( $existing ) && in_array( $existing, $valid_paths, true ) ) {
				return;
			}

			update_post_meta( $child_id, self::META_PATH_CODE, $valid_paths[0] );

			// Bare meta write, and the tail of every create/update path that
			// reaches here — the caller's post write already fired its hook
			// before this ran, so nothing else invalidates.
			//
			// The parent normally carries the recipe stamp, but a legacy block
			// adopted before the stamp existed reads 0, and invalidating recipe
			// 0 is a silent no-op — so walk the chain in that case.
			$stamped = (int) get_post_meta( $parent_id, self::META_RECIPE_ID, true );
			Recipe_Cache::invalidate( $stamped > 0 ? $stamped : self::resolve_recipe_id_from_parent_static( $parent_id ) );
			return;
		}

		// Gate on the result: with no stamp to remove this is a no-op, and
		// every root-level save would otherwise pay a generation bump for a
		// write that never happened.
		if ( delete_post_meta( $child_id, self::META_PATH_CODE ) ) {
			// The parent is not a block, so the parent id IS the recipe.
			Recipe_Cache::invalidate( $parent_id );
		}
	}

	/**
	 * Walk `post_parent` to the owning recipe, for the static reconcile tail.
	 *
	 * Mirrors the instance method of the same intent; the reconcile is static
	 * because `WP_Action_Store` calls it without a block-store instance.
	 *
	 * @param int $parent_id Immediate parent post id.
	 *
	 * @return int Recipe post id, or 0 when unresolvable.
	 */
	private static function resolve_recipe_id_from_parent_static( int $parent_id ): int {
		$depth = 0;

		while ( $parent_id > 0 && $depth < 10 ) {
			$post = get_post( $parent_id );
			if ( ! $post instanceof WP_Post ) {
				return 0;
			}
			if ( AUTOMATOR_POST_TYPE_RECIPE === $post->post_type ) {
				return $parent_id;
			}
			$parent_id = (int) $post->post_parent;
			++$depth;
		}

		return 0;
	}

	/**
	 * Get the valid path codes for a block from the block registry.
	 *
	 * @param int                 $block_id Block post ID.
	 * @param Block_Registry|null $registry Registry to resolve the block definition
	 *                                      through. Defaults to the WordPress one;
	 *                                      passing a fake is how a test exercises the
	 *                                      default-bucket branch without standing up
	 *                                      real block definitions.
	 *
	 * @return string[] Ordered path codes, first entry is the default.
	 */
	private static function get_valid_path_codes( int $block_id, ?Block_Registry $registry = null ): array {
		$block_type = get_post_meta( $block_id, self::META_BLOCK_TYPE, true );
		if ( empty( $block_type ) ) {
			return array();
		}

		$registry   = $registry ?? new WP_Block_Registry();
		$definition = $registry->get_block_definition( $block_type );
		if ( null === $definition ) {
			return array();
		}

		$paths = $definition->get_paths();
		if ( empty( $paths ) ) {
			return array();
		}

		return array_map(
			function ( $path ) {
				return $path->get_path_code();
			},
			$paths
		);
	}
}

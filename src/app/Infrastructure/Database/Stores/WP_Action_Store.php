<?php
declare(strict_types=1);

// phpcs:disable WordPress.DB.PreparedSQL -- this store receives $wpdb by
// injection and queries through $this->wpdb. The sniff only recognises the
// global $wpdb->prepare() form, so it reports every prepared call here as
// unprepared. Every value below goes through a prepare() placeholder; the
// only interpolation is the table prefix, which cannot be a placeholder.
namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Exception;
use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Events\Recipe_Change_Record;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Action\Action;
use Uncanny_Automator\App\Recipe_Builder\Action\Action_Config;
use Uncanny_Automator\App\Recipe_Builder\Action\Enums\Action_Status;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Store;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Declared_Field_Codes_Resolver;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services\Node_Test_Run_Store;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Json_String_Repair;
use Uncanny_Automator\App\Infrastructure\Database\Stores\Traits\Item_Usage_Counts;

/**
 * WordPress Action Store.
 *
 * WordPress implementation of action store using uo-action post type.
 * Stores action instances as posts with meta for all properties.
 *
 * @since 7.0.0
 */
class WP_Action_Store extends WP_Post_Store implements Action_Store {

	use Item_Usage_Counts;

	/**
	 * Post type for actions.
	 */
	const POST_TYPE = AUTOMATOR_POST_TYPE_ACTION;

	/**
	 * WordPress database object.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Save action to database.
	 *
	 * @param Action $action Action to save.
	 * @return Action The saved Action with ID and all persisted values.
	 * @throws \Exception If save fails.
	 */
	public function save( Action $action ): Action {

		if ( $action->is_persisted() ) {
			return $this->update_action( $action );
		}

		return $this->create_action( $action );
	}

	/**
	 * Get action by ID.
	 *
	 * @param int $action_id Action ID.
	 * @return Action|null Action or null if not found.
	 */
	public function get( int $action_id ): ?Action {
		$post = $this->get_wp_post( $action_id );

		if ( ! $post ) {
			return null;
		}

		return $this->build_action_from_post( $post );
	}

	/**
	 * Delete action.
	 *
	 * @param Action $action Action to delete.
	 * @return void
	 * @throws \Exception If delete fails.
	 */
	public function delete( Action $action ): void {
		if ( ! $action->is_persisted() ) {
			throw new Exception( esc_html_x( 'Cannot delete unsaved action', 'Action store delete error', 'uncanny-automator' ) );
		}

		$this->delete_by_id(
			(int) $action->get_action_id()->get_value(),
			(int) $action->get_action_recipe_id()->get_value()
		);
	}

	/**
	 * Delete action by ID.
	 *
	 * Primitive delete that skips `get()` and its cache round-trip — notably for
	 * the delete transport, where stale WP post caches have previously surfaced
	 * as false negatives. It reads the post type from the table instead, so an
	 * id that is not an action is refused rather than permanently deleted.
	 *
	 * @param int $action_id Action post ID.
	 * @param int $recipe_id Owning recipe ID. Unused — `after_delete_post` covers
	 *                       this delete. Kept because it is on the `Action_Store`
	 *                       interface.
	 * @return void
	 * @throws \Exception If the id is not an action, or wp_delete_post fails.
	 */
	public function delete_by_id( int $action_id, int $recipe_id = 0 ): void {
		// The type comes from the table, not the post cache, so a stale cache neither blocks a real
		// action nor lets any other post through: this delete is permanent.
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the current post type before a permanent delete, bypassing stale post caches.
		$post_type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $action_id ) );
		if ( self::POST_TYPE !== $post_type ) {
			throw new Exception(
				sprintf(
					/* translators: %s is the action ID. */
					esc_html_x( 'No action found with ID: %s', 'Action store delete error with ID', 'uncanny-automator' ),
					absint( $action_id )
				)
			);
		}

		$result = wp_delete_post( $action_id, true );

		if ( ! $result ) {
			throw new Exception(
				sprintf(
					/* translators: %s is the action ID. */
					esc_html_x( 'Failed to delete action with ID: %s', 'Action store delete error with ID', 'uncanny-automator' ),
					absint( $action_id )
				)
			);
		}
	}

	/**
	 * Get all actions with optional filters.
	 *
	 * @param array $filters Optional filters (recipe_id, integration, limit, etc.).
	 * @return Action[] Array of actions.
	 */
	public function all( array $filters = array() ): array {

		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( Action_Status::PUBLISH, Action_Status::DRAFT ),
			'posts_per_page' => $filters['limit'] ?? 100, // Set reasonable default instead of -1
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		// Include actions that are direct children of the recipe AND children of
		// any container (blocks nest arbitrarily).
		if ( ! empty( $filters['recipe_id'] ) ) {
			$recipe_id  = absint( $filters['recipe_id'] );
			$parent_ids = array_merge(
				array( $recipe_id ),
				Database::get_block_store()->get_recipe_container_ids( $recipe_id )
			);

			$args['post_parent__in'] = $parent_ids;
		}

		// Add meta queries for filters
		$meta_queries = array();

		if ( ! empty( $filters['integration'] ) ) {
			$meta_queries[] = array(
				'key'     => 'integration',
				'value'   => $filters['integration'],
				'compare' => '=',
			);
		}

		if ( ! empty( $meta_queries ) ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				...$meta_queries,
			);
		}

		$posts = get_posts( $args );

		// Error handling
		if ( is_wp_error( $posts ) ) {
			return array();
		}

		$actions = array();

		foreach ( $posts as $post ) {
			$action = $this->build_action_from_post( $post );
			if ( $action ) {
				$actions[] = $action;
			}
		}

		return $actions;
	}

	/**
	 * Get actions for a specific recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return Action[] Array of actions for the recipe.
	 */
	public function get_recipe_actions( int $recipe_id ): array {
		return $this->all( array( 'recipe_id' => $recipe_id ) );
	}

	/**
	 * @inheritDoc
	 */
	public function get_top_level_recipe_actions( int $recipe_id ): array {
		// Strict direct-children query — distinct from get_recipe_actions(),
		// which spans loop descendants and orders by ID.
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_parent'    => $recipe_id,
				'post_status'    => array( Action_Status::PUBLISH, Action_Status::DRAFT ),
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Top-level actions per recipe are bounded by the builder UI; no realistic cap needed.
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		$actions = array();
		foreach ( $posts as $post ) {
			$action = $this->build_action_from_post( $post );
			if ( null !== $action ) {
				$actions[] = $action;
			}
		}

		return $actions;
	}

	/**
	 * Get actions by integration.
	 *
	 * @param string $integration Integration code (e.g., 'WP', 'WC').
	 * @return Action[] Array of actions from the integration.
	 */
	public function get_by_integration( string $integration ): array {
		return $this->all( array( 'integration' => $integration ) );
	}

	/**
	 * Next append position among the parent's ordered children.
	 *
	 * The reorder service stamps explicit menu_order values (10, 20, …) on
	 * every reorder, so leaving wp_insert_post's default of 0 would sort a
	 * freshly created action to the top of the flow. Blocks and actions
	 * share one menu_order sequence per parent (see
	 * Block_Crud_Service::get_next_menu_order(), which does the same walk
	 * for new blocks).
	 *
	 * @param int $parent_id Recipe or block post ID the action is created under.
	 * @return int Menu order placing the new action after existing siblings.
	 */
	private function next_menu_order( int $parent_id ): int {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WordPress has no API for the highest menu_order among siblings; a cached value would place the new action out of order.
		$max_order = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(menu_order) FROM `{$wpdb->posts}`
				WHERE post_parent = %d
				AND post_type IN (%s, %s)
				AND post_status IN (%s, %s)",
				$parent_id,
				self::POST_TYPE,
				WP_Block_Store::POST_TYPE,
				Action_Status::PUBLISH,
				Action_Status::DRAFT
			)
		);

		if ( '' !== $this->wpdb->last_error ) {
			throw new Exception( 'Failed to read the destination action order.' );
		}

		return (int) $max_order + 1;
	}

	/**
	 * Create new action.
	 *
	 * @param Action $action Action to create.
	 * @return Action The created Action with generated ID.
	 * @throws \Exception If creation fails.
	 */
	private function create_action( Action $action ): Action {
		$action_data = $action->to_array();

		// Determine post_parent: use parent_id if explicitly set, otherwise use recipe_id
		$parent_id = null !== $action->get_parent_id() && null !== $action->get_parent_id()->get_value()
			? $action->get_parent_id()->get_value()
			: $action_data['recipe_id'];

		// Create post
		$post_data = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => $this->generate_action_title( $action ),
			'post_parent' => $parent_id,
			'post_status' => $action->get_status() ? $action->get_status()->get_value() : Action_Status::DRAFT,
			'menu_order'  => $this->next_menu_order( (int) $parent_id ),
			'meta_input'  => $this->prepare_meta_data( $action ),
		);

		$action_id = wp_insert_post( $post_data );

		if ( is_wp_error( $action_id ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to create action: %s', 'Action store creation error with message', 'uncanny-automator' ), esc_html( $action_id->get_error_message() ) ) );
		}

		if ( ! $action_id ) {
			throw new Exception( esc_html_x( 'Failed to create action: unknown error', 'Action store creation unknown error', 'uncanny-automator' ) );
		}

		// Maintain path code consistency.
		$this->persist_path_code( $action, (int) $action_id, (int) $parent_id );

		// Reload and return the persisted action with all values
		$persisted_action = $this->get( $action_id );

		if ( null === $persisted_action ) {
			// translators: %s is the action ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to reload action after creation: %s', 'Action store reload error', 'uncanny-automator' ), absint( $action_id ) ) );
		}

		return $persisted_action;
	}

	/**
	 * Update existing action.
	 *
	 * @param Action $action Action to update.
	 * @return Action The updated Action with all persisted values.
	 * @throws \Exception If update fails.
	 */
	private function update_action( Action $action ): Action {
		$action_data = $action->to_array();
		$action_id   = $action_data['action_id'];

		// Determine post_parent: use parent_id if explicitly set, otherwise use recipe_id
		$parent_id = null !== $action->get_parent_id() && null !== $action->get_parent_id()->get_value()
			? $action->get_parent_id()->get_value()
			: $action_data['recipe_id'];

		// Update post
		$post_data = array(
			'ID'          => $action_id,
			'post_title'  => $this->generate_action_title( $action ),
			'post_status' => $action->get_status() ? $action->get_status()->get_value() : Action_Status::DRAFT,
			'post_parent' => $parent_id,
		);

		$existing_post = get_post( $action_id );
		if ( $existing_post && (int) $existing_post->post_parent !== (int) $parent_id ) {
			$post_data['menu_order'] = $this->next_menu_order( (int) $parent_id );
		}

		// Request WordPress errors: a refused update otherwise answers 0 and passes as saved.
		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to update action: %s', 'Action store update error with message', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}

		// Update meta data
		$meta_data       = $this->prepare_meta_data( $action );
		$stored          = get_post_meta( $action_id );
		$candidate_edits = array();
		$written         = array();
		foreach ( $meta_data as $key => $value ) {
			// A value the save left as it was read goes unwritten: it was read still serialized
			// (an array, an object), and writing it back would serialize it again.
			if ( isset( $stored[ $key ] ) && array( $value ) === $stored[ $key ] ) {
				continue;
			}

			// A key's first materialization is configuration, not an edit —
			// only a key that already had a stored value can be a candidate
			// (spec Stage 3, AMENDED 2026-08-12).
			$existed = metadata_exists( 'post', $action_id, $key );
			$changed = update_post_meta( $action_id, $key, $value );
			if ( false !== $changed ) {
				$written[] = $key;
			}
			if ( $existed && false !== $changed ) {
				$candidate_edits[] = $key;
			}
		}

		Recipe_Change_Record::note_item_fields(
			Integration_Item_Types::ACTION,
			(int) $action_id,
			(int) $action_data['recipe_id'],
			$action->get_action_integration_code()->get_value(),
			$action->get_action_code()->get_value(),
			$written
		);

		// Declared codes resolve lazily — only a save that actually edited
		// stored values pays the definition lookup.
		if ( ! empty( $candidate_edits )
			&& array_intersect( $candidate_edits, $this->declared_field_codes( $action, $meta_data ) ) ) {
			Dispatcher::action( 'automator_node_fields_changed', 'item', (int) $action_id );
		}

		// Maintain path code consistency.
		$this->persist_path_code( $action, (int) $action_id, (int) $parent_id );

		// The field meta above lands AFTER wp_update_post, so the hook's
		// invalidation ran against the pre-edit values. Without this second
		// bump a reader that rebuilt in between persists that stale tree and
		// nothing clears it. See Store_Write_Ordering_Test.
		Recipe_Cache::invalidate( $action->get_action_recipe_id()->get_value() );

		// Reload and return the updated action
		$updated_action = $this->get( $action_id );

		if ( null === $updated_action ) {
			// translators: %s is the action ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to reload action after update: %s', 'Action store reload error', 'uncanny-automator' ), absint( $action_id ) ) );
		}

		return $updated_action;
	}

	/**
	 * The action's declared field codes — the allow-list write-time
	 * invalidation compares candidate edits against (spec Stage 3, AMENDED
	 * 2026-08-12). The aggregate carries the identity, so callers never
	 * supply this; resolution is memoized per request by the registry and
	 * only ever invoked when a save produced candidate edits.
	 *
	 * @param Action               $action        Action being saved.
	 * @param array<string, mixed> $incoming_meta Meta map being written — degraded fallback source.
	 * @return array<string>
	 */
	private function declared_field_codes( Action $action, array $incoming_meta ): array {
		return ( new Declared_Field_Codes_Resolver() )->resolve(
			$action->get_action_integration_code()->get_value(),
			Integration_Item_Types::ACTION,
			$action->get_action_code()->get_value(),
			$incoming_meta
		);
	}

	/**
	 * Persist the child's path_code from the aggregate.
	 *
	 * Explicit aggregate path_code wins (stamped via the block store);
	 * otherwise fall back to the default-or-clear reconcile. Single write
	 * site for an action's path_code.
	 *
	 * @param Action $action    Saved action (carries the explicit path, if any).
	 * @param int    $action_id Persisted action post id.
	 * @param int    $parent_id Resolved post_parent.
	 * @return void
	 */
	private function persist_path_code( Action $action, int $action_id, int $parent_id ): void {
		$path = $action->get_path_code();
		if ( null !== $path ) {
			Database::get_block_store()->set_path_code( $action_id, $path->get_value() );
			return;
		}
		WP_Block_Store::maybe_reconcile_item_path_code( $action_id, $parent_id );
	}

	/**
	 * Build action from WordPress post.
	 *
	 * @param \WP_Post $post WordPress post.
	 * @return Action|null Action or null if invalid.
	 */
	public function build_action_from_post( \WP_Post $post ): ?Action {
		// Validate post object has required properties
		if ( ! isset( $post->ID ) ) {
			return null;
		}

		// Get all post meta for this action
		$all_meta = get_post_meta( $post->ID );

		// Build meta array from all post meta fields
		$meta = array();

		// Core fields that shouldn't go into meta (they're handled separately).
		// Node_Test_Run_Store::META_KEY / ::PENDING_META_KEY (run-state postmeta)
		// are excluded for a second reason: get_post_meta() with no $meta_key
		// returns the raw, still-serialized column value, so an array-valued key
		// round-tripped through this bag and back out via prepare_meta_data()'s
		// update_post_meta() loop gets serialized a second time (WordPress
		// re-serializes a string that already looks serialized). Excluding the
		// key here is what keeps it out of that bag entirely.
		$core_fields = array( 'integration', 'code', 'meta_code', 'user_type', 'parent_id', 'status', 'recipe_id', WP_Block_Store::META_PATH_CODE, Node_Test_Run_Store::META_KEY, Node_Test_Run_Store::PENDING_META_KEY );

		foreach ( $all_meta as $key => $value_array ) {
			// Skip core fields - they're handled separately
			if ( in_array( $key, $core_fields, true ) ) {
				continue;
			}

			// WordPress stores meta as arrays, get first value
			$meta[ $key ] = $value_array[0] ?? '';

			if ( Json_String_Repair::looks_like_structured_json( $meta[ $key ] ) ) {
				$meta[ $key ] = Json_String_Repair::repair( $meta[ $key ] );
			}
		}

		// Status, parent, and recipe id derive from the post object — matching
		// Trigger — so legacy post-meta artifacts (e.g. a 'live' status
		// the VO rejects) are ignored and the action still hydrates.
		$integration_code = get_post_meta( $post->ID, 'integration', true );
		$code             = get_post_meta( $post->ID, 'code', true );
		$meta_code        = get_post_meta( $post->ID, 'meta_code', true );
		$user_type        = get_post_meta( $post->ID, 'user_type', true );

		// recipe_id: walk post ancestry to the owning uo-recipe. Block wrappers
		// (loop/filter/delay) sit between the action and its recipe.
		$resolved_recipe_id = $this->find_ancestor_recipe_id( (int) $post->post_parent );

		// status: constrain post_status to the valid action statuses; anything
		// else (auto-draft, trash, …) hydrates as draft.
		$status = in_array( $post->post_status, Action_Status::get_all(), true )
			? $post->post_status
			: Action_Status::DRAFT;

		$config = ( new Action_Config() )
			->id( (int) $post->ID )
			->recipe_id( $resolved_recipe_id )
			->integration_code( ! empty( $integration_code ) ? $integration_code : '' )
			->code( ! empty( $code ) ? $code : '' )
			->meta_code( ! empty( $meta_code ) ? $meta_code : '' )
			->user_type( ! empty( $user_type ) ? $user_type : 'user' )
			->status( $status )
			->meta( $meta );

		// parent_id: post_parent is the authoritative parent (recipe or block wrapper).
		if ( ! empty( $post->post_parent ) && $post->post_parent > 0 ) {
			$config->parent_id( new Recipe_Id( (int) $post->post_parent ) );
		}

		// path_code: dedicated branch placement, read from the block-store meta key.
		$persisted_path = get_post_meta( $post->ID, WP_Block_Store::META_PATH_CODE, true );
		if ( is_string( $persisted_path ) && Block_Path_Code::is_valid( $persisted_path ) ) {
			$config->path_code( $persisted_path );
		}

		try {
			return new Action( $config );
		} catch ( \Exception $e ) {
			// Skip invalid action.
			automator_log( sprintf( 'Failed to hydrate action %d from post: %s', $post->ID, $e->getMessage() ), 'WP Action Store Error' );
			return null;
		}
	}

	/**
	 * Prepare meta data for saving.
	 *
	 * @param Action $action Action entity.
	 * @return array Meta data array.
	 */
	public function prepare_meta_data( Action $action ): array {
		$action_data = $action->to_array();

		// Core fields that must be saved as post meta for legacy compatibility.
		// No `status` copy of the post status: nothing reads it, and postmeta keys
		// match case-insensitively, so it would overwrite a field coded STATUS.
		$meta_data = array(
			'integration' => $action->get_action_integration_code()->get_value(),
			'code'        => $action->get_action_code()->get_value(),
			'user_type'   => $action->get_action_type()->get_value(),
			'recipe_id'   => $action->get_action_recipe_id()->get_value(),
		);

		// Add sentence fields from action data
		if ( ! empty( $action_data['sentence_human_readable'] ) ) {
			$meta_data['sentence_human_readable'] = $action_data['sentence_human_readable'];
		}

		if ( ! empty( $action_data['sentence_human_readable_html'] ) ) {
			$meta_data['sentence_human_readable_html'] = $action_data['sentence_human_readable_html'];
		}

		// Persist configured fields only; the legacy complete config invents empty slots for unsaved fields.
		$fields = $action->get_saved_fields();
		if ( ! empty( $fields ) ) {
			foreach ( $fields as $key => $value ) {
				// Save all config fields as individual post meta entries
				$meta_data[ $key ] = Json_String_Repair::slash_for_storage( $value );
			}
		}

		return $meta_data;
	}

	/**
	 * Generate post title for action.
	 *
	 * @param Action $action Action entity.
	 * @return string Generated title.
	 */
	private function generate_action_title( Action $action ): string {

		$code = $action->get_action_code()->get_value();
		$meta = $action->get_action_meta()->get_value();

		// translators: %s is the action code.
		$sentence_human_readable = $meta['sentence_human_readable'] ?? sprintf( esc_html_x( 'Action: %s', 'Default action title with code', 'uncanny-automator' ), esc_html( $code ) );
		$nice_title              = preg_replace( '/{{([^:}]+):[^}]+}}/', '{{$1}}', $sentence_human_readable );

		return str_replace( array( '{', '}' ), '', $nice_title );
	}

	/**
	 * Walk the post_parent chain to find the ancestor uo-recipe post.
	 *
	 * Authoritative recipe resolution: block wrappers (loop/filter/delay) sit
	 * between the action and its uo-recipe, so the owning recipe is found by
	 * walking ancestry rather than trusting a (possibly stale) recipe_id meta.
	 * Uses get_post() which leverages the WP object cache.
	 *
	 * @param int $parent_id Starting parent ID.
	 * @return int The recipe post ID, or the original parent_id as fallback.
	 */
	private function find_ancestor_recipe_id( int $parent_id ): int {
		$max_depth = 20;
		$current   = $parent_id;

		while ( $max_depth-- > 0 && $current > 0 ) {
			$post = get_post( $current );
			if ( ! $post ) {
				return $parent_id; // Fallback to original parent.
			}
			if ( 'uo-recipe' === $post->post_type ) {
				return (int) $post->ID;
			}
			$current = (int) $post->post_parent;
		}

		return $parent_id; // Safety fallback.
	}


	/**
	 * Container-aware action ids for a recipe, filtered by post status.
	 *
	 * Covers direct recipe children AND actions inside any container
	 * (blocks nest arbitrarily).
	 *
	 * @param int      $recipe_id Recipe post ID.
	 * @param string[] $statuses  Post statuses to include.
	 * @return array<int> Action post IDs.
	 */
	public function get_recipe_action_ids( int $recipe_id, array $statuses ): array {
		$parent_ids = array_merge(
			array( $recipe_id ),
			Database::get_block_store()->get_recipe_container_ids( $recipe_id )
		);

		$ids = get_posts(
			array(
				'post_type'       => self::POST_TYPE,
				'post_parent__in' => $parent_ids,
				'post_status'     => $statuses,
				'posts_per_page'  => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Actions per recipe are bounded by the builder UI.
				'fields'          => 'ids',
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Container-aware id => code map for a recipe's actions, read raw.
	 *
	 * Deliberately NO aggregate hydration: safety scans (the user-selector
	 * publish gate) must see a row's code even when the row would fail
	 * domain validation.
	 *
	 * @param int      $recipe_id Recipe post ID.
	 * @param string[] $statuses  Post statuses to include.
	 * @return array<int, string> Action ID => action code (may be empty string).
	 */
	public function get_recipe_action_codes( int $recipe_id, array $statuses ): array {
		$codes = array();

		foreach ( $this->get_recipe_action_ids( $recipe_id, $statuses ) as $action_id ) {
			$codes[ $action_id ] = (string) get_post_meta( $action_id, 'code', true );
		}

		return $codes;
	}

	/**
	 * Raw field values for one action: every non-internal meta value.
	 *
	 * Deliberately NO aggregate hydration — token safety scans want raw
	 * visibility, not domain validity. Internal (underscore-prefixed)
	 * keys are skipped; the first meta value per key is returned.
	 *
	 * @param int $action_id Action post ID.
	 * @return array<string, mixed> Meta key => first value.
	 */
	public function get_action_field_values( int $action_id ): array {
		$meta = get_post_meta( $action_id );
		if ( empty( $meta ) || ! is_array( $meta ) ) {
			return array();
		}

		$values = array();
		foreach ( $meta as $key => $meta_values ) {
			if ( 0 === strpos( $key, '_' ) ) {
				continue;
			}
			$values[ $key ] = $meta_values[0] ?? '';
		}

		return $values;
	}

	/**
	 * Deprecated one-line accessor retained for 8.0.0.
	 *
	 * Its callers (the recipe-item REST classes) move off it in a later PR;
	 * deleting it here would break them in the interim. Removed in the
	 * post-8.0.0 cleanup pass together with those callers.
	 *
	 * @deprecated 8.0.0
	 */
	public function get_post_type(): string {
		return self::POST_TYPE;
	}
}

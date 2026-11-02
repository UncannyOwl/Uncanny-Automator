<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Stores\Recipe_Schema_Store;
use WP_Post;

/**
 * WP-native safety net for the recipe schema cache: invalidate, never rebuild.
 *
 * The stores invalidate the cached tree only where no hook covers the write
 * (meta-only writes, every `uo-recipe` write, and any method whose LAST write
 * is meta — the hook fired earlier, against pre-edit values)
 * (`Recipe_Cache::invalidate()`, which fans out to
 * `Recipe_Schema_Store::invalidate()`), and `Recipe_Schema_Reader` rebuilds
 * on the next read. This class covers the writes that bypass the stores —
 * legacy `src/core` paths, migrations, wp-admin trash, third-party code — by
 * invalidating on `save_post_<child>`, `transition_post_status` (recipes) and
 * `after_delete_post` (children). N invalidations per request are cheap and
 * idempotent; nothing rebuilds at save time, so the order of meta and post
 * writes no longer matters.
 *
 * Residual, documented: a meta-only write from outside the stores is
 * invisible to WordPress hooks and stays invisible here.
 */
final class Recipe_Schema_Lifecycle {

	/**
	 * Child post types whose save and delete invalidate their owning recipe.
	 *
	 * @var string[]
	 */
	private const CHILD_POST_TYPES = array(
		AUTOMATOR_POST_TYPE_ACTION,
		AUTOMATOR_POST_TYPE_TRIGGER,
		AUTOMATOR_POST_TYPE_BLOCK,
		AUTOMATOR_POST_TYPE_BLOCK_MODIFIER,
	);

	/**
	 * Safety ceiling for the parent-chain ascent; only guards a corrupted parent cycle.
	 *
	 * @var int
	 */
	private const MAX_PARENT_DEPTH = 10;

	/**
	 * Where the tree is invalidated.
	 *
	 * @var Recipe_Schema_Store
	 */
	private Recipe_Schema_Store $store;

	/**
	 * Wire the safety net to the store it invalidates through.
	 *
	 * @param Recipe_Schema_Store|null $store Schema store; defaults to the shared instance.
	 */
	public function __construct( ?Recipe_Schema_Store $store = null ) {
		$this->store = $store ?? Recipe_Schema_Store::instance();
	}

	/**
	 * Subscribe to the three WP-native hooks.
	 *
	 * Not idempotent at the WP level; call once per request (Recipe_Schema_Bootstrap guards this).
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'transition_post_status', array( $this, 'on_post_status_change' ), 10, 3 );
		add_action( 'after_delete_post', array( $this, 'on_after_delete_post' ), 10, 2 );

		foreach ( self::CHILD_POST_TYPES as $child_post_type ) {
			add_action( 'save_post_' . $child_post_type, array( $this, 'on_child_post_saved' ), 10, 3 );
		}
	}

	/**
	 * Invalidate the owning recipe on `save_post_<child_post_type>`.
	 *
	 * Skips revisions, autosaves and auto-drafts — none of them is a recipe-graph mutation.
	 *
	 * @param int   $post_id Saved post id.
	 * @param mixed $post    WP_Post as WordPress passes it; anything else is ignored.
	 * @param bool  $update  True when an existing post is being updated.
	 *
	 * @return void
	 */
	public function on_child_post_saved( int $post_id, $post, bool $update = false ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$this->store->invalidate( $this->resolve_owning_recipe_id( $post ) );
	}

	/**
	 * Invalidate on a recipe status change.
	 *
	 * No-op for other post types and for an unchanged status.
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Previous post status.
	 * @param mixed  $post       WP_Post as WordPress passes it; anything else is ignored.
	 *
	 * @return void
	 */
	public function on_post_status_change( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || AUTOMATOR_POST_TYPE_RECIPE !== $post->post_type ) {
			return;
		}
		if ( $new_status === $old_status ) {
			return;
		}

		$this->store->invalidate( (int) $post->ID );
	}

	/**
	 * Invalidate the owning recipe after a child post is deleted.
	 *
	 * `after_delete_post`, not `before_delete_post`: the parents still exist for the chain walk, and a rebuild by the next read no longer sees the row. Recipe deletes need nothing: the schema is the recipe's own post meta and goes with the post.
	 *
	 * @param int   $post_id Deleted post id.
	 * @param mixed $post    WP_Post as WordPress passes it post-deletion; anything else is ignored.
	 *
	 * @return void
	 */
	public function on_after_delete_post( int $post_id, $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! in_array( (string) $post->post_type, self::CHILD_POST_TYPES, true ) ) {
			return;
		}

		$this->store->invalidate( $this->resolve_owning_recipe_id( $post ) );
	}

	/**
	 * Resolve the owning recipe of a saved or deleted child.
	 *
	 * `uo-block` / `uo-block-modifier` rows carry a `recipe_id` stamp — depth-proof, preferred. Everything else (and deletes, whose stamp is already gone) ascends the parent chain through `uo-block` containers.
	 *
	 * @param WP_Post $post Saved or deleted child post.
	 *
	 * @return int Recipe post id, or 0 when unresolvable.
	 */
	private function resolve_owning_recipe_id( WP_Post $post ): int {
		$stamped = $this->stamped_recipe_id( $post );
		if ( $stamped > 0 ) {
			return $stamped;
		}

		$parent_id = (int) $post->post_parent;
		$depth     = 0;

		while ( $parent_id > 0 && $depth < self::MAX_PARENT_DEPTH ) {
			$parent = get_post( $parent_id );
			if ( ! $parent instanceof WP_Post ) {
				return 0;
			}
			if ( AUTOMATOR_POST_TYPE_RECIPE === $parent->post_type ) {
				return $parent_id;
			}
			if ( AUTOMATOR_POST_TYPE_BLOCK !== $parent->post_type ) {
				return 0;
			}
			$parent_id = (int) $parent->post_parent;
			++$depth;
		}

		return 0;
	}

	/**
	 * Read the `recipe_id` stamp through the store that owns it.
	 *
	 * @param WP_Post $post Saved or deleted child post.
	 *
	 * @return int Recipe post id; 0 for unstamped post types and for deleted rows.
	 */
	private function stamped_recipe_id( WP_Post $post ): int {
		if ( AUTOMATOR_POST_TYPE_BLOCK === $post->post_type ) {
			$row = Database::get_block_store()->get( (int) $post->ID );
			return is_array( $row ) ? (int) ( $row['recipe_id'] ?? 0 ) : 0;
		}
		if ( AUTOMATOR_POST_TYPE_BLOCK_MODIFIER === $post->post_type ) {
			$row = Database::get_block_modifier_store()->get( (int) $post->ID );
			return is_array( $row ) ? (int) ( $row['recipe_id'] ?? 0 ) : 0;
		}

		return 0;
	}
}

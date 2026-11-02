<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Legacy_Meta_Store;
use Uncanny_Automator\Json_String_Repair;

/**
 * Post-meta implementation of {@see Recipe_Legacy_Meta_Store}.
 *
 * Every write here is meta-only, which no schema-lifecycle hook hears, so each one calls
 * {@see Recipe_Cache::invalidate()} itself.
 *
 * The only place in `src/app/` that reads or writes the legacy `uo-recipe`
 * meta keys (and stashes an imported action's `async_*` delay keys), so the
 * block migrators, the old-format importer and the export adapter never
 * touch the WordPress post-meta API directly.
 */
final class WP_Recipe_Legacy_Meta_Store implements Recipe_Legacy_Meta_Store {

	/**
	 * {@inheritDoc}
	 */
	public function get_source( int $recipe_id, string $meta_key ) {
		if ( $recipe_id <= 0 || '' === $meta_key ) {
			return '';
		}

		return get_post_meta( $recipe_id, $meta_key, true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function move_source_to_backup( int $recipe_id, string $source_key, string $backup_key ): void {
		if ( $recipe_id <= 0 || '' === $source_key || '' === $backup_key ) {
			return;
		}

		$existing = get_post_meta( $recipe_id, $source_key, true );

		if ( empty( $existing ) ) {
			return;
		}

		// Idempotent short-circuit: if backup already matches the source,
		// the move is half-done from a previous run (likely the source-delete
		// was the last failed step). Just retry the delete.
		$current_backup = get_post_meta( $recipe_id, $backup_key, true );
		if ( $current_backup === $existing ) {
			delete_post_meta( $recipe_id, $source_key );
			Recipe_Cache::invalidate( $recipe_id );
			return;
		}

		// update_post_meta() unslashes; the stored backup must equal the stored source.
		update_post_meta( $recipe_id, $backup_key, wp_slash( $existing ) );

		// Verify the backup landed before deleting the source — guards
		// against partial-failure data loss. If the verify read disagrees
		// with what we just wrote (e.g. an exotic filter mutated the
		// stored value, or the write silently failed), keep the source.
		// Next migration pass will retry the move idempotently.
		$verified = get_post_meta( $recipe_id, $backup_key, true );
		if ( $verified !== $existing ) {
			automator_log(
				sprintf(
					'move_source_to_backup verify failed for recipe %d (%s → %s); leaving source intact for retry',
					$recipe_id,
					$source_key,
					$backup_key
				)
			);
			Recipe_Cache::invalidate( $recipe_id );
			return;
		}

		delete_post_meta( $recipe_id, $source_key );
		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function has_flag( int $recipe_id, string $flag_key ): bool {
		if ( $recipe_id <= 0 || '' === $flag_key ) {
			return false;
		}

		return (bool) get_post_meta( $recipe_id, $flag_key, true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_flag( int $recipe_id, string $flag_key ): void {
		if ( $recipe_id <= 0 || '' === $flag_key ) {
			return;
		}

		update_post_meta( $recipe_id, $flag_key, 1 );
		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function clear_flag( int $recipe_id, string $flag_key ): void {
		if ( $recipe_id <= 0 || '' === $flag_key ) {
			return;
		}

		delete_post_meta( $recipe_id, $flag_key );
		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function stash_actions_conditions( int $recipe_id, string $json ): void {
		// update_post_meta() unslashes, so escaped inner quotes must be pre-slashed
		// or the migrator's json_decode() of this key returns null.
		update_post_meta( $recipe_id, 'actions_conditions', Json_String_Repair::slash_for_storage( $json ) );
		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function stash_action_async_meta( int $recipe_id, int $action_id, array $meta ): void {
		foreach ( $meta as $key => $value ) {
			update_post_meta( $action_id, (string) $key, wp_slash( $value ) );
		}

		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function stash_user_selector_meta( int $recipe_id, string $source, array $fields ): void {
		update_post_meta( $recipe_id, 'source', $source );
		update_post_meta( $recipe_id, 'fields', $fields );
		Recipe_Cache::invalidate( $recipe_id );
	}
}

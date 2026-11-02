<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Exception;
use WP_Post;

/**
 * Base class for the CPT-backed recipe-entity stores.
 *
 * Closed family: WP_Recipe_Store, WP_Recipe_Trigger_Store, WP_Action_Store,
 * WP_Block_Store, WP_Block_Modifier_Store. Each subclass
 * MUST declare `const POST_TYPE` with its post type — the helpers here
 * resolve it late-statically. (PHP 7.4 floor: the language cannot enforce
 * the constant; a missing declaration fatals loudly on first use.)
 */
abstract class WP_Post_Store {

	/**
	 * Fetch the store's WP_Post for an id, or null when the post is missing
	 * or belongs to a different post type.
	 *
	 * @param int $post_id Post id.
	 * @return WP_Post|null
	 */
	public function get_wp_post( int $post_id ): ?WP_Post {
		$post = get_post( $post_id );

		if ( ! $post || static::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Read a single meta value off one of this store's posts.
	 *
	 * Scoped to `static::POST_TYPE`: a post of any other type reads as the
	 * default, so a caller holding the wrong store cannot silently pull a
	 * neighbouring entity's meta.
	 *
	 * @param int    $post_id       Post id.
	 * @param string $key           Meta key.
	 * @param mixed  $default_value Returned when the post is not this store's, or the value is empty.
	 *                              `get_post_meta( …, true )` collapses unset, '', null and false to
	 *                              '' — use `metadata_exists()` if that distinction matters. A stored
	 *                              '0' is kept, not defaulted.
	 * @return mixed
	 */
	public function get_meta( int $post_id, string $key, $default_value = '' ) {
		if ( ! $this->is_owned_post( $post_id ) ) {
			return $default_value;
		}

		$value = get_post_meta( $post_id, $key, true );

		return '' === $value ? $default_value : $value;
	}

	/**
	 * Write a single meta value onto one of this store's posts.
	 *
	 * Scoped to `static::POST_TYPE` like get_meta(): a post of any other
	 * type is refused so a caller holding the wrong store cannot stamp a
	 * neighbouring entity's meta.
	 *
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool True when the write was issued, false when the post is not this store's.
	 */
	public function update_meta( int $post_id, string $key, $value ): bool {
		if ( ! $this->is_owned_post( $post_id ) ) {
			return false;
		}

		update_post_meta( $post_id, $key, $value );

		return true;
	}

	/**
	 * Delete a single meta key off one of this store's posts.
	 *
	 * Scoped to `static::POST_TYPE` like get_meta()/update_meta(). Deleting
	 * an absent key is a harmless no-op.
	 *
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 * @return bool True when the delete was issued, false when the post is not this store's.
	 */
	public function delete_meta( int $post_id, string $key ): bool {
		if ( ! $this->is_owned_post( $post_id ) ) {
			return false;
		}

		delete_post_meta( $post_id, $key );

		return true;
	}

	/**
	 * Store an adopted post's rows, then rename it to this store's type, verifying each write.
	 *
	 * The post keeps its legacy type until every row is stored, so a refused write never leaves a
	 * renamed post without its rows. `update_post_meta()` returns false for an unchanged value as well
	 * as for a refused write, and `wp_update_post()` does not report a rename a filter undid, so both
	 * are read back. A post already of this type is not updated again: `wp_update_post()` with no
	 * change still fires every save hook.
	 *
	 * @since 8.0.0
	 *
	 * @param WP_Post               $post The post being adopted.
	 * @param array<string, string> $rows Meta key => value to store.
	 *
	 * @return void
	 * @throws Exception When a row or the rename is not stored.
	 */
	protected function adopt_rows( WP_Post $post, array $rows ): void {
		$post_id = (int) $post->ID;

		foreach ( $rows as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
			if ( (string) get_post_meta( $post_id, $key, true ) !== (string) $value ) {
				// translators: 1: post ID, 2: meta key.
				throw new Exception( sprintf( esc_html_x( 'Failed to adopt post %1$d: its %2$s could not be stored.', 'Post store adopt error', 'uncanny-automator' ), absint( $post_id ), esc_html( $key ) ) );
			}
		}

		if ( $this->is_owned_post( $post ) ) {
			return;
		}

		$result = wp_update_post(
			array(
				'ID'        => $post_id,
				'post_type' => static::POST_TYPE,
			),
			true
		);

		if ( is_wp_error( $result ) || static::POST_TYPE !== get_post_type( $post_id ) ) {
			$reason = is_wp_error( $result ) ? ' ' . $result->get_error_message() : '';
			// translators: 1: post ID, 2: post type, 3: the database's reason, if any.
			throw new Exception( sprintf( esc_html_x( 'Failed to adopt post %1$d: it could not be renamed to %2$s.%3$s', 'Post store adopt error', 'uncanny-automator' ), absint( $post_id ), esc_html( static::POST_TYPE ), esc_html( $reason ) ) );
		}
	}

	/**
	 * Whether the given post (object or id) is one of this store's rows.
	 *
	 * @param WP_Post|int|null $post Post object or id.
	 * @return bool
	 */
	protected function is_owned_post( $post ): bool {
		$post = $post instanceof WP_Post ? $post : get_post( (int) $post );

		return $post instanceof WP_Post && static::POST_TYPE === $post->post_type;
	}
}

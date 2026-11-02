<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Item_Status_Store;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use WP_Post;

/**
 * WordPress implementation of Recipe_Item_Status_Store.
 *
 * Single seam that writes `post_status` for a recipe's trigger and action
 * posts (uo-trigger, uo-action). Direct `wp_update_post` — no
 * per-aggregate logic, no meta updates, no derived fields. Status is a
 * column-level concern.
 */
final class WP_Recipe_Item_Status_Store implements Recipe_Item_Status_Store {

	/**
	 * The post types whose status this store writes.
	 *
	 * @since 8.0.0
	 *
	 * @var string[]
	 */
	private const ITEM_POST_TYPES = array( AUTOMATOR_POST_TYPE_TRIGGER, AUTOMATOR_POST_TYPE_ACTION );

	/**
	 * Flip a recipe item between live and draft.
	 *
	 * Writes the post status directly rather than going through the item
	 * services, so a status change never re-runs item validation.
	 *
	 * @since 8.0.0 Refuses a post that is not a trigger or action, and raises when WordPress refuses the write.
	 *
	 * @param int    $post_id Item post id.
	 * @param string $status  Target post status.
	 *
	 * @return void
	 *
	 * @throws Store_Exception If $post_id is not a trigger or action post, or wp_update_post fails.
	 */
	public function update_post_status( int $post_id, string $status ): void {
		if ( ! $this->is_item_post( $post_id ) ) {
			// translators: %d is the post ID.
			throw new Store_Exception( sprintf( esc_html_x( 'Failed to update item status: post %d is not a trigger or action.', 'Recipe item status store error', 'uncanny-automator' ), absint( $post_id ) ) );
		}

		// Request WordPress errors: a refused update otherwise answers 0 and passes as saved.
		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new Store_Exception( sprintf( esc_html_x( 'Failed to update item status: %s', 'Recipe item status store error', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}
	}

	/**
	 * Whether an id names a trigger or action post, so the writer never changes another post.
	 *
	 * `get_post( 0 )` answers the global post, so a non-positive id is refused before the lookup.
	 *
	 * @since 8.0.0
	 *
	 * @param int $post_id Post id the caller names as a trigger or action.
	 *
	 * @return bool
	 */
	private function is_item_post( int $post_id ): bool {
		if ( 0 >= $post_id ) {
			return false;
		}

		$post = get_post( $post_id );

		return $post instanceof WP_Post && in_array( $post->post_type, self::ITEM_POST_TYPES, true );
	}
}

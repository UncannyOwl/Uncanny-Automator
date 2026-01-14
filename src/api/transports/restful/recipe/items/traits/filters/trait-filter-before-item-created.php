<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Filters;

use WP_Error;
use WP_REST_Response;
use WP_Post;
use Uncanny_Automator\Api\Database\Database;

/**
 * Trait for handling the automator_add_recipe_child filter.
 *
 * @since 7.0
 */
trait Filter_Before_Item_Created {

	/**
	 * Apply the automator_add_recipe_child filter.
	 *
	 * @since 7.0
	 *
	 * @param string $post_type The post type being created.
	 * @param string $action    The action being performed.
	 *
	 * @return true|int|WP_Error True to proceed, int if filter created item, WP_Error to abort.
	 */
	protected function apply_creation_filter( string $post_type, string $action ) {

		/**
		 * Filters whether to allow creation of a recipe child item.
		 *
		 * @since 3.0
		 *
		 * @param bool    $create_post Whether to create the post. Default true.
		 * @param string  $post_type   The post type being created (uo-trigger, uo-action, uo-closure).
		 * @param string  $action      The action being performed (create_trigger, create_action, create_closure).
		 * @param WP_Post $recipe      The parent recipe post object.
		 */
		$result = apply_filters(
			'automator_add_recipe_child',
			true,
			$post_type,
			$action,
			Database::get_recipe_store()->get_wp_post( $this->get_recipe_id() )
		);

		if ( true === $result ) {
			return true;
		}

		return $this->normalize_filter_response( $result );
	}

	/**
	 * Normalize filter response to our standard format.
	 *
	 * @since 7.0
	 *
	 * @param mixed $result The filter result.
	 *
	 * @return int|WP_Error Post ID if success with ID, WP_Error otherwise.
	 */
	private function normalize_filter_response( $result ) {

		// WP_REST_Response - check for success with post_id.
		if ( $result instanceof WP_REST_Response ) {
			$data = $result->get_data();

			if ( ! empty( $data['success'] ) && true === $data['success'] && ! empty( $data['post_id'] ) ) {
				return (int) $data['post_id'];
			}

			$message = $data['message'] ?? $this->get_child_creation_failure_message();

			return $this->failure(
				$message,
				$result->get_status(),
				'automator_add_recipe_child_blocked'
			);
		}

		// WP_Error - normalize through our failure method.
		if ( is_wp_error( $result ) ) {
			$status  = $result->get_error_data()['status'] ?? 403;
			$message = ! empty( $result->get_error_message() )
				? $result->get_error_message()
				: $this->get_child_creation_failure_message();

			return $this->failure( $message, $status, $result->get_error_code() );
		}

		// Any other falsy value means blocked.
		return $this->failure(
			$this->get_child_creation_failure_message(),
			403,
			'automator_add_recipe_child_blocked'
		);
	}

	/**
	 * Get failure message.
	 *
	 * @since 7.0
	 *
	 * @return string The failure message.
	 */
	private function get_child_creation_failure_message(): string {
		return esc_html_x( 'Item creation was blocked by filter automator_add_recipe_child.', 'REST API error', 'uncanny-automator' );
	}
}

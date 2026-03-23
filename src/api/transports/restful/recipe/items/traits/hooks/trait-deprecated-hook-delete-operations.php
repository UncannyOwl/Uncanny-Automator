<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks;

/**
 * Trait for deprecated delete operation hooks.
 *
 * Consolidates all backwards compatibility for deprecated hooks.
 * Remove this trait when dropping support for deprecated hooks.
 *
 * @since 7.0
 */
trait Deprecated_Hook_Delete_Operations {

	/**
	 * Dispatch deprecated deleted hook.
	 *
	 * @param array $response Response data.
	 *
	 * @return void
	 */
	protected function dispatch_deprecated_delete_hooks( array $response ): void {

		// Confirm this is a post type.
		$post_type = $this->get_item_post_type();
		if ( empty( $post_type ) ) {
			return;
		}

		/**
		 * Fires when a recipe item is deleted.
		 *
		 * @since 5.7
		 *
		 * @param int   $item_id   Item ID.
		 * @param int   $recipe_id Recipe ID.
		 * @param array $response  Response data.
		 */
		do_action_deprecated(
			'automator_recipe_item_deleted',
			array(
				$this->get_item_id(),
				$this->get_recipe_id(),
				array(
					'message'        => 'Deleted!',
					'success'        => true,
					'delete_posts'   => true,
					'action'         => 'deleted-' . $post_type,
					'recipes_object' => $response['recipes_object'],
					'_recipe'        => $response['recipe'],
				),
			),
			'7.0',
			'automator_recipe_item_delete_complete',
			esc_html_x(
				'The parameter structure for this hook has changed. Use automator_recipe_item_delete_complete instead.',
				'Restful API',
				'uncanny-automator'
			)
		);
	}
}

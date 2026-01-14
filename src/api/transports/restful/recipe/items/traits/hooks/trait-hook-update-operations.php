<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks;

use WP_REST_Request;

/**
 * Trait for update operation hooks.
 *
 * @since 7.0
 */
trait Hook_Update_Operations {

	/**
	 * Dispatch update before hooks.
	 *
	 * @return void
	 */
	protected function dispatch_update_before_hooks(): void {
		$type = $this->get_item_type();

		/**
		 * Fires before a recipe item is updated.
		 *
		 * @since 7.0
		 *
		 * @param WP_REST_Request $request The REST request object.
		 * @param string          $type    The item type.
		 */
		do_action( 'automator_recipe_item_update_before', $this->get_request(), $type );

		/**
		 * Fires before a specific recipe item type is updated.
		 *
		 * @since 7.0
		 *
		 * @param WP_REST_Request $request The REST request object.
		 */
		do_action( "automator_recipe_{$type}_update_before", $this->get_request() );
	}

	/**
	 * Dispatch update complete hooks.
	 *
	 * @param array  $item_data Service return data (action, trigger, or updated_fields array).
	 * @param object $recipe    Recipe aggregate object.
	 *
	 * @return void
	 */
	protected function dispatch_update_complete_hooks( array $item_data, object $recipe ): void {
		$type = $this->get_item_type();

		/**
		 * Fires when a recipe item update is complete.
		 *
		 * @since 7.0
		 *
		 * @param int|string $item_id   The item ID.
		 * @param string     $item_code The item code.
		 * @param int        $recipe_id The recipe ID.
		 * @param string     $item_type The item type (trigger, action, closure, filter_condition).
		 * @param array      $item_data Service return data.
		 * @param object     $recipe    Recipe aggregate object.
		 */
		do_action(
			'automator_recipe_item_update_complete',
			$this->get_item_id(),
			$this->get_item_code(),
			$this->get_recipe_id(),
			$type,
			$item_data,
			$recipe
		);

		/**
		 * Fires when a specific recipe item type update is complete.
		 *
		 * @since 7.0
		 *
		 * @param int|string $item_id   The item ID.
		 * @param string     $item_code The item code.
		 * @param int        $recipe_id The recipe ID.
		 * @param array      $item_data Service return data.
		 * @param object     $recipe    Recipe aggregate object.
		 */
		do_action(
			"automator_recipe_{$type}_update_complete",
			$this->get_item_id(),
			$this->get_item_code(),
			$this->get_recipe_id(),
			$item_data,
			$recipe
		);
	}
}

<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks;

use WP_REST_Request;

/**
 * Trait for delete operation hooks.
 *
 * @since 7.0
 */
trait Hook_Delete_Operations {

	/**
	 * Dispatches before delete hooks.
	 *
	 * @return void
	 */
	protected function dispatch_before_delete_hooks(): void {
		$type = $this->get_item_type();

		/**
		 * Fires before any recipe item is deleted.
		 *
		 * @since 7.0
		 *
		 * @param WP_REST_Request $request The REST request object.
		 * @param string          $type    The item type.
		 */
		do_action(
			'automator_recipe_item_delete_before',
			$this->get_request(),
			$type
		);

		/**
		 * Fires before a specific item type is deleted.
		 *
		 * @since 7.0
		 *
		 * @param WP_REST_Request $request The REST request object.
		 */
		do_action(
			"automator_recipe_{$type}_delete_before",
			$this->get_request()
		);
	}

	/**
	 * Dispatches delete complete hooks.
	 *
	 * @param object $recipe Recipe aggregate object.
	 *
	 * @return void
	 */
	protected function dispatch_delete_complete_hooks( object $recipe ): void {
		$type = $this->get_item_type();

		/**
		 * Fires after any recipe item is deleted.
		 *
		 * @since 7.0
		 *
		 * @param int|string $item_id   The item ID.
		 * @param string     $item_code The item code.
		 * @param int        $recipe_id The recipe ID.
		 * @param string     $item_type The item type (trigger, action, closure, filter_condition).
		 * @param object     $recipe    Recipe aggregate object.
		 */
		do_action(
			'automator_recipe_item_delete_complete',
			$this->get_item_id(),
			$this->get_item_code(),
			$this->get_recipe_id(),
			$type,
			$recipe
		);

		/**
		 * Fires after a specific item type is deleted.
		 *
		 * @since 7.0
		 *
		 * @param int|string $item_id   The item ID.
		 * @param string     $item_code The item code.
		 * @param int        $recipe_id The recipe ID.
		 * @param object     $recipe    Recipe aggregate object.
		 */
		do_action(
			"automator_recipe_{$type}_delete_complete",
			$this->get_item_id(),
			$this->get_item_code(),
			$this->get_recipe_id(),
			$recipe
		);
	}
}

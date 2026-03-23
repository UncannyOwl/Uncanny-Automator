<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks;

use WP_REST_Request;

/**
 * Trait for add operation hooks.
 *
 * @since 7.0
 */
trait Hook_Add_Operations {

	/**
	 * Dispatches add before hooks.
	 *
	 * @return void
	 */
	protected function dispatch_add_before_hooks(): void {
		$type = $this->get_item_type();

		/**
		 * Fires before any recipe item is added.
		 *
		 * @since 7.0
		 *
		 * @param WP_REST_Request $request The REST request object.
		 * @param string          $type    The item type.
		 */
		do_action(
			'automator_recipe_item_add_before',
			$this->get_request(),
			$type
		);

		/**
		 * Fires before a specific item type is added.
		 *
		 * @since 7.0
		 *
		 * @param WP_REST_Request $request The REST request object.
		 */
		do_action(
			"automator_recipe_{$type}_add_before",
			$this->get_request()
		);
	}

	/**
	 * Dispatches add complete hooks.
	 *
	 * @param array  $item_data Service return data (action, trigger, or condition data).
	 * @param object $recipe    Recipe aggregate object.
	 *
	 * @return void
	 */
	protected function dispatch_add_complete_hooks( array $item_data, object $recipe ): void {
		$type = $this->get_item_type();

		/**
		 * Fires after any recipe item is added.
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
			'automator_recipe_item_add_complete',
			$this->get_item_id(),
			$this->get_item_code(),
			$this->get_recipe_id(),
			$type,
			$item_data,
			$recipe
		);

		/**
		 * Fires after a specific item type is added.
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
			"automator_recipe_{$type}_add_complete",
			$this->get_item_id(),
			$this->get_item_code(),
			$this->get_recipe_id(),
			$item_data,
			$recipe
		);
	}
}

<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Blocks;

use WP_Error;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Condition_Service;

/**
 * Filter Block REST handler.
 *
 * Handles CRUD operations for filter blocks (Action condition groups).
 *
 * @package Uncanny_Automator\Api\Transports\Restful\Recipe\Blocks
 */
class Filter_Block_Rest extends Recipe_Block_Rest {

	/**
	 * Add a filter block to recipe.
	 *
	 * Creates an empty condition group (filter block) with no conditions.
	 *
	 * @return string|WP_Error Block ID (group_id) on success.
	 */
	protected function do_add_block() {
		$service = Recipe_Condition_Service::instance();
		$result  = $service->add_empty_condition_group(
			$this->get_recipe_id(),
			'any',  // Default mode
			10      // Default priority
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_add_filter_block_failed'
			);
		}

		// Return the group_id as the block_id
		return $result['group_id'] ?? '';
	}

	/**
	 * Update filter block configuration.
	 *
	 * Updates properties like mode ('any' or 'all') and priority for the condition group.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_update_block() {
		$fields  = $this->get_fields();
		$service = Recipe_Condition_Service::instance();
		$result  = $service->update_condition_group(
			$this->get_recipe_id(),
			$this->get_block_id(),
			$fields['mode'] ?? null,
			isset( $fields['priority'] ) ? (int) $fields['priority'] : null
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_update_filter_block_failed'
			);
		}

		return true;
	}

	/**
	 * Delete filter block from recipe.
	 *
	 * Removes the entire condition group (filter block) from the recipe.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_block() {
		$service = Recipe_Condition_Service::instance();
		$result  = $service->remove_condition_group(
			$this->get_recipe_id(),
			$this->get_block_id()
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_delete_filter_block_failed'
			);
		}

		return true;
	}
}

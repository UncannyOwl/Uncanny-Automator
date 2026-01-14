<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items;

use WP_Error;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Condition_Service;
use Uncanny_Automator\Api\Database\Database;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Filters\Filter_Field_Values_Before_Save;

/**
 * Filter Condition REST handler.
 *
 * Handles CRUD operations for filter conditions (formerly "action conditions").
 * Filter conditions are not post types - they're stored in recipe meta with generated IDs.
 *
 * @package Uncanny_Automator\Api\Transports\Restful\Recipe\Items
 */
class Filter_Condition_Rest extends Recipe_Item_Rest {

	use Filter_Field_Values_Before_Save;

	/**
	 * Add a filter condition to an existing condition group.
	 *
	 * Filter conditions require a group_id (passed via parent_id) to specify
	 * which condition group the new condition should be added to.
	 *
	 * @return array|WP_Error Array with 'item_id' and 'item_data', or WP_Error on failure.
	 */
	protected function do_add_item() {

		// Filter conditions require a group_id (passed as parent_id)
		$group_id = $this->get_parent_id();
		if ( empty( $group_id ) ) {
			return $this->failure(
				'Missing group_id. Filter conditions must be added to an existing condition group.',
				400,
				'automator_missing_group_id'
			);
		}

		$service = Recipe_Condition_Service::instance();
		$result  = $service->add_condition_to_group(
			$this->get_recipe_id(),
			$group_id,
			$this->get_integration_code(),
			$this->get_item_code(),
			array() // Empty fields for initial creation
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_add_filter_condition_failed'
			);
		}

		// Filter conditions return string IDs, not integer post IDs.
		$condition_id = $result['condition_id'] ?? '';

		return array(
			'item_id'   => (string) $condition_id,
			'item_data' => array(
				'condition_id' => $condition_id,
				'group_id'     => $group_id,
			),
		);
	}

	/**
	 * Update filter condition configuration.
	 *
	 * Filter conditions use generated string IDs (like midg6fp2bub188jxpxb).
	 * We need to find which group contains this condition before updating.
	 *
	 * @return array|WP_Error
	 */
	protected function do_update_item() {

		// Find which group contains this condition
		$group_id = $this->find_group_for_condition( $this->get_recipe_id(), $this->get_item_id() );
		if ( is_wp_error( $group_id ) ) {
			return $group_id;
		}

		// Get filtered config from request fields.
		$config = $this->prepare_field_config_for_services();

		$service = Recipe_Condition_Service::instance();
		$result  = $service->update_condition(
			$this->get_item_id(), // condition_id (string)
			$group_id,
			$this->get_recipe_id(),
			$config
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_update_filter_condition_failed'
			);
		}

		return array(
			'success'   => true,
			'item_data' => array(
				'updated_fields' => $result['updated_fields'] ?? array(),
				'group_id'       => $group_id,
			),
		);
	}

	/**
	 * Delete filter condition from its group.
	 *
	 * Removes a single condition from a condition group.
	 * Does not delete the group itself.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_item() {

		// Find which group contains this condition
		$group_id = $this->find_group_for_condition( $this->get_recipe_id(), $this->get_item_id() );
		if ( is_wp_error( $group_id ) ) {
			return $group_id;
		}

		$service = Recipe_Condition_Service::instance();
		$result  = $service->remove_condition_from_group(
			$this->get_item_id(), // condition_id (string)
			$group_id,
			$this->get_recipe_id()
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_delete_filter_condition_failed'
			);
		}

		return true;
	}

	/**
	 * Find which condition group contains a specific condition.
	 *
	 * Searches all condition groups in the recipe to find the one that contains
	 * the specified condition ID. Also sets the item_code property when found.
	 *
	 * @param int    $recipe_id    Recipe ID.
	 * @param string $condition_id Condition ID to find.
	 * @return string|WP_Error Group ID on success, WP_Error if not found.
	 */
	private function find_group_for_condition( int $recipe_id, string $condition_id ) {
		$recipe_store = Database::get_recipe_store();
		$recipe       = $recipe_store->get( $recipe_id );

		if ( ! $recipe ) {
			return $this->failure(
				'Recipe not found.',
				404,
				'automator_recipe_not_found'
			);
		}

		$conditions = $recipe->get_recipe_action_conditions();
		if ( ! $conditions ) {
			return $this->failure(
				'Recipe has no filter condition groups.',
				404,
				'automator_no_condition_groups'
			);
		}

		// Search all groups for this condition
		foreach ( $conditions->get_all() as $group ) {
			foreach ( $group->get_conditions() as $condition ) {
				if ( $condition->get_condition_id()->get_value() === $condition_id ) {
					// Set item_code from found condition for hooks.
					$this->set_item_code( $condition->get_condition_code() );
					return $group->get_group_id()->get_value();
				}
			}
		}

		return $this->failure(
			sprintf( 'Filter condition with ID %s not found in any group.', $condition_id ),
			404,
			'automator_condition_not_found'
		);
	}
}

<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Condition\Services;

use Uncanny_Automator\Api\Components\Recipe\Recipe;
use Uncanny_Automator\Api\Database\Stores\Action_Condition_Store;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Locator;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Validator;
use Uncanny_Automator\Api\Services\Traits\Service_Response_Formatter;
use WP_Error;

/**
 * Condition Action Service - Handles action management in condition groups.
 *
 * Manages the assignment and removal of actions to/from condition groups,
 * ensuring proper validation and data consistency.
 *
 * @since 7.0.0
 */
class Condition_Action_Service {

	use Service_Response_Formatter;

	private Action_Condition_Store $repository;
	private Condition_Locator $group_locator;
	private Condition_Validator $validation;

	/**
	 * Constructor.
	 *
	 * @param Action_Condition_Store $repository    Action condition store.
	 * @param Condition_Locator      $group_locator Condition group locator.
	 * @param Condition_Validator    $validation    Condition validator.
	 */
	public function __construct(
		Action_Condition_Store $repository,
		Condition_Locator $group_locator,
		Condition_Validator $validation
	) {
		$this->repository    = $repository;
		$this->group_locator = $group_locator;
		$this->validation    = $validation;
	}

	/**
	 * Add actions to an existing condition group.
	 *
	 * @param int    $recipe_id  Recipe ID.
	 * @param string $group_id   Condition group ID.
	 * @param array  $action_ids Array of action IDs to add.
	 * @return array|\WP_Error Result data or error.
	 */
	public function add_actions_to_condition_group( int $recipe_id, string $group_id, array $action_ids ) {
		try {
			$recipe = $this->repository->get_recipe( $recipe_id );
			if ( ! $recipe ) {
				return $this->error_response( 'condition_recipe_not_found', 'Recipe not found' );
			}

			$validation_result = $this->validation->assert_actions_in_recipe( $recipe_id, $action_ids );
			if ( is_wp_error( $validation_result ) ) {
				return $validation_result;
			}

			$current_conditions = $recipe->get_recipe_action_conditions();
			if ( ! $current_conditions ) {
				return $this->error_response( 'condition_group_not_found', 'Condition group not found' );
			}

			$target_group = $this->group_locator->require_group( $current_conditions, $group_id );
			if ( is_wp_error( $target_group ) ) {
				return $target_group;
			}

			$current_action_ids = $target_group->get_action_ids();
			$merged_action_ids  = array_values( array_unique( array_merge( $current_action_ids, $action_ids ) ) );

			$updated_group      = $this->group_locator->with_updated_actions( $target_group, $merged_action_ids );
			$updated_conditions = $this->group_locator->replace_group( $current_conditions, $updated_group );
			$updated_recipe     = $this->repository->update_conditions( $recipe, $updated_conditions );

			if ( is_wp_error( $updated_recipe ) ) {
				return $updated_recipe;
			}

			return array(
				'message'       => 'Actions added to condition group successfully',
				'group_id'      => $group_id,
				'recipe_id'     => $recipe_id,
				'action_ids'    => $action_ids,
				'total_actions' => count( $merged_action_ids ),
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'condition_add_actions_failed', $e->getMessage() );
		}
	}

	/**
	 * Remove actions from an existing condition group.
	 *
	 * @param int    $recipe_id  Recipe ID.
	 * @param string $group_id   Condition group ID.
	 * @param array  $action_ids Array of action IDs to remove.
	 * @return array|\WP_Error Result data or error.
	 */
	public function remove_actions_from_condition_group( int $recipe_id, string $group_id, array $action_ids ) {
		try {
			$recipe = $this->repository->get_recipe( $recipe_id );
			if ( ! $recipe ) {
				return $this->error_response( 'condition_recipe_not_found', 'Recipe not found' );
			}

			$current_conditions = $recipe->get_recipe_action_conditions();
			if ( ! $current_conditions ) {
				return $this->error_response( 'condition_group_not_found', 'Condition group not found' );
			}

			$target_group = $this->group_locator->require_group( $current_conditions, $group_id );
			if ( is_wp_error( $target_group ) ) {
				return $target_group;
			}

			$updated_group      = $this->group_locator->remove_actions( $target_group, $action_ids );
			$remaining_actions  = $updated_group->get_action_ids();
			$updated_conditions = $this->group_locator->replace_group( $current_conditions, $updated_group );
			$updated_recipe     = $this->repository->update_conditions( $recipe, $updated_conditions );

			if ( is_wp_error( $updated_recipe ) ) {
				return $updated_recipe;
			}

			return array(
				'message'           => 'Actions removed from condition group successfully',
				'group_id'          => $group_id,
				'recipe_id'         => $recipe_id,
				'removed_actions'   => $action_ids,
				'remaining_actions' => $remaining_actions,
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'condition_remove_actions_failed', $e->getMessage() );
		}
	}

	/**
	 * Remove conditions for specific action.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @param int $action_id Action ID to remove conditions for.
	 * @return array|\WP_Error Success data or error.
	 */
	public function remove_action_conditions( int $recipe_id, int $action_id ) {
		try {
			$recipe = $this->repository->get_recipe( $recipe_id );
			if ( ! $recipe ) {
				return $this->error_response( 'condition_recipe_not_found', 'Recipe not found' );
			}

			$current_conditions = $recipe->get_recipe_action_conditions();
			if ( ! $current_conditions ) {
				return array( 'message' => 'No conditions to remove' );
			}

			$updated_conditions = $this->group_locator->remove_action_from_groups( $current_conditions, $action_id );

			$updated_recipe = $this->repository->update_conditions( $recipe, $updated_conditions );
			if ( is_wp_error( $updated_recipe ) ) {
				return $updated_recipe;
			}

			return array(
				'message'   => 'Action conditions removed successfully',
				'action_id' => $action_id,
				'recipe_id' => $recipe_id,
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'condition_remove_actions_failed', $e->getMessage() );
		}
	}
}

<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Condition\Services;

use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group_Id;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group_Mode;
use Uncanny_Automator\Api\Components\Recipe\Recipe;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Action_Conditions;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Database\Stores\Action_Condition_Store;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Factory;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Locator;
use Uncanny_Automator\Api\Services\Traits\Service_Response_Formatter;
use WP_Error;

/**
 * Condition Group Service - Handles condition group CRUD operations.
 *
 * Manages the lifecycle of condition groups within recipes, including
 * creation, updates, and removal of groups.
 *
 * @since 7.0.0
 */
class Condition_Group_Service {

	use Service_Response_Formatter;

	private Action_Condition_Store $repository;
	private Condition_Factory $assembler;
	private Condition_Locator $group_locator;

	/**
	 * Constructor.
	 *
	 * @param Action_Condition_Store $repository    Action condition store.
	 * @param Condition_Factory      $assembler     Condition factory.
	 * @param Condition_Locator      $group_locator Condition group locator.
	 */
	public function __construct(
		Action_Condition_Store $repository,
		Condition_Factory $assembler,
		Condition_Locator $group_locator
	) {
		$this->repository    = $repository;
		$this->assembler     = $assembler;
		$this->group_locator = $group_locator;
	}

	/**
	 * Add condition group to recipe.
	 *
	 * @param int    $recipe_id Recipe ID to add conditions to.
	 * @param array  $action_ids Array of action IDs the conditions apply to.
	 * @param string $mode Evaluation mode ('any' or 'all').
	 * @param array  $conditions Array of condition configurations.
	 * @return array|\WP_Error Success data or error.
	 */
	public function add_condition_group( int $recipe_id, array $action_ids, string $mode, array $conditions ) {
		try {
			$recipe = $this->repository->get_recipe( $recipe_id );
			if ( ! $recipe ) {
				return $this->error_response( 'condition_recipe_not_found', 'Recipe not found' );
			}

			$condition_group = $this->assembler->create_group(
				new Recipe_Id( $recipe_id ),
				$action_ids,
				$mode,
				$conditions
			);

			if ( is_wp_error( $condition_group ) ) {
				return $condition_group;
			}

			$current_conditions = $this->get_conditions_or_empty( $recipe );
			$updated_conditions = $current_conditions->with_group( $condition_group );

			$updated_recipe = $this->repository->update_conditions( $recipe, $updated_conditions );
			if ( is_wp_error( $updated_recipe ) ) {
				return $updated_recipe;
			}

			return array(
				'message'         => 'Condition group added successfully',
				'condition_group' => $condition_group->to_array(),
				'recipe_id'       => $recipe_id,
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'condition_creation_failed', $e->getMessage() );
		}
	}

	/**
	 * Update condition group in recipe.
	 *
	 * @param int         $recipe_id Recipe ID.
	 * @param string      $group_id  Condition group ID to update.
	 * @param string|null $mode      New evaluation mode (optional).
	 * @param int|null    $priority  New priority (optional).
	 * @return array|\WP_Error Success data or error.
	 */
	public function update_condition_group( int $recipe_id, string $group_id, ?string $mode = null, ?int $priority = null ) {
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

			$updated_group    = $target_group;
			$updated_fields   = array();
			$updated_mode     = $target_group->get_mode();
			$updated_priority = $target_group->get_priority();

			if ( $mode && $mode !== $target_group->get_mode()->get_value() ) {
				$updated_mode     = new Condition_Group_Mode( $mode );
				$updated_group    = $this->group_locator->with_updated_mode( $updated_group, $updated_mode );
				$updated_fields[] = 'mode';
			}

			if ( null !== $priority && $priority !== $target_group->get_priority() ) {
				$updated_priority = $priority;
				$updated_group    = $this->group_locator->with_updated_priority( $updated_group, $priority );
				$updated_fields[] = 'priority';
			}

			$updated_conditions = $this->group_locator->replace_group( $current_conditions, $updated_group );
			$updated_recipe     = $this->repository->update_conditions( $recipe, $updated_conditions );

			if ( is_wp_error( $updated_recipe ) ) {
				return $updated_recipe;
			}

			return array(
				'message'        => 'Condition group updated successfully',
				'group_id'       => $group_id,
				'recipe_id'      => $recipe_id,
				'mode'           => $updated_mode->get_value(),
				'priority'       => $updated_priority,
				'updated_fields' => $updated_fields,
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'condition_update_failed', $e->getMessage() );
		}
	}

	/**
	 * Remove condition group from recipe.
	 *
	 * @param int    $recipe_id Recipe ID.
	 * @param string $group_id Condition group ID to remove.
	 * @return array|\WP_Error Success data or error.
	 */
	public function remove_condition_group( int $recipe_id, string $group_id ) {
		try {
			$recipe = $this->repository->get_recipe( $recipe_id );
			if ( ! $recipe ) {
				return $this->error_response( 'condition_recipe_not_found', 'Recipe not found' );
			}

			$current_conditions = $recipe->get_recipe_action_conditions();
			if ( ! $current_conditions ) {
				return $this->error_response( 'condition_no_groups', 'Recipe has no condition groups' );
			}

			$updated_conditions = $this->group_locator->remove_group( $current_conditions, $group_id );
			$updated_recipe     = $this->repository->update_conditions( $recipe, $updated_conditions );
			if ( is_wp_error( $updated_recipe ) ) {
				return $updated_recipe;
			}

			return array(
				'message'   => 'Condition group removed successfully',
				'group_id'  => $group_id,
				'recipe_id' => $recipe_id,
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'condition_removal_failed', $e->getMessage() );
		}
	}

	/**
	 * Add an empty condition group to a recipe or loop.
	 *
	 * @param int      $recipe_id Recipe ID.
	 * @param string   $mode      Evaluation mode ('any' or 'all').
	 * @param int      $priority  Group priority.
	 * @param int|null $parent_id Parent ID (recipe or loop). Defaults to recipe_id.
	 * @return array|\WP_Error Result data or error.
	 */
	public function add_empty_condition_group( int $recipe_id, string $mode = 'any', int $priority = 20, ?int $parent_id = null ) {
		try {
			$recipe = $this->repository->get_recipe( $recipe_id );

			if ( ! $recipe ) {
				return $this->error_response( 'condition_recipe_not_found', 'Recipe not found' );
			}

			// Default parent_id to recipe_id if not provided.
			$effective_parent_id = $parent_id ?? $recipe_id;

			$group_id        = Condition_Group_Id::generate();
			$group_mode      = new Condition_Group_Mode( $mode );
			$parent_id_vo    = new Recipe_Id( $effective_parent_id );
			$condition_group = new Condition_Group(
				$group_id,
				$priority,
				array(),
				$group_mode,
				$parent_id_vo,
				array()
			);

			$current_conditions = $this->get_conditions_or_empty( $recipe );
			$updated_conditions = $current_conditions->with_group( $condition_group );
			$updated_recipe     = $this->repository->update_conditions( $recipe, $updated_conditions );

			if ( is_wp_error( $updated_recipe ) ) {
				return $updated_recipe;
			}

			return array(
				'message'   => 'Empty condition group created successfully',
				'group_id'  => $group_id->get_value(),
				'recipe_id' => $recipe_id,
				'parent_id' => $effective_parent_id,
				'mode'      => $mode,
				'priority'  => $priority,
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'condition_creation_failed', $e->getMessage() );
		}
	}

	/**
	 * Get conditions or empty collection for recipe.
	 *
	 * @param Recipe $recipe Recipe object.
	 * @return Recipe_Action_Conditions Empty conditions if none exist.
	 */
	private function get_conditions_or_empty( Recipe $recipe ) {
		return $recipe->get_recipe_action_conditions() ?? new Recipe_Action_Conditions( array() );
	}
}

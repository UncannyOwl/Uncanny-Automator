<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Condition\Services;

use Uncanny_Automator\Api\Components\Recipe\Recipe;
use Uncanny_Automator\Api\Database\Stores\Action_Condition_Store;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Factory;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Locator;
use Uncanny_Automator\Api\Services\Traits\Service_Response_Formatter;
use WP_Error;

/**
 * Condition Management Service - Handles individual condition operations.
 *
 * Manages the creation and manipulation of individual conditions within
 * condition groups, ensuring proper validation and consistency.
 *
 * @since 7.0.0
 */
class Condition_Management_Service {

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
	 * Validate recipe exists.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return Recipe|\WP_Error Recipe object or error.
	 */
	public function validate_recipe_exists( int $recipe_id ) {
		$recipe = $this->repository->get_recipe( $recipe_id );

		if ( ! $recipe ) {
			return $this->error_response( 'condition_recipe_not_found', 'Recipe not found' );
		}

		return $recipe;
	}

	/**
	 * Validate recipe has conditions.
	 *
	 * @param Recipe $recipe Recipe object.
	 * @return mixed Conditions object or WP_Error.
	 */
	public function validate_recipe_has_conditions( Recipe $recipe ) {
		$current_conditions = $recipe->get_recipe_action_conditions();

		if ( ! $current_conditions ) {
			return $this->error_response( 'condition_group_not_found', 'Condition group not found' );
		}

		return $current_conditions;
	}

	/**
	 * Validate group exists in conditions.
	 *
	 * @param mixed  $conditions Conditions collection.
	 * @param string $group_id   Group ID.
	 * @return mixed Group object or WP_Error.
	 */
	public function validate_group_exists( $conditions, string $group_id ) {
		$target_group = $this->group_locator->require_group( $conditions, $group_id );

		if ( is_wp_error( $target_group ) ) {
			return $target_group;
		}

		return $target_group;
	}

	/**
	 * Update recipe with new conditions.
	 *
	 * @param Recipe $recipe             Recipe object.
	 * @param mixed  $updated_conditions Updated conditions.
	 * @return Recipe|\WP_Error Updated recipe or error.
	 */
	public function update_recipe_conditions( Recipe $recipe, $updated_conditions ) {
		$updated_recipe = $this->repository->update_conditions( $recipe, $updated_conditions );

		if ( is_wp_error( $updated_recipe ) ) {
			return $updated_recipe;
		}

		return $updated_recipe;
	}

	/**
	 * Build add condition response.
	 *
	 * @param string $group_id       Group ID.
	 * @param int    $recipe_id      Recipe ID.
	 * @param mixed  $new_condition  New condition object.
	 * @param string $integration    Integration code.
	 * @param string $condition_code Condition code.
	 * @param mixed  $updated_group  Updated group object.
	 * @return array Response array.
	 */
	public function build_add_condition_response( string $group_id, int $recipe_id, $new_condition, string $integration, string $condition_code, $updated_group ): array {
		return array(
			'message'          => 'Condition added to group successfully',
			'group_id'         => $group_id,
			'recipe_id'        => $recipe_id,
			'condition_id'     => $new_condition->get_condition_id()->get_value(),
			'integration'      => $integration,
			'condition_code'   => $condition_code,
			'total_conditions' => $updated_group->count_conditions(),
		);
	}

	/**
	 * Build update condition response.
	 *
	 * @param string $condition_id Condition ID.
	 * @param string $group_id     Group ID.
	 * @param int    $recipe_id    Recipe ID.
	 * @param array  $fields       Updated fields.
	 * @return array Response array.
	 */
	public function build_update_condition_response( string $condition_id, string $group_id, int $recipe_id, array $fields ): array {
		return array(
			'message'        => 'Condition updated successfully',
			'condition_id'   => $condition_id,
			'group_id'       => $group_id,
			'recipe_id'      => $recipe_id,
			'updated_fields' => $fields,
		);
	}

	/**
	 * Build remove condition response.
	 *
	 * @param string $condition_id  Condition ID.
	 * @param string $group_id      Group ID.
	 * @param int    $recipe_id     Recipe ID.
	 * @param mixed  $updated_group Updated group object.
	 * @return array Response array.
	 */
	public function build_remove_condition_response( string $condition_id, string $group_id, int $recipe_id, $updated_group ): array {
		return array(
			'message'              => 'Condition removed from group successfully',
			'condition_id'         => $condition_id,
			'group_id'             => $group_id,
			'recipe_id'            => $recipe_id,
			'remaining_conditions' => $updated_group->count_conditions(),
		);
	}

	/**
	 * Add a condition to an existing condition group.
	 *
	 * @param int    $recipe_id        Recipe ID.
	 * @param string $group_id         Condition group ID.
	 * @param string $integration_code Integration code.
	 * @param string $condition_code   Condition code.
	 * @param array  $fields           Field configuration.
	 * @return array|\WP_Error Result data or error.
	 */
	public function add_condition_to_group( int $recipe_id, string $group_id, string $integration_code, string $condition_code, array $fields ) {
		try {
			// Validate recipe
			$recipe = $this->validate_recipe_exists( $recipe_id );
			if ( is_wp_error( $recipe ) ) {
				return $recipe;
			}

			// Validate conditions exist
			$current_conditions = $this->validate_recipe_has_conditions( $recipe );
			if ( is_wp_error( $current_conditions ) ) {
				return $current_conditions;
			}

			// Validate group exists
			$target_group = $this->validate_group_exists( $current_conditions, $group_id );
			if ( is_wp_error( $target_group ) ) {
				return $target_group;
			}

			// Create new condition
			$new_condition = $this->assembler->create_condition(
				array(
					'integration_code' => $integration_code,
					'condition_code'   => $condition_code,
					'fields'           => $fields,
				)
			);

			if ( is_wp_error( $new_condition ) ) {
				return $new_condition;
			}

			// Update group and recipe
			$updated_group      = $this->group_locator->add_condition_to_group( $target_group, $new_condition );
			$updated_conditions = $this->group_locator->replace_group( $current_conditions, $updated_group );
			$updated_recipe     = $this->update_recipe_conditions( $recipe, $updated_conditions );

			if ( is_wp_error( $updated_recipe ) ) {
				return $updated_recipe;
			}

			return $this->build_add_condition_response( $group_id, $recipe_id, $new_condition, $integration_code, $condition_code, $updated_group );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'add_condition_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to add condition: %s', 'Condition management error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Update an existing condition in a group.
	 *
	 * @param string $condition_id   Condition ID.
	 * @param string $group_id       Group ID.
	 * @param int    $recipe_id      Recipe ID.
	 * @param array  $fields         Updated field values.
	 * @return array|\WP_Error Updated condition data on success, WP_Error on failure.
	 */
	public function update_condition( string $condition_id, string $group_id, int $recipe_id, array $fields ) {
		try {
			// Validate recipe
			$recipe = $this->validate_recipe_exists( $recipe_id );
			if ( is_wp_error( $recipe ) ) {
				return $recipe;
			}

			// Validate conditions exist
			$current_conditions = $this->validate_recipe_has_conditions( $recipe );
			if ( is_wp_error( $current_conditions ) ) {
				return $current_conditions;
			}

			// Validate group exists
			$target_group = $this->validate_group_exists( $current_conditions, $group_id );
			if ( is_wp_error( $target_group ) ) {
				return $target_group;
			}

			// Find existing condition in group
			$existing_condition = null;
			foreach ( $target_group->get_conditions() as $condition ) {
				if ( $condition->get_condition_id()->get_value() === $condition_id ) {
					$existing_condition = $condition;
					break;
				}
			}

			if ( null === $existing_condition ) {
				return new WP_Error(
					'condition_not_found',
					sprintf(
						/* translators: %s Condition ID. */
						esc_html_x( 'Condition with ID %s not found in group.', 'Condition management error', 'uncanny-automator' ),
						$condition_id
					)
				);
			}

			// Update condition
			$updated_condition = $this->assembler->refresh_condition_with_id(
				$existing_condition,
				$fields
			);

			if ( is_wp_error( $updated_condition ) ) {
				return $updated_condition;
			}

			// Update group and recipe
			$updated_group      = $this->group_locator->replace_condition_in_group( $target_group, $condition_id, $updated_condition );
			$updated_conditions = $this->group_locator->replace_group( $current_conditions, $updated_group );
			$updated_recipe     = $this->update_recipe_conditions( $recipe, $updated_conditions );

			if ( is_wp_error( $updated_recipe ) ) {
				return $updated_recipe;
			}

			return $this->build_update_condition_response( $condition_id, $group_id, $recipe_id, $fields );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'update_condition_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to update condition: %s', 'Condition management error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Remove a condition from a group.
	 *
	 * @param string $condition_id Condition ID.
	 * @param string $group_id     Group ID.
	 * @param int    $recipe_id    Recipe ID.
	 * @return array|\WP_Error Success confirmation on success, WP_Error on failure.
	 */
	public function remove_condition_from_group( string $condition_id, string $group_id, int $recipe_id ) {
		try {
			// Validate recipe
			$recipe = $this->validate_recipe_exists( $recipe_id );
			if ( is_wp_error( $recipe ) ) {
				return $recipe;
			}

			// Validate conditions exist
			$current_conditions = $this->validate_recipe_has_conditions( $recipe );
			if ( is_wp_error( $current_conditions ) ) {
				return $current_conditions;
			}

			// Validate group exists
			$target_group = $this->validate_group_exists( $current_conditions, $group_id );
			if ( is_wp_error( $target_group ) ) {
				return $target_group;
			}

			// Remove condition and update recipe
			$updated_group      = $this->group_locator->remove_condition_from_group( $target_group, $condition_id );
			$updated_conditions = $this->group_locator->replace_group( $current_conditions, $updated_group );
			$updated_recipe     = $this->update_recipe_conditions( $recipe, $updated_conditions );

			if ( is_wp_error( $updated_recipe ) ) {
				return $updated_recipe;
			}

			return $this->build_remove_condition_response( $condition_id, $group_id, $recipe_id, $updated_group );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'remove_condition_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to remove condition: %s', 'Condition management error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}
}

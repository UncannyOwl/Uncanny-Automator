<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group_Id;

/**
 * Recipe Action Conditions Value Object.
 *
 * Represents a collection of condition groups for a recipe.
 * Each recipe can have multiple condition groups, and each group
 * can apply to different sets of actions.
 *
 * @since 7.0.0
 */
class Recipe_Action_Conditions {

	private array $condition_groups;

	/**
	 * Constructor.
	 *
	 * @param array $condition_groups Array of Condition_Group instances.
	 * @throws \InvalidArgumentException If condition groups are invalid.
	 */
	public function __construct( array $condition_groups = array() ) {
		$this->condition_groups = $this->validate_condition_groups( $condition_groups );
	}

	/**
	 * Create empty action conditions.
	 *
	 * @return self Empty action conditions collection.
	 */
	public static function empty(): self {
		return new self( array() );
	}

	/**
	 * Get all condition groups.
	 *
	 * @return Condition_Group[] Array of Condition_Group instances.
	 */
	public function get_all(): array {
		return $this->condition_groups;
	}

	/**
	 * Get condition groups for a specific action.
	 *
	 * @param int $action_id Action ID to filter by.
	 * @return Condition_Group[] Array of Condition_Group instances that apply to the action.
	 */
	public function get_for_action( int $action_id ): array {
		$normalized_action_id = (int) $action_id;
		return array_filter(
			$this->condition_groups,
			function ( Condition_Group $group ) use ( $normalized_action_id ) {
				return $group->applies_to_action( $normalized_action_id );
			}
		);
	}

	/**
	 * Get a specific condition group by ID.
	 *
	 * @param Condition_Group_Id $group_id Group ID to find.
	 * @return Condition_Group|null Group if found, null otherwise.
	 */
	public function get_group( Condition_Group_Id $group_id ): ?Condition_Group {
		foreach ( $this->condition_groups as $group ) {
			if ( $group->get_group_id()->equals( $group_id ) ) {
				return $group;
			}
		}

		return null;
	}

	/**
	 * Check if there are any condition groups.
	 *
	 * @return bool True if there are condition groups.
	 */
	public function has_conditions(): bool {
		foreach ( $this->condition_groups as $group ) {
			if ( $group->has_conditions() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a specific action has conditions.
	 *
	 * @param int $action_id Action ID to check.
	 * @return bool True if action has condition groups.
	 */
	public function action_has_conditions( int $action_id ): bool {
		foreach ( $this->get_for_action( $action_id ) as $group ) {
			if ( $group->has_conditions() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Count the total number of condition groups.
	 *
	 * @return int Number of condition groups.
	 */
	public function count_groups(): int {
		return count( $this->condition_groups );
	}

	/**
	 * Count the total number of individual conditions across all groups.
	 *
	 * @return int Total number of conditions.
	 */
	public function count_conditions(): int {
		$total = 0;
		foreach ( $this->condition_groups as $group ) {
			$total += $group->count_conditions();
		}

		return $total;
	}

	/**
	 * Add a condition group.
	 *
	 * @param Condition_Group $group Group to add.
	 * @return self New instance with added group.
	 * @throws \InvalidArgumentException If group ID already exists.
	 */
	public function with_group( Condition_Group $group ): self {
		// Check for duplicate group IDs
		foreach ( $this->condition_groups as $existing_group ) {
			if ( $existing_group->get_group_id()->equals( $group->get_group_id() ) ) {
				throw new \InvalidArgumentException( 'Condition group ID already exists' );
			}
		}

		$groups   = $this->condition_groups;
		$groups[] = $group;

		return new self( $groups );
	}

	/**
	 * Remove a condition group.
	 *
	 * @param Condition_Group_Id $group_id Group ID to remove.
	 * @return self New instance without the group.
	 */
	public function without_group( Condition_Group_Id $group_id ): self {
		$groups = array_filter(
			$this->condition_groups,
			function ( Condition_Group $group ) use ( $group_id ) {
				return ! $group->get_group_id()->equals( $group_id );
			}
		);

		return new self( array_values( $groups ) ); // Reindex array
	}

	/**
	 * Update a condition group.
	 *
	 * @param Condition_Group $updated_group Updated group.
	 * @return self New instance with updated group.
	 * @throws \InvalidArgumentException If group ID not found.
	 */
	public function with_updated_group( Condition_Group $updated_group ): self {
		$groups = array();
		$found  = false;

		foreach ( $this->condition_groups as $group ) {
			if ( $group->get_group_id()->equals( $updated_group->get_group_id() ) ) {
				$groups[] = $updated_group;
				$found    = true;
			} else {
				$groups[] = $group;
			}
		}

		if ( ! $found ) {
			throw new \InvalidArgumentException( 'Condition group ID not found for update' );
		}

		return new self( $groups );
	}

	/**
	 * Get remaining action IDs after removing a specific action.
	 *
	 * @param array $action_ids Action IDs to filter.
	 * @param int   $action_id_to_remove Action ID to remove.
	 * @return array Remaining action IDs.
	 */
	public function get_remaining_action_ids( array $action_ids, int $action_id_to_remove ): array {
		return array_values(
			array_filter(
				array_map( 'intval', $action_ids ),
				function ( $id ) use ( $action_id_to_remove ) {
					return $id !== $action_id_to_remove;
				}
			)
		);
	}

	/**
	 * Remove all condition groups for a specific action.
	 *
	 * @param int $action_id Action ID to remove conditions for.
	 * @return self New instance without conditions for the action.
	 */
	public function without_action_conditions( int $action_id ): self {
		$normalized_action_id = (int) $action_id;
		$groups               = array();

		foreach ( $this->condition_groups as $group ) {
			if ( ! $group->applies_to_action( $normalized_action_id ) ) {
				$groups[] = $group;
				continue;
			}

			$remaining_action_ids = $this->get_remaining_action_ids( $group->get_action_ids(), $normalized_action_id );

			if ( empty( $remaining_action_ids ) ) {
				continue;
			}

			$groups[] = $group->with_action_ids( $remaining_action_ids );
		}

		return new self( $groups );
	}

	/**
	 * Convert to array representation for storage.
	 *
	 * @return array Conditions as array matching legacy format.
	 */
	public function to_array(): array {
		return array_map(
			function ( Condition_Group $group ) {
				return $group->to_array();
			},
			$this->condition_groups
		);
	}

	/**
	 * Create from array (for hydration from storage).
	 *
	 * @param array $data Array data from storage.
	 * @return self Action conditions instance.
	 * @throws \InvalidArgumentException If data is invalid.
	 */
	public static function from_array( array $data ): self {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Action conditions data must be an array' );
		}

		$condition_groups = array();

		foreach ( $data as $group_data ) {
			if ( ! is_array( $group_data ) ) {
				continue;
			}

			try {
				$condition_groups[] = Condition_Group::from_array( $group_data );
			} catch ( \Throwable $exception ) {
				// Skip invalid condition groups silently.
				continue;
			}
		}

		return new self( $condition_groups );
	}

	/**
	 * Get condition groups sorted by priority (descending).
	 *
	 * @return array Sorted array of Condition_Group instances.
	 */
	public function get_sorted_by_priority(): array {
		$groups = $this->condition_groups;

		usort(
			$groups,
			function ( Condition_Group $a, Condition_Group $b ) {
				return $b->get_priority() - $a->get_priority(); // Descending order
			}
		);

		return $groups;
	}

	/**
	 * Normalize action IDs to unique positive integers.
	 *
	 * @param array $action_ids Action IDs to normalize.
	 * @return array Normalized action IDs.
	 */
	public function normalize_action_ids( array $action_ids ): array {
		return array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $action_ids ),
					function ( $id ) {
						return $id > 0;
					}
				)
			)
		);
	}

	/**
	 * Validate group instance type.
	 *
	 * @param mixed $group Group to validate.
	 * @throws \InvalidArgumentException If group is not Condition_Group instance.
	 */
	public function validate_group_instance( $group ): void {
		if ( ! $group instanceof Condition_Group ) {
			throw new \InvalidArgumentException( 'All condition groups must be Condition_Group instances' );
		}
	}

	/**
	 * Validate group ID is unique.
	 *
	 * @param string $group_id Group ID to validate.
	 * @param array  $existing_group_ids Array of existing group IDs.
	 * @throws \InvalidArgumentException If group ID is duplicate.
	 */
	public function validate_unique_group_id( string $group_id, array $existing_group_ids ): void {
		if ( in_array( $group_id, $existing_group_ids, true ) ) {
			throw new \InvalidArgumentException( 'Duplicate condition group ID found: ' . $group_id );
		}
	}

	/**
	 * Validate condition groups array.
	 *
	 * @param array $condition_groups Groups to validate.
	 * @throws \InvalidArgumentException If groups are invalid.
	 */
	private function validate_condition_groups( array $condition_groups ): array {
		$group_ids         = array();
		$normalized_groups = array();

		foreach ( $condition_groups as $group ) {
			$this->validate_group_instance( $group );

			$normalized_action_ids = $this->normalize_action_ids( $group->get_action_ids() );

			if ( $normalized_action_ids !== $group->get_action_ids() ) {
				$group = $group->with_action_ids( $normalized_action_ids );
			}

			$group_id = $group->get_group_id()->get_value();
			$this->validate_unique_group_id( $group_id, $group_ids );

			$group_ids[]         = $group_id;
			$normalized_groups[] = $group;
		}

		return $normalized_groups;
	}
}

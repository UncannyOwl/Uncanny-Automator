<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Condition\Value_Objects;

use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;

/**
 * Condition Group Value Object.
 *
 * Represents a group of conditions that apply to specific actions.
 * Each group has a mode (any/all) that determines how conditions
 * are evaluated together.
 *
 * @since 7.0.0
 */
class Condition_Group {

	private Condition_Group_Id $group_id;
	private int $priority;
	private array $action_ids;
	private Condition_Group_Mode $mode;
	private Recipe_Id $parent_id;
	private array $conditions;

	/**
	 * Constructor.
	 *
	 * @param Condition_Group_Id   $group_id Unique group identifier.
	 * @param int                  $priority Group priority (default 20).
	 * @param array                $action_ids Array of action IDs this group applies to.
	 * @param Condition_Group_Mode $mode Evaluation mode (any/all).
	 * @param Recipe_Id            $parent_id Recipe ID this group belongs to.
	 * @param array                $conditions Array of Individual_Condition instances.
	 * @throws \InvalidArgumentException If parameters are invalid.
	 */
	public function __construct(
		Condition_Group_Id $group_id,
		int $priority,
		array $action_ids,
		Condition_Group_Mode $mode,
		Recipe_Id $parent_id,
		array $conditions = array()
	) {
		$this->validate_priority( $priority );
		$this->validate_action_ids( $action_ids );
		$this->validate_conditions( $conditions );

		$this->group_id   = $group_id;
		$this->priority   = $priority;
		$this->action_ids = array_values( array_unique( $action_ids ) ); // Remove duplicates and reindex
		$this->mode       = $mode;
		$this->parent_id  = $parent_id;
		$this->conditions = $conditions;
	}

	/**
	 * Create a new condition group with generated ID.
	 *
	 * @param array                $action_ids Array of action IDs.
	 * @param Condition_Group_Mode $mode Evaluation mode.
	 * @param Recipe_Id            $parent_id Recipe ID.
	 * @param array                $conditions Array of Individual_Condition instances.
	 * @param int                  $priority Group priority (default 20).
	 * @return self New condition group.
	 */
	public static function create(
		array $action_ids,
		Condition_Group_Mode $mode,
		Recipe_Id $parent_id,
		array $conditions = array(),
		int $priority = 20
	): self {
		return new self(
			Condition_Group_Id::generate(),
			$priority,
			$action_ids,
			$mode,
			$parent_id,
			$conditions
		);
	}

	/**
	 * Get the group ID.
	 *
	 * @return Condition_Group_Id Group identifier.
	 */
	public function get_group_id(): Condition_Group_Id {
		return $this->group_id;
	}

	/**
	 * Get the priority.
	 *
	 * @return int Group priority.
	 */
	public function get_priority(): int {
		return $this->priority;
	}

	/**
	 * Get the action IDs.
	 *
	 * @return array Array of action IDs.
	 */
	public function get_action_ids(): array {
		return $this->action_ids;
	}

	/**
	 * Get the evaluation mode.
	 *
	 * @return Condition_Group_Mode Evaluation mode.
	 */
	public function get_mode(): Condition_Group_Mode {
		return $this->mode;
	}

	/**
	 * Get the parent recipe ID.
	 *
	 * @return Recipe_Id Recipe identifier.
	 */
	public function get_parent_id(): Recipe_Id {
		return $this->parent_id;
	}

	/**
	 * Get all conditions.
	 *
	 * @return Individual_Condition[] Array of Individual_Condition instances.
	 */
	public function get_conditions(): array {
		return $this->conditions;
	}

	/**
	 * Check if group applies to a specific action.
	 *
	 * @param int $action_id Action ID to check.
	 * @return bool True if group applies to the action.
	 */
	public function applies_to_action( int $action_id ): bool {
		return in_array( $action_id, $this->action_ids, true );
	}

	/**
	 * Check if group has conditions.
	 *
	 * @return bool True if group has at least one condition.
	 */
	public function has_conditions(): bool {
		return ! empty( $this->conditions );
	}

	/**
	 * Count the number of conditions.
	 *
	 * @return int Number of conditions in the group.
	 */
	public function count_conditions(): int {
		return count( $this->conditions );
	}

	/**
	 * Add a condition to the group.
	 *
	 * @param Individual_Condition $condition Condition to add.
	 * @return self New instance with added condition.
	 */
	public function with_condition( Individual_Condition $condition ): self {
		$conditions   = $this->conditions;
		$conditions[] = $condition;

		return new self(
			$this->group_id,
			$this->priority,
			$this->action_ids,
			$this->mode,
			$this->parent_id,
			$conditions
		);
	}

	/**
	 * Remove a condition from the group.
	 *
	 * @param Condition_Id $condition_id Condition ID to remove.
	 * @return self New instance without the condition.
	 */
	public function without_condition( Condition_Id $condition_id ): self {
		$conditions = array_filter(
			$this->conditions,
			function ( Individual_Condition $condition ) use ( $condition_id ) {
				return ! $condition->get_condition_id()->equals( $condition_id );
			}
		);

		return new self(
			$this->group_id,
			$this->priority,
			$this->action_ids,
			$this->mode,
			$this->parent_id,
			array_values( $conditions ) // Reindex array
		);
	}

	/**
	 * Update action IDs for the group.
	 *
	 * @param array $action_ids New action IDs.
	 * @return self New instance with updated action IDs.
	 */
	public function with_action_ids( array $action_ids ): self {
		return new self(
			$this->group_id,
			$this->priority,
			$action_ids,
			$this->mode,
			$this->parent_id,
			$this->conditions
		);
	}

	/**
	 * Update evaluation mode.
	 *
	 * @param Condition_Group_Mode $mode New evaluation mode.
	 * @return self New instance with updated mode.
	 */
	public function with_mode( Condition_Group_Mode $mode ): self {
		return new self(
			$this->group_id,
			$this->priority,
			$this->action_ids,
			$mode,
			$this->parent_id,
			$this->conditions
		);
	}

	/**
	 * Convert to array representation for storage.
	 *
	 * @return array Group as array matching legacy format.
	 */
	public function to_array(): array {
		$conditions_array = array_map(
			function ( Individual_Condition $condition ) {
				return $condition->to_array();
			},
			$this->conditions
		);

		return array(
			'id'         => $this->group_id->get_value(),
			'priority'   => $this->priority,
			'actions'    => $this->action_ids,
			'mode'       => $this->mode->get_value(),
			'parent_id'  => $this->parent_id->get_value(),
			'conditions' => $conditions_array,
		);
	}

	/**
	 * Create from array (for hydration from storage).
	 *
	 * @param array $data Array data from storage.
	 * @return self Condition group instance.
	 * @throws \InvalidArgumentException If required fields are missing or invalid.
	 */
	public static function from_array( array $data ): self {
		$required_fields = array( 'id', 'priority', 'actions', 'mode', 'parent_id', 'conditions' );

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				throw new \InvalidArgumentException( sprintf( 'Missing required group field: %s', $field ) );
			}
		}

		$conditions = array_map(
			function ( array $condition_data ) {
				return Individual_Condition::from_array( $condition_data );
			},
			$data['conditions']
		);

		return new self(
			new Condition_Group_Id( $data['id'] ),
			$data['priority'],
			$data['actions'],
			new Condition_Group_Mode( $data['mode'] ),
			new Recipe_Id( $data['parent_id'] ),
			$conditions
		);
	}

	/**
	 * Validate priority value.
	 *
	 * @param int $priority Priority to validate.
	 * @throws \InvalidArgumentException If priority is invalid.
	 */
	private function validate_priority( int $priority ): void {
		if ( $priority <= 0 ) {
			throw new \InvalidArgumentException( 'Priority must be a positive integer' );
		}
	}

	/**
	 * Validate action IDs array.
	 *
	 * @param array $action_ids Action IDs to validate.
	 * @throws \InvalidArgumentException If action IDs are invalid.
	 */
	private function validate_action_ids( array $action_ids ): void {
		// Allow empty action IDs for empty condition groups (granular workflow)
		if ( empty( $action_ids ) ) {
			return;
		}

		foreach ( $action_ids as $action_id ) {
			if ( ! is_int( $action_id ) || $action_id <= 0 ) {
				throw new \InvalidArgumentException( 'All action IDs must be positive integers' );
			}
		}
	}

	/**
	 * Validate conditions array.
	 *
	 * @param array $conditions Conditions to validate.
	 * @throws \InvalidArgumentException If conditions are invalid.
	 */
	private function validate_conditions( array $conditions ): void {
		foreach ( $conditions as $condition ) {
			if ( ! $condition instanceof Individual_Condition ) {
				throw new \InvalidArgumentException( 'All conditions must be Individual_Condition instances' );
			}
		}
	}
}

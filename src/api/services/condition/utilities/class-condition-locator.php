<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Condition\Utilities;

use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group_Id;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group_Mode;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Id;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Individual_Condition;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Action_Conditions;
use WP_Error;

class Condition_Locator {
	/**
	 * Find group.
	 *
	 * @param Recipe_Action_Conditions $conditions The condition.
	 * @param string $group_id The ID.
	 * @return ?
	 */
	public function find_group( Recipe_Action_Conditions $conditions, string $group_id ): ?Condition_Group {
		foreach ( $conditions->get_all() as $group ) {
			if ( $group->get_group_id()->get_value() === $group_id ) {
				return $group;
			}
		}

		return null;
	}
	/**
	 * Require group.
	 *
	 * @param Recipe_Action_Conditions $conditions The condition.
	 * @param string $group_id The ID.
	 * @return mixed
	 */
	public function require_group( Recipe_Action_Conditions $conditions, string $group_id ) {
		$group = $this->find_group( $conditions, $group_id );

		if ( null === $group ) {
			return new WP_Error(
				'group_not_found',
				esc_html_x( 'Condition group not found.', 'Condition locator error', 'uncanny-automator' )
			);
		}

		return $group;
	}
	/**
	 * Replace group.
	 *
	 * @param Recipe_Action_Conditions $conditions The condition.
	 * @param Condition_Group $updated_group The updated group.
	 * @return mixed
	 */
	public function replace_group( Recipe_Action_Conditions $conditions, Condition_Group $updated_group ) {
		return $conditions
			->without_group( $updated_group->get_group_id() )
			->with_group( $updated_group );
	}
	/**
	 * Remove group.
	 *
	 * @param Recipe_Action_Conditions $conditions The condition.
	 * @param string $group_id The ID.
	 * @return mixed
	 */
	public function remove_group( Recipe_Action_Conditions $conditions, string $group_id ) {
		return $conditions->without_group( new Condition_Group_Id( $group_id ) );
	}
	/**
	 * Remove action from groups.
	 *
	 * @param Recipe_Action_Conditions $conditions The condition.
	 * @param int $action_id The ID.
	 * @return mixed
	 */
	public function remove_action_from_groups( Recipe_Action_Conditions $conditions, int $action_id ) {
		return $conditions->without_action_conditions( $action_id );
	}
	/**
	 * Add condition to group.
	 *
	 * @param Condition_Group $group The group.
	 * @param Individual_Condition $condition The condition.
	 * @return Condition_Group
	 */
	public function add_condition_to_group( Condition_Group $group, Individual_Condition $condition ): Condition_Group {
		$conditions   = $group->get_conditions();
		$conditions[] = $condition;

		return new Condition_Group(
			$group->get_group_id(),
			$group->get_priority(),
			$group->get_action_ids(),
			$group->get_mode(),
			$group->get_parent_id(),
			$conditions
		);
	}
	/**
	 * With updated mode.
	 *
	 * @param Condition_Group $group The group.
	 * @param Condition_Group_Mode $mode The mode.
	 * @return Condition_Group
	 */
	public function with_updated_mode( Condition_Group $group, Condition_Group_Mode $mode ): Condition_Group {
		return new Condition_Group(
			$group->get_group_id(),
			$group->get_priority(),
			$group->get_action_ids(),
			$mode,
			$group->get_parent_id(),
			$group->get_conditions()
		);
	}
	/**
	 * With updated priority.
	 *
	 * @param Condition_Group $group The group.
	 * @param int $priority The priority.
	 * @return Condition_Group
	 */
	public function with_updated_priority( Condition_Group $group, int $priority ): Condition_Group {
		return new Condition_Group(
			$group->get_group_id(),
			$priority,
			$group->get_action_ids(),
			$group->get_mode(),
			$group->get_parent_id(),
			$group->get_conditions()
		);
	}
	/**
	 * With updated actions.
	 *
	 * @param Condition_Group $group The group.
	 * @param array $action_ids The ID.
	 * @return Condition_Group
	 */
	public function with_updated_actions( Condition_Group $group, array $action_ids ): Condition_Group {
		return new Condition_Group(
			$group->get_group_id(),
			$group->get_priority(),
			$action_ids,
			$group->get_mode(),
			$group->get_parent_id(),
			$group->get_conditions()
		);
	}
	/**
	 * Remove actions.
	 *
	 * @param Condition_Group $group The group.
	 * @param array $action_ids The ID.
	 * @return Condition_Group
	 */
	public function remove_actions( Condition_Group $group, array $action_ids ): Condition_Group {
		$remaining = array_values( array_diff( $group->get_action_ids(), $action_ids ) );

		return $this->with_updated_actions( $group, $remaining );
	}
	/**
	 * Contains condition.
	 *
	 * @param Condition_Group $group The group.
	 * @param Condition_Id $condition_id The ID.
	 * @return bool
	 */
	public function contains_condition( Condition_Group $group, Condition_Id $condition_id ): bool {
		foreach ( $group->get_conditions() as $condition ) {
			if ( $condition->get_condition_id()->equals( $condition_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove a condition from a group by condition ID.
	 *
	 * @param Condition_Group $group        Group to remove condition from.
	 * @param string          $condition_id Condition ID to remove.
	 * @return Condition_Group Updated group.
	 */
	public function remove_condition_from_group( Condition_Group $group, string $condition_id ): Condition_Group {
		$conditions = $group->get_conditions();
		$filtered   = array();

		foreach ( $conditions as $condition ) {
			if ( $condition->get_condition_id()->get_value() !== $condition_id ) {
				$filtered[] = $condition;
			}
		}

		return new Condition_Group(
			$group->get_group_id(),
			$group->get_priority(),
			$group->get_action_ids(),
			$group->get_mode(),
			$group->get_parent_id(),
			$filtered
		);
	}

	/**
	 * Replace a condition in a group.
	 *
	 * @param Condition_Group      $group            Group containing the condition.
	 * @param string               $condition_id     ID of condition to replace.
	 * @param Individual_Condition $new_condition   New condition to put in place.
	 * @return Condition_Group Updated group.
	 */
	public function replace_condition_in_group( Condition_Group $group, string $condition_id, Individual_Condition $new_condition ): Condition_Group {
		$conditions = $group->get_conditions();
		$updated    = array();

		foreach ( $conditions as $condition ) {
			if ( $condition->get_condition_id()->get_value() === $condition_id ) {
				$updated[] = $new_condition;
			} else {
				$updated[] = $condition;
			}
		}

		return new Condition_Group(
			$group->get_group_id(),
			$group->get_priority(),
			$group->get_action_ids(),
			$group->get_mode(),
			$group->get_parent_id(),
			$updated
		);
	}
}

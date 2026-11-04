<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Action_Logs_Helpers;

use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks\Filter_Block;

final class Conditions_Helper {

	/**
	 * @var mixed[] $conditions The encoded JSON object.
	 */
	protected $conditions;

	/**
	 * This is used to evaluate condition result.
	 *
	 * @var mixed[] $action_item  The action item.
	 */
	protected $action_item = array();

	/**
	 * Used to determine which conditions are evaluated.
	 *
	 * @var mixed[] $evaluated_conditions The list of evaluated conditions.
	 *
	 * @return self
	 */
	protected $evaluated_conditions = array();

	/**
	 * @param string $given_condition
	 *
	 * @return self
	 */
	public function set_conditions( $given_condition ) {

		if ( empty( $given_condition ) ) {
			$this->conditions = array();
			return $this;
		}

		$this->conditions = (array) json_decode( $given_condition, true );

		return $this;
	}

	/**
	 * @return mixed[]
	 */
	public function get_conditions() {
		return $this->conditions;
	}

	/**
	 * @param mixed[] $action_item
	 *
	 * @return self
	 */
	public function set_action_item( $action_item ) {
		$this->action_item = $action_item;
		return $this;
	}

	/**
	 * @return mixed[]
	 */
	public function get_action_item() {
		return $this->action_item;
	}

	/**
	 * @param array<mixed[]> $evaluated_conditions
	 *
	 * @return void
	 */
	public function set_evaluated_conditions( $evaluated_conditions ) {
		$this->evaluated_conditions = $evaluated_conditions;
	}

	/**
	 * @return mixed[]
	 */
	protected function hash_conditions() {

		$hashed_conditions = array();

		foreach ( $this->get_conditions() as $filter ) {
			if ( is_array( $filter ) ) {
				$hashed_conditions[ $filter['id'] ] = $filter;
				foreach ( $filter['conditions'] as $filter_condition ) {
					$hashed_conditions[ $filter['id'] ]['hashed_conditions'][ $filter_condition['id'] ] = $filter_condition;
				}
			}
		}

		return $hashed_conditions;
	}

	/**
	 * @return mixed[]
	 */
	public function get_hash_conditions() {
		return $this->hash_conditions();
	}

	/**
	 * A condition group's match mode: 'any' or 'all'.
	 *
	 * Runs recorded before 8.0, and from 8.0.1 on, name it `mode`. Runs of block-first
	 * recipes recorded by 8.0.0 name it `logic` (Run_Log_Flow_Translator wrote that key then).
	 * A group with neither reads the filter block's default, 'any', as the filter runs with it.
	 *
	 * @since 8.0.1
	 *
	 * @param mixed[] $group A group from get_hash_conditions().
	 *
	 * @return string
	 */
	public function get_match_mode( array $group ) {
		return (string) ( $group['mode'] ?? $group['logic'] ?? Filter_Block::MATCH_MODE_ANY );
	}

	/**
	 * Retrieve the conditions result based on the action runs.
	 *
	 * A group's result is the status of its action in this run, whether or not the group
	 * has conditions: a filter saved with no conditions still ran (or skipped) its actions.
	 *
	 * @return mixed[]
	 */
	public function get_conditions_result() {

		$predermined_result = array();
		$action_item        = $this->get_action_item();

		if ( ! isset( $action_item['runs'][0] ) ) {
			return $predermined_result;
		}

		$status_id      = isset( $action_item['status_id'] ) ? $action_item['status_id'] : 'not-completed';
		$result_message = isset( $action_item['result_message'] ) ? $action_item['result_message'] : '';

		foreach ( $this->get_conditions() as $condition ) {

			$condition = wp_parse_args(
				(array) $condition,
				array(
					'actions' => array(),
				)
			);

			if ( in_array( $action_item['id'], (array) $condition['actions'], true ) ) {
				$predermined_result[ $condition['id'] ] = array(
					'status_id'      => $status_id,
					'result_message' => $result_message,
				);
			}
		}

		return $predermined_result;
	}

	/**
	 * @return mixed[]
	 */
	public function get_evaluated_conditions() {

		$conditions_evaluated_list = array();

		$action_condition_evaluated = $this->evaluated_conditions;

		if ( is_array( $action_condition_evaluated ) ) {
			foreach ( $action_condition_evaluated as $action_condition_evaluated ) {
				if ( ! isset( $action_condition_evaluated['meta_value'] ) ) {
					continue;
				}
				$value = json_decode( $action_condition_evaluated['meta_value'], true );
				if ( false === $value ) {
					continue;
				}
				$conditions_evaluated_list[] = $value;
			}
		}

		return $conditions_evaluated_list;
	}
}

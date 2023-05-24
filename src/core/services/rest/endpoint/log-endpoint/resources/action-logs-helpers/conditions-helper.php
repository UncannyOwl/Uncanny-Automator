<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Action_Logs_Helpers;

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
	 * Retrieve the conditions result based on the action runs.
	 *
	 * @return mixed[]
	 */
	public function get_conditions_result() {

		$predermined_result = array();
		$action_item        = $this->get_action_item();

		foreach ( $this->get_conditions() as $condition ) {

			$condition = wp_parse_args(
				(array) $condition,
				array(
					'conditions' => array(),
				)
			);

			if ( ! is_array( $condition['conditions'] ) ) {
				continue;
			}

			foreach ( $condition['conditions'] as $item ) {

				if ( ! isset( $action_item['runs'][0] ) ) {
					continue;
				}

				$status_id      = isset( $action_item['status_id'] ) ? $action_item['status_id'] : 'not-completed';
				$result_message = isset( $action_item['result_message'] ) ? $action_item['result_message'] : '';

				if ( in_array( $action_item['id'], $condition['actions'], true ) ) {
					$data                                   = array(
						'status_id'      => $status_id,
						'result_message' => $result_message,
					);
					$predermined_result[ $condition['id'] ] = $data;
				}
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

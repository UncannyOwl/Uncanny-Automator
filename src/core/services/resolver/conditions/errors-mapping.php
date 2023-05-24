<?php
namespace Uncanny_Automator\Resolver\Conditions;

/**
 * Maps the error message to the Condition ID.
 *
 * $json = 'sample.json';
 * $mapper = new Errors_Mapping();
 * $mapper->set_action_id( 57 );
 * $mapper->set_source( $json );
 *
 * $conditions = $mapper->resolve_from_source();
 */
class Errors_Mapping {

	/**
	 * @var int $action_id
	 */
	protected $action_id = 0;

	/**
	 * @var mixed[] $source
	 */
	protected $source = array();

	/**
	 * @param int $action_id
	 *
	 * @return void
	 */
	public function set_action_id( $action_id ) {
		$this->action_id = $action_id;
	}


	/**
	 * @param string $source. JSON stringified array/object.
	 *
	 * @return void
	 */
	public function set_source( $source = '' ) {
		$source = json_decode( $source, true );
		if ( empty( $source ) || ! is_array( $source ) ) {
			$source = array();
		}
		$this->source = $source;
	}

	/**
	 * @return mixed[]
	 */
	public function get_source() {
		if ( ! is_array( $this->source ) ) {
			return array();
		}
		return $this->source;
	}

	/**
	 * @param string $code
	 *
	 * @return string[]
	 */
	public function condition_ids_from_code( $code ) {

		$cond_ids  = array();
		$action_id = $this->action_id;
		$source    = $this->get_source();

		foreach ( $source as $filter ) {

			$filter = wp_parse_args(
				(array) $filter,
				array(
					'actions'    => array(),
					'conditions' => array(),
				)
			);

			if ( in_array( $action_id, $filter['actions'], true ) ) {
				if ( is_array( $filter['conditions'] ) ) {
					foreach ( $filter['conditions'] as $condition ) {
						if ( isset( $condition['condition'] ) && $condition['condition'] === $code ) {
							$cond_ids[] = $condition['id'];
						}
					}
				}
			}
		}

		return $cond_ids;
	}

	/**
	 * @return mixed[] The collections of conditions IDs.
	 */
	public function resolve_from_source() {
		$action_id = $this->action_id;
		$source    = $this->get_source();

		foreach ( $source as $filter ) {
			$filter = wp_parse_args(
				(array) $filter,
				array(
					'actions'    => array(),
					'conditions' => array(),
				)
			);
			if ( in_array( $action_id, $filter['actions'], true ) ) {
				if ( is_array( $filter['conditions'] ) ) {
					return array_column( $filter['conditions'], 'id' );
				}
			}
		}

		return array();
	}
}

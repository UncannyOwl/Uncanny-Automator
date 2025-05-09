<?php

namespace Uncanny_Automator\Services\Recipe\Builder\Settings\Fields;

/**
 * Class Field
 *
 * @package Uncanny_Automator\Services\Recipe\Builder\Settings\Fields
 */
class Field {

	/**
	 * @var null
	 */
	protected $field_id = null;

	/**
	 * @var string
	 */
	protected $type = 'string';
	/**
	 * @var null
	 */
	protected $value = null;

	/**
	 * @var array
	 */
	protected $backup = array();

	/**
	 * @return null
	 */
	public function get_field_id() {
		return $this->field_id;
	}

	/**
	 * @param string $field_id
	 *
	 * @return void
	 */
	public function set_field_id( string $field_id ) {
		$this->field_id = $field_id;
	}

	/**
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * @param string $type
	 *
	 * @return void
	 */
	public function set_type( string $type ) {
		$this->type = $type;
	}

	/**
	 * @return null
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * @param \Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\mixed $value
	 *
	 * @return void
	 */
	public function set_value( $value ) {
		$this->value = $value;
	}

	/**
	 * @return array
	 */
	public function get_backup() {
		return $this->backup;
	}

	/**
	 * @param array $key_value_pairs
	 *
	 * @return void
	 */
	public function set_backup( array $key_value_pairs = array() ) {
		$this->backup = $key_value_pairs;
	}

	/**
	 * @return array[]
	 * @throws \Exception
	 */
	public function get_field() {
		// These properties are required.
		$this->ensure_required_properties( array( 'field_id', 'type', 'value', 'backup' ) );

		return array(
			$this->field_id => array(
				'type'   => $this->get_type(),
				'value'  => $this->get_value(),
				'backup' => $this->get_backup(),
			),
		);
	}

	/**
	 * @param array $properties
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function ensure_required_properties( array $properties ) {
		foreach ( $properties as $property ) {
			if ( null === $this->$property
				|| ( is_array( $this->$property )
				&& empty( $this->$property ) ) ) {
				throw new \Exception(
					sprintf(
						'The property "%s" is required and has not been set.',
						esc_html( $property )
					)
				);
			}
		}
	}
}

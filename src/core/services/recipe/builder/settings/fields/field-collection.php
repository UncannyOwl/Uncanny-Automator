<?php
namespace Uncanny_Automator\Services\Recipe\Builder\Settings\Fields;

class Field_Collection {

	/**
	 * @var Field[]
	 */
	protected $fields = array();

	public function add( Field $field, $id ) {
		$this->fields[ $id ] = $field;
	}

	public function get_fields() {
		return $this->fields;
	}

	public function get_fields_formatted( $json_encode = false ) {

		$field_items = array();

		foreach ( (array) $this->fields as $id => $field ) {
			$field_items[ $id ] = array(
				'type'   => $field->get_type(),
				'value'  => $field->get_value(),
				'backup' => $field->get_backup(),
			);
		}

		if ( $json_encode ) {
			return wp_json_encode( $field_items, true );
		}

		return $field_items;
	}
}

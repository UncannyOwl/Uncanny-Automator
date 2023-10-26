<?php

namespace Uncanny_Automator\Services\Integrations;

use Uncanny_Automator\Automator_Exception;

/**
 * Handles the fields from integrations object.
 *
 * @since 5.0
 * @package Uncanny_Automator\Services\Integrations
 *
 */
class Fields {

	/**
	 * @var mixed[] $config
	 * - object_type - String. The type of object, e.g. 'trigger' or 'action'
	 * - recipe_id - Int. The recipe ID
	 * - code - String. The Trigger or the Action code.
	 */
	protected $config = array();

	/**
	 * Sets the config that will be use by the Fields to look into the specific.
	 *
	 * @param mixed[] $config
	 * - object_type - The type of object, e.g. 'trigger' or 'action'
	 * - recipe_id - The recipe ID
	 * - code - The Trigger or the Action code.
	 */
	public function set_config( $config = array() ) {

		$this->config = wp_parse_args(
			$config,
			array(
				'object_type' => 'trigger',
				'recipe_id'   => null,
				'code'        => null,
			)
		);

	}

	/**
	 * Retrieves the object field.
	 *
	 * @return mixed[] The fields
	 * - 'options_group' - When available
	 * - 'options' - When available
	 * @throws Automator_Exception
	 * - If the object does not implement 'options_callback'
	 * - If the the options_callback is not a valid callable.
	 *
	 */
	public function get() {

		if ( 'triggers' === $this->config['object_type'] ) {
			$object = Automator()->get_trigger( $this->config['code'] );
		}

		if ( 'actions' === $this->config['object_type'] ) {
			$object = Automator()->get_action( $this->config['code'] );
		}

		if ( ! $object ) {
			throw new Automator_Exception(
				sprintf(
					'Cannot identify object type of %s',
					$this->config['object_type']
				),
				400
			);
		}

		$options_callback = isset( $object['options_callback'] ) ? $object['options_callback'] : null;

		$options = isset( $object['options'] ) ? $object['options'] : array();

		$options_group = isset( $object['options_group'] ) ? $object['options_group'] : array();

		if ( isset( $options_callback[0] ) && isset( $options_callback[1] ) ) {

			$callable = array( $options_callback[0], $options_callback[1] );

			if ( ! is_callable( $callable ) ) {
				throw new Automator_Exception(
					sprintf(
						'Invalid callback detected for object ' . $this->config['code'],
						$this->config['code']
					),
					400
				);
			}

			try {
				$fields = call_user_func( $callable );
			} catch ( \Error $e ) {
				throw new \Uncanny_Automator\Automator_Error( $e->getMessage() );
			} catch ( \Exception $e ) {
				throw new \Uncanny_Automator\Automator_Exception( $e->getMessage() );
			}

			$options       = isset( $fields['options'] ) ? $fields['options'] : array();
			$options_group = isset( $fields['options_group'] ) ? $fields['options_group'] : array();

			return $this->normalize_fields( $options, $options_group );

		}

		return $this->normalize_fields( $options, $options_group );

	}

	/**
	 * Normalizes the options fields.
	 *
	 * @param mixed[] $options
	 * @param mixed[] $options_group
	 *
	 * @return mixed[]
	 */
	private function normalize_fields( $options, $options_group ) {

		$normalized_options_fields       = array();
		$normalized_options_group_fields = array();

		if ( ! empty( $options ) ) {
			$normalized_options_fields = $this->normalize_options_fields( $options );
		}

		if ( ! empty( $options_group ) ) {
			$normalized_options_group_fields = $this->normalize_options_group_fields( $options_group );
		}

		return (array) array_merge( $normalized_options_fields, $normalized_options_group_fields );

	}

	/**
	 * Normalize the options fields before sending to UI.
	 *
	 * @param mixed[] $options
	 *
	 * @return mixed[]
	 */
	private function normalize_options_fields( $options ) {

		$option_fields = array();

		foreach ( (array) $options as $option ) {
			$option_fields[ $option['option_code'] ][] = $option;
		}

		return $option_fields;

	}

	/**
	 * Normalize the options groups fields.
	 *
	 * @param mixed[] $options_group
	 *
	 * @return mixed[]
	 */
	private function normalize_options_group_fields( $options_group ) {

		$option_fields = array();

		foreach ( (array) $options_group as $group_code => $option_group ) {
			$option_fields[ $group_code ] = $option_group;
		}

		return $option_fields;

	}


}

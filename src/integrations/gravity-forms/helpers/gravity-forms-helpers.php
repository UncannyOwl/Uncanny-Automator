<?php

namespace Uncanny_Automator;

use GFFormsModel;
use Uncanny_Automator_Pro\Gravity_Forms_Pro_Helpers;

/**
 * Class Gravity_Forms_Helpers
 *
 * @package Uncanny_Automator
 */
class Gravity_Forms_Helpers {
	/**
	 * @var Gravity_Forms_Helpers
	 */
	public $options;

	/**
	 * @var Gravity_Forms_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Gravity_Forms_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Gravity_Forms_Helpers $options
	 */
	public function setOptions( Gravity_Forms_Helpers $options ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * @param Gravity_Forms_Pro_Helpers $pro
	 */
	public function setPro( Gravity_Forms_Pro_Helpers $pro ) {  //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function list_gravity_forms( $label = null, $option_code = 'GFFORMS', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Form', 'uncanny-automator' );
		}

		$token                 = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax               = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field          = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point             = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$uncanny_code_specific = key_exists( 'uncanny_code_specific', $args ) ? $args['uncanny_code_specific'] : '';
		$options               = array();

		if ( Automator()->helpers->recipe->load_helpers ) {
			$forms = \GFAPI::get_forms();
			if ( $uncanny_code_specific ) {
				$forms = self::get_uncanny_codes_forms( $forms );
			}
			if ( $forms ) {
				foreach ( $forms as $form ) {
					$options[ $form['id'] ] = esc_html( $form['title'] );
				}
			}
		}
		$type = 'select';

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code         => __( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID' => __( 'Form ID', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_list_gravity_forms', $option );

	}

	/**
	 * @param $forms
	 *
	 * @return mixed
	 */
	public static function get_uncanny_codes_forms( $forms ) {
		if ( empty( $forms ) ) {
			return $forms;
		}

		foreach ( $forms as $k => $form ) {

			$uo_codes_fields = self::is_uncanny_code_field_exist( $form['fields'] );

			if ( ! $uo_codes_fields ) {
				unset( $forms[ $k ] );
			}
		}

		return $forms;
	}

	/**
	 * @param $forms
	 *
	 * @return mixed
	 */
	public static function is_uncanny_code_field_exist( $fields ) {
		$uo_codes_fields = false;
		foreach ( $fields as $field ) {
			if ( GF_SUBFORM_CODES::UO_CODES_FIELD_TYPE !== $field->type ) {
				continue;
			}
			$uo_codes_fields = true;
			break;
		}

		return $uo_codes_fields;
	}

	/**
	 * Get the batch object by code value.
	 *
	 * @param $code_field The code field entry inside Gravity forms object.
	 * @param $entry The GF entry passed from `gform_after_submission` action hook.
	 *
	 * @return object The batch.
	 */
	public static function get_batch_by_value( $code_field, $entry ) {

		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uncanny_codes_codes as tbl_codes
				INNER JOIN {$wpdb->prefix}uncanny_codes_groups as tbl_batch
				WHERE tbl_codes.code_group = tbl_batch.ID
				AND tbl_codes.code = %s",
				$entry[ $code_field->id ]
			)
		);
	}


	/**
	 * Get all code fields via `gform_after_submission` action hook.
	 *
	 * @return array The code fields.
	 */
	public static function get_code_fields( $entry, $form ) {

		// Get all the codes field.
		$uo_codes_fields = array_filter(
			$form['fields'],
			function ( $field ) use ( $entry ) {
				return GF_SUBFORM_CODES::UO_CODES_FIELD_TYPE === $field->type && ! empty( $entry[ $field->id ] );
			}
		);

		return $uo_codes_fields;

	}

}

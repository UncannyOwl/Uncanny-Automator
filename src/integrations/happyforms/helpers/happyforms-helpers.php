<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Happyforms_Pro_Helpers;

/**
 * Class Happyforms_Helpers
 *
 * @package Uncanny_Automator
 */
class Happyforms_Helpers {

	/**
	 * @var Happyforms_Helpers
	 */
	public $options;

	/**
	 * @var Happyforms_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Happyforms_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}


	/**
	 * @param Happyforms_Pro_Helpers $pro
	 */
	public function setPro( Happyforms_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param Happyforms_Helpers $options
	 */
	public function setOptions( Happyforms_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_happyforms_forms( $label = null, $option_code = 'HFFORMS', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Form', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any form', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		if ( Automator()->helpers->recipe->load_helpers ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}
			$form_controller = happyforms_get_form_controller();

			$forms = $form_controller->do_get();

			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form['ID'] ] = $form['post_title'];
				}
			}
		}
		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code         => esc_attr__( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID' => esc_attr__( 'Form ID', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_happyforms_forms', $option );
	}

	/**
	 * @param $entry_id
	 * @param $form_id
	 * @param $args
	 *
	 * @return array
	 */
	public function extract_save_hf_fields( $submission, $form_id, $args ) {
		$data = array();
		if ( ! empty( $submission ) ) {
			$metas          = $submission;
			$trigger_id     = (int) $args['trigger_id'];
			$user_id        = (int) $args['user_id'];
			$trigger_log_id = (int) $args['trigger_log_id'];
			$run_number     = (int) $args['run_number'];
			$meta_key       = (string) $args['meta_key'];

			foreach ( $metas as $field_id => $meta ) {
				$key          = "{$trigger_id}:{$meta_key}:{$form_id}|{$field_id}";
				$data[ $key ] = $meta;
			}

			if ( $data ) {

				$insert = array(
					'user_id'        => $user_id,
					'trigger_id'     => $trigger_id,
					'trigger_log_id' => $trigger_log_id,
					'meta_key'       => $meta_key,
					'meta_value'     => maybe_serialize( $data ),
					'run_number'     => $run_number,
				);

				Automator()->insert_trigger_meta( $insert );
			}
		}

		return $data;
	}

}

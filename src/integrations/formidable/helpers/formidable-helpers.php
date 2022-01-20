<?php


namespace Uncanny_Automator;

use FrmEntryMeta;
use FrmForm;
use Uncanny_Automator_Pro\Formidable_Pro_Helpers;

/**
 * Class Formidable_Helpers
 *
 * @package Uncanny_Automator
 */
class Formidable_Helpers {
	/**
	 * @var Formidable_Helpers
	 */
	public $options;

	/**
	 * @var Formidable_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Formidable_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Formidable_Helpers $options
	 */
	public function setOptions( Formidable_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Formidable_Pro_Helpers $pro
	 */
	public function setPro( Formidable_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_formidable_forms( $label = null, $option_code = 'FIFORMS', $args = array() ) {
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Form', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any product', 'uncanny-automator' ),
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
			$s_query                = [
				[
					'or'               => 1,
					'parent_form_id'   => null,
					'parent_form_id <' => 1,
				],
			];
			$s_query['is_template'] = 0;
			$s_query['status !']    = 'trash';
			$forms                  = FrmForm::getAll( $s_query, '', ' 0, 999' );

			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form->id ] = $form->name;
				}
			}
		}
		$option = [
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
				$option_code          => __( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Form ID', 'uncanny-automator' ),
			),
		];

		return apply_filters( 'uap_option_all_formidable_forms', $option );
	}

	/**
	 * @param $entry_id
	 * @param $form_id
	 * @param $args
	 *
	 * @return array
	 */
	public function extract_save_fi_fields( $entry_id, $form_id, $args ) {
		$data = array();
		if ( $entry_id && class_exists( '\FrmEntryMeta' ) ) {
			$metas          = FrmEntryMeta::get_entry_meta_info( $entry_id );
			$trigger_id     = (int) $args['trigger_id'];
			$user_id        = (int) $args['user_id'];
			$trigger_log_id = (int) $args['trigger_log_id'];
			$run_number     = (int) $args['run_number'];
			$meta_key       = (string) $args['meta_key'];

			foreach ( $metas as $meta ) {
				$field_id     = $meta->field_id;
				$key          = "{$trigger_id}:{$meta_key}:{$form_id}|{$field_id}";
				$data[ $key ] = $meta->meta_value;
			}

			if ( $data ) {

				$insert = [
					'user_id'        => $user_id,
					'trigger_id'     => $trigger_id,
					'trigger_log_id' => $trigger_log_id,
					'meta_key'       => $meta_key,
					'meta_value'     => maybe_serialize( $data ),
					'run_number'     => $run_number,
				];
				Automator()->insert_trigger_meta( $insert );
			}
		}

		return $data;
	}
}

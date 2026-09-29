<?php


namespace Uncanny_Automator;

use FrmEntryMeta;
use Uncanny_Automator\Integrations\Formidable\Formidable_Helpers as Modern_Helpers;
use Uncanny_Automator_Pro\Formidable_Pro_Helpers;

/**
 * Class Formidable_Helpers
 *
 * Superseded by \Uncanny_Automator\Integrations\Formidable\Formidable_Helpers.
 * Nothing in this plugin instantiates or extends it any more.
 *
 * It stays on disk for one reason: a Pro that has not been updated yet declares
 * `Formidable_Pro_Helpers extends Formidable_Helpers`, which PHP resolves when
 * the class is declared. Removing this file would fatal that install on load
 * rather than degrade it. It goes when 8.0.0 forces Free and Pro to match.
 *
 * Since it only exists to keep that declaration resolvable, it holds no logic of
 * its own — each method hands off to the modern helper.
 *
 * @deprecated Use \Uncanny_Automator\Integrations\Formidable\Formidable_Helpers.
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
	public $load_options = true;

	/**
	 * Formidable_Helpers constructor.
	 */
	public function __construct() {
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

		if ( ! $label ) {
			$label = esc_attr_x( 'Form', 'Formidable', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr_x( 'Any product', 'Formidable', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		if ( Automator()->helpers->recipe->load_helpers ) {

			if ( $args['uo_include_any'] ) {
				$options[- 1] = $args['uo_any_label'];
			}

			// The modern helper owns the query; this shape is the legacy one.
			foreach ( ( new Modern_Helpers() )->get_form_options() as $form ) {
				$options[ $form['value'] ] = $form['text'];
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
				$option_code         => esc_html_x( 'Form title', 'Formidable', 'uncanny-automator' ),
				$option_code . '_ID' => esc_html_x( 'Form ID', 'Formidable', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_formidable_forms', $option );
	}

	/**
	 * Write an entry's field values into the trigger log for the legacy parser.
	 *
	 * The one method here with no modern counterpart to hand off to: the
	 * framework now writes token values itself from hydrate_tokens(), bucketed by
	 * tokenIdentifier, so there is nothing equivalent to call. It stays whole for
	 * the un-updated Pro that still drives token storage this way.
	 *
	 * @param int   $entry_id
	 * @param int   $form_id
	 * @param array $args
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

	/**
	 * Determine whether an entry is a completed submission.
	 *
	 * @param int $entry_id
	 *
	 * @return bool
	 */
	public function is_completed_entry( $entry_id ) {
		return ( new Modern_Helpers() )->is_completed_entry( $entry_id );
	}
}

<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Wp_Fluent_Forms_Pro_Helpers;
use WpFluent\Exception;

/**
 * Class Wp_Fluent_Forms_Helpers
 *
 * @package Uncanny_Automator
 */
class Wp_Fluent_Forms_Helpers {
	/**
	 * @var Wp_Fluent_Forms_Helpers
	 */
	public $options;

	/**
	 * @var Wp_Fluent_Forms_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Wp_Fluent_Forms_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Wp_Fluent_Forms_Helpers $options
	 */
	public function setOptions( Wp_Fluent_Forms_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Wp_Fluent_Forms_Pro_Helpers $pro
	 */
	public function setPro( Wp_Fluent_Forms_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function list_wp_fluent_forms( $label = null, $option_code = 'WPFFFORMS', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Form', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		if ( Automator()->helpers->recipe->load_helpers ) {
			if ( function_exists( 'wpFluent' ) ) {
				$forms = wpFluent()->table( 'fluentform_forms' )
				                   ->select( array( 'id', 'title' ) )
				                   ->orderBy( 'id', 'DESC' )
				                   ->get();

				if ( ! empty( $forms ) ) {
					foreach ( $forms as $form ) {
						$options[ $form->id ] = esc_html( $form->title );
					}
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
				$option_code         => esc_attr__( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID' => esc_attr__( 'Form ID', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_list_wp_fluent_forms', $option );

	}

	/**
	 * @param $insert_data
	 * @param $form
	 * @param $args
	 *
	 * @return array
	 */
	public function extract_save_wp_fluent_form_fields( $insert_data, $form, $args ) {
		$data = array();
		if ( $form && function_exists( 'wpFluent' ) ) {
			//$fields  = $form['fields'];
			$form_id        = (int) $form->id;
			$trigger_id     = (int) $args['trigger_id'];
			$user_id        = (int) $args['user_id'];
			$trigger_log_id = (int) $args['trigger_log_id'];
			$run_number     = (int) $args['run_number'];
			$meta_key       = (string) $args['meta_key'];
			if ( $insert_data ) {
				foreach ( $insert_data as $field_id => $field_data ) {
					if ( is_array( $field_data ) ) {
						foreach ( $field_data as $d_d => $d_v ) {
							$child_field_id = $d_d;
							$key            = "{$trigger_id}:{$meta_key}:{$form_id}|{$field_id}|{$child_field_id}";
							$data[ $key ]   = $d_v;
						}
						continue;
					}
					$key          = "{$trigger_id}:{$meta_key}:{$form_id}|{$field_id}";
					$data[ $key ] = $field_data;
				}
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
	 * Matching form fields values.
	 *
	 * @param array $entry form data.
	 * @param array|null $recipes recipe data.
	 * @param string|null $trigger_meta trigger meta key.
	 * @param string|null $trigger_code trigger code key.
	 * @param string|null $trigger_second_code trigger second code key.
	 *
	 * @return array|bool
	 */
	public function match_condition( $entry, $recipes = null, $trigger_meta = null, $trigger_code = null, $trigger_second_code = null ) {
		if ( null === $recipes ) {
			return false;
		}

		$matches        = array();
		$recipe_ids     = array();
		$entry_to_match = $entry['form_id'];
		//Matching recipe ids that has trigger meta
		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( key_exists( $trigger_meta, $trigger['meta'] ) && (int) $trigger['meta'][ $trigger_meta ] === (int) $entry_to_match ) {
					$matches[ $recipe['ID'] ]    = array(
						'field' => $trigger['meta'][ $trigger_code ],
						'value' => $trigger['meta'][ $trigger_second_code ],
					);
					$recipe_ids[ $recipe['ID'] ] = $recipe['ID'];
					break;
				}
			}
		}

		//Figure if field is available and data matches!!
		if ( ! empty( $matches ) ) {
			$matched = false;
			$fields  = $entry['fields'];
			foreach ( $matches as $recipe_id => $match ) {
				foreach ( $fields as $field ) {
					$field_id = $field['id'];
					if ( absint( $match['field'] ) !== absint( $field_id ) ) {
						continue;
					}

					$value = $field['value'];
					if ( ( (int) $field_id === (int) $match['field'] ) && ( $value == $match['value'] ) ) {
						$matched = true;
						break;
					}
				}

				if ( ! $matched ) {
					unset( $recipe_ids[ $recipe_id ] );
				}
			}
		}

		if ( ! empty( $recipe_ids ) ) {
			return array(
				'recipe_ids' => $recipe_ids,
				'result'     => true,
			);
		}

		return false;
	}
}

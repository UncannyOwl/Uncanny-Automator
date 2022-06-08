<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Elementor_Pro_Helpers;

/**
 * Class Elementor_Helpers
 *
 * @package Uncanny_Automator
 */
class Elementor_Helpers {
	/**
	 * @var Elementor_Helpers
	 */
	public $options;

	/**
	 * @var Elementor_Pro_Helpers
	 */
	public $pro;

	public $load_options = true;

	/**
	 * Elementor_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param Elementor_Helpers $options
	 */
	public function setOptions( Elementor_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Elementor_Pro_Helpers $pro
	 */
	public function setPro( Elementor_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_elementor_forms( $label = null, $option_code = 'ELEMFORMS', $args = array() ) {

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
				$options['-1'] = $args['uo_any_label'];
			}
			global $wpdb;
			$post_metas = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.meta_value
FROM $wpdb->postmeta pm
    LEFT JOIN $wpdb->posts p
        ON p.ID = pm.post_id
WHERE p.post_type IS NOT NULL
  AND p.post_type NOT LIKE %s
  AND p.post_status NOT IN('trash', 'inherit', 'auto-draft')
  AND pm.meta_key = %s
  AND pm.`meta_value` LIKE %s",
					'revision',
					'_elementor_data',
					'%%form_fields%%'
				)
			);

			if ( ! empty( $post_metas ) ) {
				foreach ( $post_metas as $post_meta ) {
					$inner_forms = self::get_all_inner_forms( json_decode( $post_meta->meta_value ) );
					if ( ! empty( $inner_forms ) ) {
						foreach ( $inner_forms as $form ) {
							$options[ $form->id ] = $form->settings->form_name;
						}
					}
				}
			}
		}//end if

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
				$option_code         => __( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID' => __( 'Form ID', 'uncanny-automator' ),
			),
		);

		//      Automator()->cache->set( 'uap_option_all_elementor_forms', $option );

		return apply_filters( 'uap_option_all_elementor_forms', $option );
	}

	public static function get_all_inner_forms( $elements ) {
		$block_is_on_page = array();
		if ( ! empty( $elements ) ) {
			foreach ( $elements as $element ) {
				if ( 'widget' === $element->elType && 'form' === $element->widgetType ) {
					$block_is_on_page[] = $element;
				}
				if ( ! empty( $element->elements ) ) {
					$inner_block_is_on_page = self::get_all_inner_forms( $element->elements );
					if ( ! empty( $inner_block_is_on_page ) ) {
						$block_is_on_page = array_merge( $block_is_on_page, $inner_block_is_on_page );
					}
				}
			}
		}

		return $block_is_on_page;
	}
}

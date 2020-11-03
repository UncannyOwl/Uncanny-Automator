<?php


namespace Uncanny_Automator;


use Uncanny_Automator_Pro\Wpjm_Pro_Helpers;

/**
 * Class Wpjm_Helpers
 * @package Uncanny_Automator
 */
class Wpjm_Helpers {
	/**
	 * @var Wpjm_Helpers
	 */
	public $options;

	/**
	 * @var Wpjm_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Wpjm_Helpers constructor.
	 */
	public function __construct() {
		global $uncanny_automator;
		$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Wpjm_Helpers $options
	 */
	public function setOptions( Wpjm_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Wpjm_Pro_Helpers $pro
	 */
	public function setPro( Wpjm_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */

	public function list_wpjm_job_types( $label = null, $option_code = 'WPJMJOBTYPE', $args = [] ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		global $uncanny_automator;
		if ( ! $label ) {
			$label =  esc_attr__( 'Job type', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];

		$options['-1'] = __( 'Any type', 'uncanny-automator' );

		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			// WP Job Manager is hidding terms on non job template
			$terms = get_terms( 'job_listing_type', [ 'hide_empty' => false, 'public' => false ] );
			if ( ! is_wp_error( $terms ) ) {
				if ( ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$options[ $term->term_id ] = esc_html( $term->name );
					}
				}
			}
		}
		$type = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];

		return apply_filters( 'uap_option_list_wpjm_job_types', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */

	public function list_wpjm_jobs( $label = null, $option_code = 'WPJMJOBS', $args = [] ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		global $uncanny_automator;
		if ( ! $label ) {
			$label =  esc_attr__( 'Job', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];

		$options['-1'] = __( 'Any job', 'uncanny-automator' );

		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			// WP Job Manager is hidding terms on non job template
			$args = [
				'post_type'      => 'job_listing',
				'posts_per_page' => 9999,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			];
			$jobs = get_posts( $args );
			if ( ! is_wp_error( $jobs ) ) {
				if ( ! empty( $jobs ) ) {
					foreach ( $jobs as $job ) {
						$options[ $job->ID ] = esc_html( $job->post_title );
					}
				}
			}
		}
		$type = 'select';

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		];
		
		return apply_filters( 'uap_option_list_wpjm_jobs', $option );
	}
}
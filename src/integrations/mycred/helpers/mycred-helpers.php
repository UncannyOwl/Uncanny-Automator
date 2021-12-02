<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Mycred_Pro_Helpers;

/**
 * Class Mycred_Helpers
 *
 * @package Uncanny_Automator
 */
class Mycred_Helpers {

	/**
	 * @var Mycred_Helpers
	 */
	public $options;
	/**
	 * @var Mycred_Pro_Helpers
	 */
	public $pro;
	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Mycred_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Mycred_Helpers $options
	 */
	public function setOptions( Mycred_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Mycred_Pro_Helpers $pro
	 */
	public function setPro( Mycred_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function list_mycred_points_types( $label = null, $option_code = 'MYCREDPOINTSTYPES', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}
		if ( ! $label ) {
			$label = esc_attr__( 'Point type', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$include_all  = key_exists( 'include_all', $args ) ? $args['include_all'] : false;

		$options = array();

		if ( $include_all ) {
			$options['ua-all-mycred-points'] = esc_attr__( 'All point types', 'uncanny-automator' );
		}

		if ( Automator()->helpers->recipe->load_helpers ) {
			$posts = mycred_get_types();

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $key => $post ) {
					$options[ $key ] = $post;
				}
			}
		}
		$type = 'select';

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => $type,
			'required'                 => true,
			'supports_tokens'          => $token,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $options,
			'custom_value_description' => esc_attr__( 'Point type meta key', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_list_mycred_points_types', $option );
	}

	/**
	 * @param null   $label
	 * @param string $option_code
	 * @param array  $args
	 *
	 * @return mixed|void
	 */
	public function list_mycred_rank_types( $label = null, $option_code = 'MYCREDRANKTYPES', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}
		if ( ! $label ) {
			$label = esc_attr__( 'Ranks', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$include_all  = key_exists( 'include_all', $args ) ? $args['include_all'] : false;
		$options      = array();

		if ( $include_all ) {
			$options['ua-all-mycred-ranks'] = esc_attr__( 'All ranks', 'uncanny-automator' );
		}

		/*if ( Automator()->helpers->recipe->load_helpers ) {
			$posts = get_posts( [
				'post_type'      => 'mycred_rank',
				'posts_per_page' => 9999,
				'post_status'    => 'publish'
			] );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					if ( $post->post_type === 'mycred_rank' ) {
						$options[ $post->ID ] = $post->post_title;
					}
				}
			}
		}*/
		$query_args = array(
			'post_type'      => 'mycred_rank',
			'posts_per_page' => 9999,
			'post_status'    => 'publish',
		);
		$options    = Automator()->helpers->recipe->wp_query( $query_args );
		$type       = 'select';

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => $type,
			'required'                 => true,
			'supports_tokens'          => $token,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $options,
			'custom_value_description' => esc_attr__( 'Rank ID', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_list_mycred_rank_types', $option );
	}

	public function list_mycred_badges( $label = null, $option_code = 'MYCREDBADGETYPES', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}
		if ( ! $label ) {
			$label = esc_attr__( 'Badges', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$include_all  = key_exists( 'include_all', $args ) ? $args['include_all'] : false;
		$options      = array();

		if ( $include_all ) {
			$options['ua-all-mycred-badges'] = esc_attr__( 'All badges', 'uncanny-automator' );
		}

		/*if ( Automator()->helpers->recipe->load_helpers ) {
			$posts = get_posts( [
				'post_type'      => 'mycred_badge',
				'posts_per_page' => 9999,
				'post_status'    => 'publish'
			] );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					if ( $post->post_type === 'mycred_badge' ) {
						$options[ $post->ID ] = $post->post_title;
					}
				}
			}
		}*/
		$query_args = array(
			'post_type'      => 'mycred_badge',
			'posts_per_page' => 9999,
			'post_status'    => 'publish',
		);
		$options    = Automator()->helpers->recipe->wp_query( $query_args );
		$type       = 'select';

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => $type,
			'required'                 => true,
			'supports_tokens'          => $token,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $options,
			'custom_value_description' => esc_attr__( 'Badge ID', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_list_mycred_badges', $option );
	}

}

<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Badgeos_Pro_Helpers;

/**
 * Class Badgeos_Helpers
 *
 * @package Uncanny_Automator
 */
class Badgeos_Helpers {
	/**
	 * @var Badgeos_Helpers
	 */
	public $options;
	/**
	 * @var Badgeos_Pro_Helpers
	 */
	public $pro;
	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Badgeos_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_action(
			'wp_ajax_select_achievements_from_types_BOAWARDACHIEVEMENT',
			array(
				$this,
				'select_achievements_from_types_func',
			)
		);
		add_action( 'wp_ajax_select_ranks_from_types_BOAWARDRANKS', array( $this, 'select_ranks_from_types_func' ) );
	}

	/**
	 * @param Badgeos_Helpers $options
	 */
	public function setOptions( Badgeos_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Badgeos_Pro_Helpers $pro
	 */
	public function setPro( Badgeos_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function list_bo_award_types( $label = null, $option_code = 'BOAWARDTYPES', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Achievement type', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$is_any       = key_exists( 'is_any', $args ) ? $args['is_any'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		if ( $is_any == true ) {
			$options['-1'] = __( 'Any achievement', 'uncanny-automator' );
		}

		global $wpdb;
		if ( Automator()->helpers->recipe->load_helpers ) {

			//$posts = Automator()->helpers->recipe->options->wp_query( [ 'post_type' => 'achievement-type' ] );
			$posts = $wpdb->get_results(
				"SELECT ID, post_name, post_title, post_type
											FROM $wpdb->posts
											WHERE post_type LIKE 'achievement-type' AND post_status = 'publish' ORDER BY post_title ASC"
			);

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$options[ $post->post_name ] = $post->post_title;
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
			'custom_value_description' => _x( 'Achievement type slug', 'BadgeOS', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_list_bo_award_types', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function list_bo_points_types( $label = null, $option_code = 'BOPOINTSTYPES', $args = array() ) {
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
			$options['ua-all-bo-types'] = esc_attr__( 'All point types', 'uncanny-automator' );
		}

		global $wpdb;
		if ( Automator()->helpers->recipe->load_helpers ) {

			//$posts = Automator()->helpers->recipe->options->wp_query( [ 'post_type' => 'point_type' ] );
			$posts = $wpdb->get_results(
				"SELECT ID, post_name, post_title
											FROM $wpdb->posts
											WHERE post_type LIKE 'point_type' AND post_status = 'publish' ORDER BY post_title ASC"
			);

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$options[ $post->post_name ] = $post->post_title;
				}
			}
		}
		//$options = Automator()->helpers->recipe->options->wp_query( [ 'post_type' => 'point_type' ] );
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
			'custom_value_description' => _x( 'Point type slug', 'BadgeOS', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_list_bo_points_types', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function list_bo_rank_types( $label = null, $option_code = 'BORANKTYPES', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Rank type', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		global $wpdb;
		if ( Automator()->helpers->recipe->load_helpers ) {

			//$posts = Automator()->helpers->recipe->options->wp_query( [ 'post_type' => 'rank_types' ] );
			$posts = $wpdb->get_results(
				"SELECT ID, post_name, post_title, post_type
											FROM $wpdb->posts
											WHERE post_type LIKE 'rank_types' AND post_status = 'publish' ORDER BY post_title ASC"
			);
			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$options[ $post->post_name ] = $post->post_title;
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
			'custom_value_description' => _x( 'Rank type slug', 'BadgeOS', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_list_bo_rank_types', $option );
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function select_achievements_from_types_func() {

		// Nonce and post object validation.
		Automator()->utilities->ajax_auth_check();

		$value = automator_filter_input( 'value', INPUT_POST );

		$fields = array();

		if ( isset( $value ) && ! empty( $value ) ) {

			$args = array(
				'post_type'      => sanitize_text_field( $value ),
				'posts_per_page' => 999,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			);

			$options = Automator()->helpers->recipe->options->wp_query( $args, true, esc_attr__( 'Any awards', 'uncanny-automator' ) );

			foreach ( $options as $award_id => $award_name ) {
				$fields[] = array(
					'value' => $award_id,
					'text'  => $award_name,
				);
			}
		}
		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function select_ranks_from_types_func() {

		// Nonce and post object validation.
		Automator()->utilities->ajax_auth_check();

		$value = automator_filter_input( 'value', INPUT_POST );

		$fields = array();

		if ( isset( $value ) && ! empty( $value ) ) {

			$args = array(
				'post_type'      => sanitize_text_field( $value ),
				'posts_per_page' => 999,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			);

			$options = Automator()->helpers->recipe->options->wp_query( $args, false, esc_attr__( 'Any awards', 'uncanny-automator' ) );

			foreach ( $options as $award_id => $award_name ) {
				$fields[] = array(
					'value' => $award_id,
					'text'  => $award_name,
				);
			}
		}
		echo wp_json_encode( $fields );
		die();
	}
}

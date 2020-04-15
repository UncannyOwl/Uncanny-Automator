<?php


namespace Uncanny_Automator;

/**
 * Class Memberpress_Helpers
 * @package Uncanny_Automator
 */
class Memberpress_Helpers {
	/**
	 * @var Memberpress_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Memberpress_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Memberpress_Helpers $options
	 */
	public function setOptions( Memberpress_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Memberpress_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Memberpress_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products( $label = null, $option_code = 'MPPRODUCT', $args = [] ) {
		if ( ! $label ) {
			$label = __( 'Select a product', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any product', 'uncanny-automator' ),
			)
		);

		$options = [];
		global $uncanny_automator;
		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}

			$posts = get_posts( [
				'post_type'      => 'memberpressproduct',
				'posts_per_page' => 999,
				'post_status'    => 'publish',
			] );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$options[ $post->ID ] = $post->post_title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Product Title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Product URL', 'uncanny-automator' ),
			],
		];


		return apply_filters( 'uap_option_all_memberpress_products', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products_onetime( $label = null, $option_code = 'MPPRODUCT', $args = [] ) {
		if ( ! $label ) {
			$label = __( 'Select a product', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any one-time subscription product', 'uncanny-automator' ),
			)
		);

		$options = [];
		global $uncanny_automator;
		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}

			$posts = get_posts( [
				'post_type'      => 'memberpressproduct',
				'posts_per_page' => 999,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => '_mepr_product_period_type',
						'value'   => 'lifetime',
						'compare' => '=',
					]
				]
			] );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$options[ $post->ID ] = $post->post_title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Product Title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Product URL', 'uncanny-automator' ),
			],
		];


		return apply_filters( 'uap_option_all_memberpress_products_onetime', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products_recurring( $label = null, $option_code = 'MPPRODUCT', $args = [] ) {
		if ( ! $label ) {
			$label = __( 'Select a product', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any recurring subscription product', 'uncanny-automator' ),
			)
		);

		$options = [];
		global $uncanny_automator;
		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}

			$posts = get_posts( [
				'post_type'      => 'memberpressproduct',
				'posts_per_page' => 999,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => '_mepr_product_period_type',
						'value'   => 'lifetime',
						'compare' => '!=',
					]
				]
			] );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$options[ $post->ID ] = $post->post_title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Product Title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Product URL', 'uncanny-automator' ),
			],
		];


		return apply_filters( 'uap_option_all_memberpress_products_recurring', $option );
	}

}
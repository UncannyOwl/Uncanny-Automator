<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Wpsp_Pro_Helpers;

/**
 * Class Wpsp_Helpers
 *
 * @package Uncanny_Automator
 */
class Wpsp_Helpers {

	/**
	 * @var Wpsp_Helpers
	 */
	public $options;

	/**
	 * @var Wpsp_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * Wpsp_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param Wpsp_Helpers $options
	 */
	public function setOptions( Wpsp_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * @param Wpsp_Pro_Helpers $pro
	 */
	public function setPro( Wpsp_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * @return array|string[]
	 */
	public function get_forms() {
		$options = array();
		if ( function_exists( 'simpay_get_form_list_options' ) ) {
			return simpay_get_form_list_options();
		}

		$forms = get_posts(
			array(
				'post_type'      => 'simple-pay',
				'posts_per_page' => 9999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'fields'         => 'ids',
			)
		);

		foreach ( $forms as $form_id ) {
			$options[ $form_id ] = get_the_title( $form_id );
		}

		return $options;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */

	public function list_wp_simpay_forms( $label = null, $option_code = 'WPSPFORMS', $args = array() ) {

		if ( ! $label ) {
			$label = esc_attr_x( 'Form', 'Wp Simple Pay', 'uncanny-automator' );
		}

		$token           = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_any          = key_exists( 'is_any', $args ) ? $args['is_any'] : false;
		$is_subscription = key_exists( 'is_subscription', $args ) ? $args['is_subscription'] : false;
		$options         = array();
		$wpsp_forms      = $this->get_forms();
		if ( ! empty( $wpsp_forms ) ) {
			foreach ( $wpsp_forms as $form_id => $form_title ) {
				$form = simpay_get_form( $form_id );
				if ( true === $is_subscription && 'disabled' === $form->subscription_type ) {
					continue;
				}
				$options[ $form_id ] = ! empty( $form_title ) ? $form_title : $form->company_name;
			}
		}
		if ( true === $is_any ) {
			$options = array( '-1' => esc_html_x( 'Any form', 'Wp Simple Pay', 'uncanny-automator' ) ) + $options;
		}
		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code         => esc_attr_x( 'Form title', 'Wp Simple Pay', 'uncanny-automator' ),
				$option_code . '_ID' => esc_attr_x( 'Form ID', 'Wp Simple Pay', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_list_wp_simpay_forms', $option );
	}

	/**
	 * @param $simpay_price_instances
	 * @param $form_id
	 *
	 * @return mixed|void
	 */
	public function get_price_option_value( $simpay_price_instances, $form_id ) {
		$price_instance = explode( ':', $simpay_price_instances );
		$price_meta_key = simpay_is_livemode() ? '_simpay_prices_live' : '_simpay_prices_test';
		$prices         = get_post_meta( $form_id, $price_meta_key, true );
		foreach ( $prices as $price_key => $price_details ) {
			if ( $price_key === $price_instance[0] ) {
				return $price_details['label'];
			}
		}
	}
}

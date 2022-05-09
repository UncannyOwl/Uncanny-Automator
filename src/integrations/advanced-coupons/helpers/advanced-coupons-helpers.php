<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Advanced_Coupons_Pro_Helpers;

/**
 * Class Advanced_Coupons_Helpers
 *
 * @package Uncanny_Automator
 */
class Advanced_Coupons_Helpers {

	/**
	 * Options variable for the class
	 *
	 * @var Advanced_Coupons_Helpers
	 */
	public $options;

	/**
	 * pro variable for Pro version check
	 *
	 * @var Advanced_Coupons_Pro_Helpers
	 */
	public $pro;

	/**
	 * Load options to store default options when file loads.
	 *
	 * @var bool
	 */
	public $load_options;

	/**
	 * Advanced_Coupons_Helpers constructor.
	 */
	public function __construct() {
		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Advanced_Coupons_Helpers $options
	 */
	public function setOptions( Advanced_Coupons_Helpers $options ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Checks if Pro version of the plugin is activated or not.
	 *
	 * @param Advanced_Coupons_Pro_Helpers $pro
	 */
	public function setPro( Advanced_Coupons_Pro_Helpers $pro ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * Get customer's current store credit balance
	 */
	public function get_current_balance_of_the_customer( $user_id ) {
		if ( empty( $user_id ) || 0 === $user_id ) {
			return 0;
		}

		return apply_filters( 'acfw_filter_amount', \ACFWF()->Store_Credits_Calculate->get_customer_balance( $user_id ) );
	}

	/**
	 * Get customer's lifetime store credit balance
	 */
	public function get_total_credits_of_the_user( $user_id ) {
		global $wpdb;

		$raw_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT entry_type,entry_action,CONVERT(entry_amount, DECIMAL(%d,%d)) AS amount
                FROM {$wpdb->prefix}acfw_store_credits
                WHERE user_id = %d",
				\ACFWF()->Store_Credits_Calculate->get_decimal_precision(),
				wc_get_price_decimals(),
				$user_id
			),
			ARRAY_A
		);

		$total_amount = 0;
		foreach ( $raw_data as $value ) {
			if ( isset( $value['entry_type'] ) && 'increase' === $value['entry_type'] ) {
				$total_amount += floatval( $value['amount'] );
			}
		}

		return apply_filters( 'acfw_filter_amount', $total_amount );
	}

	/**
	 * Get drop down options for the conditions.
	 *
	 * @param Get peepso users
	 */
	public function get_options_for_credit( $label = null, $option_code = 'PPCONDITION', $args = array() ) {

		if ( ! $label ) {
			$label = esc_attr__( 'Select condition', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any condition', 'uncanny-automator' ),
			)
		);

		$options = array();

		if ( $args['uo_include_any'] ) {
			$options['-1'] = $args['uo_any_label'];
		}

		$options['EQ']     = __( 'equal to', 'uncanny-automator' );
		$options['NOT_EQ'] = __( 'not equal to', 'uncanny-automator' );
		$options['LT']     = __( 'less than', 'uncanny-automator' );
		$options['GT']     = __( 'greater than', 'uncanny-automator' );
		$options['GT_EQ']  = __( 'greater or equal to', 'uncanny-automator' );
		$options['LT_EQ']  = __( 'less or equal to', 'uncanny-automator' );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(),
		);

		return apply_filters( 'uap_option_advanced_coupons_all_conditions', $option );
	}
}

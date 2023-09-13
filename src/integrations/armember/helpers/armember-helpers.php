<?php

namespace Uncanny_Automator;

/**
 * Class Armember_Helpers
 *
 * @package Uncanny_Automator
 */
class Armember_Helpers {

	/**
	 * @var \ARM_subscription_plans|\ARM_subscription_plans_Lite|string
	 */
	private $armember_subscription_class = '';

	/**
	 * helpers __construct
	 */
	public function __construct() {
		// If LITE version is active
		if ( defined( 'MEMBERSHIPLITE_DIR_NAME' ) && ! defined( 'MEMBERSHIP_DIR_NAME' ) ) {
			$this->armember_subscription_class = new \ARM_subscription_plans_Lite();
		}
		// If Pro version is active
		if ( defined( 'MEMBERSHIP_DIR_NAME' ) ) {
			$this->armember_subscription_class = new \ARM_subscription_plans();
		}
	}

	/**
	 * @param $args
	 *
	 * @return array|mixed|void
	 */
	public function get_all_plans( $args = array() ) {

		$defaults = array(
			'option_code'           => 'AR_MEMBERSHIP_PLANS',
			'label'                 => esc_attr__( 'Membership plan', 'uncanny-automator' ),
			'supports_custom_value' => false,
			'is_ajax'               => false,
			'target_field'          => null,
			'endpoint'              => null,
			'is_any'                => false,
			'is_all'                => false,
		);
		$args     = wp_parse_args( $args, $defaults );

		$armember_plans = $this->armember_subscription_class;
		$plans          = $armember_plans->arm_get_all_subscription_plans( 'arm_subscription_plan_id,arm_subscription_plan_name' );
		$options        = array();

		foreach ( $plans as $plan_id => $plan_name ) {
			if ( empty( $plan_name ) ) {
				$plan_id = sprintf( esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $plan_id );
			}

			$options[ $plan_id ] = $plan_name['arm_subscription_plan_name'];
		}

		if ( true === $args['is_any'] ) {
			$options = array( '-1' => __( 'Any membership', 'uncanny-automator' ) ) + $options;
		}

		if ( true === $args['is_all'] ) {
			$options = array( '-1' => __( 'All memberships', 'uncanny-automator' ) ) + $options;
		}

		$option = array(
			'input_type'            => 'select',
			'option_code'           => $args['option_code'],
			/* translators: HTTP request method */
			'label'                 => $args['label'],
			'required'              => true,
			'supports_custom_value' => $args['supports_custom_value'],
			'relevant_tokens'       => array(),
			'options'               => $options,
			'options_show_id'       => apply_filters( 'automator_options_show_id', true, $this ),
		);

		return apply_filters( 'uap_option_get_all_plans', $option );
	}

}

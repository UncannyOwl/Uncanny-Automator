<?php

namespace Uncanny_Automator;

use SimplePay\Core\Abstracts\Form;
use SimplePay\Vendor\Stripe\Charge;
use SimplePay\Vendor\Stripe\Event;
use Uncanny_Automator\Recipe\Trigger;

/**
 * Class WPSP_PAYMENT_FOR_FORM_FULLY_REFUNDED
 * @package Uncanny_Automator
 */
class WPSP_PAYMENT_FOR_FORM_FULLY_REFUNDED extends Trigger {

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'WPSIMPLEPAY' );
		$this->set_trigger_code( 'WPSP_PAYMENT_FULLY_REFUNDED' );
		$this->set_trigger_meta( 'WPSPFORMS' );
		$this->set_trigger_type( 'anonymous' );
		// translators: WP Simple Pay form title
		$this->set_sentence( sprintf( esc_attr_x( 'A payment for {{a form:%1$s}} is fully refunded', 'WP Simple Pay', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A payment for {{a form}} is fully refunded', 'WP Simple Pay', 'uncanny-automator' ) );
		$this->add_action( 'simpay_webhook_charge_refunded', 10, 3 );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		$all_products = Automator()->helpers->recipe->wp_simple_pay->options->list_wp_simpay_forms(
			null,
			$this->get_trigger_meta(),
			array(
				'is_any' => true,
			)
		);
		$options      = array();
		foreach ( $all_products['options'] as $k => $option ) {
			$options[] = array(
				'text'  => $option,
				'value' => $k,
			);
		}

		return array(
			array(
				'input_type'  => 'select',
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Form', 'WP Simple Pay', 'uncanny-automator' ),
				'required'    => true,
				'options'     => $options,
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		if ( ! isset( $hook_args[0], $hook_args[1] ) ) {
			return false;
		}

		/** @var Event $event */
		/** @var Charge $charge */
		/** @var Form $form */
		list( $event, $charge, $form ) = $hook_args;

		$selected_form_id = $trigger['meta'][ $this->get_trigger_meta() ];
		$form_id          = $form->id;

		if ( ! isset( $form_id ) ) {
			return false;
		}

		$billing_email = $charge->billing_details->email;
		if ( is_email( $billing_email ) ) {
			$user_id = false === email_exists( $billing_email ) ? 0 : email_exists( $billing_email );
			$this->set_user_id( $user_id );
		}

		$original_amount = $charge->amount / 100; // Convert from cents to dollars
		$refunded_amount = $charge->amount_refunded / 100;

		// Any form or specific form
		return ( ( intval( '-1' ) === intval( $selected_form_id ) || absint( $selected_form_id ) === absint( $form_id ) ) && $original_amount === $refunded_amount );
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $tokens
	 * @param array $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$trigger_tokens      = new Wpsp_Tokens();
		$custom_field_tokens = $trigger_tokens->get_custom_field_tokens( $trigger['meta'][ $this->get_trigger_meta() ], $tokens, $this->get_trigger_code() );
		$trigger_tokens      = array(
			array(
				'tokenId'   => 'BILLING_NAME',
				'tokenName' => esc_html_x( 'Billing name', 'Wp Simple Pay', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_EMAIL',
				'tokenName' => esc_html_x( 'Billing email', 'Wp Simple Pay', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'BILLING_TELEPHONE',
				'tokenName' => esc_html_x( 'Billing phone', 'Wp Simple Pay', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_STREET_ADDRESS',
				'tokenName' => esc_html_x( 'Billing address', 'Wp Simple Pay', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_CITY',
				'tokenName' => esc_html_x( 'Billing city', 'Wp Simple Pay', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_STATE',
				'tokenName' => esc_html_x( 'Billing state', 'Wp Simple Pay', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_POSTAL_CODE',
				'tokenName' => esc_html_x( 'Billing postal code', 'Wp Simple Pay', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_COUNTRY',
				'tokenName' => esc_html_x( 'Billing country', 'Wp Simple Pay', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRICE_OPTION',
				'tokenName' => esc_html_x( 'Price option', 'Wp Simple Pay', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'QUANTITY_PURCHASED',
				'tokenName' => esc_html_x( 'Quantity', 'Wp Simple Pay', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'AMOUNT_REFUNDED',
				'tokenName' => esc_html_x( 'Refunded amount', 'Wp Simple Pay', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $trigger_tokens, $custom_field_tokens );
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		/** @var Event $event */
		/** @var Charge $charge */
		/** @var Form $form */
		list( $event, $charge, $form ) = $hook_args;
		$defaults                      = wp_list_pluck( $this->define_tokens( $trigger, array() ), 'tokenId' );
		$metadata                      = $charge->metadata->toArray();

		$trigger_token_values = array(
			'WPSPFORMS'              => $form->company_name,
			'BILLING_NAME'           => $charge->customer->name,
			'BILLING_EMAIL'          => $charge->customer->email,
			'BILLING_TELEPHONE'      => $charge->customer->phone,
			'BILLING_STREET_ADDRESS' => $charge->billing_details->address->line1 . ' ' . $charge->billing_details->address->line1,
			'BILLING_CITY'           => $charge->billing_details->address->city,
			'BILLING_POSTAL_CODE'    => $charge->billing_details->address->postal_code,
			'BILLING_STATE'          => $charge->billing_details->address->state,
			'BILLING_COUNTRY'        => $charge->billing_details->address->country,
			'PRICE_OPTION'           => Automator()->helpers->recipe->wp_simple_pay->options->get_price_option_value( $charge->metadata->simpay_price_instances, $form->id ),
			'QUANTITY_PURCHASED'     => $charge->metadata->simpay_quantity,
			'AMOUNT_PAID'            => simpay_format_currency( $charge->amount ),
			'AMOUNT_REFUNDED'        => simpay_format_currency( $charge->amount_refunded ),
		);
		foreach ( $metadata as $metadata_key => $value ) {
			if ( in_array( $metadata_key, $defaults, true ) ) {
				$trigger_token_values[ $metadata_key ] = $value;
			}
		}

		return $trigger_token_values;
	}
}

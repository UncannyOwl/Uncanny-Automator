<?php

namespace Uncanny_Automator;

/**
 * Class EDD_USER_SUBSCRIBES_TO_DOWNLOAD
 *
 * @package Uncanny_Automator
 */
class EDD_USER_SUBSCRIBES_TO_DOWNLOAD extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {

		if ( ! class_exists( 'EDD_Recurring' ) ) {
			return;
		}

		$this->set_integration( 'EDD' );
		$this->set_trigger_code( 'EDDR_SUBSCRIBES' );
		$this->set_trigger_meta( 'EDDR_PRODUCTS' );
		$this->set_sentence( sprintf( esc_attr_x( 'A user subscribes to {{a download:%1$s}}', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A user subscribes to {{a download}}', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ) );
		$this->add_action( 'edd_recurring_post_record_signup', 10, 3 );
	}

	public function options() {
		$options = Automator()->helpers->recipe->options->edd->all_edd_downloads( '', $this->get_trigger_meta(), true, true, true );

		$all_subscription_products = array();
		foreach ( $options['options'] as $key => $option ) {
			$all_subscription_products[] = array(
				'text'  => $option,
				'value' => $key,
			);
		}

		return array(
			array(
				'input_type'      => 'select',
				'option_code'     => $this->get_trigger_meta(),
				'label'           => _x( 'Download', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ),
				'required'        => true,
				'options'         => $all_subscription_products,
				'relevant_tokens' => $options['relevant_tokens'],
			),
		);
	}

	/**
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected_product_id = $trigger['meta'][ $this->get_trigger_meta() ];
		$subscription        = $hook_args[1];
		$download_id         = $subscription['id'];

		if ( intval( '-1' ) !== intval( $selected_product_id ) && absint( $selected_product_id ) !== absint( $download_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * define_tokens
	 *
	 * @param mixed $tokens
	 * @param mixed $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens[] = array(
			'tokenId'   => 'EDDR_PERIOD',
			'tokenName' => _x( 'Recurring period', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => 'EDDR_SIGN_UP_FEE',
			'tokenName' => _x( 'Signup fee', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'EDDR_TIMES',
			'tokenName' => _x( 'Times', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ),
			'tokenType' => 'int',
		);
		$tokens[] = array(
			'tokenId'   => 'EDDR_FREE_TRAIL_PERIOD',
			'tokenName' => _x( 'Free trial period', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ),
			'tokenType' => 'text',
		);

		return $tokens;
	}

	/**
	 * hydrate_tokens
	 *
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $subscription_object, $subscription, $payment_object ) = $hook_args;
		$purchase_data                                               = $payment_object->purchase_data;

		$token_values = array(
			'EDDR_PERIOD'                   => $subscription['period'],
			'EDDR_SIGN_UP_FEE'              => number_format( $subscription['signup_fee'], 2 ),
			'EDDR_TIMES'                    => $subscription['frequency'],
			'EDDR_FREE_TRAIL_PERIOD'        => $subscription_object->trial_period,
			'EDDR_PRODUCTS_DISCOUNT_CODES'  => $purchase_data['user_info']['discount'],
			'EDDR_PRODUCTS'                 => $subscription['name'],
			'EDDR_PRODUCTS_ID'              => $subscription['id'],
			'EDDR_PRODUCTS_URL'             => get_permalink( $subscription['id'] ),
			'EDDR_PRODUCTS_THUMB_ID'        => get_post_thumbnail_id( $subscription['id'] ),
			'EDDR_PRODUCTS_THUMB_URL'       => get_the_post_thumbnail_url( $subscription['id'] ),
			'EDDR_PRODUCTS_ORDER_DISCOUNTS' => number_format( $purchase_data['discount'], 2 ),
			'EDDR_PRODUCTS_ORDER_SUBTOTAL'  => number_format( $purchase_data['subtotal'], 2 ),
			'EDDR_PRODUCTS_ORDER_TAX'       => number_format( $purchase_data['tax'], 2 ),
			'EDDR_PRODUCTS_ORDER_TOTAL'     => number_format( $purchase_data['price'], 2 ),
			'EDDR_PRODUCTS_PAYMENT_METHOD'  => edd_get_payment_gateway( $subscription_object->parent_payment_id ),
		);

		return $token_values;
	}
}

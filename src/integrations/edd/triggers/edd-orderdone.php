<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class EDD_ORDERDONE
 *
 * @package Uncanny_Automator\Integrations\Easy_Digital_Downloads
 * @method \Uncanny_Automator\Integrations\Easy_Digital_Downloads\EDD_Helpers get_item_helpers()
 */
class EDD_ORDERDONE extends Trigger {

	/**
	 * Trigger code
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'EDDORDERDONE';

	/**
	 * Trigger meta
	 *
	 * @var string
	 */
	const TRIGGER_META = 'EDDORDERTOTAL';

	/**
	 * Set up Automator trigger.
	 */
	protected function setup_trigger() {
		$this->set_integration( 'EDD' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->add_action( 'edd_complete_purchase', 10, 3 );
		$this->set_sentence(
			/* translators: %1$s: Order total, %2$s: less or greater than, %3$s: number of times */
			sprintf(
				esc_html_x( 'Order total is {{equals to:%1$s}} ${{0:%2$s}} and placed {{a number of:%3$s}} time(s)', 'Easy Digital Downloads', 'uncanny-automator' ),
				'NUMBERCOND:' . self::TRIGGER_META,
				$this->get_trigger_meta() . ':' . self::TRIGGER_META,
				'NUMTIMES:' . self::TRIGGER_META
			)
		);
		$this->set_readable_sentence( esc_html_x( 'User completes {{an order}}', 'Easy Digital Downloads', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Order total', 'Easy Digital Downloads', 'uncanny-automator' ),
				'input_type'  => 'float',
				'required'    => true,
				'placeholder' => esc_html_x( 'Example: 100', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			array(
				'option_code'   => 'NUMTIMES',
				'label'         => esc_html_x( 'Number of times', 'Easy Digital Downloads', 'uncanny-automator' ),
				'input_type'    => 'int',
				'required'      => true,
				'placeholder'   => esc_html_x( 'Example: 1', 'Easy Digital Downloads', 'uncanny-automator' ),
				'default_value' => 1,
			),
			array(
				'option_code' => 'NUMBERCOND',
				/* translators: Noun */
				'label'       => esc_html_x( 'Condition', 'Easy Digital Downloads', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(
					array(
						'text'  => esc_html_x( 'equal to', 'Easy Digital Downloads', 'uncanny-automator' ),
						'value' => '=',
					),
					array(
						'text'  => esc_html_x( 'not equal to', 'Easy Digital Downloads', 'uncanny-automator' ),
						'value' => '!=',
					),
					array(
						'text'  => esc_html_x( 'less than', 'Easy Digital Downloads', 'uncanny-automator' ),
						'value' => '<',
					),
					array(
						'text'  => esc_html_x( 'greater than', 'Easy Digital Downloads', 'uncanny-automator' ),
						'value' => '>',
					),
					array(
						'text'  => esc_html_x( 'greater or equal to', 'Easy Digital Downloads', 'uncanny-automator' ),
						'value' => '>=',
					),
					array(
						'text'  => esc_html_x( 'less or equal to', 'Easy Digital Downloads', 'uncanny-automator' ),
						'value' => '<=',
					),
				),
			),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( empty( $hook_args ) || ! isset( $hook_args[0] ) ) {
			return false;
		}

		$payment_id = $hook_args[0];
		$user_id    = get_current_user_id();

		// Check if user is logged in
		if ( ! $user_id ) {
			return false;
		}

		// Set user ID for the trigger
		$this->set_user_id( $user_id );

		// Get payment details
		$payment = new \EDD_Payment( $payment_id );
		if ( ! $payment->ID ) {
			return false;
		}

		// Check if payment belongs to current user
		if ( absint( $payment->user_id ) !== absint( $user_id ) ) {
			return false;
		}

		// Get order total from trigger meta
		$selected_total = $trigger['meta'][ self::TRIGGER_META ];
		$actual_total   = $payment->total;

		// Get comparison condition
		$condition = $trigger['meta']['NUMBERCOND'] ?? '=';

		// Convert to float for consistent comparison
		$actual_total_float   = floatval( $actual_total );
		$selected_total_float = floatval( $selected_total );

		// Check if total matches condition
		$total_matches = false;
		switch ( $condition ) {
			case '=':
				// Use small epsilon for floating point comparison
				$total_matches = ( abs( $actual_total_float - $selected_total_float ) < 0.01 );
				break;
			case '!=':
				$total_matches = ( abs( $actual_total_float - $selected_total_float ) >= 0.01 );
				break;
			case '>':
				$total_matches = ( $actual_total_float > $selected_total_float );
				break;
			case '<':
				$total_matches = ( $actual_total_float < $selected_total_float );
				break;
			case '>=':
				$total_matches = ( $actual_total_float >= $selected_total_float );
				break;
			case '<=':
				$total_matches = ( $actual_total_float <= $selected_total_float );
				break;
		}

		return $total_matches;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$tokens['EDD_DOWNLOAD_ORDER_PAYMENT_INFO'] = array(
			'name' => esc_html_x( 'Payment information', 'Easy Digital Downloads', 'uncanny-automator' ),
			'type' => 'text',
		);

		return $tokens;
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		if ( empty( $hook_args ) || ! isset( $hook_args[0] ) ) {
			return array();
		}
		$payment_id = $hook_args[0];
		$payment    = new \EDD_Payment( $payment_id );
		$cart_items = edd_get_payment_meta_cart_details( $payment_id );

		// Prepare payment info
		$payment_info = array(
			'discount_codes'  => $payment->discounts,
			'order_discounts' => 0, // Will be calculated from cart items
			'order_subtotal'  => number_format( (float) $payment->subtotal, 2, '.', '' ),
			'order_total'     => number_format( (float) $payment->total, 2, '.', '' ),
			'order_tax'       => number_format( (float) $payment->tax, 2, '.', '' ),
			'payment_method'  => $payment->gateway,
			'license_key'     => $this->get_item_helpers()->get_licenses( $payment_id ),
		);

		// Calculate total discounts from cart items
		if ( ! empty( $cart_items ) ) {
			$total_discount = 0;
			foreach ( $cart_items as $item ) {
				if ( is_numeric( $item['discount'] ) ) {
					$total_discount += $item['discount'];
				}
			}
			$payment_info['order_discounts'] = number_format( (float) $total_discount, 2, '.', '' );
		}

		return array(
			'EDD_DOWNLOAD_ORDER_PAYMENT_INFO' => wp_json_encode( $payment_info ),
		);
	}
}

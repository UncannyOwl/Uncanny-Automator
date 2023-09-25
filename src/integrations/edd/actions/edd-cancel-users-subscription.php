<?php

namespace Uncanny_Automator;

/**
 * Class EDD_CANCEL_USERS_SUBSCRIPTION
 *
 * @package Uncanny_Automator
 */
class EDD_CANCEL_USERS_SUBSCRIPTION extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return mixed|void
	 */
	protected function setup_action() {

		if ( ! class_exists( 'EDD_Recurring' ) ) {
			return;
		}

		$this->set_integration( 'EDD' );
		$this->set_action_code( 'EDDR_CANCEL_SUBSCRIPTION' );
		$this->set_action_meta( 'EDDR_PRODUCTS' );
		$this->set_sentence( sprintf( esc_attr_x( "Cancel the user's subscription to {{a download:%1\$s}}", 'EDD Recurring', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( "Cancel the user's subscription to {{a download}}", 'EDD Recurring', 'uncanny-automator' ) );
	}

	/**
	 * Define the Action's options
	 *
	 * @return void
	 */
	public function options() {

		$options = Automator()->helpers->recipe->options->edd->all_edd_downloads( '', $this->get_action_meta(), false, false, true );

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
				'option_code'     => $this->get_action_meta(),
				'label'           => _x( 'Download', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ),
				'required'        => true,
				'options'         => $all_subscription_products,
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param       $parsed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		// Get the selected product ID
		$download_id = sanitize_text_field( $parsed[ $this->get_action_meta() ] );

		if ( empty( $download_id ) ) {
			$this->add_log_error( esc_attr_x( 'Invalid download ID', 'EDD Recurring', 'uncanny-automator' ) );

			return false;
		}

		$download_name = sanitize_text_field( $parsed[ $this->get_action_meta() . '_readable' ] );
		$subscriber    = new \EDD_Recurring_Subscriber( $user_id, true );
		$subscriptions = $subscriber->get_subscriptions( $download_id, array( 'active', 'trialling' ) );
		if ( empty( $subscriptions ) ) {
			$this->add_log_error( sprintf( esc_attr_x( 'The user does not have any active subscription for download: %s.', 'EDD Recurring', 'uncanny-automator' ), $download_name ) );

			return false;
		}

		foreach ( $subscriptions as $subscription ) {
			$subs = new \EDD_Subscription( $subscription->id );
			if ( false === $subs->can_cancel() ) {
				$this->add_log_error( sprintf( esc_attr_x( 'Sorry, unable to cancel the subscription ID: %d.', 'EDD Recurring', 'uncanny-automator' ), $subscription->id ) );

				return false;
			}

			$subs->cancel();
			break;
		}

		return true;
	}

}

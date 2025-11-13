<?php

namespace Uncanny_Automator\Integrations\Edd_Recurring_Integration;

/**
 * Class EDD_CANCEL_USERS_SUBSCRIPTION
 *
 * @package Uncanny_Automator\Integrations\Edd_Recurring_Integration
 * @method \Uncanny_Automator\Integrations\Edd_Recurring_Integration\Edd_Recurring_Helpers get_item_helpers()
 */
class EDD_CANCEL_USERS_SUBSCRIPTION extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return mixed|void
	 */
	protected function setup_action() {

		$this->set_integration( 'EDD_RECURRING' );
		$this->set_action_code( 'EDDR_CANCEL_SUBSCRIPTION' );
		$this->set_action_meta( 'EDDR_PRODUCTS' );
		// translators: %1$s: Download
		$this->set_sentence( sprintf( esc_html_x( "Cancel the user's subscription to {{a download:%1\$s}}", 'EDD - Recurring Payments', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( "Cancel the user's subscription to {{a download}}", 'EDD - Recurring Payments', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_item_helpers()->all_recurring_edd_downloads( esc_html_x( 'Download', 'EDD - Recurring Payments', 'uncanny-automator' ), $this->get_action_meta(), false ),
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
			$this->add_log_error( esc_html_x( 'Invalid download ID', 'EDD - Recurring Payments', 'uncanny-automator' ) );

			return false;
		}

		$download_name = sanitize_text_field( $parsed[ $this->get_action_meta() . '_readable' ] );
		$subscriber    = new \EDD_Recurring_Subscriber( $user_id, true );
		$subscriptions = $subscriber->get_subscriptions( $download_id, array( 'active', 'trialling' ) );
		if ( empty( $subscriptions ) ) {
			// translators: 1: Download name
			$this->add_log_error( sprintf( esc_html_x( 'The user does not have any active subscription for download: %s.', 'EDD - Recurring Payments', 'uncanny-automator' ), $download_name ) );

			return false;
		}

		foreach ( $subscriptions as $subscription ) {
			$subs = new \EDD_Subscription( $subscription->id );
			if ( false === $subs->can_cancel() ) {
				// translators: 1: Subscription ID
				$this->add_log_error( sprintf( esc_html_x( 'Sorry, unable to cancel the subscription ID: %d.', 'EDD - Recurring Payments', 'uncanny-automator' ), $subscription->id ) );

				return false;
			}

			$subs->cancel();
			break;
		}

		return true;
	}
}

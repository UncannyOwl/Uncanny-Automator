<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

/**
 * Class EDD_CANCEL_USERS_SUBSCRIPTION
 *
 * @package Uncanny_Automator
 */
class EDD_CANCEL_USERS_SUBSCRIPTION extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Check if the action requirements are met.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		if ( ! class_exists( 'EDD_Recurring' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @return mixed|void
	 */
	protected function setup_action() {

		$this->set_integration( 'EDD' );
		$this->set_action_code( 'EDDR_CANCEL_SUBSCRIPTION' );
		$this->set_action_meta( 'EDDR_PRODUCTS' );
		// translators: %1$s: Download
		$this->set_sentence( sprintf( esc_html_x( "Cancel the user's subscription to {{a download:%1\$s}}", 'Easy Digital Downloads', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( "Cancel the user's subscription to {{a download}}", 'Easy Digital Downloads', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_item_helpers()->all_edd_downloads( esc_html_x( 'Download', 'Easy Digital Downloads', 'uncanny-automator' ), $this->get_action_meta(), false, false, true ),
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
			$this->add_log_error( esc_html_x( 'Invalid download ID', 'Easy Digital Downloads', 'uncanny-automator' ) );

			return false;
		}

		$download_name = sanitize_text_field( $parsed[ $this->get_action_meta() . '_readable' ] );
		$subscriber    = new \EDD_Recurring_Subscriber( $user_id, true );
		$subscriptions = $subscriber->get_subscriptions( $download_id, array( 'active', 'trialling' ) );
		if ( empty( $subscriptions ) ) {
			// translators: 1: Download name
			$this->add_log_error( sprintf( esc_html_x( 'The user does not have any active subscription for download: %s.', 'Easy Digital Downloads', 'uncanny-automator' ), $download_name ) );

			return false;
		}

		foreach ( $subscriptions as $subscription ) {
			$subs = new \EDD_Subscription( $subscription->id );
			if ( false === $subs->can_cancel() ) {
				// translators: 1: Subscription ID
				$this->add_log_error( sprintf( esc_html_x( 'Sorry, unable to cancel the subscription ID: %d.', 'Easy Digital Downloads', 'uncanny-automator' ), $subscription->id ) );

				return false;
			}

			$subs->cancel();
			break;
		}

		return true;
	}
}

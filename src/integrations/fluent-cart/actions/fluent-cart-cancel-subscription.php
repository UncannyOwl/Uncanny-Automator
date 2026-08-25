<?php

namespace Uncanny_Automator\Integrations\Fluent_Cart;

use Uncanny_Automator\Recipe\Action;

/**
 * Class FLUENT_CART_CANCEL_SUBSCRIPTION
 *
 * @package Uncanny_Automator\Integrations\Fluent_Cart
 *
 * @property Fluent_Cart_Helpers $item_helpers
 */
class Fluent_Cart_Cancel_Subscription extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'FLUENT_CART' );
		$this->set_action_code( 'FLUENT_CART_CANCEL_SUBSCRIPTION' );
		$this->set_action_meta( 'FLUENT_CART_SUBSCRIPTION' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Subscription ID */
				esc_html_x( 'Cancel {{a subscription:%1$s}}', 'FluentCart', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Cancel {{a subscription}}', 'FluentCart', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Subscription ID', 'FluentCart', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'supports_tokens' => true,
			),
			array(
				'option_code'     => 'FLUENT_CART_CANCELLATION_REASON',
				'label'           => esc_html_x( 'Cancellation reason', 'FluentCart', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'supports_tokens' => true,
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'FLUENT_CART_SUBSCRIPTION_ID'     => array(
				'name' => esc_html_x( 'Subscription ID', 'FluentCart', 'uncanny-automator' ),
				'type' => 'int',
			),
			'FLUENT_CART_SUBSCRIPTION_STATUS' => array(
				'name' => esc_html_x( 'Subscription status', 'FluentCart', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$subscription_id = absint( isset( $parsed[ $this->get_action_meta() ] ) ? $parsed[ $this->get_action_meta() ] : 0 );
		$reason          = isset( $parsed['FLUENT_CART_CANCELLATION_REASON'] ) ? sanitize_text_field( $parsed['FLUENT_CART_CANCELLATION_REASON'] ) : '';

		if ( 0 === $subscription_id ) {
			$this->add_log_error( esc_html_x( 'Missing subscription ID.', 'FluentCart', 'uncanny-automator' ) );
			return false;
		}

		if ( ! class_exists( '\FluentCart\App\Models\Subscription' ) ) {
			$this->add_log_error( esc_html_x( 'FluentCart is not available.', 'FluentCart', 'uncanny-automator' ) );
			return false;
		}

		$subscription = \FluentCart\App\Models\Subscription::query()->find( $subscription_id );

		if ( empty( $subscription ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %d: Subscription ID */
					esc_html_x( 'Subscription #%d was not found.', 'FluentCart', 'uncanny-automator' ),
					$subscription_id
				)
			);
			return false;
		}

		// cancelRemoteSubscription() cancels the remote/local subscription, persists,
		// and dispatches SubscriptionCanceled itself.
		$result = $subscription->cancelRemoteSubscription( array( 'reason' => $reason ) );

		if ( is_wp_error( $result ) && 'subscription_already_cancelled' !== $result->get_error_code() ) {
			$this->add_log_error( $result->get_error_message() );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'FLUENT_CART_SUBSCRIPTION_ID'     => $subscription_id,
				'FLUENT_CART_SUBSCRIPTION_STATUS' => $subscription->status,
			)
		);

		return true;
	}
}

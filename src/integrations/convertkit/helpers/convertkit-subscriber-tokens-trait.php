<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

/**
 * Trait ConvertKit_Subscriber_Tokens_Trait
 *
 * Shared subscriber token definitions and hydration logic
 * for ConvertKit actions that return subscriber data.
 *
 * @package Uncanny_Automator
 *
 * @property ConvertKit_App_Helpers $helpers
 */
trait ConvertKit_Subscriber_Tokens_Trait {

	/**
	 * Get the common subscriber token definitions.
	 *
	 * Includes v3 legacy subscription tokens conditionally.
	 *
	 * @return array
	 */
	protected function get_subscriber_token_definitions() {

		$tokens = array(
			'SUBSCRIBER_ID'     => array(
				'name' => esc_html_x( 'Subscriber ID', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'int',
			),
			'SUBSCRIBER_STATE'  => array(
				'name' => esc_html_x( 'Subscriber state', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SUBSCRIPTION_DATE' => array(
				'name' => esc_html_x( 'Subscription date', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'date',
			),
		);

		// v3 legacy tokens — subscription concept removed in v4.
		if ( $this->helpers->is_v3() ) {
			$tokens['SUBSCRIPTION_ID']   = array(
				'name' => esc_html_x( 'Subscription ID', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'int',
			);
			$tokens['SUBSCRIBABLE_ID']   = array(
				'name' => esc_html_x( 'Subscribable ID', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'int',
			);
			$tokens['SUBSCRIBABLE_TYPE'] = array(
				'name' => esc_html_x( 'Subscription type', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'text',
			);
		}

		return $tokens;
	}

	/**
	 * Extract the subscriber array from a v3 or v4 API response.
	 *
	 * v3 nests the subscriber inside a subscription wrapper.
	 * v4 returns the subscriber at the top level.
	 *
	 * @param array $response The API response.
	 *
	 * @return array The subscriber data.
	 */
	protected function get_subscriber_from_response( $response ) {

		if ( $this->helpers->is_v3() ) {
			$subscription = $response['data']['subscription'] ?? array();
			return $subscription['subscriber'] ?? array();
		}

		return $response['data']['subscriber'] ?? array();
	}

	/**
	 * Hydrate the common subscriber tokens from an API response.
	 *
	 * Returns a token => value array ready to merge with action-specific tokens.
	 *
	 * @param array  $response      The API response.
	 * @param string $v4_date_field The v4 subscriber field to use for SUBSCRIPTION_DATE.
	 *
	 * @return array Hydrated token values.
	 */
	protected function hydrate_subscriber_tokens( $response, $v4_date_field = 'added_at' ) {

		$subscriber = $this->get_subscriber_from_response( $response );

		if ( $this->helpers->is_v3() ) {
			$subscription = $response['data']['subscription'] ?? array();
			return array(
				'SUBSCRIBER_ID'     => $subscriber['id'] ?? '',
				'SUBSCRIBER_STATE'  => $subscriber['state'] ?? '',
				'SUBSCRIPTION_DATE' => ! empty( $subscription['created_at'] ) ? $this->helpers->get_formatted_time( $subscription['created_at'] ) : '',
				'SUBSCRIPTION_ID'   => $subscription['id'] ?? '',
				'SUBSCRIBABLE_ID'   => $subscription['subscribable_id'] ?? '',
				'SUBSCRIBABLE_TYPE' => $subscription['subscribable_type'] ?? '',
			);
		}

		return array(
			'SUBSCRIBER_ID'     => $subscriber['id'] ?? '',
			'SUBSCRIBER_STATE'  => $subscriber['state'] ?? '',
			'SUBSCRIPTION_DATE' => ! empty( $subscriber[ $v4_date_field ] ) ? $this->helpers->get_formatted_time( $subscriber[ $v4_date_field ] ) : '',
		);
	}
}

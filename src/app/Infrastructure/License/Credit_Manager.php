<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\License;

use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Request;
use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Client_Interface;
use Uncanny_Automator\App\Infrastructure\Exceptions\Api_Exception;
use Uncanny_Automator\App\Plan\Services\Plan_Service;

/**
 * Class Credit_Manager
 *
 * Manages API credit balance checking and charging.
 *
 * Replaces the credit-related methods from Api_Server (has_credits, charge_usage).
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\License
 */
class Credit_Manager {

	/**
	 * License manager for validation and license data.
	 *
	 * @var License_Manager
	 */
	private $license_manager;

	/**
	 * API client for making credit API calls.
	 *
	 * @var Api_Client_Interface
	 */
	private $api_client;

	/**
	 * Plan service — the single authority for credit entitlement.
	 *
	 * All paid tiers include unlimited credits; only the free (lite) tier is
	 * metered. Resolved from the plan tier, never from the license item_name.
	 *
	 * @var Plan_Service
	 */
	private $plan_service;

	/**
	 * Constructor.
	 *
	 * @param License_Manager      $license_manager The license manager instance.
	 * @param Api_Client_Interface $api_client      The API client instance.
	 * @param Plan_Service|null    $plan_service    The plan service instance.
	 */
	public function __construct( License_Manager $license_manager, Api_Client_Interface $api_client, ?Plan_Service $plan_service = null ) {
		$this->license_manager = $license_manager;
		$this->api_client      = $api_client;
		$this->plan_service    = $plan_service ?? new Plan_Service();
	}

	/**
	 * Check whether the current license has available credits.
	 *
	 * Pro licenses have unlimited credits. Free licenses are checked
	 * against their usage limit.
	 *
	 * @return bool True if credits are available.
	 *
	 * @throws \Exception If license is invalid or credits are exhausted.
	 */
	public function has_credits(): bool {
		$license = $this->license_manager->validate();

		if ( $this->plan_service->is_pro() ) {
			return true;
		}

		$paid_usage = isset( $license['paid_usage_count'] ) ? intval( $license['paid_usage_count'] ) : 0;
		$limit      = isset( $license['usage_limit'] ) ? intval( $license['usage_limit'] ) : 0;

		if ( $paid_usage >= $limit ) {
			throw new Api_Exception( esc_html__( 'Not enough credits', 'uncanny-automator' ) );
		}

		return true;
	}

	/**
	 * Get the remaining credit balance.
	 *
	 * @return int The number of remaining credits, or -1 for unlimited (Pro).
	 */
	public function get_balance(): int {
		try {
			$license = $this->license_manager->validate();
		} catch ( \Exception $e ) {
			return 0;
		}

		if ( $this->plan_service->is_pro() ) {
			return -1;
		}

		$paid_usage = isset( $license['paid_usage_count'] ) ? intval( $license['paid_usage_count'] ) : 0;
		$limit      = isset( $license['usage_limit'] ) ? intval( $license['usage_limit'] ) : 0;

		$remaining = $limit - $paid_usage;

		return max( 0, $remaining );
	}

	/**
	 * Charge one credit for an API usage.
	 *
	 * Validates that credits are available, then sends a reduce_credits
	 * request to the API.
	 *
	 * The reduction response is only a command acknowledgement. It is not the
	 * complete license and allocation document returned by get_credits, so it
	 * must never replace the shared license transient.
	 *
	 * @param array|null $trigger_data Optional trigger data for context.
	 *
	 * @return array The legacy-formatted API response array.
	 *
	 * @throws \Exception If no credits available or API call fails.
	 */
	public function charge( ?array $trigger_data = null ): array {
		$this->has_credits();

		$request = new Api_Request(
			'v2/credits',
			array( 'action' => 'reduce_credits' )
		);

		$response = $this->api_client->send( $request );

		/*
		 * DO NOT RE-ENABLE:
		 * set_transient( License_Manager::TRANSIENT_LICENSE, $response->data(), License_Manager::CACHE_DURATION );
		 *
		 * reduce_credits returns only a command acknowledgement. Caching it here
		 * destroys the complete get_credits snapshot, while already-loaded request
		 * state can hide that damage until the next request. It also renews the bad
		 * value for 12 hours. Only the get_credits read path may replace this transient.
		 */

		return $response->to_legacy_array();
	}
}

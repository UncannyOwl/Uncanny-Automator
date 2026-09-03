<?php
/**
 * API Infrastructure - Global Service Functions
 *
 * Lightweight DI container via global functions with static caching.
 * Production code uses these to get service instances.
 * Tests can override via the $override parameter.
 *
 * @package Uncanny_Automator\App\Infrastructure
 */

declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Api_Log_Store;
use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Client;
use Uncanny_Automator\App\Infrastructure\Api_Client\License_Header_Injector;
use Uncanny_Automator\App\Infrastructure\License\Credit_Manager;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Feature_State\Application\Get_Feature_State;
use Uncanny_Automator\App\Feature_State\Infrastructure\Automator_Policy_State_Adapter;
use Uncanny_Automator\App\Feature_State\Infrastructure\Mcp_Allocation_Facts_Reader;
use Uncanny_Automator\App\Feature_State\Infrastructure\WP_Last_Known_Feature_State_Store;

/**
 * Get the License Manager instance.
 *
 * @param License_Manager|null $override Optional override for testing.
 * @return License_Manager
 */
function automator_license_manager( ?License_Manager $override = null, bool $reset = false ): ?License_Manager {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new License_Manager();
	}
	return $instance;
}

/**
 * Get the request-wide Automator feature-state query.
 *
 * Construction is lazy and the result lives only for this PHP request. The
 * query itself memoizes success or failure so every UI consumer sees one
 * coherent Axis snapshot. Production also supplies the license-bound store used
 * to preserve the most recent successful snapshot across requests.
 *
 * @param Get_Feature_State|null $override Optional override for testing.
 * @param bool                   $reset    Whether to reset the cached query.
 *
 * @return Get_Feature_State|null
 */
function automator_feature_state_query( ?Get_Feature_State $override = null, bool $reset = false ): ?Get_Feature_State {
	static $instance = null;

	if ( $reset ) {
		$instance = null;
		return null;
	}

	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}

	if ( null === $instance ) {
		$licenses = automator_license_manager();

		if ( ! $licenses instanceof License_Manager ) {
			throw new \LogicException( 'The Automator license manager is unavailable.' );
		}

		$instance = new Get_Feature_State(
			new Automator_Policy_State_Adapter(
				$licenses,
				new Mcp_Allocation_Facts_Reader( $licenses )
			),
			new WP_Last_Known_Feature_State_Store()
		);
	}

	return $instance;
}

/**
 * Get the Api_Log_Store instance.
 *
 * Defaults to the wpdb-backed implementation registered through
 * {@see Database::get_api_log_store()}. Tests pass an override.
 *
 * @param Api_Log_Store|null $override Optional override for testing.
 * @return Api_Log_Store
 */
function automator_api_log_store( ?Api_Log_Store $override = null, bool $reset = false ): ?Api_Log_Store {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = Database::get_api_log_store();
	}
	return $instance;
}

/**
 * Get the API Client instance.
 *
 * @param Api_Client|null $override Optional override for testing.
 * @return Api_Client
 */
function automator_api_client( ?Api_Client $override = null, bool $reset = false ): ?Api_Client {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$license_manager = automator_license_manager();
		$signer          = new License_Header_Injector( $license_manager );
		$logger          = automator_api_log_store();
		$instance        = new Api_Client( $signer, $logger );

		// Wire the circular dependency: License_Manager needs Api_Client for license data fetches.
		$license_manager->set_api_client( $instance );
	}
	return $instance;
}

/**
 * Get the Credit Manager instance.
 *
 * @param Credit_Manager|null $override Optional override for testing.
 * @return Credit_Manager
 */
function automator_credit_manager( ?Credit_Manager $override = null, bool $reset = false ): ?Credit_Manager {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Credit_Manager(
			automator_license_manager(),
			automator_api_client()
		);
	}
	return $instance;
}

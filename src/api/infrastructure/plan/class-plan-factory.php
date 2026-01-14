<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Infrastructure\Plan;

use Uncanny_Automator\Api\Components\Plan\Domain\Plan;
use Uncanny_Automator\Api_Server;

/**
 * Factory for creating Plan objects from external data sources (e.g., API).
 *
 * This class isolates the dependency on the static Api_Server class.
 *
 * @package Uncanny_Automator\Api\Infrastructure\Plan
 * @since 7.0.0
 */
class Plan_Factory {

	/**
	 * Creates a Plan object from the current user's license data.
	 *
	 * @return Plan
	 */
	public function create_from_api(): Plan {
		$license     = $this->get_license_from_api();
		$api_plan_id = $license['license_plan'] ?? 'lite'; // Default to 'free' from API

		$plan_map = array(
			'free'  => 'lite',
			'basic' => 'pro-basic',
			'plus'  => 'pro-plus',
			'elite' => 'pro-elite',
		);

		// Use the map to get the internal plan ID, defaulting to 'lite' if not found
		$plan_id = $plan_map[ $api_plan_id ] ?? 'lite';

		// Final check to ensure we have a valid plan, defaulting to 'lite'
		if ( ! Plan_Implementation::is_valid( $plan_id ) ) {
			$plan_id = 'lite';
		}

		return new Plan_Implementation( $plan_id );
	}

	/**
	 * Wrapper for the static Api_Server::get_license() call for testability.
	 * In a real scenario, this could be mocked.
	 *
	 * @return array
	 */
	protected function get_license_from_api(): array {
		if ( ! class_exists( '\Uncanny_Automator\Api_Server' ) ) {
			return array();
		}

		$license = Api_Server::get_license();

		// Api_Server::get_license() can return false when not connected.
		if ( false === $license || ! is_array( $license ) ) {
			return array();
		}

		return $license;
	}
}

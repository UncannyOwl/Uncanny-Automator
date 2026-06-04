<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Plan;

use Uncanny_Automator\App\Plan\Domain\Plan;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Factory for creating Plan objects from external data sources (e.g., API).
 *
 * This class isolates the dependency on the static Api_Server class.
 *
 * @package Uncanny_Automator\App\Infrastructure\Plan
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
	 * Test seam for the cached license payload.
	 *
	 * Delegates to License_Manager (the canonical reader in src/app).
	 * Returns an empty array when not connected so create_from_api() can
	 * fall back cleanly via the plan_map default.
	 *
	 * @return array
	 */
	protected function get_license_from_api(): array {
		$license = automator_license_manager()->get_license_data();

		return is_array( $license ) ? $license : array();
	}
}

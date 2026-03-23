<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Integration;

use Uncanny_Automator\Set_Up_Automator;
use Uncanny_Automator\Traits\Singleton;
use Uncanny_Automator\Utilities;

/**
 * Main integration object structure.
 *
 * This acts as a thin wrapper around the Automator()->get_integration() and Automator()->has_integration() methods.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Services
 */
class Integration_Registry_Service {

	use Singleton;

	/**
	 * Get all registered integrations.
	 *
	 * @return array Array of all integrations with their metadata
	 */
	public function get_all_integrations() {
		return Automator()->get_all_integrations();
	}

	/**
	 * Get integration by code.
	 *
	 * @param string $code The integration code
	 * @return mixed Integration data or null
	 */
	public function get_integration( $code ) {
		return Automator()->get_integration( $code );
	}

	/**
	 * Check if integration exists.
	 *
	 * @param string $code The integration code
	 * @return bool True if integration exists
	 */
	public function has_integration( $code ) {
		return Automator()->has_integration( $code );
	}

	/**
	 * Method to get integration data including active connection and settings_url.
	 *
	 * @param string $code The integration code
	 *
	 * @return array|null Integration data or null if integration not found
	 */
	public function get_integration_full( $code ) {
		// Get from registry.
		$all_integrations = $this->get_all_integrations();
		$integration      = $all_integrations[ $code ] ?? null;
		if ( null === $integration ) {
			return null;
		}

		// Check for active integration data ( catches connection and settings_url ).
		$active_integration = $this->get_integration( $code );
		if ( ! empty( $active_integration ) ) {
			$integration = array_merge( $integration, $active_integration );
		}

		return $integration;
	}

	/**
	 * Get only active integrations with codes and names.
	 *
	 * Returns integrations that are truly active/loaded on the site,
	 * not just registered. Uses Set_Up_Automator::$active_integrations_code
	 * as the source of truth.
	 *
	 * @since 7.0.0
	 * @return array Array of objects with 'code' and 'name' for each active integration.
	 *               Example: [{'code': 'LD', 'name': 'LearnDash'}, ...]
	 */
	public function get_active_integrations() {

		// Get only active integration codes (not all registered).
		if ( ! class_exists( Set_Up_Automator::class )
			|| ! isset( Set_Up_Automator::$active_integrations_code )
			|| ! is_array( Set_Up_Automator::$active_integrations_code ) ) {
			return array();
		}

		$active_codes = Set_Up_Automator::$active_integrations_code;

		// Get integration names from registry.
		$all_integrations = $this->get_all_integrations();

		$active = array();

		foreach ( $active_codes as $code ) {
			$integration = $all_integrations[ $code ] ?? null;

			if ( null !== $integration ) {
				$active[] = array(
					'code' => $code,                         // e.g., "LD"
					'name' => $integration['name'] ?? $code, // e.g., "LearnDash"
				);
			}
		}

		return $active;
	}
}

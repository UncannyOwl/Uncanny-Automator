<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Services;

use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\Set_Up_Automator;
use Uncanny_Automator\Traits\Singleton;
use Uncanny_Automator\Utilities;

/**
 * Main integration object structure.
 *
 * Thin wrapper around {@see Integration_Registry_Bridge}, the anti-corruption
 * boundary that talks to the legacy `Automator()->get_integration*()` family.
 * Service consumers depend on this class; this class depends on the bridge
 * interface so it remains testable in isolation.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Application
 */
class Integration_Registry_Service {

	use Singleton;

	/**
	 * Anti-corruption boundary to the legacy integration registry.
	 *
	 * Lazy-resolved via {@see self::integrations()} so the Singleton trait's
	 * parameterless `get_instance()` factory keeps working.
	 *
	 * @var Integration_Registry_Bridge|null
	 */
	private ?Integration_Registry_Bridge $integrations = null;

	/**
	 * Inject a bridge override (test seam).
	 *
	 * @param Integration_Registry_Bridge $integrations Bridge implementation.
	 * @return void
	 */
	public function set_integrations_bridge( Integration_Registry_Bridge $integrations ): void {
		$this->integrations = $integrations;
	}

	/**
	 * Resolve the integration registry bridge, defaulting to the legacy adapter.
	 *
	 * @return Integration_Registry_Bridge
	 */
	private function integrations(): Integration_Registry_Bridge {
		if ( null === $this->integrations ) {
			$this->integrations = new Automator_Integration_Registry_Bridge();
		}

		return $this->integrations;
	}

	/**
	 * Get all registered integrations.
	 *
	 * @return array Array of all integrations with their metadata
	 */
	public function get_all_integrations() {
		return $this->integrations()->get_all_integrations();
	}

	/**
	 * Get integration by code.
	 *
	 * @param string $code The integration code
	 * @return array|null Integration data or null
	 */
	public function get_integration( $code ) {
		return $this->integrations()->get_integration( (string) $code );
	}

	/**
	 * Check if integration exists.
	 *
	 * @param string $code The integration code
	 * @return bool True if integration exists
	 */
	public function has_integration( $code ) {
		return $this->integrations()->has_integration( (string) $code );
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

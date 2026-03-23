<?php

namespace Uncanny_Automator\Api\Services\Dependency;

use Uncanny_Automator\Api\Services\Plan\Plan_Service;
use Uncanny_Automator\Api\Services\License\License_Service;
use Uncanny_Automator\Api\Services\Plugin\Plugin_Service;

/**
 * Dependency Context.
 *
 * A thin coordinator providing unified access to plan, license, and plugin services.
 * Serves as the single point of access for dependency resolution.
 *
 * @since 7.0.0
 */
class Dependency_Context {

	/**
	 * Plan service.
	 *
	 * @var Plan_Service
	 */
	private Plan_Service $plan_service;

	/**
	 * License service.
	 *
	 * @var License_Service
	 */
	private License_Service $license_service;

	/**
	 * Plugin service.
	 *
	 * @var Plugin_Service
	 */
	private Plugin_Service $plugin_service;

	/**
	 * Constructor.
	 *
	 * Initializes all service dependencies. Accepts optional injected services
	 * for testing purposes - if null, creates real service instances.
	 *
	 * @param Plan_Service|null    $plan_service    Optional injected Plan_Service.
	 * @param License_Service|null $license_service Optional injected License_Service.
	 * @param Plugin_Service|null  $plugin_service  Optional injected Plugin_Service.
	 *
	 * @return void
	 */
	public function __construct(
		?Plan_Service $plan_service = null,
		?License_Service $license_service = null,
		?Plugin_Service $plugin_service = null
	) {
		$this->plan_service    = $plan_service ?? new Plan_Service();
		$this->license_service = $license_service ?? new License_Service();
		$this->plugin_service  = $plugin_service ?? new Plugin_Service();
	}

	/**
	 * Get plan service.
	 *
	 * @return Plan_Service
	 */
	public function get_plan_service(): Plan_Service {
		return $this->plan_service;
	}

	/**
	 * Get license service.
	 *
	 * @return License_Service
	 */
	public function get_license_service(): License_Service {
		return $this->license_service;
	}

	/**
	 * Get plugin service.
	 *
	 * @return Plugin_Service
	 */
	public function get_plugin_service(): Plugin_Service {
		return $this->plugin_service;
	}

	// ==========================================
	// Convenience methods (delegate to services)
	// ==========================================

	/**
	 * Check if user has credits.
	 *
	 * @return bool
	 */
	public function has_credits(): bool {
		return $this->license_service->has_credits();
	}

	/**
	 * Check if Automator is connected.
	 *
	 * @return bool
	 */
	public function is_automator_connected(): bool {
		return $this->license_service->is_connected();
	}

	/**
	 * Check if Pro plugin is installed.
	 *
	 * @return bool
	 */
	public function is_pro_installed(): bool {
		return $this->plugin_service->is_pro_installed();
	}

	/**
	 * Check if Pro plugin is active.
	 *
	 * @return bool
	 */
	public function is_pro_active(): bool {
		return $this->plugin_service->is_pro_active();
	}

	/**
	 * Get license data.
	 *
	 * @return array|null License data or null if no license.
	 */
	public function get_license(): ?array {
		return $this->license_service->get_license();
	}

	/**
	 * Get license status.
	 *
	 * @return string License status or empty string if no license.
	 */
	public function get_license_status(): string {
		return $this->license_service->get_license_status();
	}

	/**
	 * Get license type.
	 *
	 * Returns 'pro' if Pro license is valid, 'free' if Free license is valid, or false.
	 *
	 * @return string|false License type ('pro', 'free') or false if no valid license.
	 */
	public function get_license_type() {
		return $this->license_service->get_license_type();
	}

	/**
	 * Check if Pro license is valid.
	 *
	 * Returns true if Pro is installed, active, and has a valid Pro license.
	 *
	 * @return bool True if Pro license is valid.
	 */
	public function is_pro_license_valid(): bool {
		return $this->is_pro_installed()
			&& $this->is_pro_active()
			&& 'pro' === $this->get_license_type();
	}
}

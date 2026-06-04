<?php
/**
 * License Service
 *
 * A thin facade providing a simple API for license-related operations.
 *
 * @package Uncanny_Automator\App\Plan\Services\License
 * @since 7.0.0
 */

namespace Uncanny_Automator\App\Plan\Services\License;

use Uncanny_Automator\Api_Server;
use Exception;

/**
 * License Service.
 *
 * Provides a clean interface to license operations with error handling.
 *
 * @since 7.0.0
 */
class License_Service {

	/**
	 * License data cache.
	 *
	 * @var array|null|false False if not yet fetched, array or null after fetch.
	 */
	private $license = false;

	/**
	 * License type cache.
	 *
	 * @var string|false|null Null if not yet fetched, 'pro'/'free'/false after fetch.
	 */
	private $license_type = null;

	/**
	 * Check if user has credits.
	 *
	 * @return bool
	 */
	public function has_credits(): bool {
		$credit_manager = $this->get_credit_manager_service();
		if ( null !== $credit_manager ) {
			try {
				return $credit_manager->has_credits();
			} catch ( Exception $e ) {
				return false;
			}
		}
		try {
			return Api_Server::has_credits();
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Check if Automator is connected.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		$manager = $this->get_license_manager();
		if ( null !== $manager ) {
			return $manager->is_connected();
		}
		return (bool) Api_Server::is_automator_connected();
	}

	/**
	 * Get license data.
	 *
	 * @return array|null License data or null if no license.
	 */
	public function get_license(): ?array {
		if ( false === $this->license ) {
			$manager = $this->get_license_manager();
			if ( null !== $manager ) {
				$this->license = $manager->get_license_data();
			} else {
				$this->license = $this->fetch_license();
			}
		}
		return $this->license;
	}

	/**
	 * Get license status.
	 *
	 * @return string License status or empty string if no license.
	 */
	public function get_license_status(): string {
		$manager = $this->get_license_manager();
		if ( null !== $manager ) {
			$license = $manager->get_license_data();
			return $license['license'] ?? '';
		}
		$license = $this->get_license();
		return $license['license'] ?? '';
	}

	/**
	 * Get license ID.
	 *
	 * @return int License ID or 0 when unavailable.
	 */
	public function get_license_id(): int {
		$license = $this->get_license();

		return is_array( $license ) && isset( $license['license_id'] ) ? absint( $license['license_id'] ) : 0;
	}

	/**
	 * Get license type.
	 *
	 * Returns 'pro' if Pro license is valid, 'free' if Free license is valid, or false.
	 *
	 * @return string|false License type ('pro', 'free') or false if no valid license.
	 */
	public function get_license_type() {
		if ( null === $this->license_type ) {
			$manager = $this->get_license_manager();
			if ( null !== $manager ) {
				// License_Manager::get_type() returns string-only ('' for no license).
				// Normalize to the documented string|false contract.
				$type               = $manager->get_type();
				$this->license_type = '' === $type ? false : $type;
			} else {
				$this->license_type = $this->fetch_license_type();
			}
		}
		return $this->license_type;
	}

	/**
	 * Fetch license data with error handling.
	 *
	 * @return array|null License data or null.
	 */
	private function fetch_license(): ?array {
		try {
			$license = Api_Server::get_license();
			// Convert false to null to match return type.
			return false === $license ? null : $license;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Fetch license type.
	 *
	 * @return string|false License type ('pro', 'free') or false.
	 */
	private function fetch_license_type() {
		return Api_Server::get_license_type();
	}

	/**
	 * Get license key.
	 *
	 * @return string License key or empty string.
	 */
	public function get_license_key(): string {
		$manager = $this->get_license_manager();
		if ( null !== $manager ) {
			return $manager->get_key();
		}
		return (string) Api_Server::get_license_key();
	}

	/**
	 * Get site name.
	 *
	 * @return string Site name or empty string.
	 */
	public function get_site_name(): string {
		$manager = $this->get_license_manager();
		if ( null !== $manager ) {
			return $manager->get_site_name();
		}
		return (string) Api_Server::get_site_name();
	}

	/**
	 * Get item name.
	 *
	 * @return string Item name or empty string.
	 */
	public function get_item_name(): string {
		$manager = $this->get_license_manager();
		if ( null !== $manager ) {
			return $manager->get_item_name();
		}
		return (string) Api_Server::get_item_name();
	}

	/**
	 * Get formatted license renewal/expiry date.
	 *
	 * @return string Formatted date like "January 1, 2026" or empty string if lifetime/unavailable.
	 */
	public function get_renewal_date_formatted(): string {
		$manager = $this->get_license_manager();
		if ( null !== $manager ) {
			return $manager->get_renewal_date();
		}
		return Api_Server::get_renewal_date_formatted();
	}

	/**
	 * Get URL for purchasing additional credits.
	 *
	 * @return string URL to credits/pricing page.
	 */
	public function get_url_get_credits(): string {
		return AUTOMATOR_LLM_CREDITS_URL;
	}

	/**
	 * Get the infrastructure License_Manager if available.
	 *
	 * @return \Uncanny_Automator\App\Infrastructure\License\License_Manager|null
	 */
	protected function get_license_manager() {
		if ( function_exists( '\Uncanny_Automator\App\Infrastructure\automator_license_manager' ) ) {
			return \Uncanny_Automator\App\Infrastructure\automator_license_manager();
		}
		return null;
	}

	/**
	 * Get the infrastructure Credit_Manager if available.
	 *
	 * @return \Uncanny_Automator\App\Infrastructure\License\Credit_Manager|null
	 */
	protected function get_credit_manager_service() {
		if ( function_exists( '\Uncanny_Automator\App\Infrastructure\automator_credit_manager' ) ) {
			return \Uncanny_Automator\App\Infrastructure\automator_credit_manager();
		}
		return null;
	}
}

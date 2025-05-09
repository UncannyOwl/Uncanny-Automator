<?php

namespace Uncanny_Automator\Services\Addons\Data;

use Uncanny_Automator\Pricing_Plan_Resolver;
use Uncanny_Automator\Api_Server as Automator_Api;

/**
 * Plan Resolver
 *
 * @package Uncanny_Automator\Services\Addons\Data
 */
class Plan_Resolver {

	/**
	 * The total number of available addons.
	 *
	 * @var int
	 */
	private $total_available_addons = 0;

	/**
	 * The total number of non-elite addons.
	 *
	 * @var int
	 */
	private $total_plus_addons = 0;

	/**
	 * The total number of basic addons.
	 *
	 * @var int
	 */
	private $total_basic_addons = 0;

	/**
	 * Addons data.
	 *
	 * @var array
	 */
	private $addons_data;

	/**
	 * Elite only addons.
	 *
	 * @var array
	 */
	private $elite_only_addons;

	/**
	 * Plus only addons.
	 *
	 * @var array
	 */
	private $plus_only_addons;

	/**
	 * Basic only addons.
	 *
	 * @var array
	 */
	private $basic_only_addons;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {

		// Get addons data.
		$this->addons_data = ( new External_Feed() )->get_feed();

		// Filter elite only addons.
		$this->elite_only_addons = $this->filter_by_plan( 'elite', true );

		// Filter plus only addons.
		$this->plus_only_addons = $this->filter_by_plan( 'plus' );

		// Filter basic only addons.
		$this->basic_only_addons = $this->filter_by_plan( 'basic' );

		// Set total addons and non-elite addons.
		$this->total_available_addons = count( $this->addons_data );
		$this->total_plus_addons      = count( $this->plus_only_addons );
		$this->total_basic_addons     = count( $this->basic_only_addons );
	}

	/**
	 * Get the elite addons.
	 *
	 * @return array
	 */
	public function get_elite_addons() {
		return $this->elite_only_addons;
	}

	/**
	 * Get the plus addons.
	 *
	 * @return array
	 */
	public function get_plus_addons() {
		return $this->plus_only_addons;
	}

	/**
	 * Get the basic addons.
	 *
	 * @return array
	 */
	public function get_basic_addons() {
		return $this->basic_only_addons;
	}

	/**
	 * Get the number of addons for the license.
	 *
	 * @return int
	 */
	public function get_number_of_addons_for_license() {
		return $this->addons_available_for_license();
	}

	/**
	 * Get the number of available addons.
	 *
	 * @return int
	 */
	public function get_total_number_of_available_addons() {
		return $this->total_available_addons;
	}

	/**
	 * Check if addons are available for customer.
	 *
	 * @return int
	 */
	public function addons_available_for_license() {

		// Check if user has a valid pro license key.
		if ( ! $this->has_pro_license_key() ) {
			return 0;
		}

		// Check the license_plan property of the connected user.
		$plan = Automator_Api::get_license_plan();
		switch ( $plan ) {
			case 'basic':
				return $this->total_basic_addons;
			case 'plus':
				return $this->total_plus_addons;
			case 'elite':
				return $this->total_available_addons;
			default:
				return 0;
		}
	}

	/**
	 * Filter addons based on plan type
	 *
	 * @param string $plan - The plan to filter by.
	 * @param bool $strict - If true only return addons that have a single plan in the plans array.
	 *
	 * @return array Filtered addons list
	 */
	public function filter_by_plan( $plan, $strict = false ) {
		return array_filter(
			$this->addons_data,
			function ( $addon ) use ( $plan, $strict ) {
				// Plan is found in the plans array.
				$found = in_array( $plan, $addon['plans'], true );
				return $strict
					// If strict, filter by single plan ( elite ).
					? 1 === count( $addon['plans'] ) && $found
					: $found;
			}
		);
	}

	/**
	 * Check if user has access to an addon plan.
	 *
	 * @param string $plan_to_check The addon plan to check.
	 *
	 * @return bool
	 */
	public function has_access_to_plan( $plan_to_check ) {

		// Check the license_plan property of the connected user.
		$user_plan = Automator_Api::get_license_plan();

		switch ( $user_plan ) {
			case 'elite':
				// Elite users have access to all plans.
				return true;
			case 'plus':
				// Plus users have access to basic and plus plans.
				return in_array( $plan_to_check, array( 'basic', 'plus' ), true );
			case 'basic':
				// Basic users only have access to basic plan.
				return 'basic' === $plan_to_check;
		}

		return false;
	}

	/**
	 * Check if user has a valid pro license key.
	 *
	 * @return bool
	 */
	private function has_pro_license_key() {
		$pro_license_key = trim( automator_get_option( 'uap_automator_pro_license_key' ) );
		return ! empty( $pro_license_key );
	}
}

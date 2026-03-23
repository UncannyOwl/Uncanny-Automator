<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Availability;

/**
 * Check feature availability based on tier, integration, and connection status.
 *
 * This is a pure function class that evaluates only the data passed in.
 * It has zero dependencies and doesn't call any services or globals.
 * The caller is responsible for assembling all required data.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Services\Availability
 */
class Availability_Checker implements Availability_Checker_Interface {

	/**
	 * Check if a feature is available and return a human-readable message.
	 *
	 * @param Availability_Data $data The feature availability data.
	 *
	 * @return string Human-readable availability message.
	 */
	public function check( Availability_Data $data ) {

		$blockers = $this->get_blockers( $data );

		if ( empty( $blockers ) ) {
			return 'Ready to use.';
		}

		return implode( ' AND ', $blockers ) . '.';
	}

	/**
	 * Get an array of blocking issues preventing feature availability.
	 *
	 * @param Availability_Data $data The feature availability data.
	 *
	 * @return array Array of blocking issues (empty if available).
	 */
	public function get_blockers( Availability_Data $data ) {

		$blockers = array();

		// Check 1: Tier requirement.
		if ( ! empty( $data->user_tier_id ) && ! empty( $data->requires_tier ) && ! $this->tier_meets_requirement( $data->user_tier_id, $data->requires_tier ) ) {
			$blockers[] = sprintf( 'Upgrade to %s plan at https://automatorplugin.com/pricing/', $this->format_tier_name( $data->requires_tier ) );
		}

		// Check 2: Integration installed.
		if ( ! $data->is_integration_registered ) {
			$blockers[] = sprintf( 'Install %s plugin', $this->format_integration_name( $data->integration ) );
		}

		// Check 3: App connection (only if integration exists).
		if ( $data->is_integration_registered && $data->is_app && ! $data->is_connected ) {
			$message = sprintf( 'Connect your %s account', $this->format_integration_name( $data->integration ) );

			// Append settings URL if available.
			if ( ! empty( $data->settings_url ) ) {
				$message .= sprintf( ' at %s', $data->settings_url );
			}

			$blockers[] = $message;
		}

		return $blockers;
	}

	/**
	 * Check if a feature is available (simple boolean check).
	 *
	 * @param Availability_Data $data The feature availability data.
	 *
	 * @return bool True if available, false otherwise.
	 */
	public function is_available( Availability_Data $data ) {

		return empty( $this->get_blockers( $data ) );
	}

	/**
	 * Check if user tier meets the required tier.
	 *
	 * Tier hierarchy: lite < pro-basic < pro-plus < pro-elite.
	 *
	 * @param string $user_tier     User's current tier.
	 * @param string $required_tier Required tier.
	 *
	 * @return bool True if user tier meets requirement, false otherwise.
	 */
	private function tier_meets_requirement( $user_tier, $required_tier ) {

		$tier_hierarchy = array(
			'lite'      => 1,
			'pro-basic' => 2,
			'pro-plus'  => 3,
			'pro-elite' => 4,
		);

		$user_level     = isset( $tier_hierarchy[ $user_tier ] ) ? $tier_hierarchy[ $user_tier ] : 0;
		$required_level = isset( $tier_hierarchy[ $required_tier ] ) ? $tier_hierarchy[ $required_tier ] : 0;

		return $user_level >= $required_level;
	}

	/**
	 * Format tier name for display.
	 *
	 * @param string $tier_id Tier ID (e.g., 'pro-basic').
	 *
	 * @return string Formatted tier name (e.g., 'Automator Pro Basic').
	 */
	private function format_tier_name( $tier_id ) {

		$tier_names = array(
			'lite'      => 'Automator Lite',
			'pro-basic' => 'Automator Pro Basic',
			'pro-plus'  => 'Automator Pro Plus',
			'pro-elite' => 'Automator Pro Elite',
		);

		return isset( $tier_names[ $tier_id ] ) ? $tier_names[ $tier_id ] : $tier_id;
	}

	/**
	 * Format integration name for display.
	 *
	 * @param string $integration_code Integration code (e.g., 'WC', 'WHATSAPP').
	 *
	 * @return string Formatted integration name.
	 */
	private function format_integration_name( $integration_code ) {

		// Basic formatting: replace underscores with spaces and title case.
		$name = str_replace( '_', ' ', $integration_code );
		$name = ucwords( strtolower( $name ) );

		return $name;
	}
}

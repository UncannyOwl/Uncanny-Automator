<?php

namespace Uncanny_Automator;

/**
 * Class PricingPlanResolver
 *
 * Resolves the pricing plan tier based on the Pro plugin installation status and license details.
 *
 * The resolution logic is as follows:
 * - If the Pro plugin is not installed, the plan is "lite".
 * - If the Pro plugin is installed but there is no valid license connection, the plan is "basic".
 * - If the Pro plugin is installed and a valid license is connected, the plan tier is determined from the license's price ID.
 */
class PricingPlanResolver {
	/**
	 * Mapping of price IDs to plan tiers.
	 *
	 * @var array<int, string>
	 */
	private const PRICE_TIER_MAP = array(
		1 => 'basic',
		2 => 'plus',
		3 => 'plus', // unlimited
		4 => 'elite',
	);

	/**
	 * Retrieve detailed plan information.
	 *
	 * @return array{plan: string, is_pro_installed: bool}
	 */
	public static function getPlanDetails(): array {
		return array(
			'plan'             => self::resolvePlanTier(),
			'is_pro_installed' => self::isProInstalled(),
		);
	}

	/**
	 * Determine the pricing tier for the current installation.
	 *
	 * The logic is:
	 * - If the Pro plugin is not installed, return "lite".
	 * - If Pro is installed but the API server does not report a valid license connection, return "basic".
	 * - Otherwise, if Pro is installed with a valid license, return the tier mapped from the license's price ID. If the price ID is not recognized, default to "basic".
	 *
	 * @return string
	 */
	public static function resolvePlanTier(): string {
		if ( ! self::isProInstalled() ) {
			return 'lite';
		}

		// Get the license information
		$licenseInfo = Api_Server::is_automator_connected();

		if (
			// If the license info is not an array
			! is_array( $licenseInfo ) ||
			// If the license key is empty
			empty( $licenseInfo['license_key'] ) ||
			// If the license is not valid
			( ( $licenseInfo['license'] ?? '' ) !== 'valid' )
		) {
			return 'basic';
		}

		$priceId = (int) ( $licenseInfo['price_id'] ?? 0 );

		// Return the tier mapped from the price ID
		return self::PRICE_TIER_MAP[ $priceId ] ?? 'basic';
	}

	/**
	 * Check whether the Pro plugin is installed.
	 *
	 * This method assumes that the Pro plugin defines the constant
	 * AUTOMATOR_PRO_PLUGIN_VERSION when active.
	 *
	 * @return bool
	 */
	public static function isProInstalled(): bool {
		return defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' );
	}

	/**
	 * Retrieve the mapping of price IDs to plan tiers.
	 *
	 * @return array<int, string>
	 */
	public static function getPriceTierMap(): array {
		return self::PRICE_TIER_MAP;
	}
}

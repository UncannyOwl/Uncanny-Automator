<?php
/**
 * License Scenario Service
 *
 * Service for generating license scenario IDs and descriptions.
 * Used by dependency resolvers to determine license requirements and messaging.
 *
 * @package Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\License
 * @since 7.0.0
 */
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\License;

use Uncanny_Automator\Api\Services\Dependency\Resolvers\Abstract_Scenario;
use Uncanny_Automator\Services\Plugin\Info as Plugin_Info;

/**
 * License Scenario.
 *
 * @since 7.0.0
 */
class License_Scenario extends Abstract_Scenario {

	/**
	 * Get all scenario configurations.
	 *
	 * @return array Keyed array of scenario configurations
	 */
	protected function get_scenarios() {

		if ( null !== $this->scenarios ) {
			return $this->scenarios;
		}

		$this->scenarios = array(
			// Lite active. Entity uses App Credits. No Uncanny Automator account connected, so App Credits cannot be assigned/used.
			'app-lite-no-api'              => array(
				'scenario_id' => 'license-app-lite-no-api-account',
				'name'        => esc_html_x( 'Uncanny Automator account', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( 'App integrations require App Credits. Set up and connect your Uncanny Automator account to receive 250 free App Credits!', 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_setup_account_cta(),
			),
			// Lite active. Uncanny Automator account connected. Entity uses App Credits but balance is 0; must upgrade to Pro (unlimited App Credits).
			'app-lite-no-credits'          => array(
				'scenario_id' => 'license-app-lite-no-credits',
				'name'        => esc_html_x( 'App Credits', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( "Looks like you're out of App Credits. To use this integration, Upgrade to Pro and get unlimited App Credits.", 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_upgrade_cta( esc_html_x( 'Upgrade to Pro', 'Dependency CTA', 'uncanny-automator' ) ),
			),
			// Only Lite installed. Entity requires Uncanny Automator Pro with Pro Basic or higher. Pro plugin missing.
			'pro-basic-not-installed'      => array(
				'scenario_id' => 'license-requires-pro-basic-not-installed',
				'name'        => esc_html_x( 'Uncanny Automator Pro', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( 'To use this feature, you need Uncanny Automator Pro.', 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_upgrade_cta( esc_html_x( 'Upgrade to Pro', 'Dependency CTA', 'uncanny-automator' ) ),
			),
			// Only Lite installed. Entity requires Pro Plus or higher. Pro plugin missing.
			'pro-plus-not-installed'       => array(
				'scenario_id' => 'license-requires-pro-plus-not-installed',
				'name'        => esc_html_x( 'Uncanny Automator Pro - Plus license', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( 'To use this feature, you need Uncanny Automator Pro with a Plus license or higher.', 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_upgrade_cta( esc_html_x( 'Upgrade to Pro Plus or higher', 'Dependency CTA', 'uncanny-automator' ) ),
			),
			// Only Lite installed. Entity requires Pro Elite. Pro plugin missing.
			'pro-elite-not-installed'      => array(
				'scenario_id' => 'license-requires-pro-elite-not-installed',
				'name'        => esc_html_x( 'Uncanny Automator Pro - Elite license', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( 'To use this feature, you need Uncanny Automator Pro with an Elite license.', 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_upgrade_cta( esc_html_x( 'Upgrade to Pro Elite', 'Dependency CTA', 'uncanny-automator' ) ),
			),
			// Pro plugin present but inactive. Entity needs Pro (any tier). Cannot check tier until plugin is active.
			'pro-deactivated'              => array(
				'scenario_id' => 'license-requires-pro-installed-but-deactivated',
				'name'        => esc_html_x( 'Uncanny Automator Pro', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( 'This feature needs the Pro plugin to be active on your site. Activate it to continue.', 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_activate_pro_cta(),
			),
			// Pro plugin active. License status = inactive (not activated/connected). Entity needs Pro with an active license.
			'license-inactive'             => array(
				'scenario_id' => 'license-requires-pro-installed-license-inactive',
				'name'        => esc_html_x( 'Uncanny Automator Pro', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( "This feature requires an active Uncanny Automator Pro license. Activate your license to continue.", 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_license_settings_cta( esc_html_x( 'Activate license', 'Dependency CTA', 'uncanny-automator' ) ),
			),
			// Pro plugin active. License status = expired. Entity needs Pro with a current license.
			'license-expired'              => array(
				'scenario_id' => 'license-requires-pro-installed-license-expired',
				'name'        => esc_html_x( 'Uncanny Automator Pro', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( 'This feature is unavailable because your Pro license has expired. Renew to restore access.', 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_renew_now_cta(),
			),
			// Pro plugin active. License status = disabled (e.g., in account backend). Entity needs Pro but disabled licenses are treated as invalid.
			'license-disabled'             => array(
				'scenario_id' => 'license-requires-pro-installed-license-disabled',
				'name'        => esc_html_x( 'Uncanny Automator Pro', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( 'This feature requires a valid Uncanny Automator Pro license. Please contact support.', 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_license_settings_cta( esc_html_x( 'Fix your license', 'Dependency CTA', 'uncanny-automator' ) ),
			),
			// Pro plugin active. License exists but not assigned/active for this specific site. Entity needs Pro license activated on this domain.
			'license-site-inactive'        => array(
				'scenario_id' => 'license-requires-pro-installed-license-site-inactive',
				'name'        => esc_html_x( 'Uncanny Automator Pro', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( "This feature requires an active Uncanny Automator Pro license. Activate your license to continue.", 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_license_settings_cta( esc_html_x( 'Activate your license', 'Dependency CTA', 'uncanny-automator' ) ),
			),
			// Pro plugin active. License status = invalid (bad key or not recognized). Entity needs Pro with a valid license key.
			'license-invalid'              => array(
				'scenario_id' => 'license-requires-pro-installed-license-invalid',
				'name'        => esc_html_x( 'Uncanny Automator Pro', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( "There's a problem with your Uncanny Automator Pro license key. Please activate a valid license or contact support.", 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_license_settings_cta( esc_html_x( 'Activate your license', 'Dependency CTA', 'uncanny-automator' ) ),
			),
			// Pro plugin active. License = Pro Basic. Entity requires Pro Plus or higher. Upgrade path: Basic -> Plus (or Elite).
			'upgrade-basic-to-plus'        => array(
				'scenario_id' => 'license-requires-pro-installed-license-active-pro-basic-requires-plus',
				'name'        => esc_html_x( 'Uncanny Automator Pro - Plus license', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( "You're on Pro Basic, but this feature requires a Pro Plus license.", 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_upgrade_cta( esc_html_x( 'Upgrade to Pro Plus or higher', 'Dependency CTA', 'uncanny-automator' ) ),
			),
			// Pro plugin active. License = Pro Basic. Entity requires Pro Elite. Upgrade path: Basic -> Elite.
			'upgrade-basic-to-elite'       => array(
				'scenario_id' => 'license-requires-pro-installed-license-active-pro-basic-requires-elite',
				'name'        => esc_html_x( 'Uncanny Automator Pro - Elite license', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( "You're on Pro Basic, but this feature requires a Pro Elite license.", 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_upgrade_cta( esc_html_x( 'Upgrade to Pro Elite', 'Dependency CTA', 'uncanny-automator' ) ),
			),
			// Pro plugin active. License = Pro Plus. Entity requires Pro Elite. Upgrade path: Plus -> Elite.
			'upgrade-plus-to-elite'        => array(
				'scenario_id' => 'license-requires-pro-installed-license-active-pro-plus-requires-elite',
				'name'        => esc_html_x( 'Uncanny Automator Pro - Elite license', 'Dependency', 'uncanny-automator' ),
				'description' => esc_html_x( "You're on Pro Plus, but this feature requires a Pro Elite license.", 'Dependency', 'uncanny-automator' ),
				'cta'         => $this->get_upgrade_cta( esc_html_x( 'Upgrade to Pro Elite', 'Dependency CTA', 'uncanny-automator' ) ),
			),
		);

		return $this->scenarios;
	}

	/**
	 * Get scenario ID for a tier requirement.
	 *
	 * @param string $required_tier Required tier (first argument)
	 * @param string $type Type of dependency ('tier' or 'credits') (second argument, defaults to 'tier')
	 *
	 * @return string Scenario ID
	 */
	public function get_scenario_id( ...$args ) {
		$required_tier = $args[0] ?? '';
		$type          = $args[1] ?? 'tier';

		$key       = $this->determine_scenario_key( $required_tier, $type );
		$scenarios = $this->get_scenarios();
		$config    = $scenarios[ $key ] ?? null;

		return $config ? $config['scenario_id'] : 'license-requires-pro-not-installed';
	}

	/**
	 * Get scenario name.
	 *
	 * @param string $scenario_id Scenario ID
	 *
	 * @return string Scenario name
	 */
	public function get_name( string $scenario_id ) {
		$scenarios = $this->get_scenarios();

		// Find config by scenario_id.
		foreach ( $scenarios as $config ) {
			if ( $config['scenario_id'] === $scenario_id ) {
				return $config['name'];
			}
		}

		// Fallback name.
		return esc_html_x( 'License', 'Dependency', 'uncanny-automator' );
	}

	/**
	 * Get description for a scenario.
	 *
	 * @param string $scenario_id Scenario ID
	 * @param string $name Integration or item name to insert in description
	 *
	 * @return string Translated description
	 */
	public function get_description( string $scenario_id, string $name ) {
		$scenarios = $this->get_scenarios();

		// Find config by scenario_id.
		foreach ( $scenarios as $config ) {
			if ( $config['scenario_id'] === $scenario_id ) {
				return sprintf(
					// translators: %s: Integration or item name
					esc_html( $config['description'] ),
					$name
				);
			}
		}

		// Fallback description.
		return sprintf(
			// translators: %s: Integration or item name
			esc_html_x( 'Upgrade your license to use %s', 'Dependency Description', 'uncanny-automator' ),
			$name
		);
	}

	/**
	 * Get CTA configuration for a scenario.
	 *
	 * @param string $scenario_id Scenario ID (first argument)
	 * @param string $name Integration name (second argument, not used in license scenarios)
	 *
	 * @return array|null CTA configuration or null if not found
	 */
	protected function get_cta_config( string $scenario_id, string $name, ...$args ) {
		$scenarios = $this->get_scenarios();

		// Find config by scenario_id.
		foreach ( $scenarios as $config ) {
			if ( $config['scenario_id'] === $scenario_id ) {
				return $config['cta'] ?? null;
			}
		}

		return null;
	}

	/**
	 * Determine scenario key based on requirements.
	 *
	 * @param string $required_tier Required tier
	 * @param string $type Type of dependency ('tier' or 'credits')
	 *
	 * @return string Scenario key
	 */
	private function determine_scenario_key( string $required_tier, string $type = 'tier' ) {

		// Handle app credits scenario.
		if ( 'credits' === $type ) {
			return $this->determine_app_credits_key();
		}

		$current_plan = $this->context->get_plan_service()->get_current_plan_id();

		// User has Lite, needs Pro - check Pro plugin status.
		if ( 'lite' === $current_plan && 'lite' !== $required_tier ) {
			return $this->determine_pro_status_key( $required_tier );
		}

		// User has Pro but needs higher tier - map current to required.
		$tier_upgrade_map = array(
			'pro-basic' => array(
				'pro-plus'  => 'upgrade-basic-to-plus',
				'pro-elite' => 'upgrade-basic-to-elite',
			),
			'pro-plus'  => array(
				'pro-elite' => 'upgrade-plus-to-elite',
			),
		);

		if ( isset( $tier_upgrade_map[ $current_plan ][ $required_tier ] ) ) {
			return $tier_upgrade_map[ $current_plan ][ $required_tier ];
		}

		// Fallback to generic insufficient scenario.
		return 'pro-basic-not-installed';
	}

	/**
	 * Determine Pro status scenario key.
	 *
	 * @param string $required_tier Required tier
	 *
	 * @return string Scenario key
	 */
	private function determine_pro_status_key( string $required_tier = 'pro-basic' ) {

		// Not installed - return tier-specific scenario.
		if ( ! $this->context->is_pro_installed() ) {
			$tier_not_installed_map = array(
				'pro-basic' => 'pro-basic-not-installed',
				'pro-plus'  => 'pro-plus-not-installed',
				'pro-elite' => 'pro-elite-not-installed',
			);
			return $tier_not_installed_map[ $required_tier ] ?? 'pro-basic-not-installed';
		}

		// Installed but not active.
		if ( ! $this->context->is_pro_active() ) {
			return 'pro-deactivated';
		}

		// Pro is active - check license status.
		return $this->determine_license_status_key();
	}

	/**
	 * Determine license status scenario key.
	 *
	 * @return string Scenario key
	 */
	private function determine_license_status_key() {
		$status = $this->context->get_license_status();

		// Map license status to scenario key.
		$status_key_map = array(
			'expired'       => 'license-expired',
			'disabled'      => 'license-disabled',
			'site_inactive' => 'license-site-inactive',
			'invalid'       => 'license-invalid',
		);

		return $status_key_map[ $status ] ?? 'license-inactive';
	}

	/**
	 * Determine app credits scenario key.
	 *
	 * @return string Scenario key
	 */
	private function determine_app_credits_key() {
		// Not connected to Automator - needs to register site.
		if ( ! $this->context->is_automator_connected() ) {
			return 'app-lite-no-api';
		}

		// Connected but no credits - needs to purchase or upgrade.
		return 'app-lite-no-credits';
	}

	/**
	 * Get "Set up account" CTA.
	 *
	 * Directs users to the setup wizard to connect their site.
	 *
	 * @return array CTA configuration
	 */
	private function get_setup_account_cta() {
		return array(
			'label' => esc_html_x( 'Create free account', 'Dependency CTA', 'uncanny-automator' ),
			'url'   => add_query_arg(
				array(
					'post_type' => 'uo-recipe',
					'page'      => 'uncanny-automator-setup-wizard',
				),
				admin_url( 'edit.php' )
			),
			'type'  => 'link-external',
		);
	}

	/**
	 * Get upgrade CTA with custom label.
	 *
	 * @param string $label CTA label (already translated).
	 *
	 * @return array CTA configuration
	 */
	private function get_upgrade_cta( string $label ) {
		return array(
			'label' => esc_html( $label ),
			'url'   => automator_utm_parameters( AUTOMATOR_STORE_URL . 'pricing/', 'upgrade_to_pro', 'upgrade_to_pro_button' ),
			'type'  => 'link-external',
		);
	}

	/**
	 * Get "Active Uncanny Automator Pro" CTA.
	 *
	 * @return array CTA configuration
	 */
	private function get_activate_pro_cta() {
		return array(
			'label' => esc_html_x( 'Activate Uncanny Automator Pro', 'Dependency CTA', 'uncanny-automator' ),
			'url'   => Plugin_Info::get_plugin_search_url( 'Uncanny Automator Pro' ),
			'type'  => 'link-external',
		);
		/*
		// TODO : Future implementation activate via fetch.
		return array(
			'label' => esc_html_x( 'Active Uncanny Automator Pro', 'Dependency CTA', 'uncanny-automator' ),
			'type'  => 'fetch',
		);
		*/
	}

	/**
	 * Get license settings CTA with custom label.
	 *
	 * @param string $label CTA label (already translated).
	 *
	 * @return array CTA configuration
	 */
	private function get_license_settings_cta( string $label ) {
		return array(
			'label' => esc_html( $label ),
			'url'   => add_query_arg(
				array(
					'post_type' => 'uo-recipe',
					'page'      => 'uncanny-automator-config',
					'tab'       => 'general',
					'general'   => 'license',
				),
				admin_url( 'edit.php' )
			),
			'type'  => 'link-external',
		);
	}

	/**
	 * Get "Renew now" CTA.
	 *
	 * @return array CTA configuration
	 */
	private function get_renew_now_cta() {
		return array(
			'label' => esc_html_x( 'Renew now', 'Dependency CTA', 'uncanny-automator' ),
			'url'   => AUTOMATOR_STORE_URL . 'my-account/downloads/',
			'type'  => 'link-external',
		);
	}
}

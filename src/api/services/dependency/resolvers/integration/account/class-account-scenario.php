<?php
/**
 * Account Scenario Service
 *
 * Service for generating account scenario IDs, descriptions, and CTAs.
 * Used by dependency resolvers to determine account connection requirements and messaging.
 *
 * @package Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\Account
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\Account;

use Uncanny_Automator\Api\Services\Dependency\Resolvers\Abstract_Scenario;

/**
 * Account Scenario.
 *
 * @since 7.0.0
 */
class Account_Scenario extends Abstract_Scenario {

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
			// App account connection
			'account-app' => array(
				'scenario_id' => 'account-app',
				// translators: %s: Integration or service name.
				'description' => esc_attr__( 'Connect your %s account to use this integration', 'uncanny-automator' ),
				'cta_connect' => array(
					'type'  => 'link-open-and-wait',
					// translators: %s: Integration or service name.
					'label' => esc_attr_x( 'Connect %s account', 'Dependency CTA', 'uncanny-automator' ),
				),
				'cta_get'     => array(
					'type'  => 'link-external',
					// translators: %s: Integration or service name
					'label' => esc_attr_x( 'Connect %s account', 'Dependency CTA', 'uncanny-automator' ),
				),
			),
		);

		return $this->scenarios;
	}

	/**
	 * Get scenario ID.
	 *
	 * @param mixed ...$args Arguments (not used for account scenarios)
	 *
	 * @return string Scenario ID
	 */
	public function get_scenario_id( ...$args ) {
		return 'account-app';
	}

	/**
	 * Get scenario name.
	 *
	 * Returns '%s account' pattern for formatting with integration name.
	 *
	 * @param string $scenario_id Scenario ID
	 *
	 * @return string Scenario name pattern
	 */
	public function get_name( string $scenario_id ) {
		// translators: %s: Integration name
		return esc_html_x( '%s account', 'Dependency', 'uncanny-automator' );
	}

	/**
	 * Get description for a scenario.
	 *
	 * @param string $scenario_id Scenario ID
	 * @param string $name Integration name to insert in description
	 *
	 * @return string Translated description
	 */
	public function get_description( string $scenario_id, string $name ) {
		$config = $this->get_scenario_config( $scenario_id );

		if ( $config && ! empty( $config['description'] ) ) {
			return sprintf(
				// translators: %s: Integration name
				$config['description'],
				$name
			);
		}

		// Fallback description
		return sprintf(
			// translators: %s: Integration name
			esc_attr__( 'Connect your %s account to use this integration', 'uncanny-automator' ),
			$name
		);
	}

	/**
	 * Get CTA configuration array.
	 *
	 * @param string $scenario_id Scenario ID (first argument)
	 * @param string $name Integration name (second argument)
	 * @param string $settings_url Settings URL for connecting (third argument from ...$args)
	 * @param string $developer_site Developer site URL (fourth argument from ...$args, fallback)
	 *
	 * @return array CTA configuration array
	 */
	protected function get_cta_config( string $scenario_id, string $name, ...$args ) {
		$settings_url   = $args[0] ?? '';
		$developer_site = $args[1] ?? '';
		$config         = $this->get_scenario_config( $scenario_id );

		// If no settings URL, use "Get" CTA with developer site
		if ( empty( $settings_url ) ) {
			$cta_config = $config['cta_get'] ?? array();
			return array(
				'type'  => $cta_config['type'] ?? 'link-external',
				// translators: %s: Integration name
				'label' => sprintf( $cta_config['label'] ?? esc_attr_x( 'Get %s', 'Dependency CTA', 'uncanny-automator' ), $name ),
				'url'   => ! empty( $developer_site ) ? $developer_site : '#',
			);
		}

		// Use "Connect" CTA with settings URL
		$cta_config = $config['cta_connect'] ?? array();
		return array(
			'type'  => $cta_config['type'] ?? 'link-open-and-wait',
			// translators: %s: Integration name
			'label' => sprintf( $cta_config['label'] ?? esc_attr_x( 'Connect %s', 'Dependency CTA', 'uncanny-automator' ), $name ),
			'url'   => $settings_url,
		);
	}
}

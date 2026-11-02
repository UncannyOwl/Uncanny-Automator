<?php
/**
 * Plugin Scenario Service
 *
 * Service for generating plugin installation scenario IDs, descriptions, and CTAs.
 * Used by dependency resolvers to determine installation requirements and messaging.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Plugin
 * @since 7.0.0
 */
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Plugin;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Distribution_Type;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Abstract_Scenario;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Value_Objects\Dependency_Cta;
use Uncanny_Automator\Services\Plugin\Info as Plugin_Info;

/**
 * Plugin Scenario.
 *
 * @since 7.0.0
 */
class Plugin_Scenario extends Abstract_Scenario {

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
			// WP.org plugin.
			Distribution_Type::SLUG_WP_ORG      => array(
				'scenario_id'         => 'installable-' . Distribution_Type::SLUG_WP_ORG,
				// translators: %s: Plugin name
				'description'         => esc_attr__( 'Install %s from the WordPress.org plugin directory', 'uncanny-automator' ),
				// translators: %1$s: Plugin name. %2$s: Name of the plugin that must be installed first. Asterisks are markdown emphasis and must be kept.
				'description_blocked' => esc_attr__( 'Install %1$s from the WordPress.org plugin directory. *Requires %2$s to be installed and active.*', 'uncanny-automator' ),
				'cta'                 => array(
					'type'  => 'link-external', // TODO: Change to 'fetch' when ready.
					// translators: %s: Plugin name
					'label' => esc_attr_x( 'Install %s', 'Dependency CTA', 'uncanny-automator' ),
				),
			),
			// Open source (non-WP.org).
			Distribution_Type::SLUG_OPEN_SOURCE => array(
				'scenario_id'         => 'installable-' . Distribution_Type::SLUG_OPEN_SOURCE,
				// translators: %s: Plugin name
				'description'         => esc_attr__( 'Install the %s plugin to use this integration', 'uncanny-automator' ),
				// translators: %1$s: Plugin name. %2$s: Name of the plugin that must be installed first. Asterisks are markdown emphasis and must be kept.
				'description_blocked' => esc_attr__( 'Install the %1$s plugin to use this integration. *Requires %2$s to be installed and active.*', 'uncanny-automator' ),
				'cta'                 => array(
					'type'  => 'link-external',
					// translators: %s: Plugin name
					'label' => esc_attr_x( 'Install %s', 'Dependency CTA', 'uncanny-automator' ),
				),
			),
			// Commercial plugin.
			Distribution_Type::SLUG_COMMERCIAL  => array(
				'scenario_id'         => 'installable-' . Distribution_Type::SLUG_COMMERCIAL,
				// translators: %s: Plugin name
				'description'         => esc_attr__( 'Purchase and install %s to use this integration', 'uncanny-automator' ),
				// translators: %1$s: Plugin name. %2$s: Name of the plugin that must be installed first. Asterisks are markdown emphasis and must be kept.
				'description_blocked' => esc_attr__( 'Purchase and install %1$s to use this integration. *Requires %2$s to be installed and active.*', 'uncanny-automator' ),
				'cta'                 => array(
					'type'  => 'link-external',
					// translators: %s: Plugin name
					'label' => esc_attr_x( 'Install %s', 'Dependency CTA', 'uncanny-automator' ),
				),
			),
		);

		return $this->scenarios;
	}

	/**
	 * Get scenario ID for a distribution type.
	 *
	 * @param string $distribution_type Distribution type (wp_org, open_source, commercial) (first argument)
	 *
	 * @return string Scenario ID
	 */
	public function get_scenario_id( ...$args ) {
		$distribution_type = $args[0] ?? Distribution_Type::COMMERCIAL;
		$scenarios         = $this->get_scenarios();

		$slug   = Distribution_Type::get_slug( $distribution_type );
		$config = $scenarios[ $slug ] ?? null;

		return $config ? $config['scenario_id'] : 'installable-' . Distribution_Type::SLUG_COMMERCIAL;
	}

	/**
	 * Get scenario name.
	 *
	 * @param string $scenario_id Scenario ID
	 *
	 * @return string Scenario name
	 */
	public function get_name( string $scenario_id ) {
		return esc_html_x( 'Plugin', 'Dependency', 'uncanny-automator' );
	}

	/**
	 * Get description for a scenario.
	 *
	 * @param string $scenario_id Scenario ID
	 * @param string $name Integration name to insert in description
	 * @param string $blocked_by Name of the plugin that must be installed first.
	 *                           Empty when nothing blocks installing this one.
	 *
	 * @return string Translated description
	 */
	public function get_description( string $scenario_id, string $name, string $blocked_by = '' ) {
		$config = $this->get_scenario_config( $scenario_id );

		if ( $config && '' !== $blocked_by && ! empty( $config['description_blocked'] ) ) {
			return sprintf(
				// translators: %1$s: Integration name. %2$s: Blocking plugin name.
				$config['description_blocked'],
				$name,
				$blocked_by
			);
		}

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
			esc_attr__( 'Install %s to use this integration', 'uncanny-automator' ),
			$name
		);
	}

	/**
	 * Description for a plugin that is installed but not active.
	 *
	 * @since 8.0.0
	 *
	 * @param string $name       Plugin name.
	 * @param string $blocked_by Name of the plugin that must be active first. Empty when nothing blocks it.
	 *
	 * @return string Translated description
	 */
	public function get_inactive_description( string $name, string $blocked_by = '' ): string {
		if ( '' !== $blocked_by ) {
			return sprintf(
				// translators: %1$s: Plugin name. %2$s: Name of the plugin that must be installed and active first. Asterisks are markdown emphasis and must be kept.
				esc_attr__( '%1$s is installed but not active. Activate it to use this integration. *Requires %2$s to be installed and active.*', 'uncanny-automator' ),
				$name,
				$blocked_by
			);
		}

		return sprintf(
			// translators: %s: Plugin name
			esc_attr__( '%s is installed but not active. Activate it to use this integration', 'uncanny-automator' ),
			$name
		);
	}

	/**
	 * Call to action for a plugin that is installed but not active: the Plugins screen, filtered to it.
	 *
	 * @since 8.0.0
	 *
	 * @param string $name Plugin name.
	 *
	 * @return Dependency_Cta
	 */
	public function create_activate_cta( string $name ): Dependency_Cta {
		return new Dependency_Cta(
			array(
				'type'  => 'link-external',
				'label' => sprintf(
					// translators: %s: Plugin name
					esc_attr_x( 'Activate %s', 'Dependency CTA', 'uncanny-automator' ),
					esc_html( $name )
				),
				'url'   => Plugin_Info::get_plugin_search_url( $name ),
			)
		);
	}

	/**
	 * Get CTA configuration array.
	 *
	 * @param string $scenario_id Scenario ID (first argument)
	 * @param string $name Integration name (second argument)
	 * @param string $developer_site Developer site URL (third argument from ...$args)
	 *
	 * @return array CTA configuration array
	 */
	protected function get_cta_config( string $scenario_id, string $name, ...$args ) {
		$developer_site = $args[0] ?? '';
		$config         = $this->get_scenario_config( $scenario_id );

		if ( ! $config || empty( $config['cta'] ) ) {
			// Fallback CTA
			return array(
				'type'  => 'link-external',
				'label' => sprintf(
					// translators: %s: Plugin name
					esc_attr_x( 'Install %s', 'Dependency CTA', 'uncanny-automator' ),
					$name
				),
				'url'   => $developer_site,
			);
		}

		$cta_config = $config['cta'];

		// Determine URL: WP.org uses plugin search, others use developer site.
		$wp_org_scenario_id = 'installable-' . Distribution_Type::SLUG_WP_ORG;
		$url                = $wp_org_scenario_id === $scenario_id
			? Plugin_Info::get_plugin_install_search_url( $name )
			: $developer_site;

		// Build CTA configuration from scenario config
		return array(
			'type'  => $cta_config['type'],
			'label' => sprintf( $cta_config['label'], esc_html( $name ) ),
			'url'   => $url,
		);
	}
}

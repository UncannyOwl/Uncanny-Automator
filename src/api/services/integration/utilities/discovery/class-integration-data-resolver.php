<?php
/**
 * Integration Data Resolver
 *
 * Resolves final integration data structure from multiple sources.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Discovery
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration\Utilities\Discovery;

use Uncanny_Automator\Api\Components\Integration\Enums\Distribution_Type;
use Uncanny_Automator\Api\Services\Plugin\Plugin_Service;

/**
 * Resolves integration data from manifest, plugin, and registration data.
 *
 * @since 7.0.0
 */
class Integration_Data_Resolver {

	/**
	 * Plugin resolver.
	 *
	 * @var Integration_Plugin_Resolver
	 */
	private $plugin_resolver;

	/**
	 * Item discoverers.
	 *
	 * @var array
	 */
	private $item_discoverers;

	/**
	 * Constructor.
	 *
	 * @param Integration_Plugin_Resolver $plugin_resolver Plugin resolver instance
	 * @param array $item_discoverers Item discoverer instances
	 *
	 * @return void
	 */
	public function __construct( Integration_Plugin_Resolver $plugin_resolver, array $item_discoverers ) {
		$this->plugin_resolver  = $plugin_resolver;
		$this->item_discoverers = $item_discoverers;
	}

	/**
	 * Resolve integration data.
	 *
	 * Combines manifest, plugin, and registration data into final structure.
	 *
	 * @param string $code Integration code
	 * @param array $integration Integration data from registration
	 * @param array $manifest Manifest data
	 * @param string $plugin_file Plugin file path
	 * @param array $plugin_data Plugin data
	 *
	 * @return array Resolved integration data
	 */
	public function resolve( string $code, array $integration, array $manifest, string $plugin_file, array $plugin_data ): array {
		$integration_type = $this->resolve_integration_type( $manifest, $integration );
		$integration_tier = $this->resolve_integration_tier( $manifest );

		return array(
			'integration_id'           => $code,
			'integration_name'         => $integration['name'],
			'integration_icon'         => $integration['icon_svg'] ?? '',
			'integration_color'        => $manifest['integration_color'] ?? '#666666',
			'integration_tier'         => $integration_tier,
			'integration_type'         => $integration_type,
			'integration_link'         => $this->resolve_external_url( $integration_type, $manifest, $plugin_data, $integration['name'] ?? '' ),
			'description'              => $this->resolve_description( $manifest, $plugin_data ),
			'plugin_file_path'         => $plugin_file,
			'settings_url'             => $integration['settings_url'] ?? '',
			'integration_triggers'     => $this->item_discoverers['trigger']->discover( $code, $integration['name'] ),
			'integration_actions'      => $this->item_discoverers['action']->discover( $code, $integration['name'] ),
			'integration_conditions'   => $this->item_discoverers['filter_condition']->discover( $code, $integration['name'] ),
			'integration_loop_filters' => $this->item_discoverers['loop_filter']->discover( $code, $integration['name'] ),
			'plugin_details'           => $this->build_plugin_details( $manifest, $plugin_data, $plugin_file, $integration_type, $integration_tier ),
			'_plugin_version'          => self::resolve_version( $manifest, $plugin_data, $plugin_file ),
		);
	}

	/**
	 * Resolve integration type.
	 *
	 * @param array $manifest    Manifest data
	 * @param array $integration Integration data from registration
	 *
	 * @return string Integration type
	 */
	private function resolve_integration_type( array $manifest, array $integration ): string {
		$integration_type = $manifest['integration_type'] ?? '';
		if ( ! empty( $integration_type ) ) {
			return $integration_type;
		}

		// Fallback to is_third_party logic
		$is_third_party = $integration['is_third_party'] ?? true;
		return $is_third_party ? 'third_party' : 'plugin';
	}

	/**
	 * Resolve integration tier.
	 *
	 * @param array $manifest Manifest data
	 *
	 * @return string Integration tier
	 */
	private function resolve_integration_tier( array $manifest ): string {
		$integration_tier = $manifest['integration_tier'] ?? '';
		return ! empty( $integration_tier ) ? $integration_tier : 'lite';
	}

	/**
	 * Resolve external URL.
	 *
	 * @param string $integration_type Integration type
	 * @param array  $manifest          Manifest data
	 * @param array  $plugin_data       Plugin data
	 * @param string $integration_name Integration name
	 *
	 * @return string External URL
	 */
	private function resolve_external_url( string $integration_type, array $manifest, array $plugin_data, string $integration_name ): string {
		// Built-in integrations use pricing page
		if ( 'built-in' === $integration_type ) {
			return 'https://automatorplugin.com/pricing/';
		}

		return $this->plugin_resolver->resolve_external_url(
			$manifest,
			$plugin_data,
			$integration_name
		);
	}

	/**
	 * Resolve description from manifest and plugin data.
	 *
	 * @param array $manifest    Manifest data
	 * @param array $plugin_data Plugin data
	 *
	 * @return array Description array in complete.json format
	 */
	private function resolve_description( array $manifest, array $plugin_data ): array {
		// Get description from manifest (priority: full > short)
		$short_description = $manifest['short_description'] ?? '';
		$full_description  = $manifest['full_description'] ?? '';

		// Fallback to plugin description if manifest doesn't have either
		if ( empty( $short_description ) && empty( $full_description ) ) {
			$plugin_desc = $this->plugin_resolver->get_plugin_description( $plugin_data );
			if ( ! empty( $plugin_desc ) ) {
				$short_description = $plugin_desc;
				$full_description  = $plugin_desc;
			}
		}

		// Use short for full if full is missing (manifest trait allows this)
		if ( ! empty( $short_description ) && empty( $full_description ) ) {
			$full_description = $short_description;
		}

		// Build description array (complete.json format)
		if ( empty( $short_description ) && empty( $full_description ) ) {
			return array();
		}

		return array(
			array(
				'short' => $short_description,
				'full'  => $full_description,
			),
		);
	}

	/**
	 * Build plugin details from manifest and plugin data.
	 *
	 * @param array  $manifest         Manifest data
	 * @param array  $plugin_data      Plugin data
	 * @param string $plugin_file      Plugin file path
	 * @param string $integration_type Integration type
	 * @param string $integration_tier Integration tier
	 *
	 * @return array Plugin details array
	 */
	private function build_plugin_details( array $manifest, array $plugin_data, string $plugin_file, string $integration_type, string $integration_tier ): array {
		// Built-in integrations have hardcoded plugin details.
		if ( 'built-in' === $integration_type ) {
			return $this->build_built_in_plugin_details( $integration_tier );
		}

		// Non-built-in integrations use standard resolution.
		$required_plugins = $this->plugin_resolver->get_required_plugins( $plugin_data );

		$details = array(
			'plugin_file'      => $plugin_file,
			'developer_name'   => $manifest['developer_name'] ?? ( ! empty( $plugin_data ) ? ( $plugin_data['Author'] ?? '' ) : '' ),
			'plugin_required'  => $manifest['plugin_required'] ?? $required_plugins,
			'integration_type' => $integration_type,
		);

		// Add manifest-only fields.
		if ( ! empty( $manifest['integration_required'] ) ) {
			$details['integration_required'] = $manifest['integration_required'];
		}
		if ( ! empty( $manifest['distribution_type'] ) ) {
			$details['distribution_type'] = $manifest['distribution_type'];
		}
		if ( ! empty( $manifest['plugin_variations'] ) ) {
			$details['plugin_variations'] = $manifest['plugin_variations'];
		}

		return array( $details );
	}

	/**
	 * Resolve plugin version.
	 *
	 * Determines version from multiple sources in priority order:
	 * 1. Manifest integration_version
	 * 2. Plugin data Version
	 * 3. Plugin file version
	 * 4. Default fallback
	 *
	 * @param array           $manifest       Manifest data
	 * @param array           $plugin_data    Plugin data (optional)
	 * @param string          $plugin_file    Plugin file path (optional)
	 * @param ?Plugin_Service $plugin_service Optional Plugin_Service for DI/testing.
	 *
	 * @return string Plugin version
	 */
	public static function resolve_version( array $manifest, array $plugin_data = array(), string $plugin_file = '', $plugin_service = null ): string {
		$version = $manifest['integration_version'] ?? '';
		if ( ! empty( $version ) ) {
			return $version;
		}

		if ( ! empty( $plugin_data ) && ! empty( $plugin_data['Version'] ) ) {
			return $plugin_data['Version'];
		}

		if ( ! empty( $plugin_file ) ) {
			$plugin_service = $plugin_service ?? new Plugin_Service();
			$file_version   = $plugin_service->get_plugin_version( $plugin_file );
			if ( ! empty( $file_version ) && '1.0.0' !== $file_version ) {
				return $file_version;
			}
		}

		return '1.0.0';
	}

	/**
	 * Build built-in plugin details.
	 *
	 * @param string $integration_tier Integration tier
	 *
	 * @return array Plugin details array
	 */
	private function build_built_in_plugin_details( string $integration_tier ): array {
		$is_pro = 'lite' !== $integration_tier;
		$file   = $is_pro
			? 'uncanny-automator-pro/uncanny-automator-pro.php'
			: 'uncanny-automator/uncanny-automator.php';

		return array(
			'plugin_file'          => $file,
			'developer_name'       => 'Uncanny Automator',
			'plugin_required'      => null,
			'integration_type'     => 'automator_core',
			'distribution_type'    => $is_pro ? Distribution_Type::COMMERCIAL : Distribution_Type::WP_ORG,
			'plugin_variations'    => array(),
			'integration_required' => null,
		);
	}
}

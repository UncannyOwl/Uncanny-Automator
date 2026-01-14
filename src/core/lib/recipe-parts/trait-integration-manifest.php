<?php
/**
 * Integration Manifest Trait
 *
 * Provides getters/setters for integration metadata, allowing 3rd party developers
 * to explicitly define integration details instead of relying solely on expensive plugin discovery.
 *
 * ## Usage
 *
 * **For Third-Party Developers (Optional):**
 * Use this trait and call setters in your integration's `setup()` method to provide explicit metadata:
 * ```php
 * protected function setup() {
 *     $this->set_integration( 'MY_INTEGRATION' );
 *     $this->set_name( 'My Integration' );
 *
 *     // Optional: Use manifest trait setters to provide metadata
 *     $this->set_short_description( 'Short description' );
 *     $this->set_integration_color( '#FF0000' );
 *     $this->set_developer_site( 'https://example.com' );
 * }
 * ```
 *
 * **During Registration:**
 * If this trait is used, manifest data is automatically extracted during integration registration
 * and made available to the discovery service. This allows the discovery service to use your
 * explicit metadata instead of performing expensive plugin discovery operations.
 *
 * **Important:** Getters only return values explicitly set via setters.
 * They do NOT fetch from complete.json or perform any data lookups.
 *
 * **For Internal Integrations:**
 * Internal integrations have metadata in complete.json, but getters do NOT
 * access this data to avoid performance overhead and circular dependencies.
 *
 * To access data for internal integrations, use:
 * ```php
 * $store = \Uncanny_Automator\Api\Services\Integration\Integration_Store::get_instance();
 * $integration = $store->get_by_code( 'INTEGRATION_CODE' );
 * ```
 *
 * @package Uncanny_Automator
 * @since 5.7
 */

namespace Uncanny_Automator;

use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;
use InvalidArgumentException;

/**
 * Trait Integration_Manifest
 *
 * Provides manifest properties and methods for integration metadata.
 *
 * **Note:** Getters return only values set via setters. They do NOT fetch from
 * complete.json or perform lookups. For internal integration data, use Integration_Store.
 *
 * @package Uncanny_Automator
 * @since 5.7
 */
trait Integration_Manifest {

	/**
	 * Short description.
	 *
	 * @var string
	 */
	protected $short_description = '';

	/**
	 * Full description.
	 *
	 * @var string
	 */
	protected $full_description = '';

	/**
	 * Integration color (hex code).
	 *
	 * @var string
	 */
	protected $integration_color = '';

	/**
	 * Developer name.
	 *
	 * @var string
	 */
	protected $developer_name = '';

	/**
	 * Developer site URL.
	 *
	 * @var string
	 */
	protected $developer_site = '';

	/**
	 * Main plugin file path.
	 *
	 * @var string
	 */
	protected $plugin_file_path = '';

	/**
	 * Required plugin file paths.
	 *
	 * @var array
	 */
	protected $plugin_required = array();

	/**
	 * Required integration codes.
	 *
	 * @var array
	 */
	protected $integration_required = array();

	/**
	 * Distribution type ('wp_org', 'commercial', 'free', 'saas').
	 *
	 * @var string
	 */
	protected $distribution_type = '';

	/**
	 * Plugin variations (alternative plugin file paths).
	 *
	 * @var array
	 */
	protected $plugin_variations = array();

	/**
	 * Integration version.
	 *
	 * Used for cache busting. When changed, forces cache refresh.
	 *
	 * @var string
	 */
	protected $integration_version = '';

	/**
	 * Integration type ('plugin', 'app', 'built-in', 'third_party').
	 *
	 * @var string
	 */
	protected $integration_type = '';

	/**
	 * Integration tier ('lite', 'pro-basic', 'pro-plus', 'pro-elite').
	 *
	 * @var string
	 */
	protected $integration_tier = '';

	/**
	 * Set short description.
	 *
	 * @param string $description Short description.
	 * @return void
	 */
	public function set_short_description( $description ) {
		$this->short_description = (string) $description;
	}

	/**
	 * Get short description.
	 *
	 * Returns only values set via set_short_description(). Does NOT fetch from complete.json.
	 * For internal integration data, use Integration_Store.
	 *
	 * @return string
	 */
	public function get_short_description() {
		return $this->short_description;
	}

	/**
	 * Set full description.
	 *
	 * @param string $description Full description.
	 * @return void
	 */
	public function set_full_description( $description ) {
		$this->full_description = (string) $description;
	}

	/**
	 * Get full description.
	 *
	 * Returns full description if set, otherwise falls back to short description.
	 * Does NOT fetch from complete.json. For internal integration data, use Integration_Store.
	 *
	 * @return string
	 */
	public function get_full_description() {
		if ( ! empty( $this->full_description ) ) {
			return $this->full_description;
		}
		return $this->short_description;
	}

	/**
	 * Set integration color.
	 *
	 * Validates hex color format (with or without #).
	 *
	 * @param string $color Hex color code.
	 *
	 * @return void
	 * @throws InvalidArgumentException If color format is invalid.
	 */
	public function set_integration_color( $color ) {
		$color = (string) $color;

		// Allow empty string (no color set)
		if ( empty( $color ) ) {
			$this->integration_color = '';
			return;
		}

		// Remove # if present for validation
		$color_clean = ltrim( $color, '#' );

		// Validate hex color format (3 or 6 hex digits)
		if ( ! preg_match( '/^[0-9A-Fa-f]{3}$|^[0-9A-Fa-f]{6}$/', $color_clean ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Invalid hex color format: %s. Expected format: #RRGGBB or #RGB (e.g., #FF0000 or #F00)',
					esc_html( $color )
				)
			);
		}

		// Ensure # prefix for storage
		$this->integration_color = strpos( $color, '#' ) === 0 ? $color : '#' . $color_clean;
	}

	/**
	 * Get integration color.
	 *
	 * Returns only values set via set_integration_color(). Does NOT fetch from complete.json.
	 * For internal integration data, use Integration_Store.
	 *
	 * @return string
	 */
	public function get_integration_color() {
		return $this->integration_color;
	}

	/**
	 * Set developer name.
	 *
	 * @param string $name Developer name.
	 * @return void
	 */
	public function set_developer_name( $name ) {
		$this->developer_name = (string) $name;
	}

	/**
	 * Get developer name.
	 *
	 * Returns only values set via set_developer_name(). Does NOT fetch from complete.json.
	 * For internal integration data, use Integration_Store.
	 *
	 * @return string
	 */
	public function get_developer_name() {
		return $this->developer_name;
	}

	/**
	 * Set developer site URL.
	 *
	 * Validates URL format.
	 *
	 * @param string $url Developer site URL.
	 *
	 * @return void
	 * @throws InvalidArgumentException If URL format is invalid.
	 */
	public function set_developer_site( $url ) {
		$url = (string) $url;

		// Allow empty string (no URL set)
		if ( empty( $url ) ) {
			$this->developer_site = '';
			return;
		}

		// Validate URL format
		$validated_url = filter_var( $url, FILTER_VALIDATE_URL );
		if ( false === $validated_url ) {
			throw new InvalidArgumentException(
				sprintf(
					'Invalid URL format: %s. Expected a valid URL (e.g., https://example.com)',
					esc_html( $url )
				)
			);
		}

		$this->developer_site = $validated_url;
	}

	/**
	 * Get developer site URL.
	 *
	 * Returns only values set via set_developer_site(). Does NOT fetch from complete.json.
	 * For internal integration data, use Integration_Store.
	 *
	 * @return string
	 */
	public function get_developer_site() {
		return $this->developer_site;
	}

	/**
	 * Set plugin file path.
	 *
	 * @param string $file_path Main plugin file path.
	 *
	 * @return void
	 */
	public function set_plugin_file_path( $file_path ) {
		$this->plugin_file_path = (string) $file_path;
	}

	/**
	 * Get plugin file path.
	 *
	 * Returns only values set via set_plugin_file_path(). Does NOT fetch from complete.json.
	 * For internal integration data, use Integration_Store.
	 *
	 * @return string
	 */
	public function get_plugin_file_path() {
		return $this->plugin_file_path;
	}

	/**
	 * Set required plugins.
	 *
	 * Accepts string or array, normalizes to array.
	 * Empty strings result in empty arrays.
	 *
	 * @param string|array $plugins Plugin file path(s).
	 *
	 * @return void
	 */
	public function set_plugin_required( $plugins ) {
		if ( is_string( $plugins ) ) {
			$this->plugin_required = ! empty( $plugins ) ? array( $plugins ) : array();
		} elseif ( is_array( $plugins ) ) {
			$this->plugin_required = $plugins;
		} else {
			$this->plugin_required = array();
		}
	}

	/**
	 * Get required plugins.
	 *
	 * Returns only values set via set_plugin_required(). Does NOT fetch from complete.json.
	 * For internal integration data, use Integration_Store.
	 *
	 * @return array Array of plugin file paths.
	 */
	public function get_plugin_required() {
		return $this->plugin_required;
	}

	/**
	 * Set required integrations.
	 *
	 * Accepts string or array, normalizes to array.
	 * Empty strings result in empty arrays.
	 *
	 * @param string|array $integrations Integration code(s).
	 *
	 * @return void
	 */
	public function set_integration_required( $integrations ) {
		if ( is_string( $integrations ) ) {
			$this->integration_required = ! empty( $integrations ) ? array( $integrations ) : array();
		} elseif ( is_array( $integrations ) ) {
			$this->integration_required = $integrations;
		} else {
			$this->integration_required = array();
		}
	}

	/**
	 * Get required integrations.
	 *
	 * Returns only values set via set_integration_required(). Does NOT fetch from complete.json.
	 * For internal integration data, use Integration_Store.
	 *
	 * @return array Array of integration codes.
	 */
	public function get_integration_required() {
		return $this->integration_required;
	}

	/**
	 * Check if a plugin file corresponds to an existing Automator integration.
	 *
	 * @param string $plugin_file Plugin file path to check.
	 *
	 * @return bool True if plugin is an existing integration, false otherwise.
	 */
	public function is_plugin_an_integration( $plugin_file ) {
		if ( empty( $plugin_file ) ) {
			return false;
		}

		$registry     = Integration_Registry_Service::get_instance();
		$integrations = $registry->get_all_integrations();

		if ( empty( $integrations ) ) {
			return false;
		}

		foreach ( $integrations as $code => $integration ) {
			$integration_plugin_file = $integration['plugin_file_path'] ?? '';
			if ( ! empty( $integration_plugin_file ) && $integration_plugin_file === $plugin_file ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Set distribution type.
	 *
	 * @param string $type Distribution type ('wp_org', 'commercial', 'free', 'saas').
	 *
	 * @return void
	 */
	public function set_distribution_type( $type ) {
		$this->distribution_type = (string) $type;
	}

	/**
	 * Get distribution type.
	 *
	 * Returns only values set via set_distribution_type(). Does NOT fetch from complete.json.
	 * For internal integration data, use Integration_Store.
	 *
	 * @return string
	 */
	public function get_distribution_type() {
		return $this->distribution_type;
	}

	/**
	 * Set distribution type to WordPress.org.
	 *
	 * Convenience method for setting 'wp_org' distribution type.
	 *
	 * @return void
	 */
	public function set_distribution_wp_org() {
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Set plugin variations.
	 *
	 * @param array $variations Array of alternative plugin file paths.
	 *
	 * @return void
	 */
	public function set_plugin_variations( $variations ) {
		$this->plugin_variations = is_array( $variations ) ? $variations : array();
	}

	/**
	 * Add a plugin variation.
	 *
	 * @param string $variation Alternative plugin file path.
	 *
	 * @return void
	 */
	public function add_plugin_variation( $variation ) {
		if ( ! empty( $variation ) && is_string( $variation ) ) {
			$this->plugin_variations[] = $variation;
		}
	}

	/**
	 * Get plugin variations.
	 *
	 * Returns only values set via set_plugin_variations() or add_plugin_variation().
	 * Does NOT fetch from complete.json. For internal integration data, use Integration_Store.
	 *
	 * @return array Array of alternative plugin file paths.
	 */
	public function get_plugin_variations() {
		return $this->plugin_variations;
	}

	/**
	 * Extract manifest data from integration instance.
	 *
	 * Only extracts non-empty values from manifest trait.
	 * This method is called automatically during integration registration
	 * if the trait is detected.
	 *
	 * @return array Manifest data
	 */
	public function extract_manifest_data() {
		$manifest = array();

		// Only include non-empty values
		$short_desc = $this->get_short_description();
		if ( ! empty( $short_desc ) ) {
			$manifest['short_description'] = $short_desc;
		}

		$full_desc = $this->get_full_description();
		if ( ! empty( $full_desc ) ) {
			$manifest['full_description'] = $full_desc;
		}

		$color = $this->get_integration_color();
		if ( ! empty( $color ) ) {
			$manifest['integration_color'] = $color;
		}

		$dev_name = $this->get_developer_name();
		if ( ! empty( $dev_name ) ) {
			$manifest['developer_name'] = $dev_name;
		}

		$dev_site = $this->get_developer_site();
		if ( ! empty( $dev_site ) ) {
			$manifest['developer_site'] = $dev_site;
		}

		// Plugin file path - may override existing
		$plugin_file = $this->get_plugin_file_path();
		if ( ! empty( $plugin_file ) ) {
			$manifest['plugin_file_path'] = $plugin_file;
		}

		$plugin_required = $this->get_plugin_required();
		if ( ! empty( $plugin_required ) ) {
			$manifest['plugin_required'] = $plugin_required;
		}

		$integration_required = $this->get_integration_required();
		if ( ! empty( $integration_required ) ) {
			$manifest['integration_required'] = $integration_required;
		}

		$dist_type = $this->get_distribution_type();
		if ( ! empty( $dist_type ) ) {
			$manifest['distribution_type'] = $dist_type;
		}

		$variations = $this->get_plugin_variations();
		if ( ! empty( $variations ) ) {
			$manifest['plugin_variations'] = $variations;
		}

		$version = $this->get_integration_version();
		if ( ! empty( $version ) ) {
			$manifest['integration_version'] = $version;
		}

		$integration_type = $this->get_integration_type();
		if ( ! empty( $integration_type ) ) {
			$manifest['integration_type'] = $integration_type;
		}

		$integration_tier = $this->get_integration_tier();
		if ( ! empty( $integration_tier ) ) {
			$manifest['integration_tier'] = $integration_tier;
		}

		return $manifest;
	}

	/**
	 * Set integration version.
	 *
	 * Used for cache busting. When changed, forces cache refresh.
	 * Useful for developers to easily invalidate cache during development.
	 *
	 * @param string $version Integration version (e.g., '1.0.0', 'dev-2024-01-01').
	 *
	 * @return void
	 */
	public function set_integration_version( $version ) {
		$this->integration_version = (string) $version;
	}

	/**
	 * Get integration version.
	 *
	 * Returns only values set via set_integration_version(). Does NOT fetch from complete.json.
	 * For internal integration data, use Integration_Store.
	 *
	 * @return string
	 */
	public function get_integration_version() {
		return $this->integration_version;
	}

	/**
	 * Set integration type.
	 *
	 * Validates against allowed values: 'plugin', 'app', 'built-in', 'third_party'.
	 *
	 * @param string $type Integration type.
	 *
	 * @return void
	 * @throws InvalidArgumentException If type is invalid.
	 */
	public function set_integration_type( $type ) {
		$type = (string) $type;

		// Allow empty string (no type set)
		if ( empty( $type ) ) {
			$this->integration_type = '';
			return;
		}

		// Validate against allowed values
		$allowed_types = array( 'plugin', 'app', 'built-in', 'third_party' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Invalid integration type: %s. Allowed values: %s',
					esc_html( $type ),
					esc_html( implode( ', ', $allowed_types ) )
				)
			);
		}

		$this->integration_type = $type;
	}

	/**
	 * Get integration type.
	 *
	 * Returns only values set via set_integration_type(). Does NOT fetch from complete.json.
	 * For internal integration data, use Integration_Store.
	 *
	 * @return string
	 */
	public function get_integration_type() {
		return $this->integration_type;
	}

	/**
	 * Set integration tier.
	 *
	 * Validates against allowed values: 'lite', 'pro-basic', 'pro-plus', 'pro-elite'.
	 *
	 * @param string $tier Integration tier.
	 *
	 * @return void
	 * @throws InvalidArgumentException If tier is invalid.
	 */
	public function set_integration_tier( $tier ) {
		$tier = (string) $tier;

		// Allow empty string (no tier set)
		if ( empty( $tier ) ) {
			$this->integration_tier = '';
			return;
		}

		// Validate against allowed values
		$allowed_tiers = array( 'lite', 'pro-basic', 'pro-plus', 'pro-elite' );
		if ( ! in_array( $tier, $allowed_tiers, true ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Invalid integration tier: %s. Allowed values: %s',
					esc_html( $tier ),
					esc_html( implode( ', ', $allowed_tiers ) )
				)
			);
		}

		$this->integration_tier = $tier;
	}

	/**
	 * Get integration tier.
	 *
	 * Returns only values set via set_integration_tier(). Does NOT fetch from complete.json.
	 * For internal integration data, use Integration_Store.
	 *
	 * @return string
	 */
	public function get_integration_tier() {
		return $this->integration_tier;
	}
}

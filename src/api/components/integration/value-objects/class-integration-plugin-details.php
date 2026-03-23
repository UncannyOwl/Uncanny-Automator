<?php
/**
 * Integration Plugin Details Value Object
 *
 * WordPress plugin-specific details.
 *
 * @package Uncanny_Automator\Api\Components\Integration\Value_Objects
 * @since 7.0.0
 */
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use Uncanny_Automator\Api\Components\Integration\Enums\Distribution_Type;

/**
 * Immutable value object for WordPress plugin details.
 *
 * @since 7.0.0
 */
class Integration_Plugin_Details {

	/**
	 * Plugin type (plugin, addon, third_party)
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Plugin file path.
	 *
	 * The path to the plugin file relative to the WordPress plugin directory.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Plugin required.
	 *
	 * The plugin required to be installed and activated.
	 *
	 * @var string
	 */
	private string $plugin_required;

	/**
	 * Plugin variations.
	 *
	 * Alternate plugin files that are variations of the main plugin file.
	 *
	 * @var array
	 */
	private array $plugin_variations;

	/**
	 * Integration required.
	 *
	 * The integration code required to be installed and activated.
	 *
	 * @var string
	 */
	private string $integration_required;

	/**
	 * Distribution type.
	 *
	 * The distribution type of the plugin.
	 *
	 * Can be:
	 * - wp_org
	 * - open_source
	 * - commercial
	 *
	 * @var string
	 */
	private string $distribution_type;

	/**
	 * Constructor.
	 *
	 * @param array $data Plugin details data
	 */
	public function __construct( array $data = array() ) {
		$this->type                 = $data['type'] ?? '';
		$this->plugin_file          = $data['plugin_file'] ?? '';
		$this->plugin_required      = $data['plugin_required'] ?? '';
		$this->plugin_variations    = $data['plugin_variations'] ?? array();
		$this->integration_required = $data['integration_required'] ?? '';
		$this->distribution_type    = $data['distribution_type'] ?? '';
	}

	/**
	 * Get plugin type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get plugin file path.
	 *
	 * @return string
	 */
	public function get_plugin_file(): string {
		return $this->plugin_file;
	}

	/**
	 * Get plugin required.
	 *
	 * @return string
	 */
	public function get_plugin_required(): string {
		return $this->plugin_required;
	}

	/**
	 * Get plugin variations.
	 *
	 * @return array
	 */
	public function get_plugin_variations(): array {
		return $this->plugin_variations;
	}

	/**
	 * Get integration required.
	 *
	 * @return string
	 */
	public function get_integration_required(): string {
		return $this->integration_required;
	}

	/**
	 * Get distribution type.
	 *
	 * @return string
	 */
	public function get_distribution_type(): string {
		return $this->distribution_type;
	}

	/**
	 * Check if this is a third-party plugin.
	 *
	 * @return bool
	 */
	public function is_third_party(): bool {
		return 'third_party' === $this->type;
	}

	/**
	 * Check if this is an Automator addon.
	 *
	 * @return bool
	 */
	public function is_automator_addon(): bool {
		return 'addon' === $this->type;
	}

	/**
	 * Check if this is a WP.org plugin.
	 *
	 * @return bool
	 */
	public function is_wp_org(): bool {
		return Distribution_Type::WP_ORG === $this->distribution_type;
	}

	/**
	 * Check if this is an open source plugin.
	 *
	 * @return bool
	 */
	public function is_open_source(): bool {
		return Distribution_Type::OPEN_SOURCE === $this->distribution_type;
	}

	/**
	 * Check if this is a commercial plugin.
	 *
	 * @return bool
	 */
	public function is_commercial(): bool {
		return Distribution_Type::COMMERCIAL === $this->distribution_type;
	}

	/**
	 * Check if plugin details are empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->plugin_file ) && empty( $this->plugin_required );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'type'                 => $this->type,
			'plugin_file'          => $this->plugin_file,
			'plugin_required'      => $this->plugin_required,
			'plugin_variations'    => $this->plugin_variations,
			'integration_required' => $this->integration_required,
			'distribution_type'    => $this->distribution_type,
		);
	}
}

<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use InvalidArgumentException;

/**
 * Integration Details Value Object.
 *
 * Contains static, marketing-oriented information about the integration.
 * This data is sourced from automatorplugin.com.
 *
 * @since 7.0.0
 */
class Integration_Details {

	/**
	 * The icon URL.
	 *
	 * @var string
	 */
	private string $icon;

	/**
	 * The description of the integration.
	 *
	 * @var string|null
	 */
	private ?string $description;

	/**
	 * The short description of the integration.
	 *
	 * @var string|null
	 */
	private ?string $short_description;

	/**
	 * The primary color of the integration.
	 *
	 * @var string
	 */
	private string $primary_color;

	/**
	 * The external URL of the integration.
	 *
	 * @var string
	 */
	private string $external_url;

	/**
	 * The categories of the integration.
	 *
	 * @var Integration_Categories
	 */
	private Integration_Categories $categories;

	/**
	 * The collections of the integration.
	 *
	 * @var Integration_Collections
	 */
	private Integration_Collections $collections;

	/**
	 * The tags of the integration.
	 *
	 * @var Integration_Tags
	 */
	private Integration_Tags $tags;

	/**
	 * The plugin details (WordPress plugin-specific).
	 *
	 * @var Integration_Plugin_Details|null
	 */
	private ?Integration_Plugin_Details $plugin = null;

	/**
	 * The developer details.
	 *
	 * @var Integration_Developer_Details
	 */
	private Integration_Developer_Details $developer;

	/**
	 * The account details (app integration-specific).
	 *
	 * @var Integration_Account_Details|null
	 */
	private ?Integration_Account_Details $account = null;

	/**
	 * Constructor.
	 *
	 * @param array $details Details array with keys: icon, description, short_description, primary_color, external_url, taxonomies.
	 *  @property string $icon The icon URL.
	 *  @property string|null $description The description of the integration.
	 *  @property string|null $short_description The short description of the integration.
	 *  @property string $primary_color The primary color of the integration.
	 *  @property string $external_url The external URL of the integration.
	 *  @property array $taxonomies The taxonomies of the integration.
	 *    @property Integration_Categories $categories The categories of the integration.
	 *    @property Integration_Collections $collections The collections of the integration.
	 *    @property Integration_Tags $tags The tags of the integration.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid details.
	 */
	public function __construct( array $details ) {
		$this->validate( $details );

		$this->icon              = $details['icon'] ?? '';
		$this->description       = ! empty( $details['description'] ) ? $details['description'] : null;
		$this->short_description = ! empty( $details['short_description'] ) ? $details['short_description'] : null;
		$this->primary_color     = $details['primary_color'] ?? '';
		$this->external_url      = $details['external_url'] ?? '';

		// Initialize taxonomy value objects.
		$taxonomies        = $details['taxonomies'] ?? array();
		$this->categories  = new Integration_Categories( $taxonomies['categories'] ?? array() );
		$this->collections = new Integration_Collections( $taxonomies['collections'] ?? array() );
		$this->tags        = new Integration_Tags( $taxonomies['tags'] ?? array() );

		// Initialize developer value object.
		$this->developer = new Integration_Developer_Details( $details['developer'] ?? array() );

		// Initialize plugin value object only if plugin data exists (plugin integrations only).
		if ( isset( $details['plugin'] ) && ! empty( $details['plugin'] ) ) {
			$this->plugin = new Integration_Plugin_Details( $details['plugin'] );
		}

		// Initialize account value object only if account data exists (app integrations only).
		if ( isset( $details['account'] ) && ! empty( $details['account'] ) ) {
			$this->account = new Integration_Account_Details( $details['account'] );
		}
	}

	/**
	 * Get icon URL.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return $this->icon;
	}

	/**
	 * Get description.
	 *
	 * @return string|null
	 */
	public function get_description(): ?string {
		return $this->description;
	}

	/**
	 * Get short description.
	 *
	 * @return string|null
	 */
	public function get_short_description(): ?string {
		return $this->short_description;
	}

	/**
	 * Get primary color.
	 *
	 * @return string
	 */
	public function get_primary_color(): string {
		return $this->primary_color;
	}

	/**
	 * Get external URL.
	 *
	 * @return string
	 */
	public function get_external_url(): string {
		return $this->external_url;
	}

	/**
	 * Get categories.
	 *
	 * @return Integration_Categories
	 */
	public function get_categories(): Integration_Categories {
		return $this->categories;
	}

	/**
	 * Get collections.
	 *
	 * @return Integration_Collections
	 */
	public function get_collections(): Integration_Collections {
		return $this->collections;
	}

	/**
	 * Get tags.
	 *
	 * @return Integration_Tags
	 */
	public function get_tags(): Integration_Tags {
		return $this->tags;
	}

	/**
	 * Get plugin details (WordPress plugin-specific).
	 *
	 * @return Integration_Plugin_Details|null Null for non-plugin integrations
	 */
	public function get_plugin(): ?Integration_Plugin_Details {
		return $this->plugin;
	}

	/**
	 * Get developer details.
	 *
	 * @return Integration_Developer_Details
	 */
	public function get_developer(): Integration_Developer_Details {
		return $this->developer;
	}

	/**
	 * Get account details (app integration-specific).
	 *
	 * @return Integration_Account_Details|null Null for non-app integrations
	 */
	public function get_account(): ?Integration_Account_Details {
		return $this->account;
	}

	/**
	 * Get taxonomies as array.
	 *
	 * @return array
	 */
	public function get_taxonomies(): array {
		return array(
			'categories'  => $this->categories->get_value(),
			'collections' => $this->collections->get_value(),
			'tags'        => $this->tags->get_value(),
		);
	}

	/**
	 * Convert to array (includes plugin, developer, and account for internal use).
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'icon'              => $this->icon,
			'description'       => $this->description,
			'short_description' => $this->short_description,
			'primary_color'     => $this->primary_color,
			'external_url'      => $this->external_url,
			'taxonomies'        => $this->get_taxonomies(),
			'plugin'            => $this->plugin ? $this->plugin->to_array() : null,
			'developer'         => $this->developer->to_array(),
			'account'           => $this->account ? $this->account->to_array() : null,
		);
	}

	/**
	 * Convert to REST format (excludes plugin and developer for clean API response).
	 *
	 * @return array
	 */
	public function to_rest(): array {
		return array(
			'icon'              => $this->icon,
			'description'       => $this->description,
			'short_description' => $this->short_description,
			'primary_color'     => $this->primary_color,
			'external_url'      => $this->external_url,
			'taxonomies'        => $this->get_taxonomies(),
		);
	}

	/**
	 * Validate details.
	 *
	 * @param array $details Details to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( array $details ): void {

		// Validate icon
		if ( empty( $details['icon'] ) ) {
			throw new InvalidArgumentException( 'Integration icon is required' );
		}

		if ( ! filter_var( $details['icon'], FILTER_VALIDATE_URL ) ) {
			throw new InvalidArgumentException( 'Integration icon must be a valid URL' );
		}

		// Validate description (allow empty, will be converted to null)
		if ( isset( $details['description'] ) && ! is_string( $details['description'] ) ) {
			throw new InvalidArgumentException( 'Integration description must be a string' );
		}

		// Validate short description (allow empty, will be converted to null)
		if ( isset( $details['short_description'] ) && ! is_string( $details['short_description'] ) ) {
			throw new InvalidArgumentException( 'Integration short description must be a string' );
		}

		// Validate primary color
		if ( ! isset( $details['primary_color'] ) ) {
			throw new InvalidArgumentException( 'Integration primary color is required' );
		}

		if ( ! is_string( $details['primary_color'] ) ) {
			throw new InvalidArgumentException( 'Integration primary color must be a string' );
		}

		// Validate hex color format if provided
		if ( ! empty( $details['primary_color'] ) && ! preg_match( '/^#[0-9a-fA-F]{6}$/', $details['primary_color'] ) ) {
			throw new InvalidArgumentException( 'Integration primary color must be a valid hex color (e.g., #004cff)' );
		}

		// Validate external URL
		if ( empty( $details['external_url'] ) ) {
			throw new InvalidArgumentException( 'Integration external URL is required' );
		}

		if ( ! filter_var( $details['external_url'], FILTER_VALIDATE_URL ) ) {
			throw new InvalidArgumentException( 'Integration external URL must be a valid URL' );
		}

		// Validate taxonomies structure
		if ( isset( $details['taxonomies'] ) && ! is_array( $details['taxonomies'] ) ) {
			throw new InvalidArgumentException( 'Integration taxonomies must be an array' );
		}
	}
}

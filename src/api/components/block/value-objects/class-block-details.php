<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\Api\Components\Block\Block_Colors;

/**
 * Block Details Value Object.
 *
 * Contains block metadata including icon, color, descriptions, and taxonomies.
 *
 * @since 7.0.0
 */
class Block_Details {

	/**
	 * Icon.
	 *
	 * @var string
	 */
	private string $icon;

	/**
	 * Primary color.
	 *
	 * @var string
	 */
	private string $primary_color;

	/**
	 * Description.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * Short description.
	 *
	 * @var string
	 */
	private string $short_description;

	/**
	 * Taxonomies.
	 *
	 * @var Block_Taxonomies
	 */
	private Block_Taxonomies $taxonomies;

	/**
	 * External URL.
	 *
	 * @var string
	 */
	private string $external_url;

	/**
	 * Constructor.
	 *
	 * @param array $data Details data
	 *   @property string $icon Icon URL.
	 *   @property string $primary_color Primary color.
	 *   @property string $description Full description.
	 *   @property string $short_description Short description.
	 *   @property Block_Taxonomies|array $taxonomies Taxonomy classifications.
	 *   @property string $external_url External URL.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid data
	 */
	public function __construct( array $data ) {
		$this->validate( $data );

		$this->icon              = $data['icon'];
		$this->primary_color     = $data['primary_color'];
		$this->description       = $data['description'];
		$this->short_description = $data['short_description'];
		$this->external_url      = $data['external_url'];

		if ( $data['taxonomies'] instanceof Block_Taxonomies ) {
			$this->taxonomies = $data['taxonomies'];
		} else {
			$this->taxonomies = new Block_Taxonomies( $data['taxonomies'] ?? array() );
		}
	}

	/**
	 * Get icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return $this->icon;
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
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Get short description.
	 *
	 * @return string
	 */
	public function get_short_description(): string {
		return $this->short_description;
	}

	/**
	 * Get taxonomies.
	 *
	 * @return Block_Taxonomies
	 */
	public function get_taxonomies(): Block_Taxonomies {
		return $this->taxonomies;
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
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'icon'              => $this->icon,
			'primary_color'     => $this->primary_color,
			'description'       => $this->description,
			'short_description' => $this->short_description,
			'taxonomies'        => $this->taxonomies->to_array(),
			'external_url'      => $this->external_url,
		);
	}

	/**
	 * Validate details data.
	 *
	 * @param array $data Details data to validate
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid
	 */
	private function validate( array $data ): void {
		if ( empty( $data['icon'] ) || ! is_string( $data['icon'] ) ) {
			throw new InvalidArgumentException( 'Icon must be a non-empty string' );
		}

		if ( empty( $data['primary_color'] ) || ! Block_Colors::is_valid( $data['primary_color'] ) ) {
			throw new InvalidArgumentException( 'Primary color must be one of: ' . implode( ', ', Block_Colors::get_all() ) );
		}

		if ( empty( $data['description'] ) || ! is_string( $data['description'] ) ) {
			throw new InvalidArgumentException( 'Description must be a non-empty string' );
		}

		if ( empty( $data['short_description'] ) || ! is_string( $data['short_description'] ) ) {
			throw new InvalidArgumentException( 'Short description must be a non-empty string' );
		}

		if ( empty( $data['external_url'] ) || ! is_string( $data['external_url'] ) ) {
			throw new InvalidArgumentException( 'External URL must be a non-empty string' );
		}
	}
}

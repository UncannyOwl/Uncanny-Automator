<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block\Value_Objects;

use InvalidArgumentException;

/**
 * Block Taxonomies Value Object.
 *
 * Taxonomy classifications for organization and discovery.
 *
 * @since 7.0.0
 */
class Block_Taxonomies {

	/**
	 * Categories.
	 *
	 * @var array
	 */
	private array $categories;

	/**
	 * Collections.
	 *
	 * @var array
	 */
	private array $collections;

	/**
	 * Tags.
	 *
	 * @var array
	 */
	private array $tags;

	/**
	 * Maximum tags allowed.
	 *
	 * @var int
	 */
	private int $max_tags = 5;

	/**
	 * Constructor.
	 *
	 * @param array $data Taxonomy data
	 *   @property array $categories Category slugs.
	 *   @property array $collections Collection slugs.
	 *   @property array $tags Keyword tags (max 5).
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid data
	 */
	public function __construct( array $data ) {
		$this->validate( $data );

		$this->categories  = $data['categories'] ?? array();
		$this->collections = $data['collections'] ?? array();
		$this->tags        = $data['tags'] ?? array();
	}

	/**
	 * Get categories.
	 *
	 * @return array
	 */
	public function get_categories(): array {
		return $this->categories;
	}

	/**
	 * Get collections.
	 *
	 * @return array
	 */
	public function get_collections(): array {
		return $this->collections;
	}

	/**
	 * Get tags.
	 *
	 * @return array
	 */
	public function get_tags(): array {
		return $this->tags;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'categories'  => $this->categories,
			'collections' => $this->collections,
			'tags'        => $this->tags,
		);
	}

	/**
	 * Validate taxonomy data.
	 *
	 * @param array $data Taxonomy data to validate
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid
	 */
	private function validate( array $data ): void {
		if ( isset( $data['categories'] ) && ! is_array( $data['categories'] ) ) {
			throw new InvalidArgumentException( 'Categories must be an array' );
		}

		if ( isset( $data['collections'] ) && ! is_array( $data['collections'] ) ) {
			throw new InvalidArgumentException( 'Collections must be an array' );
		}

		if ( isset( $data['tags'] ) ) {
			if ( ! is_array( $data['tags'] ) ) {
				throw new InvalidArgumentException( 'Tags must be an array' );
			}

			if ( count( $data['tags'] ) > $this->max_tags ) {
				throw new InvalidArgumentException( sprintf( 'Tags array cannot exceed %d items', $this->max_tags ) );
			}
		}
	}
}

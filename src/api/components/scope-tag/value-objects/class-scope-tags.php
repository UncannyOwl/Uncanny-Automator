<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Scope_Tag\Value_Objects;

use Uncanny_Automator\Api\Components\Scope_Tag\Scope_Tag;
use Uncanny_Automator\Api\Components\Scope_Tag\Scope_Tag_Config;

/**
 * Scope Tags Value Object.
 *
 * Contains the scope tags ( e.g. License, Availability, Dependency ).
 * Tags are stored as Scope_Tag objects.
 *
 * @since 7.0.0
 */
class Scope_Tags {

	/**
	 * The tags.
	 *
	 * @var array<int, Scope_Tag>
	 */
	private array $tags;

	/**
	 * Constructor.
	 *
	 * @param array $tags Tags array with tag data (arrays, Scope_Tag_Config, or Scope_Tag objects).
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid tags.
	 */
	public function __construct( array $tags = array() ) {
		$this->validate( $tags );
		$this->tags = $this->build_tag_objects( $tags );
	}

	/**
	 * Get tags.
	 *
	 * @return array<int, Scope_Tag> Array of Scope_Tag objects.
	 */
	public function get_tags(): array {
		return $this->tags;
	}

	/**
	 * Get value.
	 *
	 * @return array Array of tag data.
	 */
	public function get_value(): array {
		return $this->to_array();
	}

	/**
	 * Check if has scope tags.
	 *
	 * @return bool
	 */
	public function has_tags(): bool {
		return ! empty( $this->tags );
	}

	/**
	 * Convert to array.
	 *
	 * @return array Array of tag data.
	 */
	public function to_array(): array {
		return $this->tags_to_array( $this->tags );
	}

	/**
	 * Validate tags.
	 *
	 * Note: Array type is enforced by constructor parameter type hint.
	 *
	 * @param array $tags Tags to validate.
	 *
	 * @return void
	 */
	private function validate( array $tags ): void {
		// Array type is enforced by constructor signature.
		// Keeping method for potential future validation rules (e.g., max count, uniqueness).
	}

	/**
	 * Build Scope_Tag objects from array data.
	 *
	 * @param array $tags Array of tag data (arrays, Scope_Tag_Config, or Scope_Tag objects).
	 *
	 * @return array<int, Scope_Tag> Array of Scope_Tag objects.
	 */
	private function build_tag_objects( array $tags ): array {
		$objects = array();

		foreach ( $tags as $tag_data ) {
			// If already a Scope_Tag object, use it directly.
			if ( $tag_data instanceof Scope_Tag ) {
				$objects[] = $tag_data;
				continue;
			}

			// If it's a Scope_Tag_Config, create Scope_Tag from it.
			if ( $tag_data instanceof Scope_Tag_Config ) {
				$objects[] = new Scope_Tag( $tag_data );
				continue;
			}

			// Otherwise, create config from array data, then create Scope_Tag.
			if ( ! is_array( $tag_data ) ) {
				continue;
			}

			$config    = Scope_Tag_Config::from_array( $tag_data );
			$objects[] = new Scope_Tag( $config );
		}

		return $objects;
	}

	/**
	 * Convert array of Scope_Tag objects to arrays.
	 *
	 * @param array<int, Scope_Tag> $tags Array of Scope_Tag objects.
	 *
	 * @return array Array of tag data.
	 */
	private function tags_to_array( array $tags ): array {
		$arrays = array();

		foreach ( $tags as $tag ) {
			$arrays[] = $tag->to_array();
		}

		return $arrays;
	}
}

<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use InvalidArgumentException;

/**
 * Integration Tags Value Object.
 *
 * Represents general keywords for the integration (e.g., "woocommerce", "ecommerce").
 * Maximum 5 tags allowed. Default: empty array
 *
 * Note: These are different from dynamic, scoped tags. These are keyword provided for the integration.
 *
 * @since 7.0.0
 */
class Integration_Tags {

	/**
	 * The tags value.
	 *
	 * @var array
	 */
	private array $value;

	/**
	 * The maximum number of tags allowed.
	 *
	 * @var int
	 */
	private const MAX_TAGS = 5;

	/**
	 * Constructor.
	 *
	 * @param array $value Tags array.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid tags.
	 */
	public function __construct( array $value = array() ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return array
	 */
	public function get_value(): array {
		return $this->value;
	}

	/**
	 * Check if integration has a specific tag.
	 *
	 * @param string $tag Tag to check.
	 * @return bool
	 */
	public function has_tag( string $tag ): bool {
		return in_array( $tag, $this->value, true );
	}

	/**
	 * Get tags count.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->value );
	}

	/**
	 * Check if max tags limit is reached.
	 *
	 * @return bool
	 */
	public function is_at_max(): bool {
		return $this->count() >= self::MAX_TAGS;
	}

	/**
	 * Validate tags.
	 *
	 * @param array $value Value to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( array $value ): void {
		// Tags should be an array of strings.
		foreach ( $value as $tag ) {
			if ( ! is_string( $tag ) ) {
				throw new InvalidArgumentException( 'All tags must be strings' );
			}
		}

		// Maximum 5 tags.
		if ( count( $value ) > self::MAX_TAGS ) {
			throw new InvalidArgumentException(
				'Maximum ' . self::MAX_TAGS . ' tags allowed, got: ' . count( $value )
			);
		}
	}
}

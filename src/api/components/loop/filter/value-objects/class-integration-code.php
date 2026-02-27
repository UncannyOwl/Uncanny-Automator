<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects;

/**
 * Integration_Code Value Object.
 *
 * Represents the integration code for a filter (e.g., 'WP', 'WOOCOMMERCE').
 * Must be a non-empty uppercase string.
 *
 * @since 7.0.0
 */
class Integration_Code {

	/**
	 * The validated integration code value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Integration code value.
	 * @throws \InvalidArgumentException If invalid code.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = strtoupper( trim( $value ) );
	}

	/**
	 * Get value.
	 *
	 * @return string
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * String representation.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->value;
	}

	/**
	 * Check equality with another Integration_Code.
	 *
	 * @param Integration_Code $other Other value object to compare.
	 * @return bool
	 */
	public function equals( Integration_Code $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * Validate integration code.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( trim( $value ) ) ) {
			throw new \InvalidArgumentException( 'Integration_Code cannot be empty' );
		}
	}
}

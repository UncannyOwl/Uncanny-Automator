<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use InvalidArgumentException;

/**
 * Integration Name Value Object.
 *
 * Represents the human-readable display name for the integration.
 * Examples: "WooCommerce", "LearnDash", "Mailchimp"
 *
 * @since 7.0.0
 */
class Integration_Name {

	/**
	 * The integration name value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Integration name value.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid name.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = $value;
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
	 * Validate integration name.
	 *
	 * @param string $value Value to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( trim( $value ) ) ) {
			throw new InvalidArgumentException( 'Integration name cannot be empty' );
		}
	}
}

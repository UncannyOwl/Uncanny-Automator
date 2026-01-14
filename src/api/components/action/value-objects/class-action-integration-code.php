<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Action Integration Code Value Object.
 *
 * Represents the integration identifier (e.g., 'WP', 'MAILCHIMP', 'ZAPIER').
 * Must be non-empty string.
 *
 * @since 7.0.0
 */
class Action_Integration_Code {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Integration code value.
	 * @throws \InvalidArgumentException If invalid code.
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
	 * Check if this is a WordPress native integration.
	 *
	 * @return bool
	 */
	public function is_wordpress_native(): bool {
		return 'WP' === strtoupper( $this->value );
	}

	/**
	 * Check if this is an API integration.
	 *
	 * @return bool
	 */
	public function is_api_integration(): bool {
		return ! $this->is_wordpress_native();
	}

	/**
	 * Validate integration code.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( trim( $value ) ) ) {
			throw new \InvalidArgumentException( 'Action integration code cannot be empty' );
		}
	}
}

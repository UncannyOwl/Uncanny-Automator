<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use InvalidArgumentException;

/**
 * Integration Type Value Object.
 *
 * Represents the integration type - must be 'plugin', 'app', or 'built-in'.
 *
 * - plugin: Depends on another WordPress plugin (e.g., WooCommerce, LearnDash)
 * - app: Calls external API, consumes credits (e.g., Google Sheets, Slack)
 * - built-in: Bundled utility, no external requests (e.g., WordPress Core, Email)
 *
 * @since 7.0.0
 */
class Integration_Type {

	/**
	 * The integration type value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Integration type value.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid type.
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
	 * Check if integration is a plugin integration.
	 *
	 * @return bool
	 */
	public function is_plugin(): bool {
		return 'plugin' === $this->value;
	}

	/**
	 * Check if integration is an app integration.
	 *
	 * @return bool
	 */
	public function is_app(): bool {
		return 'app' === $this->value;
	}

	/**
	 * Check if integration is a built-in integration.
	 *
	 * @return bool
	 */
	public function is_built_in(): bool {
		return 'built-in' === $this->value;
	}

	/**
	 * Validate integration type.
	 *
	 * @param string $value Value to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		$valid_types = array( 'plugin', 'app', 'built-in' );

		if ( ! in_array( $value, $valid_types, true ) ) {
			throw new InvalidArgumentException(
				'Integration type must be "plugin", "app", or "built-in", got: ' . $value
			);
		}
	}
}

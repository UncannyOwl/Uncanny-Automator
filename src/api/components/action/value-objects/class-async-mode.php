<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Async Mode Value Object.
 *
 * Represents async execution mode - can be 'delay', 'schedule', or 'custom'.
 * Nullable for instant/immediate execution.
 *
 * @since 7.0.0
 */
class Async_Mode {

	private ?string $value;

	/**
	 * Constructor.
	 *
	 * @param string|null $value Async mode value.
	 * @throws \InvalidArgumentException If invalid mode.
	 */
	public function __construct( ?string $value ) {
		if ( null !== $value ) {
			$this->validate( $value );
		}
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return string|null
	 */
	public function get_value(): ?string {
		return $this->value;
	}

	/**
	 * Check if instant execution (no async).
	 *
	 * @return bool
	 */
	public function is_instant(): bool {
		return null === $this->value;
	}

	/**
	 * Check if delay mode.
	 *
	 * @return bool
	 */
	public function is_delay(): bool {
		return 'delay' === $this->value;
	}

	/**
	 * Check if schedule mode.
	 *
	 * @return bool
	 */
	public function is_schedule(): bool {
		return 'schedule' === $this->value;
	}

	/**
	 * Check if custom mode.
	 *
	 * @return bool
	 */
	public function is_custom(): bool {
		return 'custom' === $this->value;
	}

	/**
	 * Validate async mode.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		$valid_modes = array( 'delay', 'schedule', 'custom' );

		if ( ! in_array( $value, $valid_modes, true ) ) {
			throw new \InvalidArgumentException(
				'Async mode must be "delay", "schedule", or "custom", got: ' . $value
			);
		}
	}
}

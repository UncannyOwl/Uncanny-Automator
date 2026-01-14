<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger\Value_Objects;

use Uncanny_Automator\Api\Components\Trigger\Enums\Trigger_Status;

/**
 * Trigger Status Value Object.
 *
 * Represents the status of a trigger (draft or published).
 * Validates against the Trigger_Status enum constants.
 *
 * @since 7.0.0
 */
class Trigger_Status_Value {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Status value ('draft' or 'publish').
	 * @throws \InvalidArgumentException If status is invalid.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return string Status value.
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Check if trigger is draft.
	 *
	 * @return bool True if draft.
	 */
	public function is_draft(): bool {
		return Trigger_Status::DRAFT === $this->value;
	}

	/**
	 * Check if trigger is published.
	 *
	 * @return bool True if published.
	 */
	public function is_published(): bool {
		return Trigger_Status::PUBLISH === $this->value;
	}

	/**
	 * Validate status value.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( ! Trigger_Status::is_valid( $value ) ) {
			throw new \InvalidArgumentException(
				'Trigger status must be "' . Trigger_Status::DRAFT . '" or "' . Trigger_Status::PUBLISH . '", got: ' . $value
			);
		}
	}
}

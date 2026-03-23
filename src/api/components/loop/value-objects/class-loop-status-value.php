<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Value_Objects;

use Uncanny_Automator\Api\Components\Loop\Enums\Loop_Status;

/**
 * Loop Status Value Object.
 *
 * Represents the status of a loop (draft or published).
 * Validates against the Loop_Status enum constants.
 *
 * @since 7.0.0
 */
class Loop_Status_Value {

	/**
	 * The status value.
	 *
	 * @var string
	 */
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
	 * Check if loop is draft.
	 *
	 * @return bool True if draft.
	 */
	public function is_draft(): bool {
		return Loop_Status::DRAFT === $this->value;
	}

	/**
	 * Check if loop is published.
	 *
	 * @return bool True if published.
	 */
	public function is_published(): bool {
		return Loop_Status::PUBLISH === $this->value;
	}

	/**
	 * Validate status value.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( ! Loop_Status::is_valid( $value ) ) {
			throw new \InvalidArgumentException(
				'Invalid loop status: ' . $value . '. Must be "' . Loop_Status::DRAFT . '" or "' . Loop_Status::PUBLISH . '"'
			);
		}
	}
}

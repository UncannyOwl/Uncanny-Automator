<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Field\Value_Objects;

/**
 * Field Readable Value Object.
 *
 * Represents the human-readable label for a field value.
 * Optional string value.
 *
 * @since 7.0
 */
class Field_Readable {

	/**
	 * Readable value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Readable value.
	 *
	 * @return void
	 */
	public function __construct( string $value = '' ) {
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
	 * Check if has readable value. Only an empty string has none, so `0` is a
	 * readable value.
	 *
	 * @since 8.0.0 `0` is a readable value.
	 *
	 * @return bool
	 */
	public function has_value(): bool {
		return '' !== $this->value;
	}
}

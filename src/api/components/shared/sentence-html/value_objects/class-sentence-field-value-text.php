<?php
/**
 * Sentence Field Value Text Value Object.
 *
 * Represents a single field's selected value for sentence rendering.
 *
 * Design decisions:
 * - Stores `code`, `value`, `text`, and `is_filled` as simple scalars to keep the value object
 *   small and serialization-friendly.
 * - get_value() returns an array keyed by `code`:
 *       [code => ['value' => ..., 'text' => ..., 'is_filled' => ...]]
 *   This matches the existing `$fields` input shape used by the
 *   Sentence_Human_Readable_Context, so multiple value objects can be merged directly
 *   into `$fields` without an extra foreach/transform step.
 * - The `is_filled` flag determines if the field should render with the --filled CSS class
 *   in HTML output. Fields with "Any" or default selections should set this to false.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects;

/**
 * Class Sentence_Field_Value_Text
 *
 * Value object representing a field's value and human-readable text.
 */
class Sentence_Field_Value_Text {

	/**
	 * Field code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Raw stored value (ID, count, etc.).
	 *
	 * @var string|int
	 */
	private $value;

	/**
	 * Human-readable text.
	 *
	 * @var string
	 */
	private string $text;

	/**
	 * Whether the field is considered "filled" (explicit user selection).
	 *
	 * When true, the HTML output includes the --filled CSS modifier class.
	 * Set to false for "Any" or default selections.
	 *
	 * @var bool
	 */
	private bool $is_filled;

	/**
	 * Constructor.
	 *
	 * @param string     $code      The code of the field (e.g., 'WOOPRODUCT').
	 * @param string|int $value     The value of the field (e.g., 516).
	 * @param string     $text      The human-readable text of the field (e.g., 'ASUS TUF A16...').
	 * @param bool       $is_filled Whether the field has an explicit user selection. Default true.
	 */
	public function __construct( string $code, $value, string $text, bool $is_filled = true ) {
		$this->code      = $code;
		$this->value     = $value;
		$this->text      = $text;
		$this->is_filled = $is_filled;
	}

	/**
	 * Get the value as an array.
	 *
	 * Returns structure compatible with the $fields input array.
	 *
	 * @return array {
	 *     Field value data keyed by field code.
	 *
	 *     @type array $code {
	 *         Field data for the given code.
	 *
	 *         @type mixed  $value     The raw field value.
	 *         @type string $text      The human-readable text.
	 *         @type bool   $is_filled Whether the field is filled.
	 *     }
	 * }
	 */
	public function get_value(): array {
		return array(
			$this->code => array(
				'value'     => $this->value,
				'text'      => $this->text,
				'is_filled' => $this->is_filled,
			),
		);
	}
}

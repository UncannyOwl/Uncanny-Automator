<?php
/**
 * Sentence Field Label Value Object.
 *
 * Represents a field label with its code and human-readable label.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects;

/**
 * Class Sentence_Field_Label
 *
 * Value object representing a field label.
 */
class Sentence_Field_Label {

	/**
	 * Field code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Human-readable field label.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Constructor.
	 *
	 * @param string $code  The field code (e.g., 'NUMTIMES').
	 * @param string $label The human-readable label (e.g., 'Number of Times').
	 */
	public function __construct( string $code, string $label ) {
		$this->code  = $code;
		$this->label = $label;
	}

	/**
	 * Get the value as an array.
	 *
	 * Returns a stable serializable representation of the field label.
	 *
	 * @return array {
	 *     Field label data.
	 *
	 *     @type string $code  The field code.
	 *     @type string $label The human-readable label.
	 * }
	 */
	public function get_value(): array {
		return array(
			'code'  => $this->code,
			'label' => $this->label,
		);
	}
}

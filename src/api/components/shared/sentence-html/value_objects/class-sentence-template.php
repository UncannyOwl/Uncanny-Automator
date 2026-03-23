<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects;

/**
 * Sentence Template Value Object.
 *
 * Represents a raw Automator sentence template containing literal text and
 * token placeholders in the form {{decorator:option_code}}.
 *
 * Immutable by design. Stores the template exactly as provided without
 * normalization or mutation. Interpretation/parsing is delegated to
 * downstream services/contexts.
 *
 * @since 7.0.0
 */
class Sentence_Template {

	/**
	 * The exact sentence template string as provided.
	 *
	 * @var string
	 */
	private string $sentence_template;

	/**
	 * Constructor.
	 *
	 * @param string $sentence_template Raw sentence template.
	 *                                  The template MAY contain token placeholders of the form:
	 *                                  {{decorator:option_code}}
	 *                                  No validation is performed here; this value object only stores
	 *                                  the literal template.
	 */
	public function __construct( string $sentence_template ) {
		$this->sentence_template = $sentence_template;
	}

	/**
	 * Get the raw template string.
	 *
	 * @return string The raw template string.
	 */
	public function get_value(): string {
		return $this->sentence_template;
	}
}

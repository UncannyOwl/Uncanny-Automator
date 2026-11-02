<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects;

/**
 * One reason a publish/on transition was (or would be) refused: an
 * open-vocabulary `code`, a localized `message` (limited Markdown:
 * strong / em / links), and a `target` — null for the recipe itself,
 * else the offending flow node.
 *
 * `to_wire()` is the 422 rejection-list shape (target key always
 * present); `to_annotation()` is the payload-annotation shape (no
 * target key — the node it sits on IS the target).
 */
final class Recipe_Violation {

	/**
	 * Open-vocabulary rule identifier (lowercase snake_case).
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Localized human-readable reason.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * The targeted flow node; null targets the recipe itself.
	 *
	 * @var Recipe_Violation_Target|null
	 */
	private ?Recipe_Violation_Target $target;

	/**
	 * Wrap and validate the violation.
	 *
	 * @param string                       $code    Rule identifier.
	 * @param string                       $message Localized reason.
	 * @param Recipe_Violation_Target|null $target  Targeted node, or null for the recipe.
	 * @throws \InvalidArgumentException On an empty code or message.
	 */
	public function __construct( string $code, string $message, ?Recipe_Violation_Target $target = null ) {
		if ( '' === $code ) {
			throw new \InvalidArgumentException( 'Violation code must not be empty.' );
		}
		if ( '' === $message ) {
			throw new \InvalidArgumentException( 'Violation message must not be empty.' );
		}
		$this->code    = $code;
		$this->message = $message;
		$this->target  = $target;
	}

	/**
	 * Rejection-list wire shape: `{ code, message, target }` — exactly
	 * these three keys (the FE payload schema is strict).
	 *
	 * @return array{code: string, message: string, target: array|null}
	 */
	public function to_wire(): array {
		return array(
			'code'    => $this->code,
			'message' => $this->message,
			'target'  => null === $this->target ? null : $this->target->to_wire(),
		);
	}

	/**
	 * Payload-annotation shape: `{ code, message }` — no target key
	 * (the node annotated IS the target; the FE node schema is strict).
	 *
	 * @return array{code: string, message: string}
	 */
	public function to_annotation(): array {
		return array(
			'code'    => $this->code,
			'message' => $this->message,
		);
	}
}

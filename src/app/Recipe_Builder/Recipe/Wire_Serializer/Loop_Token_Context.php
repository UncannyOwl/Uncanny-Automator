<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

/**
 * Frozen payload handed to the `automator_recipe_main_object_loop_tokens_items`
 * listeners at projection time.
 *
 * The seam contract is duck-typed: every live listener (Free loopable classes,
 * Pro loops/token/*) receives `$loop` untyped and reads only `get('id')` and
 * `get('iterable_expression')`. This class exposes exactly that surface with
 * zero legacy dependencies — the legacy fire site passes its own Structure
 * loop object, which satisfies the same contract via Trait_Setter_Getter.
 */
final class Loop_Token_Context {

	/**
	 * Loop block post id.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Reconstructed iterable_expression (storage shape; token-loop `fields`
	 * pre-encoded to the JSON string the listeners json_decode).
	 *
	 * @var array<string,mixed>
	 */
	private array $iterable_expression;

	/**
	 * Freeze the context.
	 *
	 * @param int                 $id                  Loop block post id.
	 * @param array<string,mixed> $iterable_expression Reconstructed iterable expression.
	 */
	public function __construct( int $id, array $iterable_expression ) {
		$this->id                  = $id;
		$this->iterable_expression = $iterable_expression;
	}

	/**
	 * Property accessor matching the legacy loop object's Trait_Setter_Getter
	 * surface the listeners consume.
	 *
	 * @param string $prop 'id' or 'iterable_expression'.
	 * @return mixed Null for any other property.
	 */
	public function get( string $prop ) {
		if ( 'id' === $prop ) {
			return $this->id;
		}
		if ( 'iterable_expression' === $prop ) {
			return $this->iterable_expression;
		}
		return null;
	}
}

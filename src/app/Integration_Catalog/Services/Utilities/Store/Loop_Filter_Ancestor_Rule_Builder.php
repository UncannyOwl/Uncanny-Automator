<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store;

use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Ancestor_Rule;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks\Loop_Block;
use Uncanny_Automator\App\Recipe_Builder\Loop\Iterable_Expression\Enums\Iteration_Type;

/**
 * Turns a loop filter's iteration types into the ancestor rules the builder matches on.
 *
 * Turns a filter's iteration types into ancestor rules — one required rule
 * per iteration type (OR semantics on the frontend). Single owner of the
 * loop-block ancestor literals (`Block_Type::LOOP`, `Loop_Block::FIELD_LOOP_TYPE`).
 */
class Loop_Filter_Ancestor_Rule_Builder {

	/**
	 * Build one ancestor rule per iteration type, in input order.
	 *
	 * @param string[] $iteration_types e.g. ['users'] or ['users','posts'].
	 *
	 * @return array<int,array> Wire-shaped AncestorRule[]; empty when no usable types.
	 */
	public function build( array $iteration_types ): array {
		$rules = array();
		foreach ( $iteration_types as $type ) {
			if ( ! is_string( $type ) || '' === $type ) {
				continue;
			}
			$rule    = new Ancestor_Rule(
				array(
					'entity_type' => 'block',
					'entity_code' => Block_Type::LOOP,
					'when'        => array(
						array(
							'field'  => Loop_Block::FIELD_LOOP_TYPE,
							'equals' => $type,
						),
					),
					'reason'      => $this->reason_for( $type ),
				)
			);
			$rules[] = $rule->to_array();
		}
		return $rules;
	}

	/**
	 * Human-readable reason for a given iteration type, shown disabled-with-reason
	 * in the picker.
	 *
	 * @param string $type Iteration type (users/posts/token, or an unknown future value).
	 *
	 * @return string Translated reason sentence.
	 */
	private function reason_for( string $type ): string {
		switch ( $type ) {
			case Iteration_Type::POSTS:
				return esc_html_x( 'This filter applies when the loop iterates over posts.', 'Loop filter ancestor rule', 'uncanny-automator' );
			case Iteration_Type::TOKEN:
				return esc_html_x( 'This filter applies when the loop iterates over a token.', 'Loop filter ancestor rule', 'uncanny-automator' );
			case Iteration_Type::USERS:
				return esc_html_x( 'This filter applies when the loop iterates over users.', 'Loop filter ancestor rule', 'uncanny-automator' );
			default:
				return esc_html_x( 'This filter applies for the selected loop type.', 'Loop filter ancestor rule', 'uncanny-automator' );
		}
	}
}

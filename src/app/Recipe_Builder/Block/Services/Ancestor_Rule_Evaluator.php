<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Services;

/**
 * Evaluates a candidate's field-aware ancestor rules against an ancestor chain.
 *
 * Backend mirror of the frontend `getEntityAncestorRuleViolation`: any matching
 * `unsupported_block_ancestors` rule rejects; otherwise an empty
 * `required_block_ancestors` allows, and a non-empty one requires at least one
 * match. Returns a clear/keep decision (bool) rather than a reason row — the
 * backend only needs whether to clear.
 */
class Ancestor_Rule_Evaluator {

	/**
	 * Whether the candidate is rejected by the chain (i.e. should be cleared).
	 *
	 * @param array<int,array{block_type:string,fields:array<string,array<string,mixed>>}> $chain     Ancestor blocks (each with block_type + wire-shape fields).
	 * @param array{required_block_ancestors?:array,unsupported_block_ancestors?:array}     $candidate The entity's rule arrays.
	 *
	 * @return bool True when the chain violates the candidate's rules.
	 */
	public function is_violation( array $chain, array $candidate ): bool {
		foreach ( $candidate['unsupported_block_ancestors'] ?? array() as $rule ) {
			if ( $this->rule_matches_chain( $rule, $chain ) ) {
				return true;
			}
		}

		$required = $candidate['required_block_ancestors'] ?? array();
		if ( empty( $required ) ) {
			return false;
		}

		foreach ( $required as $rule ) {
			if ( $this->rule_matches_chain( $rule, $chain ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Existential match: some chain entry's block_type equals the rule's
	 * entity_code and that entry's fields satisfy every `when` row.
	 *
	 * @param array $rule  Ancestor rule (entity_code + optional when[]).
	 * @param array $chain Ancestor chain.
	 *
	 * @return bool
	 */
	private function rule_matches_chain( array $rule, array $chain ): bool {
		$entity_code = $rule['entity_code'] ?? '';
		if ( '' === $entity_code ) {
			return false;
		}
		foreach ( $chain as $entry ) {
			if ( ( $entry['block_type'] ?? '' ) === $entity_code
				&& $this->when_conditions_met( $rule['when'] ?? array(), $entry['fields'] ?? array() ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * True when every `when` row's field value equals its expected value
	 * (strict `===`, all rows ANDed). Absent field never matches. Empty `when`
	 * returns true (structural match). Callers pass `equals` as the wire-shape
	 * string; strict comparison means a non-string equals won't match.
	 *
	 * @param array $when   Equality rows ([{field, equals}]).
	 * @param array $fields Wire-shape field outputs keyed by option code.
	 *
	 * @return bool
	 */
	private function when_conditions_met( array $when, array $fields ): bool {
		foreach ( $when as $row ) {
			$field = $row['field'] ?? '';
			$value = $fields[ $field ]['value'] ?? null;
			if ( ( $row['equals'] ?? null ) !== $value ) {
				return false;
			}
		}
		return true;
	}
}

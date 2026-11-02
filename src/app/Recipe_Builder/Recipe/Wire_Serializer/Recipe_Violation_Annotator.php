<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Violation_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Projects a rejected toggle's violations onto the rejection's wire
 * recipe, so the error state rides the FE's cached recipe model.
 *
 * Rejection-scoped by contract (FE clarification 2026-07-08): violations
 * are only computed when a set-status transition is attempted, and only
 * the 422 rejection's `recipe` payload carries these annotations — no
 * other payload ever emits them, so this is NOT part of the serializer
 * chain; the REST rejection intercepts call it directly.
 *
 * Annotations are `{ code, message }` only (the FE node schema is strict
 * — never a `target` key; the node the annotation sits on IS the
 * target). Target-`null` entries land on the recipe root; targeted
 * entries anchor to the flow node whose `type` AND `id` match strictly
 * (an id type mismatch must not anchor — the FE would mis-route it).
 * Nodes and root without matches get no key: never `[]`, never `null`.
 */
final class Recipe_Violation_Annotator {

	/**
	 * Stamp the violations onto the wire recipe.
	 *
	 * @param array<string,mixed>            $recipe     Serialized wire recipe.
	 * @param array<int,array<string,mixed>> $violations Wire-shape violations (`{ code, message, target }`).
	 * @return array<string,mixed> The recipe with `violations` keys stamped.
	 */
	public function annotate( array $recipe, array $violations ): array {
		$root = array();
		foreach ( $violations as $violation ) {
			if ( is_array( $violation ) && ! isset( $violation['target'] ) ) {
				$root[] = $this->to_annotation( $violation );
			}
		}
		if ( Recipe_Violation_Service::has_violations( $root ) ) {
			$recipe['violations'] = $root;
		}

		$targeted = array_values(
			array_filter(
				$violations,
				static function ( $violation ) {
					return is_array( $violation ) && isset( $violation['target'] ) && is_array( $violation['target'] );
				}
			)
		);
		if ( ! Recipe_Violation_Service::has_violations( $targeted ) || ! isset( $recipe[ Wire_Key::FLOW ] ) || ! is_array( $recipe[ Wire_Key::FLOW ] ) ) {
			return $recipe;
		}

		if ( isset( $recipe[ Wire_Key::FLOW ][ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] ) && is_array( $recipe[ Wire_Key::FLOW ][ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] ) ) {
			$recipe[ Wire_Key::FLOW ][ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] = $this->annotate_list( $recipe[ Wire_Key::FLOW ][ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ], $targeted );
		}
		if ( isset( $recipe[ Wire_Key::FLOW ][ Wire_Key::CONTENT ] ) && is_array( $recipe[ Wire_Key::FLOW ][ Wire_Key::CONTENT ] ) ) {
			$recipe[ Wire_Key::FLOW ][ Wire_Key::CONTENT ] = $this->annotate_list( $recipe[ Wire_Key::FLOW ][ Wire_Key::CONTENT ], $targeted );
		}

		return $recipe;
	}

	/**
	 * Annotate each node in a list, skipping non-array entries.
	 *
	 * @param array<int,mixed>               $nodes    Serialized flow nodes.
	 * @param array<int,array<string,mixed>> $targeted Violations with targets.
	 * @return array<int,mixed>
	 */
	private function annotate_list( array $nodes, array $targeted ): array {
		foreach ( $nodes as $i => $node ) {
			if ( is_array( $node ) ) {
				$nodes[ $i ] = $this->annotate_node( $node, $targeted );
			}
		}

		return $nodes;
	}

	/**
	 * Stamp matching violations onto one node, then recurse into a block's
	 * modifiers and path contents.
	 *
	 * @param array<string,mixed>            $node     Serialized flow node.
	 * @param array<int,array<string,mixed>> $targeted Violations with targets.
	 * @return array<string,mixed>
	 */
	private function annotate_node( array $node, array $targeted ): array {
		$matches = array();
		foreach ( $targeted as $violation ) {
			$target = $violation['target'];
			// Strict on BOTH: the wire id type (int item vs string filter
			// block) must match exactly or the FE mis-anchors the violation.
			if ( ( $target['node_type'] ?? null ) === ( $node[ Wire_Key::TYPE ] ?? null )
				&& ( $target['id'] ?? null ) === ( $node[ Wire_Key::ID ] ?? null ) ) {
				$matches[] = $this->to_annotation( $violation );
			}
		}
		if ( Recipe_Violation_Service::has_violations( $matches ) ) {
			$node['violations'] = $matches;
		}

		if ( Wire_Node_Type::BLOCK !== ( $node[ Wire_Key::TYPE ] ?? '' ) ) {
			return $node;
		}

		if ( isset( $node[ Wire_Key::MODIFIERS ] ) && is_array( $node[ Wire_Key::MODIFIERS ] ) ) {
			$node[ Wire_Key::MODIFIERS ] = $this->annotate_list( $node[ Wire_Key::MODIFIERS ], $targeted );
		}
		if ( isset( $node[ Wire_Key::PATHS ] ) && is_array( $node[ Wire_Key::PATHS ] ) ) {
			foreach ( $node[ Wire_Key::PATHS ] as $p => $path ) {
				if ( is_array( $path ) && isset( $path[ Wire_Key::CONTENT ] ) && is_array( $path[ Wire_Key::CONTENT ] ) ) {
					$node[ Wire_Key::PATHS ][ $p ][ Wire_Key::CONTENT ] = $this->annotate_list( $path[ Wire_Key::CONTENT ], $targeted );
				}
			}
		}

		return $node;
	}

	/**
	 * The annotation projection of one wire violation: exactly
	 * `{ code, message }`.
	 *
	 * @param array<string,mixed> $violation Wire-shape violation.
	 * @return array{code: string, message: string}
	 */
	private function to_annotation( array $violation ): array {
		return array(
			'code'    => (string) ( $violation['code'] ?? '' ),
			'message' => (string) ( $violation['message'] ?? '' ),
		);
	}
}

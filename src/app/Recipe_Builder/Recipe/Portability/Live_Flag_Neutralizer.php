<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Portability;

/**
 * Clears `is_item_on` live-flags on tree nodes so a downstream
 * materialization births the copies as drafts.
 *
 * Two consumers, two scopes: duplication neutralizes a whole extracted
 * subtree unconditionally (contract: duplicated items are born off);
 * the importer neutralizes triggers and actions separately because its
 * `automator_recipe_import_draft_post_types` filter can exempt either
 * post type.
 *
 * The importer may pass codes to force live (its legacy
 * `automator_recipe_import_published_trigger_codes` filter); there is no
 * built-in list. Triggers without an on / off switch are switched on by
 * the importer after replay, through the same item-on check as the builder.
 */
final class Live_Flag_Neutralizer {

	/**
	 * Clear every trigger/action live-flag in one extracted subtree, descending block / loop `paths[].items`.
	 *
	 * @param array<string,mixed> $node Extracted tree node.
	 * @return array<string,mixed> The node with live-flags cleared.
	 */
	public function neutralize_subtree( array $node ): array {
		return $this->walk( $node, array( 'trigger', 'action' ) );
	}

	/**
	 * Clear action live-flags across a content-node list, descending
	 * block / loop `paths[].items`. Trigger kinds are left alone.
	 *
	 * @param array<int,mixed> $nodes Content nodes.
	 * @return array<int,mixed> Nodes with action live-flags cleared.
	 */
	public function neutralize_actions( array $nodes ): array {
		foreach ( $nodes as $i => $node ) {
			if ( is_array( $node ) ) {
				$nodes[ $i ] = $this->walk( $node, array( 'action' ) );
			}
		}

		return $nodes;
	}

	/**
	 * Clear the live-flag on every trigger entry (triggers never nest),
	 * except the given codes, which are forced ON.
	 *
	 * @param array<int,mixed>  $triggers          Tree trigger entries.
	 * @param array<int,string> $always_live_codes Codes forced live.
	 * @return array<int,mixed> Entries with live-flags cleared (or forced).
	 */
	public function neutralize_triggers( array $triggers, array $always_live_codes = array() ): array {
		foreach ( $triggers as $i => $trigger ) {
			if ( is_array( $trigger ) ) {
				$triggers[ $i ]['is_item_on'] = $this->is_always_live( $trigger, $always_live_codes );
			}
		}

		return $triggers;
	}

	/**
	 * Force the live-flag ON for trigger entries matching the given codes,
	 * leaving every other entry's flag untouched. The importer's path when
	 * trigger drafting is filtered off — the filter's codes land live
	 * regardless of the draft policy (legacy parity).
	 *
	 * @param array<int,mixed>  $triggers          Tree trigger entries.
	 * @param array<int,string> $always_live_codes Codes forced live.
	 * @return array<int,mixed> Entries with matching flags forced on.
	 */
	public function force_live_triggers( array $triggers, array $always_live_codes ): array {
		foreach ( $triggers as $i => $trigger ) {
			if ( is_array( $trigger ) && $this->is_always_live( $trigger, $always_live_codes ) ) {
				$triggers[ $i ]['is_item_on'] = true;
			}
		}

		return $triggers;
	}

	/**
	 * Whether a node is a trigger carrying an always-live code.
	 *
	 * @param array<string,mixed> $node              Tree node.
	 * @param array<int,string>   $always_live_codes Codes forced live.
	 *
	 * @return bool
	 */
	private function is_always_live( array $node, array $always_live_codes ): bool {
		return in_array( (string) ( $node['code'] ?? '' ), $always_live_codes, true );
	}

	/**
	 * Recursive flag-clearing walk over one node.
	 *
	 * @param array<string,mixed> $node  Tree node.
	 * @param array<int,string>   $kinds Node kinds whose flag is cleared.
	 * @return array<string,mixed>
	 */
	private function walk( array $node, array $kinds ): array {
		if ( in_array( (string) ( $node['kind'] ?? '' ), $kinds, true ) ) {
			$node['is_item_on'] = false;
		}

		foreach ( (array) ( $node['paths'] ?? array() ) as $p => $path ) {
			$items = (array) ( $path['items'] ?? array() );
			foreach ( $items as $i => $item ) {
				if ( is_array( $item ) ) {
					$items[ $i ] = $this->walk( $item, $kinds );
				}
			}
			$node['paths'][ $p ]['items'] = $items;
		}

		return $node;
	}
}

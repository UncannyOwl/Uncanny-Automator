<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

/**
 * Decides whether a tree node existed when a run started. Nodes added to
 * the recipe AFTER a run must not render inside that run's log — a
 * just-added action showing "Not completed" on last month's run reads as
 * a failure that never happened.
 *
 * The signal is the node post's creation date vs the run's start — NEVER
 * bare row-absence. Row-absence alone would also hide the legitimately
 * row-less: the pending inner actions of a delayed run, cancelled runs,
 * and items on an untaken filter path. All of those were created before
 * the run started, so the creation-time rule keeps them visible while
 * hiding true late additions. Run data always wins the other way too: a
 * node with ANY trace in the run (action/trigger rows, loop iteration
 * rows) is never hidden, whatever the dates say.
 */
final class Node_Run_Visibility {

	/**
	 * Whether an item (trigger/action) tree node should be hidden
	 * from this run's flow: created after the run started AND left no trace
	 * in the run.
	 *
	 * @param array<string,mixed> $node   Tree item entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return bool True when the item must be omitted from the run's flow.
	 */
	public function item_hidden( array $node, Run_Data_Bundle $bundle ): bool {
		$id = $node['id'] ?? 0;
		if ( ! is_numeric( $id ) ) {
			return false;
		}
		$id   = (int) $id;
		$kind = (string) ( $node['item_type'] ?? $node['kind'] ?? 'action' );

		if ( 'trigger' === $kind ) {
			if ( ! empty( $bundle->trigger_rows_for( $id ) ) ) {
				return false;
			}
		} elseif ( null !== $bundle->action_row( $id ) || ! empty( $bundle->loop_breakdown_for_action( $id ) ) ) {
			// Loop-body actions never get action_log rows — their trace is
			// the per-iteration breakdown, so it counts as run data too.
			return false;
		}

		return $this->created_after_run_start( $id, $bundle );
	}

	/**
	 * Whether a block tree node should be hidden from this run's flow.
	 *
	 * EDGE CASE — migration-created wrappers: the 8.0.0 migration CREATES
	 * uo-block wrapper posts around legacy delay/condition recipes, so on
	 * every pre-migration run the wrapper post-dates the run start even
	 * though the flow it wraps genuinely ran. Hiding it would evict its
	 * (ran) inner actions into the orphan appendix, where they render as
	 * "integration isn't installed". A block therefore hides only when
	 * EVERY descendant hides too — a wrapper around history stays visible.
	 *
	 * @param array<string,mixed> $block  Tree block entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return bool True when the block must be omitted from the run's flow.
	 */
	public function block_hidden( array $block, Run_Data_Bundle $bundle ): bool {
		$id = (int) ( $block['id'] ?? 0 );

		// Some runner versions log blocks as their own action_log rows —
		// any trace in the run keeps the block, whatever the dates say.
		if ( null !== $bundle->action_row( $id ) ) {
			return false;
		}
		if ( ! $this->created_after_run_start( $id, $bundle ) ) {
			return false;
		}

		foreach ( (array) ( $block['paths'] ?? array() ) as $path ) {
			foreach ( (array) ( ( (array) $path )['items'] ?? array() ) as $child ) {
				$child        = (array) $child;
				$child_hidden = 'block' === ( $child['kind'] ?? '' )
					? $this->block_hidden( $child, $bundle )
					: $this->item_hidden( $child, $bundle );
				if ( ! $child_hidden ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Whether a block modifier should be hidden from this run's flow.
	 * Modifiers never leave run rows, so creation time is the only signal;
	 * non-numeric ids (legacy fixtures) can't be dated and stay visible.
	 *
	 * @param array<string,mixed> $modifier Tree modifier entry.
	 * @param Run_Data_Bundle     $bundle   Run data.
	 * @return bool True when the modifier must be omitted from the run's flow.
	 */
	public function modifier_hidden( array $modifier, Run_Data_Bundle $bundle ): bool {
		$id = $modifier['id'] ?? 0;
		if ( ! is_numeric( $id ) ) {
			return false;
		}
		return $this->created_after_run_start( (int) $id, $bundle );
	}

	/**
	 * Whether the node post was created strictly after the run started.
	 *
	 * `post_date` and `uap_recipe_log.date_time` are both site-local wall
	 * clock, so the comparison pairs like-for-like. Unknown dates never
	 * hide (fail-open to visible), and same-second creation is kept.
	 *
	 * EDGE CASE — mid-flight edits: an action added while a delayed run is
	 * still pending was created after the run START and hides from that
	 * run. The runner dispatches resumes from its schedule-time snapshot,
	 * so the late action never executes within that run — hiding matches
	 * reality. Revisit if the runner rebase makes resumes re-read the
	 * current tree.
	 *
	 * @param int             $post_id Node post id.
	 * @param Run_Data_Bundle $bundle  Run data.
	 * @return bool True when created after the run's start.
	 */
	private function created_after_run_start( int $post_id, Run_Data_Bundle $bundle ): bool {
		$created = $bundle->node_created_at( $post_id );
		$started = (string) ( $bundle->recipe_log_row()['date_time'] ?? '' );
		if ( null === $created || '' === $created || '' === $started ) {
			return false;
		}
		$created_ts = strtotime( $created );
		$started_ts = strtotime( $started );
		if ( false === $created_ts || false === $started_ts ) {
			return false;
		}
		return $created_ts > $started_ts;
	}
}

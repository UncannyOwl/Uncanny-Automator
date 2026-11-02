<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Logs\Status\Log_Status;
use Uncanny_Automator\App\Recipe_Logs\Status\Log_Status_Registry;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Code;
use Uncanny_Automator\Automator_Status;

/**
 * Derives the wire status object for each log node from the raw run data,
 * applying the spec §6c rules, then materializes it through
 * Log_Status_Registry. Owns "which status is this node in"; the serializers
 * own shape assembly.
 */
final class Node_Status_Resolver {

	/**
	 * Recipe-log root status from the uap_recipe_log.completed int.
	 *
	 * @param Run_Data_Bundle $bundle Run data.
	 * @return Log_Status The recipe root's status.
	 */
	public function recipe_root( Run_Data_Bundle $bundle ): Log_Status {
		$row = $bundle->recipe_log_row();
		return Log_Status_Registry::from_legacy_int( (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED ) );
	}

	/**
	 * Item (trigger/action) status.
	 *
	 * @param int             $item_id Post id.
	 * @param Run_Data_Bundle $bundle  Run data.
	 * @param array<string,bool> $ctx  inside_loop, in_untaken_path (+ kind).
	 * @return Log_Status The item's status.
	 */
	public function item( int $item_id, Run_Data_Bundle $bundle, array $ctx ): Log_Status {
		if ( 'trigger' === (string) ( $ctx['kind'] ?? 'action' ) ) {
			$rows = $bundle->trigger_rows_for( $item_id );
			if ( empty( $rows ) ) {
				return Log_Status_Registry::from_code( Log_Status_Code::NOT_COMPLETED );
			}
			$latest = end( $rows );
			return Log_Status_Registry::from_legacy_int( (int) ( $latest['completed'] ?? Automator_Status::NOT_COMPLETED ) );
		}
		if ( ! empty( $ctx['inside_loop'] ) ) {
			return $this->rollup_from_breakdown( $bundle->loop_breakdown_for_action( $item_id ) );
		}
		$row = $bundle->action_row( $item_id );
		if ( is_array( $row ) ) {
			$pending = $this->pending_delay( $item_id, $bundle );
			if ( null !== $pending ) {
				return Log_Status_Registry::from_code( $pending['code'] );
			}
			return Log_Status_Registry::from_legacy_int( (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED ) );
		}
		if ( ! empty( $ctx['in_untaken_path'] ) ) {
			return Log_Status_Registry::from_code( Log_Status_Code::SKIPPED );
		}
		return Log_Status_Registry::from_code( Log_Status_Code::NOT_COMPLETED );
	}

	/**
	 * Whether an item ships status_breakdown (loop-body) vs runs.
	 *
	 * @param array<string,bool> $ctx Context flags.
	 * @return bool True when the item ships a status_breakdown instead of runs.
	 */
	public function item_ships_breakdown( array $ctx ): bool {
		return ! empty( $ctx['inside_loop'] );
	}

	/**
	 * Pending delay/schedule descriptor for an action, or null.
	 *
	 * Mirrors the legacy fabricate_item_with_delay() gate: the action's log
	 * row must be non-terminal AND a stored action_delays record with a
	 * numeric `time` must exist. Terminal rows ignore the record (history
	 * shows the final status); malformed records never fatal.
	 *
	 * @param int             $action_id Action post id.
	 * @param Run_Data_Bundle $bundle    Run data.
	 * @return array{code: string, time: int}|null The pending descriptor, or null when not pending.
	 */
	public function pending_delay( int $action_id, Run_Data_Bundle $bundle ): ?array {
		$row = $bundle->action_row( $action_id );
		if ( ! is_array( $row ) || Automator_Status::finished( (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED ) ) ) {
			return null;
		}
		$config = $bundle->delay_config_for_action( $action_id );
		if ( ! is_array( $config ) || ! isset( $config['time'] ) || ! is_numeric( $config['time'] ) ) {
			return null;
		}
		return array(
			'code' => 'schedule' === (string) ( $config['type'] ?? '' ) ? Log_Status_Code::SCHEDULED : Log_Status_Code::DELAYED,
			'time' => (int) $config['time'],
		);
	}

	/**
	 * Loop-block status from the uap_loop_entries.status string.
	 *
	 * @param int             $loop_id Loop block post id.
	 * @param Run_Data_Bundle $bundle  Run data.
	 * @return Log_Status The loop block's status.
	 */
	public function loop_block( int $loop_id, Run_Data_Bundle $bundle ): Log_Status {
		$entry = $bundle->loop_entry( $loop_id );
		if ( ! is_array( $entry ) || '' === (string) ( $entry['status'] ?? '' ) ) {
			return Log_Status_Registry::from_code( Log_Status_Code::NOT_COMPLETED );
		}
		return Log_Status_Registry::from_code( (string) $entry['status'] );
	}

	/**
	 * Per-path execution status.
	 *
	 * @param bool $taken Whether this is the chosen path.
	 * @return Log_Status The path's status.
	 */
	public function path_status( bool $taken ): Log_Status {
		return Log_Status_Registry::from_code( $taken ? Log_Status_Code::COMPLETED : Log_Status_Code::SKIPPED );
	}

	/**
	 * Modifier (filter_condition / loop_filter) status.
	 *
	 * @param array<string,mixed> $modifier_node Modifier tree node.
	 * @param int                 $block_id      Parent block post id.
	 * @param Run_Data_Bundle     $bundle        Run data.
	 * @param array<string,mixed> $ctx           is_loop_filter, loop_ran, chosen_path, inside_loop, path_entities.
	 * @return Log_Status The modifier's status.
	 */
	public function modifier( array $modifier_node, int $block_id, Run_Data_Bundle $bundle, array $ctx ): Log_Status {
		// A block the walker refused ran none of its modifiers; the ones the refusal names caused it.
		if ( ! empty( $ctx['refused'] ) ) {
			return Log_Status_Registry::from_code(
				empty( $ctx['refusal_messages'] ) ? Log_Status_Code::CONDITION_NOT_EVALUATED : Log_Status_Code::FAILED
			);
		}
		if ( ! empty( $ctx['is_loop_filter'] ) ) {
			// A loop filter that threw, or whose plugin is not active, matched nothing: Pro's loop recorded why.
			$outcome = $bundle->loop_filter_outcome( (int) ( $modifier_node['id'] ?? 0 ) );
			if ( null !== $outcome && ! $outcome['met'] ) {
				return Log_Status_Registry::from_code( Log_Status_Code::CONDITION_NOT_MET );
			}

			return Log_Status_Registry::from_code(
				! empty( $ctx['loop_ran'] ) ? Log_Status_Code::CONDITION_MET : Log_Status_Code::CONDITION_NOT_EVALUATED
			);
		}
		// A condition inside a loop body evaluated once per entity; the entity counts on its paths are the record.
		if ( ! empty( $ctx['inside_loop'] ) ) {
			$met     = (int) ( $ctx['path_entities'][ Block_Path_Code::FILTER_CONDITIONS_MET ] ?? 0 );
			$not_met = (int) ( $ctx['path_entities'][ Block_Path_Code::FILTER_CONDITIONS_NOT_MET ] ?? 0 );
			if ( $met > 0 ) {
				return Log_Status_Registry::from_code( Log_Status_Code::CONDITION_MET );
			}

			return Log_Status_Registry::from_code( $not_met > 0 ? Log_Status_Code::CONDITION_NOT_MET : Log_Status_Code::CONDITION_NOT_EVALUATED );
		}

		$chosen = (string) ( $ctx['chosen_path'] ?? '' );
		if ( '' === $chosen ) {
			return Log_Status_Registry::from_code( Log_Status_Code::CONDITION_NOT_EVALUATED );
		}
		$met = false === strpos( $chosen, 'NOT-MET' );
		return Log_Status_Registry::from_code( $met ? Log_Status_Code::CONDITION_MET : Log_Status_Code::CONDITION_NOT_MET );
	}

	/**
	 * Roll up a per-status count map into a single item status.
	 *
	 * @param array<string,int> $breakdown status => count.
	 * @return Log_Status The rolled-up status.
	 */
	public function rollup_from_breakdown( array $breakdown ): Log_Status {
		if ( empty( $breakdown ) ) {
			return Log_Status_Registry::from_code( Log_Status_Code::NOT_COMPLETED );
		}
		if ( ! empty( $breakdown[ Log_Status_Code::FAILED ] ) ) {
			return Log_Status_Registry::from_code( Log_Status_Code::FAILED );
		}
		if ( ! empty( $breakdown[ Log_Status_Code::IN_PROGRESS ] ) ) {
			return Log_Status_Registry::from_code( Log_Status_Code::IN_PROGRESS );
		}
		// Pro writes 'cancelled' to uap_loop_entries_items. Below in-progress:
		// running work outranks abandoned work.
		if ( ! empty( $breakdown[ Log_Status_Code::CANCELLED ] ) ) {
			return Log_Status_Registry::from_code( Log_Status_Code::CANCELLED );
		}
		return Log_Status_Registry::from_code( Log_Status_Code::COMPLETED );
	}
}

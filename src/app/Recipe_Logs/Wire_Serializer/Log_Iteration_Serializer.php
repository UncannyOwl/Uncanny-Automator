<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Logs\Status\Log_Status_Registry;
use Uncanny_Automator\App\Recipe_Builder\Field\Value_Objects\Field_Context;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Field_Output_Serializer;
use Uncanny_Automator\Automator_Compression;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;

/**
 * Serializes iterations-sub-resource entries: one uap_loop_entries_items row
 * to the strict ModelRecipeLogItemIteration shape (ITEM arm — a REQUIRED
 * single-entry runs[] stream, never control/status_breakdown). The ITEM arm
 * is the only populated arm: per-iteration detail belongs to loop-body
 * actions (product ruling 2026-07-16); blocks and modifiers get structural
 * empty pages from the service.
 */
final class Log_Iteration_Serializer {

	/**
	 * Tree-field to FieldsOutput serializer, used for the tokens_snapshot overlay.
	 *
	 * @var Field_Output_Serializer
	 */
	private $fields;

	/**
	 * Wires up the serializer's collaborators, defaulting each to its production implementation.
	 *
	 * @param Field_Output_Serializer|null $fields FieldsOutput serializer.
	 */
	public function __construct( ?Field_Output_Serializer $fields = null ) {
		$this->fields = $fields ?? new Field_Output_Serializer();
	}

	/**
	 * Serialize one iteration row for a loop-body action (ITEM arm).
	 *
	 * @param array<string,mixed> $row   uap_loop_entries_items row.
	 * @param int                 $index 0-based absolute iteration index (page offset included).
	 * @param string|null         $label Resolved iteration label, or null to omit.
	 * @param Field_Context|null  $ctx   The action's catalog coordinates; null (orphan) omits `fields`.
	 * @return array<string,mixed> The wire ModelRecipeLogItemIteration entry.
	 */
	public function serialize_item_row( array $row, int $index, ?string $label, ?Field_Context $ctx = null ): array {
		$status_vo    = Log_Status_Registry::from_code( (string) ( $row['status'] ?? '' ) );
		$status       = $status_vo->to_array();
		$date_added   = (string) ( $row['date_added'] ?? '' );
		$date_updated = (string) ( $row['date_updated'] ?? '' );

		$execution = array(
			'status'       => $status,
			'date_started' => $this->fmt( $date_added ),
		);
		// Terminality comes from the descriptor's phase (Log_Status SSoT) —
		// unknown codes fall back to a non-terminal descriptor, so an exotic
		// status can never fake a date_ended.
		if ( $status_vo->is_terminal() ) {
			$this->append_ended_dates( $execution, $date_added, $date_updated );
		}

		$run     = array(
			'run_number' => 1,
			'status'     => $status,
			'date'       => $this->fmt( $date_added ),
		);
		$message = Log_Message_Text::from_stored( (string) ( $row['error_message'] ?? '' ) );
		if ( '' !== $message ) {
			$run['result_message'] = $message;
		}
		$tokens = $this->decode_tokens( (string) ( $row['action_tokens'] ?? '' ) );
		if ( ! empty( $tokens ) ) {
			$run['tokens'] = $tokens;
		}
		$overlay = $this->snapshot_overlay( (string) ( $row['tokens_snapshot'] ?? '' ), $ctx );
		if ( null !== $overlay ) {
			$run['fields'] = $overlay;
		}
		$execution['runs'] = array( $run );

		return $this->entry( $index, $label, $execution );
	}

	/**
	 * Assemble the shared iteration-entry envelope: index, optional label, execution.
	 *
	 * @param int                 $index     0-based iteration index.
	 * @param string|null         $label     Label, or null/empty to omit.
	 * @param array<string,mixed> $execution Execution sub-object.
	 * @return array<string,mixed> The iteration entry.
	 */
	private function entry( int $index, ?string $label, array $execution ): array {
		$out = array( 'iteration_index' => $index );
		if ( null !== $label && '' !== $label ) {
			$out['iteration_label'] = $label;
		}
		$out['execution'] = $execution;
		return $out;
	}

	/**
	 * Append date_ended + date_elapsed when the end strictly follows the start.
	 *
	 * @param array<string,mixed> $execution Execution sub-object, mutated in place.
	 * @param string              $start     Raw start datetime.
	 * @param string              $end       Raw end datetime.
	 * @return void
	 */
	private function append_ended_dates( array &$execution, string $start, string $end ): void {
		$elapsed = $this->elapsed( $start, $end );
		if ( null !== $elapsed && $elapsed['seconds'] > 0 ) {
			$execution['date_ended']   = $this->fmt( $end );
			$execution['date_elapsed'] = $elapsed;
		}
	}

	/**
	 * Decode an action_tokens JSON blob to a string map, keeping scalar
	 * values only (keys are plain token codes — no brace handling here).
	 *
	 * @param string $tokens_json Raw action_tokens column value.
	 * @return array<string,string> The token map; empty on empty/malformed input.
	 */
	private function decode_tokens( string $tokens_json ): array {
		if ( '' === $tokens_json ) {
			return array();
		}
		$decoded = json_decode( $tokens_json, true );
		if ( ! is_array( $decoded ) ) {
			automator_log( 'Malformed action_tokens JSON on a loop iteration row; omitting tokens.', 'Log_Iteration_Serializer' );
			return array();
		}
		$out = array();
		foreach ( $decoded as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$out[ (string) $key ] = (string) $value;
			}
		}
		return $out;
	}

	/**
	 * Decode a tokens_snapshot blob (maybe-compressed, PHP-serialized) into a
	 * FieldsOutput overlay of ONLY the action's catalog-defined fields. The
	 * snapshot is a flattened action-postmeta dump (system meta mixed with
	 * the configured fields), so Field_Output_Serializer::serialize_defined()
	 * supplies both the filter and the labels/types — mirroring the legacy
	 * definitions-from-resolver + values-from-snapshot split. No context
	 * (orphan action) or catalog miss → omit; never dump.
	 *
	 * @param string             $snapshot Raw tokens_snapshot column value.
	 * @param Field_Context|null $ctx      The action's catalog coordinates, or null.
	 * @return array<string,mixed>|null The overlay, or null when unresolvable (caller omits the key).
	 */
	private function snapshot_overlay( string $snapshot, ?Field_Context $ctx ): ?array {
		if ( '' === $snapshot || null === $ctx ) {
			return null;
		}
		$parsed = automator_safe_unserialize( Automator_Compression::maybe_decompress_string( $snapshot ) );
		if ( ! is_array( $parsed ) || empty( $parsed ) ) {
			automator_log( 'Undecodable tokens_snapshot blob on a loop iteration row; omitting fields.', 'Log_Iteration_Serializer' );
			return null;
		}
		$input = array();
		foreach ( $parsed as $meta_key => $value ) {
			$input[ (string) $meta_key ] = array(
				'value'    => is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value ),
				'readable' => '',
			);
		}
		$serialized = $this->fields->serialize_defined( $input, $ctx );
		return is_array( $serialized ) && ! empty( $serialized ) ? $serialized : null;
	}

	/**
	 * Format a MySQL datetime to the display string. Empty in → empty out.
	 *
	 * @param string $mysql_datetime Raw datetime.
	 * @return string The formatted display string, or empty when the input is empty.
	 */
	private function fmt( string $mysql_datetime ): string {
		if ( '' === $mysql_datetime ) {
			return '';
		}
		return (string) Formatters_Utils::date_time_format_relative( $mysql_datetime );
	}

	/**
	 * Elapsed sub-object from two raw MySQL datetimes, or null when unparsable.
	 *
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @return array{seconds: int, human_readable: string}|null The elapsed sub-object, or null.
	 */
	private function elapsed( string $start, string $end ): ?array {
		$start_ts = strtotime( $start );
		$end_ts   = strtotime( $end );
		if ( false === $start_ts || false === $end_ts || $end_ts < $start_ts ) {
			return null;
		}
		return array(
			'seconds'        => $end_ts - $start_ts,
			'human_readable' => human_time_diff( $start_ts, $end_ts ),
		);
	}
}

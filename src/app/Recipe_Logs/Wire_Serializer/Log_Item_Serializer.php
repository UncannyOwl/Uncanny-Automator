<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Recipe_Logs\Status\Log_Status_Registry;
use Uncanny_Automator\App\Recipe_Builder\Field\Value_Objects\Field_Context;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Field_Output_Serializer;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;

/**
 * Serializes one recipe-log item (trigger / action) to the strict
 * ModelRecipeLogItem wire shape: static identity + `fields` + an `execution`
 * that ships either a per-run retry stream or (loop-body) a status breakdown.
 *
 * Reads tree keys as literals; emits wire keys from Wire_Key.
 */
final class Log_Item_Serializer {

	/**
	 * Non-empty sentinel for integration_code / code when unknown (orphan or
	 * plugin-deactivated item). The FE contract is `z.string().min(1)` on both,
	 * so an empty string aborts the whole log parse; a non-matching sentinel
	 * degrades gracefully to the "integration not installed" UI via
	 * `last_known_integration_name`.
	 *
	 * @var string
	 */
	private const UNKNOWN_CODE = 'UNKNOWN';

	/**
	 * Status derivation for items.
	 *
	 * @var Node_Status_Resolver
	 */
	private $resolver;

	/**
	 * Tree-field to FieldsOutput serializer.
	 *
	 * @var Field_Output_Serializer
	 */
	private $fields;

	/**
	 * Log-properties-meta mapper.
	 *
	 * @var Log_Properties_Mapper
	 */
	private $properties;

	/**
	 * Integration-name resolver.
	 *
	 * @var Integration_Registry_Bridge
	 */
	private $integrations;

	/**
	 * Wires up the item serializer's collaborators, defaulting each to its production implementation.
	 *
	 * @param Node_Status_Resolver|null        $resolver     Status derivation.
	 * @param Field_Output_Serializer|null      $fields       Tree-field → FieldsOutput serializer.
	 * @param Log_Properties_Mapper|null        $properties   Property mapper.
	 * @param Integration_Registry_Bridge|null  $integrations Integration-name resolver.
	 */
	public function __construct(
		?Node_Status_Resolver $resolver = null,
		?Field_Output_Serializer $fields = null,
		?Log_Properties_Mapper $properties = null,
		?Integration_Registry_Bridge $integrations = null
	) {
		$this->resolver     = $resolver ?? new Node_Status_Resolver();
		$this->fields       = $fields ?? new Field_Output_Serializer();
		$this->properties   = $properties ?? new Log_Properties_Mapper();
		$this->integrations = $integrations ?? new Automator_Integration_Registry_Bridge();
	}

	/**
	 * Serialize an item tree node with its run data.
	 *
	 * @param array<string,mixed> $node   Tree item entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @param array<string,mixed> $ctx    inside_loop, in_untaken_path, block_errors (the enclosing blocks' run-level errors as wire text).
	 * @return array<string,mixed> The wire ModelRecipeLogItem entry.
	 */
	public function serialize( array $node, Run_Data_Bundle $bundle, array $ctx ): array {
		$kind             = (string) ( $node['item_type'] ?? $node['kind'] ?? 'action' );
		$id               = $node['id'] ?? 0;
		$integration_code = (string) ( $node['integration_code'] ?? '' );
		$code             = (string) ( $node['code'] ?? '' );
		$ctx              = array_merge( $ctx, array( 'kind' => $kind ) );
		$field_ctx        = ( '' !== $integration_code && '' !== $kind && '' !== $code )
			? new Field_Context( $integration_code, $kind, $code )
			: null;

		$item = array(
			Wire_Key::TYPE             => Wire_Node_Type::ITEM,
			Wire_Key::ITEM_TYPE        => $kind,
			Wire_Key::ID               => is_numeric( $id ) ? (int) $id : (string) $id,
			Wire_Key::INTEGRATION_CODE => '' !== $integration_code ? $integration_code : self::UNKNOWN_CODE,
			Wire_Key::CODE             => '' !== $code ? $code : self::UNKNOWN_CODE,
			Wire_Key::CUSTOM_NAME      => $node['custom_name'] ?? null,
			Wire_Key::FIELDS           => $this->fields->serialize( $this->configuration( $kind, (int) $id, $node, $bundle ), $field_ctx ),
			'execution'                => $this->execution( $kind, (int) $id, $bundle, $ctx, $field_ctx ),
		);

		if ( '' !== $integration_code ) {
			$item[ Wire_Key::LAST_KNOWN_INTEGRATION_NAME ] = $this->integrations->get_integration_name_by_code( $integration_code );
		}

		return $item;
	}

	/**
	 * Build the item's execution sub-object.
	 *
	 * @param string                $kind      Item kind.
	 * @param int                   $id        Item post id.
	 * @param Run_Data_Bundle       $bundle    Run data.
	 * @param array<string,mixed>   $ctx       Context flags.
	 * @param Field_Context|null    $field_ctx The item's field context, or null.
	 * @return array<string,mixed> The item's execution sub-object.
	 */
	private function execution( string $kind, int $id, Run_Data_Bundle $bundle, array $ctx, ?Field_Context $field_ctx = null ): array {
		$exec = array( 'status' => $this->resolver->item( $id, $bundle, $ctx )->to_array() );

		if ( $this->resolver->item_ships_breakdown( $ctx ) ) {
			$exec['date_started'] = $this->fmt( (string) ( $bundle->recipe_log_row()['date_time'] ?? '' ) );
			$message              = $this->with_block_errors( '', $ctx );
			if ( '' !== $message ) {
				$exec['result_message'] = $message;
			}
			$exec['status_breakdown'] = $this->breakdown_entries( $bundle->loop_breakdown_for_action( $id ) );
			return $exec;
		}

		if ( 'trigger' === $kind ) {
			$runs    = $this->trigger_runs( $id, $bundle, $field_ctx );
			$started = $runs['first_date'];
			$ended   = '' !== $runs['last_date'] && $runs['last_date'] !== $runs['first_date'] ? $runs['last_date'] : null;
			$message = $runs['last_message'];
		} else {
			$runs                    = $this->action_runs( $id, $bundle, $field_ctx );
			list( $started, $ended ) = $this->action_dates( $id, $bundle, $runs['last_date'] );
			// A step that never ran (skipped, or not reached) did not run early either.
			$message = '' !== $runs['last_message'] || '' === $started ? $runs['last_message'] : $this->ran_early_message( $id, $bundle );
			$message = $this->with_block_errors( $message, $ctx );
		}

		$exec['date_started'] = $this->fmt( $started );
		if ( null !== $ended ) {
			$exec['date_ended'] = $this->fmt( $ended );
			$elapsed            = $this->elapsed( $started, $ended );
			if ( null !== $elapsed ) {
				$exec['date_elapsed'] = $elapsed;
			}
		}
		if ( '' !== $message ) {
			$exec['result_message'] = $message;
		}
		$exec['runs'] = $runs['runs'];

		if ( 'trigger' !== $kind ) {
			$control = $this->control( $id, $bundle );
			if ( null !== $control ) {
				$exec['control'] = $control;
			}
		}

		return $exec;
	}

	/**
	 * An action's message followed by the run-level errors of the blocks it sits under (outermost first), each
	 * once: a block that did not run, or whose processing failed, says so on every action under it.
	 *
	 * @since 8.0.0
	 *
	 * @param string              $message The action's own message, '' when none.
	 * @param array<string,mixed> $ctx     Context flags; `block_errors` holds the enclosing blocks' error texts.
	 * @return string The message.
	 */
	private function with_block_errors( string $message, array $ctx ): string {
		$parts = '' === $message ? array() : array( $message );
		foreach ( (array) ( $ctx['block_errors'] ?? array() ) as $text ) {
			if ( ! in_array( (string) $text, $parts, true ) ) {
				$parts[] = (string) $text;
			}
		}

		return implode( ' ', $parts );
	}

	/**
	 * An action's start and end: it starts with the run, or when its delay was due (or run early), and ends when it
	 * finished. One the run did not reach, or skipped on a path it did not take, has neither.
	 *
	 * @param int             $id        Action post id.
	 * @param Run_Data_Bundle $bundle    Run data.
	 * @param string          $last_date Raw MySQL datetime of its last run (the row or its last retry).
	 * @return array{0: string, 1: string|null} Raw MySQL start ('' when none) and end (null while unfinished).
	 */
	private function action_dates( int $id, Run_Data_Bundle $bundle, string $last_date ): array {
		$row    = $bundle->action_row( $id );
		$status = (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED );
		if ( null === $row || in_array( $status, array( Automator_Status::NOT_COMPLETED, Automator_Status::SKIPPED ), true ) ) {
			return array( '', null );
		}

		$delay   = $bundle->delay_config_for_action( $id );
		$due     = $delay['ran_now_at'] ?? $delay['time'] ?? null;
		$started = is_numeric( $due ) ? (string) wp_date( 'Y-m-d H:i:s', (int) $due ) : (string) ( $bundle->recipe_log_row()['date_time'] ?? '' );

		return array( $started, Automator_Status::finished( $status ) ? $last_date : null );
	}

	/**
	 * Who ran a delayed action before its scheduled time, and when, or '' when it ran on schedule.
	 *
	 * @param int             $id     Action post id.
	 * @param Run_Data_Bundle $bundle Run data.
	 * @return string The message.
	 */
	private function ran_early_message( int $id, Run_Data_Bundle $bundle ): string {
		$delay = $bundle->delay_config_for_action( $id );
		if ( '' === (string) ( $delay['ran_now_by'] ?? '' ) || ! is_numeric( $delay['ran_now_at'] ?? null ) || ! is_numeric( $delay['time'] ?? null ) ) {
			return '';
		}

		return sprintf(
			/* translators: 1: the admin's email address, 2: when they ran it, 3: when it was scheduled to run */
			_x( 'Ran early by %1$s on %2$s, before its scheduled time of %3$s.', 'Recipe log', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text sent as JSON; escaped where it is rendered.
			(string) $delay['ran_now_by'],
			(string) Formatters_Utils::date_time_format( (string) wp_date( 'Y-m-d H:i:s', (int) $delay['ran_now_at'] ) ),
			(string) Formatters_Utils::date_time_format( (string) wp_date( 'Y-m-d H:i:s', (int) $delay['time'] ) )
		);
	}

	/**
	 * Breakdown entries: [ { status: <full object>, count: int }, ... ].
	 *
	 * @param array<string,int> $counts status-code => count.
	 * @return array<int,array<string,mixed>> The wire breakdown entries.
	 */
	private function breakdown_entries( array $counts ): array {
		$out = array();
		foreach ( $counts as $code => $count ) {
			$out[] = array(
				'status' => Log_Status_Registry::from_code( (string) $code )->to_array(),
				'count'  => (int) $count,
			);
		}
		return $out;
	}

	/**
	 * Assemble runs for an action: primary log row + api-response retries.
	 *
	 * @param int                $id        Action post id.
	 * @param Run_Data_Bundle    $bundle    Run data.
	 * @param Field_Context|null $field_ctx The item's field context, or null.
	 * @return array{runs: array<int,array<string,mixed>>, first_date: string, last_date: string, last_message: string} The assembled runs and summary dates.
	 */
	private function action_runs( int $id, Run_Data_Bundle $bundle, ?Field_Context $field_ctx = null ): array {
		$row = $bundle->action_row( $id );
		// A row at NOT_COMPLETED was born at run start and nothing ran on it yet: no run to report.
		if ( null === $row || Automator_Status::NOT_COMPLETED === (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED ) ) {
			return array(
				'runs'         => array(),
				'first_date'   => '',
				'last_date'    => '',
				'last_message' => '',
			);
		}
		$action_log_id = (int) $row['ID'];
		$tokens        = $bundle->tokens_map();
		$primary_msg   = Log_Message_Text::from_stored( (string) ( $bundle->error_message_for( $action_log_id ) ?? ( $row['error_message'] ?? '' ) ) );

		$run = array(
			'run_number' => 1,
			'status'     => Log_Status_Registry::from_legacy_int( (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED ) )->to_array(),
			'date'       => $this->fmt( (string) ( $row['date_time'] ?? '' ) ),
		);
		if ( '' !== $primary_msg ) {
			$run['result_message'] = $primary_msg;
		}
		if ( ! empty( $tokens ) ) {
			$run['tokens'] = $bundle->wire_tokens();
		}
		$overlay = $this->run_fields_overlay( (string) ( $bundle->action_meta( $action_log_id, 'action_fields' ) ?? '' ), $tokens, $field_ctx );
		if ( null !== $overlay ) {
			$run['fields'] = $overlay;
		}
		$props = $this->properties->map( automator_safe_unserialize( (string) ( $bundle->action_meta( $action_log_id, 'properties' ) ?? '' ) ) );
		if ( ! empty( $props ) ) {
			$run['properties'] = $props;
		}

		$runs      = array( $run );
		$last_date = (string) ( $row['date_time'] ?? '' );
		$last_msg  = $primary_msg;
		$n         = 1;
		foreach ( $bundle->retries_for( $action_log_id ) as $retry ) {
			++$n;
			$rmsg      = Log_Message_Text::from_stored( (string) ( $retry['message'] ?? '' ) );
			$last_date = (string) ( $retry['date_added'] ?? $last_date );
			$last_msg  = $rmsg;
			$entry     = array(
				'run_number' => $n,
				'status'     => Log_Status_Registry::from_code( (string) ( $retry['result'] ?? '' ) )->to_array(),
				'date'       => $this->fmt( (string) ( $retry['date_added'] ?? '' ) ),
			);
			if ( '' !== $rmsg ) {
				$entry['result_message'] = $rmsg;
			}
			$runs[] = $entry;
		}

		return array(
			'runs'         => $runs,
			'first_date'   => (string) ( $row['date_time'] ?? '' ),
			'last_date'    => $last_date,
			'last_message' => $last_msg,
		);
	}

	/**
	 * Assemble runs for a trigger: one entry per trigger-log row (no retries).
	 *
	 * @param int                $id        Trigger post id.
	 * @param Run_Data_Bundle    $bundle    Run data.
	 * @param Field_Context|null $field_ctx The item's field context, or null.
	 * @return array{runs: array<int,array<string,mixed>>, first_date: string, last_date: string, last_message: string} The assembled runs and summary dates.
	 */
	private function trigger_runs( int $id, Run_Data_Bundle $bundle, ?Field_Context $field_ctx = null ): array {
		$rows   = $bundle->trigger_rows_for( $id );
		$tokens = $bundle->tokens_map();
		$runs   = array();
		$n      = 0;
		$first  = '';
		$last   = '';
		foreach ( $rows as $row ) {
			++$n;
			$date = (string) ( $row['date_time'] ?? '' );
			if ( '' === $first ) {
				$first = $date;
			}
			$last    = $date;
			$entry   = array(
				'run_number' => $n,
				'status'     => Log_Status_Registry::from_legacy_int( (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED ) )->to_array(),
				'date'       => $this->fmt( $date ),
			);
			$overlay = $this->run_fields_overlay( (string) ( $bundle->trigger_meta( (int) $row['ID'], 'trigger_fields' ) ?? '' ), $tokens, $field_ctx );
			if ( null !== $overlay ) {
				$entry['fields'] = $overlay;
			}
			if ( ! empty( $tokens ) ) {
				$entry['tokens'] = $bundle->wire_tokens();
			}
			$props = $this->trigger_properties( (int) $row['ID'], $bundle );
			if ( ! empty( $props ) ) {
				$entry['properties'] = $props;
			}
			$runs[] = $entry;
		}
		return array(
			'runs'         => $runs,
			'first_date'   => $first,
			'last_date'    => $last,
			'last_message' => '',
		);
	}

	/**
	 * The configuration the item ran with, for `fields`: the field record its run stored, token references as
	 * written so the sentence renders them as tokens. The record replaces the tree whole, never blended with it;
	 * an item the run recorded nothing for (not reached, or a run older than the record) has only the current
	 * tree to show.
	 *
	 * @param string              $kind   Item kind.
	 * @param int                 $id     Item post id.
	 * @param array<string,mixed> $node   Tree node.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return array<string,mixed> Fields for Field_Output_Serializer.
	 */
	private function configuration( string $kind, int $id, array $node, Run_Data_Bundle $bundle ): array {
		$recorded = $this->recorded_fields( $this->recorded_fields_json( $kind, $id, $bundle ) );
		if ( ! empty( $recorded ) ) {
			return $recorded;
		}
		return is_array( $node['fields'] ?? null ) ? $node['fields'] : array();
	}

	/**
	 * The item's stored field record for this run: `trigger_fields` on the latest trigger-log row that holds one
	 * (a trigger that fired more than once completed the run with its last fire), or `action_fields` on the
	 * action's row.
	 *
	 * @param string          $kind   Item kind.
	 * @param int             $id     Item post id.
	 * @param Run_Data_Bundle $bundle Run data.
	 * @return string The stored JSON, or '' when the run recorded none.
	 */
	private function recorded_fields_json( string $kind, int $id, Run_Data_Bundle $bundle ): string {
		if ( 'trigger' === $kind ) {
			foreach ( array_reverse( $bundle->trigger_rows_for( $id ) ) as $row ) {
				$json = (string) ( $bundle->trigger_meta( (int) $row['ID'], 'trigger_fields' ) ?? '' );
				if ( '' !== $json ) {
					return $json;
				}
			}
			return '';
		}
		$row = $bundle->action_row( $id );
		return null === $row ? '' : (string) ( $bundle->action_meta( (int) $row['ID'], 'action_fields' ) ?? '' );
	}

	/**
	 * Decode an action_fields/trigger_fields meta blob into Field_Output_Serializer input, values as stored.
	 * Stored `value.parsed` is ignored (it is the raw saved value, not a resolved one).
	 *
	 * @param string $fields_json Serialized/JSON action_fields or trigger_fields meta.
	 * @return array<string,array<string,mixed>> Input keyed by field code; empty when there are no fields.
	 */
	private function recorded_fields( string $fields_json ): array {
		if ( '' === $fields_json ) {
			return array();
		}
		$decoded = json_decode( $fields_json, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$input = array();
		foreach ( $decoded as $entry ) {
			$entry = (array) $entry;
			$code  = (string) ( $entry['field_code'] ?? '' );
			if ( '' === $code ) {
				continue;
			}
			$value          = $entry['value'] ?? array();
			$value          = is_array( $value ) ? $value : array( 'raw' => $value );
			$input[ $code ] = array(
				'type'          => (string) ( $entry['type'] ?? '' ),
				'value'         => (string) ( $value['raw'] ?? '' ),
				'readable'      => (string) ( $value['readable'] ?? '' ),
				'miscellaneous' => array( 'label' => (string) ( $entry['label'] ?? '' ) ),
			);
		}
		return $input;
	}

	/**
	 * Per-run FieldsOutput overlay from an action_fields/trigger_fields meta blob.
	 * Interpolates each stored value against the run token map, then hands
	 * the fields to Field_Output_Serializer so the wire shape, type guard,
	 * label resolution, and readable fallback all come from the one
	 * FieldsOutput producer.
	 *
	 * @param string               $fields_json Serialized/JSON action_fields meta.
	 * @param array<string,string> $tokens      Run token map (braced keys).
	 * @param Field_Context|null    $field_ctx  The item's field context, or null.
	 * @return array<string,mixed>|null Null when there are no fields (caller omits the key).
	 */
	private function run_fields_overlay( string $fields_json, array $tokens, ?Field_Context $field_ctx = null ): ?array {
		$input = $this->recorded_fields( $fields_json );
		if ( empty( $input ) ) {
			return null;
		}
		foreach ( $input as $code => $entry ) {
			$input[ $code ]['value'] = $this->interpolate( $entry['value'], $tokens );
			// The readable interpolates too: token-bearing fields store the
			// raw template here, and shipping it un-resolved makes the FE
			// suffix the value with the whole template ("value (template)").
			// Human readables (select labels) carry no braces and pass through.
			$input[ $code ]['readable'] = $this->interpolate( $entry['readable'], $tokens );
		}
		$serialized = $this->fields->serialize( $input, $field_ctx );
		return is_array( $serialized ) ? $serialized : null;
	}

	/**
	 * Interpolate token references in a raw value using the run token map.
	 * The map keys are stored WITH braces (`{{USER_EMAIL}}`,
	 * `{{300:USER_EMAIL:1234}}`), so strtr consumes them as-is — never
	 * re-wrap the keys.
	 *
	 * @param string             $raw    Raw value.
	 * @param array<string,string> $tokens Run token map (braced keys).
	 * @return string The interpolated value.
	 */
	private function interpolate( string $raw, array $tokens ): string {
		if ( '' === $raw || empty( $tokens ) || false === strpos( $raw, '{{' ) ) {
			return $raw;
		}
		return strtr( $raw, $tokens );
	}

	/**
	 * Item control payload — only for a finished action with an API log row, which is what resend replays.
	 *
	 * @param int             $id     Action post id.
	 * @param Run_Data_Bundle $bundle Run data.
	 * @return array<string,mixed>|null The control payload, or null when the row offers no action.
	 */
	private function control( int $id, Run_Data_Bundle $bundle ): ?array {
		$row = $bundle->action_row( $id );
		if ( null === $row ) {
			return null;
		}
		$action_log_id = (int) $row['ID'];
		if ( ! Automator_Status::finished( (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED ) ) || ! $bundle->has_api_log( $action_log_id ) ) {
			return null;
		}
		return array(
			'item_log_id' => (string) $action_log_id,
			'actions'     => array( 'resend' ),
		);
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
	 * Elapsed sub-object from two raw MySQL datetimes, or null when
	 * unparsable. Legal on item/block executions ONLY — never the root
	 * (strict schema) and never modifiers.
	 *
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @return array{seconds: int, human_readable: string}|null The elapsed sub-object, or null when unparsable.
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

	/**
	 * Every property a trigger run recorded, in the order it recorded them, as wire entries. Each
	 * `set_trigger_log_properties()` call stores its own row (a webhook's request details, the runner's
	 * execution engine), so the run's list is all of its rows together, as 7.x showed it.
	 *
	 * @since 8.0.0
	 *
	 * @param int             $trigger_log_id The trigger_log row ID.
	 * @param Run_Data_Bundle $bundle         Run data.
	 * @return array<int,array<string,mixed>> Wire property entries; empty when the run recorded none.
	 */
	private function trigger_properties( int $trigger_log_id, Run_Data_Bundle $bundle ): array {
		$entries = array();
		foreach ( $bundle->trigger_properties( $trigger_log_id ) as $stored ) {
			$decoded = automator_safe_unserialize( $stored );
			if ( is_array( $decoded ) ) {
				$entries = array_merge( $entries, array_values( $decoded ) );
			}
		}
		return $this->properties->map( $entries );
	}
}

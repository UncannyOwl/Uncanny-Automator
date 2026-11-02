<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Status;

use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Appearance;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Code;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Phase;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Tone;
use Uncanny_Automator\Automator_Status;

/**
 * Canonical source of the recipe-log status object the wire emits.
 *
 * The single place the backend materializes the recipe-log wire status
 * object. Holds the canonical code => descriptor table (the frontend
 * fixture's 21 rows + blocked-on-dependency, spec
 * docs/superpowers/specs/2026-07-02-recipe-log-status-projection-design.md §6)
 * and normalizes the two storage vocabularies that exist today: the
 * kebab-case strings Pro's loop tables persist (from_code) and the legacy
 * Automator_Status integers (from_legacy_int, Task 4).
 *
 * Deliberately NOT filterable, and deliberately independent of the
 * filterable Automator_Status::get_class_name() map — a site filter must
 * not be able to corrupt the wire. Parity with the frontend is enforced by
 * Log_Status_Registry_Test::test_from_code_matches_the_frontend_contract_table.
 */
final class Log_Status_Registry {

	/**
	 * Static-only class.
	 */
	private function __construct() {
	}

	/**
	 * Descriptor for a status code (loop-entry storage vocabulary included).
	 *
	 * @param string $code Status code, e.g. 'completed'.
	 *
	 * @return Log_Status
	 */
	public static function from_code( string $code ): Log_Status {
		$table = self::table();

		if ( isset( $table[ $code ] ) ) {
			$row = $table[ $code ];

			return new Log_Status( $code, $row['phase'], $row['appearance'], $row['label'], $row['icon'], $row['tone'] );
		}

		return self::fallback_from_string( $code );
	}

	/**
	 * Descriptor for a legacy Automator_Status integer (uap_recipe_log /
	 * uap_trigger_log / uap_action_log status columns).
	 *
	 * The map is frozen here on purpose — it matches the default
	 * Automator_Status::get_class_name() output but does not route through
	 * that filterable API, so a site filter cannot corrupt the wire.
	 *
	 * @param int $status Legacy status integer.
	 *
	 * @return Log_Status
	 */
	public static function from_legacy_int( int $status ): Log_Status {
		$map = self::legacy_int_map();

		if ( isset( $map[ $status ] ) ) {
			return self::from_code( $map[ $status ] );
		}

		return self::fallback_from_int( $status );
	}

	/**
	 * Frozen Automator_Status int => wire code map (13 entries).
	 *
	 * @return array<int, string>
	 */
	private static function legacy_int_map(): array {
		return array(
			Automator_Status::NOT_COMPLETED          => Log_Status_Code::NOT_COMPLETED,
			Automator_Status::COMPLETED              => Log_Status_Code::COMPLETED,
			Automator_Status::COMPLETED_WITH_ERRORS  => Log_Status_Code::COMPLETED_WITH_ERRORS,
			Automator_Status::IN_PROGRESS            => Log_Status_Code::IN_PROGRESS,
			Automator_Status::CANCELLED              => Log_Status_Code::CANCELLED,
			Automator_Status::SKIPPED                => Log_Status_Code::SKIPPED,
			Automator_Status::DID_NOTHING            => Log_Status_Code::COMPLETED_DO_NOTHING,
			Automator_Status::COMPLETED_AWAITING     => Log_Status_Code::COMPLETED_AWAITING,
			Automator_Status::COMPLETED_WITH_NOTICE  => Log_Status_Code::COMPLETED_WITH_NOTICE,
			Automator_Status::QUEUED                 => Log_Status_Code::QUEUED,
			Automator_Status::IN_PROGRESS_WITH_ERROR => Log_Status_Code::IN_PROGRESS_WITH_ERRORS,
			Automator_Status::FAILED                 => Log_Status_Code::FAILED,
			Automator_Status::BLOCKED_ON_DEPENDENCY  => Log_Status_Code::BLOCKED_ON_DEPENDENCY,
		);
	}

	/**
	 * Fallback descriptor for an unknown legacy integer. Phase comes from
	 * the best available signal: ints a site registered as finished (via
	 * the automator_status_finished filter) read as terminal_success;
	 * everything else reads as active. Label is deliberately untranslated
	 * (no msgid exists for an unknown value).
	 *
	 * @param int $status Raw unknown integer.
	 *
	 * @return Log_Status
	 */
	private static function fallback_from_int( int $status ): Log_Status {
		self::log_unknown( 'int', (string) $status );

		$phase = Automator_Status::finished( $status ) ? Log_Status_Phase::TERMINAL_SUCCESS : Log_Status_Phase::ACTIVE;

		return new Log_Status( (string) $status, $phase, Log_Status_Appearance::NORMAL, sprintf( 'Status %d', $status ), 'circle-dashed', Log_Status_Tone::NEUTRAL );
	}

	/**
	 * Debug notice for an unknown status input, once per value per request.
	 *
	 * @param string $kind  Input kind: 'code' or 'int'.
	 * @param string $value Raw input value.
	 *
	 * @return void
	 */
	private static function log_unknown( string $kind, string $value ): void {
		static $logged = array();

		$key = $kind . ':' . $value;

		if ( isset( $logged[ $key ] ) ) {
			return;
		}

		$logged[ $key ] = true;

		automator_log( sprintf( 'Unknown recipe-log status %s "%s"; shipping fallback descriptor.', $kind, $value ), 'Log_Status_Registry' );
	}

	/**
	 * The canonical table. Labels are i18n calls, so the table is built
	 * lazily and memoized for the request.
	 *
	 * @return array<string, array{phase: string, appearance: string, label: string, icon: string, tone: string}>
	 */
	private static function table(): array {
		static $table = null;

		if ( null !== $table ) {
			return $table;
		}

		$table = array(
			Log_Status_Code::COMPLETED               => self::row( Log_Status_Phase::TERMINAL_SUCCESS, Log_Status_Appearance::NORMAL, esc_html_x( 'Completed', 'Recipe log status', 'uncanny-automator' ), 'circle-check', Log_Status_Tone::SUCCESS ),
			Log_Status_Code::COMPLETED_WITH_ERRORS   => self::row( Log_Status_Phase::TERMINAL_SUCCESS, Log_Status_Appearance::NORMAL, esc_html_x( 'Completed with errors', 'Recipe log status', 'uncanny-automator' ), 'circle-exclamation', Log_Status_Tone::ERROR ),
			Log_Status_Code::COMPLETED_DO_NOTHING    => self::row( Log_Status_Phase::TERMINAL_SUCCESS, Log_Status_Appearance::NORMAL, esc_html_x( 'Completed, do nothing', 'Recipe log status', 'uncanny-automator' ), 'circle-check', Log_Status_Tone::SUCCESS ),
			Log_Status_Code::COMPLETED_DID_NOTHING   => self::row( Log_Status_Phase::TERMINAL_SUCCESS, Log_Status_Appearance::NORMAL, esc_html_x( 'Completed, did nothing', 'Recipe log status', 'uncanny-automator' ), 'circle-check', Log_Status_Tone::SUCCESS ),
			Log_Status_Code::COMPLETED_WITH_NOTICE   => self::row( Log_Status_Phase::TERMINAL_SUCCESS, Log_Status_Appearance::NORMAL, esc_html_x( 'Completed with notice', 'Recipe log status', 'uncanny-automator' ), 'circle-info', Log_Status_Tone::SUCCESS ),
			Log_Status_Code::COMPLETED_AWAITING      => self::row( Log_Status_Phase::ACTIVE, Log_Status_Appearance::NORMAL, esc_html_x( 'Completed, awaiting', 'Recipe log status', 'uncanny-automator' ), 'clock', Log_Status_Tone::WARNING ),
			Log_Status_Code::FAILED                  => self::row( Log_Status_Phase::TERMINAL_FAILURE, Log_Status_Appearance::NORMAL, esc_html_x( 'Failed', 'Recipe log status', 'uncanny-automator' ), 'circle-xmark', Log_Status_Tone::ERROR ),
			Log_Status_Code::SKIPPED                 => self::row( Log_Status_Phase::TERMINAL_FAILURE, Log_Status_Appearance::MUTED, esc_html_x( 'Skipped', 'Recipe log status', 'uncanny-automator' ), 'circle-minus', Log_Status_Tone::NEUTRAL ),
			Log_Status_Code::IN_PROGRESS             => self::row( Log_Status_Phase::ACTIVE, Log_Status_Appearance::NORMAL, esc_html_x( 'In progress', 'Recipe log status', 'uncanny-automator' ), 'spinner-third', Log_Status_Tone::WARNING ),
			Log_Status_Code::IN_PROGRESS_WITH_ERRORS => self::row( Log_Status_Phase::ACTIVE, Log_Status_Appearance::NORMAL, esc_html_x( 'In progress with errors', 'Recipe log status', 'uncanny-automator' ), 'spinner-third', Log_Status_Tone::ERROR ),
			Log_Status_Code::QUEUED                  => self::row( Log_Status_Phase::WAITING, Log_Status_Appearance::NORMAL, esc_html_x( 'Queued', 'Recipe log status', 'uncanny-automator' ), 'hourglass-half', Log_Status_Tone::WARNING ),
			Log_Status_Code::SCHEDULED               => self::row( Log_Status_Phase::ACTIVE, Log_Status_Appearance::NORMAL, esc_html_x( 'Scheduled', 'Recipe log status', 'uncanny-automator' ), 'clock', Log_Status_Tone::WARNING ),
			Log_Status_Code::DELAYED                 => self::row( Log_Status_Phase::ACTIVE, Log_Status_Appearance::NORMAL, esc_html_x( 'Delayed', 'Recipe log status', 'uncanny-automator' ), 'clock', Log_Status_Tone::WARNING ),
			Log_Status_Code::CANCELLED               => self::row( Log_Status_Phase::TERMINAL_FAILURE, Log_Status_Appearance::NORMAL, esc_html_x( 'Cancelled', 'Recipe log status', 'uncanny-automator' ), 'circle-xmark', Log_Status_Tone::ERROR ),
			Log_Status_Code::PAUSED                  => self::row( Log_Status_Phase::PAUSED, Log_Status_Appearance::NORMAL, esc_html_x( 'Paused', 'Recipe log status', 'uncanny-automator' ), 'pause', Log_Status_Tone::WARNING ),
			Log_Status_Code::ENDED                   => self::row( Log_Status_Phase::TERMINAL_FAILURE, Log_Status_Appearance::NORMAL, esc_html_x( 'Ended', 'Recipe log status', 'uncanny-automator' ), 'stop', Log_Status_Tone::NEUTRAL ),
			Log_Status_Code::NOT_COMPLETED           => self::row( Log_Status_Phase::WAITING, Log_Status_Appearance::MUTED, esc_html_x( 'Not completed', 'Recipe log status', 'uncanny-automator' ), 'circle-dashed', Log_Status_Tone::NEUTRAL ),
			Log_Status_Code::NOT_EVALUATED           => self::row( Log_Status_Phase::WAITING, Log_Status_Appearance::MUTED, esc_html_x( 'Not evaluated', 'Recipe log status', 'uncanny-automator' ), 'circle-dashed', Log_Status_Tone::MUTED ),
			Log_Status_Code::CONDITION_MET           => self::row( Log_Status_Phase::TERMINAL_SUCCESS, Log_Status_Appearance::NORMAL, esc_html_x( 'Met', 'Recipe log status', 'uncanny-automator' ), 'circle-check', Log_Status_Tone::SUCCESS ),
			Log_Status_Code::CONDITION_NOT_MET       => self::row( Log_Status_Phase::TERMINAL_FAILURE, Log_Status_Appearance::NORMAL, esc_html_x( 'Not met', 'Recipe log status', 'uncanny-automator' ), 'circle-xmark', Log_Status_Tone::ERROR ),
			Log_Status_Code::CONDITION_NOT_EVALUATED => self::row( Log_Status_Phase::WAITING, Log_Status_Appearance::MUTED, esc_html_x( 'Not evaluated', 'Recipe log status', 'uncanny-automator' ), 'circle-minus', Log_Status_Tone::MUTED ),
			Log_Status_Code::BLOCKED_ON_DEPENDENCY   => self::row( Log_Status_Phase::WAITING, Log_Status_Appearance::MUTED, esc_html_x( 'Blocked on dependency', 'Recipe log status', 'uncanny-automator' ), 'circle-dashed', Log_Status_Tone::NEUTRAL ),
		);

		return $table;
	}

	/**
	 * Table-row shape helper.
	 *
	 * @param string $phase      Lifecycle phase.
	 * @param string $appearance Row prominence.
	 * @param string $label      Localized display label.
	 * @param string $icon       Design-system icon code.
	 * @param string $tone       Chip color tone.
	 *
	 * @return array{phase: string, appearance: string, label: string, icon: string, tone: string}
	 */
	private static function row( string $phase, string $appearance, string $label, string $icon, string $tone ): array {
		return array(
			'phase'      => $phase,
			'appearance' => $appearance,
			'label'      => $label,
			'icon'       => $icon,
			'tone'       => $tone,
		);
	}

	/**
	 * Fallback descriptor for an unknown status string. Never throws — one
	 * exotic status must not break a whole log render. The code ships
	 * verbatim (the wire contract is open-code); the empty string degrades
	 * to 'unknown' because the VO forbids empty codes. Label is
	 * deliberately untranslated (no msgid exists for an unknown value).
	 * The label is esc_html()'d so fallback labels follow the same
	 * arrives-escape-safe convention as the esc_html_x()'d known labels.
	 *
	 * @param string $code Raw unknown code.
	 *
	 * @return Log_Status
	 */
	private static function fallback_from_string( string $code ): Log_Status {
		self::log_unknown( 'code', $code );

		$safe_code = '' === $code ? 'unknown' : $code;
		$label     = esc_html( ucfirst( str_replace( '-', ' ', $safe_code ) ) );

		return new Log_Status( $safe_code, Log_Status_Phase::ACTIVE, Log_Status_Appearance::NORMAL, $label, 'circle-dashed', Log_Status_Tone::NEUTRAL );
	}
}

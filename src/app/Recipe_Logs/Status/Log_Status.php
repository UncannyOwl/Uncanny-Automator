<?php
declare(strict_types=1);
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are internal errors, not user-facing output.

namespace Uncanny_Automator\App\Recipe_Logs\Status;

use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Appearance;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Phase;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Tone;

/**
 * Log_Status Value Object.
 *
 * The six-field status object the recipe-log wire ships on every node, run,
 * iteration, and status_breakdown entry: `{ code, phase, appearance, label,
 * icon, tone }`. `code` is open (backend-owned); phase / appearance / tone
 * are validated against the closed enum classes; the frontend renders the
 * chip from this object verbatim and branches only on phase.
 *
 * Build instances through {@see Log_Status_Registry} — direct construction
 * is for the registry and tests.
 */
final class Log_Status {

	/**
	 * Status identifier (open string).
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Lifecycle phase (closed enum).
	 *
	 * @var string
	 */
	private $phase;

	/**
	 * Row prominence (closed enum).
	 *
	 * @var string
	 */
	private $appearance;

	/**
	 * Localized display label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Design-system icon code.
	 *
	 * @var string
	 */
	private $icon;

	/**
	 * Chip color tone (closed enum).
	 *
	 * @var string
	 */
	private $tone;

	/**
	 * Validates and freezes the six wire fields.
	 *
	 * @param string $code       Status identifier; non-empty.
	 * @param string $phase      One of Log_Status_Phase.
	 * @param string $appearance One of Log_Status_Appearance.
	 * @param string $label      Localized display label; non-empty.
	 * @param string $icon       Design-system icon code; non-empty.
	 * @param string $tone       One of Log_Status_Tone.
	 *
	 * @throws \InvalidArgumentException When any invariant fails.
	 */
	public function __construct( string $code, string $phase, string $appearance, string $label, string $icon, string $tone ) {
		if ( '' === $code ) {
			throw new \InvalidArgumentException( 'Log status code must be a non-empty string.' );
		}
		if ( ! Log_Status_Phase::is_valid( $phase ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid log status phase "%s".', $phase ) );
		}
		if ( ! Log_Status_Appearance::is_valid( $appearance ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid log status appearance "%s".', $appearance ) );
		}
		if ( '' === $label ) {
			throw new \InvalidArgumentException( 'Log status label must be a non-empty string.' );
		}
		if ( '' === $icon ) {
			throw new \InvalidArgumentException( 'Log status icon must be a non-empty string.' );
		}
		if ( ! Log_Status_Tone::is_valid( $tone ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid log status tone "%s".', $tone ) );
		}

		$this->code       = $code;
		$this->phase      = $phase;
		$this->appearance = $appearance;
		$this->label      = $label;
		$this->icon       = $icon;
		$this->tone       = $tone;
	}

	/**
	 * Status identifier.
	 *
	 * @return string
	 */
	public function get_code(): string {
		return $this->code;
	}

	/**
	 * Lifecycle phase.
	 *
	 * @return string
	 */
	public function get_phase(): string {
		return $this->phase;
	}

	/**
	 * Whether this status is terminal (phase terminal_success or
	 * terminal_failure). The single source for "did this finish" questions
	 * on wire statuses — never re-encode terminality as a code list
	 * elsewhere: the phase already carries it, and the unknown-code
	 * fallback descriptor is phase active, so exotic statuses read as
	 * pending for free.
	 *
	 * @return bool True when the phase is terminal.
	 */
	public function is_terminal(): bool {
		return Log_Status_Phase::TERMINAL_SUCCESS === $this->phase || Log_Status_Phase::TERMINAL_FAILURE === $this->phase;
	}

	/**
	 * Row prominence.
	 *
	 * @return string
	 */
	public function get_appearance(): string {
		return $this->appearance;
	}

	/**
	 * Localized display label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Design-system icon code.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return $this->icon;
	}

	/**
	 * Chip color tone.
	 *
	 * @return string
	 */
	public function get_tone(): string {
		return $this->tone;
	}

	/**
	 * The wire shape, keys in FE contract order.
	 *
	 * @return array<string, string>
	 */
	public function to_array(): array {
		return array(
			'code'       => $this->code,
			'phase'      => $this->phase,
			'appearance' => $this->appearance,
			'label'      => $this->label,
			'icon'       => $this->icon,
			'tone'       => $this->tone,
		);
	}
}

<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Status;

use InvalidArgumentException;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Appearance;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Phase;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Tone;

/**
 * The status object that says how a resend ended.
 *
 * `POST action-log/{id}/resend` answers with an `outcome` string from
 * `Resend_Action_Service` and, beside it, this status object, ready for the
 * frontend to draw as the action's chip. There is one per outcome, and its
 * `code` is the outcome itself.
 *
 * Deliberately not part of `Log_Status_Registry`: these describe a resend,
 * not a stored log row, so they must never appear among the statuses a list
 * can be filtered by.
 *
 * @since 8.1.0
 */
final class Resend_Outcome_Status {

	/**
	 * The stored request was replayed and the app accepted it.
	 *
	 * @var string
	 */
	const RESENT = 'resent';

	/**
	 * The stored request was replayed and the app refused it again.
	 *
	 * @var string
	 */
	const FAILED = 'failed';

	/**
	 * Nothing was replayed (no stored request, a job still running, a send
	 * whose outcome is unknown).
	 *
	 * @var string
	 */
	const SKIPPED = 'skipped';

	/**
	 * Static-only class.
	 */
	private function __construct() {
	}

	/**
	 * The status object for a resend outcome.
	 *
	 * @param string $outcome One of the outcomes `Resend_Action_Service` returns.
	 *
	 * @return Log_Status
	 *
	 * @throws InvalidArgumentException For an outcome the service never returns.
	 */
	public static function for_outcome( string $outcome ): Log_Status {
		switch ( $outcome ) {
			case self::RESENT:
				return new Log_Status( self::RESENT, Log_Status_Phase::TERMINAL_SUCCESS, Log_Status_Appearance::NORMAL, esc_html_x( 'Resent', 'Resend outcome status', 'uncanny-automator' ), 'circle-check', Log_Status_Tone::SUCCESS );
			case self::FAILED:
				return new Log_Status( self::FAILED, Log_Status_Phase::TERMINAL_FAILURE, Log_Status_Appearance::NORMAL, esc_html_x( 'Resend failed', 'Resend outcome status', 'uncanny-automator' ), 'circle-exclamation', Log_Status_Tone::ERROR );
			case self::SKIPPED:
				return new Log_Status( self::SKIPPED, Log_Status_Phase::TERMINAL_FAILURE, Log_Status_Appearance::NORMAL, esc_html_x( 'Resend skipped', 'Resend outcome status', 'uncanny-automator' ), 'circle-minus', Log_Status_Tone::WARNING );
		}

		throw new InvalidArgumentException( sprintf( 'Unknown resend outcome "%s".', esc_html( $outcome ) ) );
	}
}

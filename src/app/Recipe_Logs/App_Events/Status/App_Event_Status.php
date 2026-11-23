<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\App_Events\Status;

use InvalidArgumentException;
use Uncanny_Automator\App\Recipe_Logs\Status\Log_Status;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Appearance;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Phase;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Tone;

/**
 * Where an app event the site received stands, in the Logs page's words.
 *
 * Maps the platform inbox's own lifecycle (`pending`, `processing`,
 * `completed`, `failed`) to the wire codes the App events tab sends and
 * filters by: `received`, `handling`, `handled` and `failed`. "Handled"
 * means Automator passed the event to the app's triggers; whether a recipe
 * ran is what the entry's runs say.
 *
 * @since 8.1.0
 */
final class App_Event_Status {

	/**
	 * Wire code per inbox status.
	 *
	 * @var array<string, string>
	 */
	private const CODES = array(
		'pending'    => 'received',
		'processing' => 'handling',
		'completed'  => 'handled',
		'failed'     => 'failed',
	);

	/**
	 * Every wire code, in the order the tab offers them.
	 *
	 * @return string[]
	 */
	public static function codes(): array {
		return array_values( self::CODES );
	}

	/**
	 * Every inbox status, ordered A to Z by the label the tab shows for it,
	 * so a sort by status follows the words the person reads.
	 *
	 * @since 8.1.0
	 *
	 * @return string[]
	 */
	public static function inbox_statuses_by_label(): array {
		$labels = array();

		foreach ( array_keys( self::CODES ) as $inbox_status ) {
			$labels[ $inbox_status ] = self::for_inbox_status( $inbox_status )->get_label();
		}

		uasort( $labels, static fn( string $a, string $b ): int => strnatcasecmp( $a, $b ) );

		return array_map( 'strval', array_keys( $labels ) );
	}

	/**
	 * The inbox status a wire code stands for.
	 *
	 * @param string $code Wire code.
	 *
	 * @return string|null The inbox status, or null for an unknown code.
	 */
	public static function inbox_status_for_code( string $code ): ?string {
		$status = array_search( $code, self::CODES, true );

		return false === $status ? null : (string) $status;
	}

	/**
	 * The status chip for an inbox status.
	 *
	 * @param string $inbox_status The row's `status` column.
	 *
	 * @return Log_Status
	 *
	 * @throws InvalidArgumentException For a status the inbox never writes.
	 */
	public static function for_inbox_status( string $inbox_status ): Log_Status {
		switch ( $inbox_status ) {
			case 'pending':
				return new Log_Status( 'received', Log_Status_Phase::WAITING, Log_Status_Appearance::NORMAL, esc_html_x( 'Received', 'App event status', 'uncanny-automator' ), 'clock', Log_Status_Tone::WARNING );
			case 'processing':
				return new Log_Status( 'handling', Log_Status_Phase::ACTIVE, Log_Status_Appearance::NORMAL, esc_html_x( 'Handling', 'App event status', 'uncanny-automator' ), 'spinner-third', Log_Status_Tone::WARNING );
			case 'completed':
				return new Log_Status( 'handled', Log_Status_Phase::TERMINAL_SUCCESS, Log_Status_Appearance::NORMAL, esc_html_x( 'Handled', 'App event status', 'uncanny-automator' ), 'circle-check', Log_Status_Tone::SUCCESS );
			case 'failed':
				return new Log_Status( 'failed', Log_Status_Phase::TERMINAL_FAILURE, Log_Status_Appearance::NORMAL, esc_html_x( 'Failed', 'App event status', 'uncanny-automator' ), 'circle-exclamation', Log_Status_Tone::ERROR );
		}

		throw new InvalidArgumentException( sprintf( 'Unknown inbox status "%s".', esc_html( $inbox_status ) ) );
	}
}

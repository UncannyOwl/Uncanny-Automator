<?php
/**
 * Keep command output within what an AI conversation can carry.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Output;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;

/**
 * Long output is shortened and always says so. A partial result is never
 * presented as complete, and JSON is never cut into something unparsable.
 */
final class Output_Budget {

	public const DEFAULT_MAX_BYTES = 60000;

	private int $max_bytes;

	/**
	 * Set the limit.
	 *
	 * @param int $max_bytes Largest output returned in full.
	 */
	public function __construct( int $max_bytes = self::DEFAULT_MAX_BYTES ) {
		$this->max_bytes = $max_bytes;
	}

	/**
	 * Return the output whole, or shortened with a notice.
	 *
	 * @param string $output Printed output.
	 * @param string $format Output format, when the command lists items.
	 * @return Command_Result
	 *
	 * @throws Command_Error When JSON output is over the limit.
	 */
	public function apply( string $output, string $format = Formats::TABLE ): Command_Result {
		$bytes = strlen( $output );
		if ( $bytes <= $this->max_bytes ) {
			return Command_Result::success( $output );
		}

		if ( Formats::JSON === $format ) {
			throw new Command_Error( sprintf( 'The JSON output is %d bytes, over the %d-byte limit. Narrow the command with its filters or --fields, or use --format=table or --format=count.', $bytes, $this->max_bytes ) );
		}

		$cut  = $this->cut( $output, $this->max_bytes );
		$last = strrpos( $cut, "\n" );
		if ( false === $last ) {
			return Command_Result::truncated(
				$cut . "\n" . sprintf( 'Output truncated: showing the first %d of %d bytes. Narrow the command with its filters, --fields or --format=count.', strlen( $cut ), $bytes )
			);
		}

		$cut = substr( $cut, 0, $last );

		return Command_Result::truncated(
			$cut . "\n" . sprintf( 'Output truncated: showing %d of %d lines. Narrow the command with its filters, --fields or --format=count.', substr_count( $cut, "\n" ) + 1, substr_count( $output, "\n" ) + 1 )
		);
	}

	/**
	 * Cut at a byte limit without splitting a UTF-8 character.
	 *
	 * @param string $text  Text.
	 * @param int    $limit Byte limit.
	 * @return string
	 */
	private function cut( string $text, int $limit ): string {
		if ( function_exists( 'mb_strcut' ) ) {
			return mb_strcut( $text, 0, $limit, 'UTF-8' );
		}

		// Without mbstring, step back over at most one incomplete trailing
		// sequence; invalid bytes earlier in the text are left as they are.
		$cut    = substr( $text, 0, $limit );
		$length = strlen( $cut );
		for ( $back = 1; $back <= 3 && $back <= $length; $back++ ) {
			$byte = ord( $cut[ $length - $back ] );
			if ( $byte < 0x80 ) {
				break;
			}
			if ( $byte >= 0xC0 ) {
				$needed = $byte >= 0xF0 ? 4 : ( $byte >= 0xE0 ? 3 : 2 );
				if ( $back < $needed ) {
					$cut = substr( $cut, 0, -$back );
				}
				break;
			}
		}

		return $cut;
	}
}

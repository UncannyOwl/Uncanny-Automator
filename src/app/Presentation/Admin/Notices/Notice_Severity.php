<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

use InvalidArgumentException;

/**
 * How serious a notice is, and so where it goes in the stack: errors first,
 * then warnings, successes, information.
 *
 * @since 8.0.0
 */
final class Notice_Severity {

	public const ERROR   = 'error';
	public const WARNING = 'warning';
	public const SUCCESS = 'success';
	public const INFO    = 'info';

	/**
	 * Every severity, most serious first.
	 */
	private const ORDER = array( self::ERROR, self::WARNING, self::SUCCESS, self::INFO );

	/**
	 * Whether the value is a severity.
	 *
	 * @param string $severity The value.
	 *
	 * @return bool
	 */
	public static function is_valid( string $severity ): bool {

		return in_array( $severity, self::ORDER, true );
	}

	/**
	 * The severity's place in the stack: 0 for an error, up to 3 for information.
	 *
	 * @param string $severity One of the constants.
	 *
	 * @return int
	 *
	 * @throws InvalidArgumentException For a value that is not a severity.
	 */
	public static function rank( string $severity ): int {

		$rank = array_search( $severity, self::ORDER, true );
		if ( false === $rank ) {
			throw new InvalidArgumentException( sprintf( 'Unknown notice severity "%s".', $severity ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer message, never printed.
		}

		return (int) $rank;
	}
}

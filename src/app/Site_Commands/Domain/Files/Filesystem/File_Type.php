<?php
/**
 * The type of one filesystem entry.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem;

use InvalidArgumentException;

/**
 * A regular file, a directory, a symbolic link, or anything else.
 */
final class File_Type {

	/** A regular file. */
	public const FILE = 'file';

	/** A directory. */
	public const DIRECTORY = 'directory';

	/** A symbolic link. */
	public const LINK = 'link';

	/** A FIFO, a socket, a device. */
	public const OTHER = 'other';

	/**
	 * The single-letter code `find -type` and `ls -l` use.
	 *
	 * @param string $type One of the File_Type constants.
	 * @return string
	 *
	 * @throws InvalidArgumentException When the type is not one of the constants.
	 */
	public static function code( string $type ): string {
		switch ( $type ) {
			case self::FILE:
				return 'f';
			case self::DIRECTORY:
				return 'd';
			case self::LINK:
				return 'l';
			case self::OTHER:
				return 'o';
		}

		throw new InvalidArgumentException( 'Unknown file type: ' . $type );
	}

	/**
	 * The first character of the `-rw-r--r--` form.
	 *
	 * @param string $type One of the File_Type constants.
	 * @return string
	 *
	 * @throws InvalidArgumentException When the type is not one of the constants.
	 */
	public static function mode_char( string $type ): string {
		switch ( $type ) {
			case self::FILE:
				return '-';
			case self::DIRECTORY:
				return 'd';
			case self::LINK:
				return 'l';
			case self::OTHER:
				return '?';
		}

		throw new InvalidArgumentException( 'Unknown file type: ' . $type );
	}
}

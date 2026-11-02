<?php
/**
 * Whether a file holds text or binary content.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem;

/**
 * The GNU heuristic: a NUL byte in the first sample means binary. Archives
 * and other containers are binary too, whatever their name, even those
 * whose headers are plain text, such as ar, cpio or PDF: a container can
 * hold a configuration file past the bytes the content checks read.
 */
final class Content_Kind {

	/** No content at all. */
	public const EMPTY = 'empty';

	/** Text: no NUL byte in the sample. */
	public const TEXT = 'text';

	/** Binary: a NUL byte in the sample. */
	public const BINARY = 'binary';

	/** How many leading bytes the heuristic looks at. */
	public const SAMPLE_BYTES = 8192;

	/**
	 * Archive, compression and container signatures at the start of a file.
	 *
	 * @var string[]
	 */
	private const ARCHIVE_SIGNATURES = array(
		"!<arch>\n",          // ar, .a, .deb
		'070701',             // cpio, new ASCII
		'070702',             // cpio, new ASCII with checksums
		'070707',             // cpio, old ASCII
		"PK\x03\x04",          // zip, jar, docx
		"PK\x05\x06",          // empty zip
		"PK\x07\x08",          // spanned zip
		"\x1F\x8B",            // gzip
		'BZh',                // bzip2
		"\xFD7zXZ\x00",        // xz
		"7z\xBC\xAF\x27\x1C",   // 7-Zip
		"Rar!\x1A\x07",        // RAR
		"\x28\xB5\x2F\xFD",     // zstd
		"\x1F\x9D",            // compress
		'LZIP',               // lzip
		"\x89LZO",             // lzop
		"MSCF\x00\x00\x00\x00", // cab
		'xar!',               // xar
		'%PDF-',              // PDF, which can carry attached files as plain text
	);

	/** Where tar keeps its magic word. */
	private const TAR_MAGIC_OFFSET = 257;

	/**
	 * The kind of content the sample shows.
	 *
	 * @param string $sample The first bytes of a file.
	 * @return string One of the Content_Kind constants.
	 */
	public static function detect( string $sample ): string {
		if ( '' === $sample ) {
			return self::EMPTY;
		}

		return false !== strpos( $sample, "\0" ) || self::is_archive( $sample ) ? self::BINARY : self::TEXT;
	}

	/**
	 * Whether the sample starts an archive or compressed file.
	 *
	 * @param string $sample The first bytes of a file.
	 * @return bool
	 */
	private static function is_archive( string $sample ): bool {
		foreach ( self::ARCHIVE_SIGNATURES as $signature ) {
			if ( 0 === strncmp( $sample, $signature, strlen( $signature ) ) ) {
				return true;
			}
		}

		return 'ustar' === substr( $sample, self::TAR_MAGIC_OFFSET, 5 );
	}
}

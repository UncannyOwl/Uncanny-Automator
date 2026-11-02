<?php
/**
 * Lexical path normalisation.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox;

/**
 * Lexical path normalisation. Collapses separators and dot segments without
 * touching the filesystem.
 */
final class Path_Normalizer {

	/**
	 * The path with separators and dot segments collapsed.
	 *
	 * @param string $path Any path.
	 * @return string
	 */
	public static function normalize( string $path ): string {
		$absolute = 0 === strpos( $path, '/' );
		$segments = array();

		foreach ( explode( '/', $path ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				if ( array() !== $segments && '..' !== end( $segments ) ) {
					array_pop( $segments );
				} elseif ( ! $absolute ) {
					$segments[] = '..';
				}
				continue;
			}
			$segments[] = $segment;
		}

		$joined = implode( '/', $segments );

		if ( $absolute ) {
			return '/' . $joined;
		}

		return '' === $joined ? '.' : $joined;
	}

	/**
	 * Join a base and a relative path with one separator.
	 *
	 * @param string $base     The base directory.
	 * @param string $relative The relative path.
	 * @return string
	 */
	public static function join( string $base, string $relative ): string {
		return rtrim( $base, '/' ) . '/' . ltrim( $relative, '/' );
	}
}

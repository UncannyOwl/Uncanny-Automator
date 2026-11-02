<?php
/**
 * Flush output buffers at shutdown without removing sealed ones.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure;

/**
 * A site command seals its capture buffer so plugin code cannot remove it.
 * WordPress's wp_ob_end_flush_all() would try to remove every buffer at
 * shutdown and raise a notice for each sealed one. This does the same work:
 * it ends the buffers it may remove and flushes the first one it may not,
 * which PHP then ends itself, without a notice, when the request finishes.
 */
final class Output_Buffers {

	/**
	 * Flush every buffer down to the first one that cannot be removed.
	 *
	 * @return void
	 */
	public static function flush_all(): void {
		for ( $levels = ob_get_level(); $levels > 0; $levels-- ) {
			$status = ob_get_status();
			$flags  = is_array( $status ) ? (int) ( $status['flags'] ?? 0 ) : 0;
			if ( 0 === ( $flags & PHP_OUTPUT_HANDLER_REMOVABLE ) ) {
				if ( 0 !== ( $flags & PHP_OUTPUT_HANDLER_FLUSHABLE ) ) {
					ob_flush();
				}
				return;
			}
			ob_end_flush();
		}
	}
}

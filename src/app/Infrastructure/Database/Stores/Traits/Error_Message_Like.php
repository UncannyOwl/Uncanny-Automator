<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores\Traits;

/**
 * The LIKE pattern behind Error_Message_Rewrite_Store::scan_error_messages(), shared by the stores
 * that implement it. The using class holds `$this->wpdb`.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator\App\Infrastructure\Database\Stores\Traits
 */
trait Error_Message_Like {

	/**
	 * A LIKE pattern matching text that contains every fragment, in order.
	 *
	 * @since 8.0.0
	 *
	 * @param int      $rows      How many rows the scan reads.
	 * @param string[] $fragments Literal text, in order.
	 *
	 * @return string
	 *
	 * @throws \InvalidArgumentException When `$rows` is below 1, `$fragments` is empty or a fragment is not a non-empty string.
	 */
	private function error_message_like( int $rows, array $fragments ): string {

		if ( 1 > $rows ) {
			throw new \InvalidArgumentException( 'A scan reads at least one row.' );
		}

		if ( array() === $fragments ) {
			throw new \InvalidArgumentException( 'A scan needs at least one fragment to look for.' );
		}

		$escaped = array();

		foreach ( $fragments as $fragment ) {
			if ( ! is_string( $fragment ) || '' === $fragment ) {
				throw new \InvalidArgumentException( 'Each fragment is a non-empty string.' );
			}

			$escaped[] = $this->wpdb->esc_like( $fragment );
		}

		return '%' . implode( '%', $escaped ) . '%';
	}
}

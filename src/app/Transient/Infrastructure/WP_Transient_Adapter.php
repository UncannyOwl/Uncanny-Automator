<?php
/**
 * WordPress transient adapter.
 *
 * @package Uncanny_Automator
 * @since 7.5.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transient\Infrastructure;

use Uncanny_Automator\App\Transient\Ports\Transient_Port;

/**
 * Stores Automator transients through the WordPress transient API.
 */
class WP_Transient_Adapter implements Transient_Port {

	/**
	 * Add a transient.
	 *
	 * WordPress uses set_transient() for create and update operations.
	 *
	 * @param string $key        The transient key.
	 * @param mixed  $value      The transient value.
	 * @param int    $expiration The expiration time in seconds.
	 *
	 * @return bool
	 */
	public function add( string $key, $value, int $expiration = 0 ): bool {
		return set_transient( $key, $value, $expiration );
	}

	/**
	 * Edit a transient.
	 *
	 * WordPress uses set_transient() for create and update operations.
	 *
	 * @param string $key        The transient key.
	 * @param mixed  $value      The transient value.
	 * @param int    $expiration The expiration time in seconds.
	 *
	 * @return bool
	 */
	public function edit( string $key, $value, int $expiration = 0 ): bool {
		return set_transient( $key, $value, $expiration );
	}

	/**
	 * Delete a transient.
	 *
	 * @param string $key The transient key.
	 *
	 * @return bool
	 */
	public function delete( string $key ): bool {
		return delete_transient( $key );
	}
}

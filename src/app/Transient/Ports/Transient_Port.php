<?php
/**
 * Transient storage port.
 *
 * @package Uncanny_Automator
 * @since 7.5.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transient\Ports;

/**
 * Defines transient storage operations.
 *
 * TODO: Migrate existing transient callers to application use cases that use this port.
 * During migration, callers must treat add() as an upsert operation.
 */
interface Transient_Port {

	/**
	 * Add a transient.
	 *
	 * @param string $key        The transient key.
	 * @param mixed  $value      The transient value.
	 * @param int    $expiration The expiration time in seconds.
	 *
	 * @return bool
	 */
	public function add( string $key, $value, int $expiration = 0 ): bool;

	/**
	 * Edit a transient.
	 *
	 * @param string $key        The transient key.
	 * @param mixed  $value      The transient value.
	 * @param int    $expiration The expiration time in seconds.
	 *
	 * @return bool
	 */
	public function edit( string $key, $value, int $expiration = 0 ): bool;

	/**
	 * Delete a transient.
	 *
	 * @param string $key The transient key.
	 *
	 * @return bool
	 */
	public function delete( string $key ): bool;
}

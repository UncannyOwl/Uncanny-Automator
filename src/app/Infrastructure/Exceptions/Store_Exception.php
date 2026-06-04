<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Exceptions;

/**
 * Exception for database store validation and integrity errors.
 *
 * Thrown by wpdb stores when data invariants fail (missing required
 * fields, invalid post objects, failed updates, etc.).
 *
 * @package Uncanny_Automator\App\Infrastructure\Exceptions
 * @since   7.3
 */
class Store_Exception extends \Exception {
}

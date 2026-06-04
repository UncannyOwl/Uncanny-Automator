<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Exceptions;

/**
 * Exception for external service / API errors.
 *
 * Thrown by license manager, credit manager, and API client when
 * external operations fail (invalid license, insufficient credits,
 * API timeouts, etc.).
 *
 * @package Uncanny_Automator\App\Infrastructure\Exceptions
 * @since   7.3
 */
class Api_Exception extends \Exception {
}

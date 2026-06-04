<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Bridge\Automator_Error_Bridge;
use Uncanny_Automator\App\Bridge\Error_Bridge;

/**
 * Error handler — pipeline-facing wrapper around wp_error and error_message
 * operations.
 *
 * All legacy `Automator()->wp_error->*()` and `->error_message->get()` calls
 * are funneled through {@see Error_Bridge}, the anti-corruption boundary in
 * `src/app/bridge/`.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.3
 */
class Error_Handler {

	/**
	 * Anti-corruption boundary to the legacy error facades.
	 *
	 * @var Error_Bridge
	 */
	private Error_Bridge $errors;

	/**
	 * @param Error_Bridge|null $errors Optional bridge override (tests).
	 */
	public function __construct( ?Error_Bridge $errors = null ) {
		$this->errors = $errors ?? new Automator_Error_Bridge();
	}

	/**
	 * @param string $context The error context key.
	 * @param string $message The error message.
	 * @param mixed  $data    Optional error data.
	 *
	 * @return void
	 */
	public function add_error( string $context, string $message, $data = null ): void {
		$this->errors->add_error( $context, $message, $data );
	}

	/**
	 * @param string $context The error context key.
	 *
	 * @return array
	 */
	public function get_messages( string $context ): array {
		return $this->errors->get_error_messages( $context );
	}

	/**
	 * @param string $key The error message key (e.g. 'action-not-active').
	 *
	 * @return string The error message string.
	 */
	public function get_error_message( string $key ): string {
		return $this->errors->get_error_message( $key );
	}
}

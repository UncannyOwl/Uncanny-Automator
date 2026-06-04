<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the legacy error/message facades.
 *
 * Wraps `Automator()->wp_error->add_error()`, `->wp_error->get_messages()`,
 * and `Automator()->error_message->get()` so the recipe runner can capture
 * and surface errors through one contract.
 *
 * @since 7.4.0
 */
interface Error_Bridge {

	/**
	 * Record an error against a context.
	 *
	 * Wraps `Automator()->wp_error->add_error( $context, $message, $data )`.
	 *
	 * @param string $context Logical context (e.g. an action code or stage name).
	 * @param string $message Human-readable error message.
	 * @param mixed  $data    Optional data payload (often the calling object).
	 * @return void
	 */
	public function add_error( string $context, string $message, $data = null ): void;

	/**
	 * Get all error messages recorded against a context.
	 *
	 * Wraps `Automator()->wp_error->get_messages( $context )`.
	 *
	 * @param string $context Logical context.
	 * @return array<int,string> Error messages (empty array when none).
	 */
	public function get_error_messages( string $context ): array;

	/**
	 * Look up a translatable error template by key.
	 *
	 * Wraps `Automator()->error_message->get( $key )`.
	 *
	 * @param string $key Error template key.
	 * @return string Localised error message string.
	 */
	public function get_error_message( string $key ): string;
}

<?php
/**
 * Load Error Handler
 *
 * Collects integration loading errors into a transient for batched admin notice display.
 * Replaces the old wp_mail()-based error reporting which fired during plugin loading
 * before SMTP plugins were ready.
 *
 * @package Uncanny_Automator\Integration_Loader
 * @since   7.2
 */

namespace Uncanny_Automator\Integration_Loader;

/**
 * Class Load_Error_Handler
 *
 * Single responsibility: collect integration load errors and display them
 * as a dismissible admin notice with a copyable support report.
 */
class Load_Error_Handler {

	/**
	 * Transient key for batched error storage.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'automator_load_errors';


	/**
	 * How long to keep batched load errors before they expire on their own.
	 *
	 * Short window so a fix (plugin update, dependency restored) clears the
	 * notice quickly without requiring an explicit dismissal.
	 *
	 * @var int
	 */
	const TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Whether the current request recorded any new load errors.
	 *
	 * Used by maybe_clear_on_clean_load() to wipe the transient when a
	 * subsequent request bootstraps cleanly.
	 *
	 * @var bool
	 */
	private static $recorded_error = false;

	/**
	 * Handle an error encountered during integration class loading.
	 *
	 * Logs the error and stores it in a transient array for batched admin notice display.
	 * Deduplicates by hashing class + message + file + line so the same error is only
	 * stored once per window.
	 *
	 * @param string     $class The fully qualified class name that failed to load.
	 * @param \Throwable $e     The caught error or exception.
	 *
	 * @return void
	 */
	public function handle( $class, \Throwable $e ) {

		$message = $e->getMessage();
		$file    = $e->getFile();
		$line    = $e->getLine();

		// Always log regardless of transient state.
		automator_log( $class . ': ' . $message . ' in ' . $file . ':' . $line, 'Integration load error' );

		$errors = get_transient( self::TRANSIENT_KEY );

		if ( ! is_array( $errors ) ) {
			$errors = array();
		}

		$error_hash = md5( $class . '|' . $message . '|' . $file . '|' . $line );

		// Mark this request as having produced a load error so the
		// post-bootstrap auto-clear hook leaves the transient in place.
		self::$recorded_error = true;

		// Deduplicate by hash.
		if ( ! isset( $errors[ $error_hash ] ) ) {
			$errors[ $error_hash ] = array(
				'class'   => $class,
				'message' => $message,
				'file'    => $file,
				'line'    => $line,
				'time'    => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			);
			set_transient( self::TRANSIENT_KEY, $errors, self::TTL );
		}
	}

	/**
	 * Clear the load-errors transient if the current request bootstrapped
	 * cleanly. Called after all integrations have been loaded.
	 *
	 * Hooked from Integration_Loader once bootstrap is complete. The early
	 * exit on $recorded_error means we never wipe a transient that the
	 * current request just populated.
	 *
	 * @return void
	 */
	public static function maybe_clear_on_clean_load() {

		if ( self::$recorded_error ) {
			return;
		}

		if ( false === get_transient( self::TRANSIENT_KEY ) ) {
			return;
		}

		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Build a plain-text error report for support.
	 *
	 * @param array $errors The error entries.
	 *
	 * @return string The formatted report text.
	 */
	public static function build_report( $errors ) {

		$lines   = array();
		$lines[] = 'Uncanny Automator: Integration Load Errors';
		$lines[] = 'Site: ' . home_url();
		$lines[] = 'Automator: ' . ( defined( 'AUTOMATOR_PLUGIN_VERSION' ) ? AUTOMATOR_PLUGIN_VERSION : 'unknown' );
		$lines[] = 'PHP: ' . PHP_VERSION;
		$lines[] = 'WP: ' . get_bloginfo( 'version' );
		$lines[] = '';

		foreach ( $errors as $error ) {
			$lines[] = sprintf(
				'[%s] %s: %s (%s:%d)',
				$error['time'],
				$error['class'],
				$error['message'],
				$error['file'],
				$error['line']
			);
		}

		return implode( "\n", $lines );
	}
}

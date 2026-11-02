<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

/**
 * Explains a PHP fatal error on an MCP or OAuth request without exposing it.
 *
 * WordPress answers every fatal with "There has been a critical error on this
 * website.", which tells the connecting app, its user and support nothing. On
 * Automator's MCP and OAuth routes this report gives the answer a stable code,
 * a plain message that names the plugin, theme or WordPress part that failed,
 * and the reference UA-MCP-190. The authentication log retains a safe location
 * and error type, but never the uncontrolled error text.
 *
 * Everything here runs inside WordPress's fatal error handler during PHP
 * shutdown. Each step is wrapped and falls back to WordPress's own answer, so
 * the report can improve the response but never make the failure worse.
 */
final class Mcp_Fatal_Error_Report {

	public const CODE = 'automator_mcp_php_fatal_error';

	public const REF = 'UA-MCP-190';

	/**
	 * Seconds in which the same fatal is not logged again, so a failing
	 * endpoint cannot cycle the bounded authentication log.
	 */
	private const REPEAT_WINDOW = 60;

	/** Free memory the log write needs; with less, the entry is skipped. */
	private const LOG_MEMORY_HEADROOM = 2097152;

	/**
	 * Watch this request for a fatal when it targets the MCP or OAuth routes.
	 */
	public static function register(): void {
		// The actual REST route only: a mention in another request's query string,
		// or a longer route base, leaves that request to WordPress.
		$uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] )
			? wp_unslash( $_SERVER['REQUEST_URI'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed only to compare the route; never stored or printed.
			: '';
		if ( ! Mcp_Rest_Controller::is_mcp_route( $uri ) && ! Mcp_Rest_Controller::is_namespace_route( $uri, 'oauth' ) ) {
			return;
		}

		add_filter( 'wp_php_error_message', array( __CLASS__, 'filter_message' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_php_error_args', array( __CLASS__, 'filter_args' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Replace WordPress's generic message and record the details for administrators.
	 *
	 * @param mixed $message HTML message WordPress would show.
	 * @param mixed $error   Error information from error_get_last().
	 * @return mixed
	 */
	public static function filter_message( $message, $error ) {
		$error = is_array( $error ) ? $error : array();

		try {
			$message = sprintf(
				'This WordPress site stopped with a PHP fatal error in %1$s, so it could not finish this request. A site administrator can check Automator › Settings › MCP settings › Authentication log for a record and ask the host or developer to check the site\'s PHP error log. Reference: %2$s.',
				self::component( $error ),
				self::REF
			);
		} catch ( \Throwable $failure ) {
			unset( $failure );
		}

		self::record( $error );

		return $message;
	}

	/**
	 * Give the answer a stable code and keep the raw error out of JSON answers.
	 *
	 * @param mixed $args  Arguments WordPress passes to wp_die().
	 * @param mixed $error Error information from error_get_last().
	 * @return mixed
	 */
	public static function filter_args( $args, $error ) {
		unset( $error );

		try {
			$args         = is_array( $args ) ? $args : array();
			$args['code'] = self::CODE;

			// With WP_DEBUG_DISPLAY on, its default, WordPress adds the error
			// text, file and line to JSON error answers.
			add_filter( 'wp_die_json_handler', array( __CLASS__, 'without_error_details' ), PHP_INT_MAX );
			add_filter( 'wp_die_jsonp_handler', array( __CLASS__, 'without_error_details' ), PHP_INT_MAX );
		} catch ( \Throwable $failure ) {
			unset( $failure );
		}

		return $args;
	}

	/**
	 * Wrap a wp_die() handler so it answers with the message only.
	 *
	 * @param mixed $handler The handler WordPress chose.
	 * @return mixed
	 */
	public static function without_error_details( $handler ) {
		if ( ! is_callable( $handler ) ) {
			return $handler;
		}

		return static function ( $message, $title = '', $args = array() ) use ( $handler ) {
			if ( $message instanceof \WP_Error ) {
				$message = $message->get_error_message();
			}

			call_user_func( $handler, $message, $title, $args );
		};
	}

	/**
	 * Name the plugin, theme or WordPress part a fatal came from, never its path.
	 *
	 * The error message and stack trace are not used to name a component: a
	 * third-party exception can put private data in either one.
	 *
	 * @param array $error Error information from error_get_last().
	 */
	private static function component( array $error ): string {
		$file = (string) ( $error['file'] ?? '' );

		$extension = self::extension( $file );
		if ( '' !== $extension ) {
			return $extension;
		}

		$root = wp_normalize_path( ABSPATH );
		$file = wp_normalize_path( $file );
		if ( 0 === strpos( $file, $root . WPINC . '/' ) || 0 === strpos( $file, $root . 'wp-admin/' ) ) {
			return 'WordPress code';
		}

		return "this site's code";
	}

	/**
	 * Describe the plugin, must-use plugin, theme or drop-in that holds a file.
	 *
	 * @return string Empty when the file belongs to none of them.
	 */
	private static function extension( string $file ): string {
		$file  = wp_normalize_path( $file );
		$roots = array(
			'plugin'          => WP_PLUGIN_DIR,
			'must-use plugin' => WPMU_PLUGIN_DIR,
			'theme'           => get_theme_root(),
		);

		foreach ( $roots as $kind => $root ) {
			$root = trailingslashit( wp_normalize_path( (string) $root ) );
			if ( 0 === strpos( $file, $root ) ) {
				return sprintf( 'the "%s" %s', self::folder_name( (string) strtok( substr( $file, strlen( $root ) ), '/' ) ), $kind );
			}
		}

		if ( wp_normalize_path( WP_CONTENT_DIR ) === dirname( $file ) ) {
			return sprintf( 'the "%s" drop-in', self::folder_name( basename( $file ) ) );
		}

		return '';
	}

	/**
	 * Keep a folder or file name safe to show in plain text and HTML.
	 */
	private static function folder_name( string $name ): string {
		return (string) preg_replace( '/[^A-Za-z0-9._-]/', '', $name );
	}

	/**
	 * Record one authentication-log entry, unless the same fatal was just recorded.
	 *
	 * @param array $error Error information from error_get_last().
	 */
	private static function record( array $error ): void {
		try {
			$subject = Mcp_Auth_Log::safe_fatal_location( self::relative( (string) ( $error['file'] ?? '' ) ) . ':' . (int) ( $error['line'] ?? 0 ) );

			if ( ! self::has_memory_headroom() || self::recently_recorded( $subject ) ) {
				return;
			}

			Mcp_Auth_Log::record(
				'request',
				'php_fatal_error',
				array(
					'ref'        => self::REF,
					'subject'    => $subject,
					'fatal_type' => $error['type'] ?? null,
				)
			);
		} catch ( \Throwable $failure ) {
			unset( $failure );
		}
	}

	/**
	 * Strip the WordPress root from paths in a file name or error message.
	 */
	private static function relative( string $text ): string {
		$roots = array(
			WP_CONTENT_DIR . '/'                           => 'wp-content/',
			WP_CONTENT_DIR . DIRECTORY_SEPARATOR           => 'wp-content/',
			ABSPATH                                        => '',
			rtrim( ABSPATH, '/\\' ) . DIRECTORY_SEPARATOR => '',
		);

		return str_replace( array_keys( $roots ), array_values( $roots ), $text );
	}

	/**
	 * Whether the log write can run without exhausting memory a second time.
	 */
	private static function has_memory_headroom(): bool {
		wp_raise_memory_limit( 'admin' );

		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );

		return 1 > $limit || self::LOG_MEMORY_HEADROOM < $limit - memory_get_usage();
	}

	/**
	 * Whether this fatal was logged in the last minute.
	 */
	private static function recently_recorded( string $subject ): bool {
		foreach ( array_slice( Mcp_Auth_Log::all(), 0, 20 ) as $entry ) {
			$entry = is_array( $entry ) ? $entry : array();
			$same  = self::REF === ( $entry['ref'] ?? '' ) && ( $entry['subject'] ?? '' ) === $subject;

			if ( $same && self::REPEAT_WINDOW > time() - (int) ( $entry['t'] ?? 0 ) ) {
				return true;
			}
		}

		return false;
	}
}

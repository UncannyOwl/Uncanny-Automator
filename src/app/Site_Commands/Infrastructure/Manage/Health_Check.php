<?php
/**
 * Whether the site still loads after a command changed its code.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Manage;

/**
 * After a theme switch, a plugin activation or an update of active code, the
 * site loads its home page and its REST API index in separate requests, as
 * WordPress's own background updater checks an update. Each request carries
 * WordPress's scrape key, so WordPress itself reports a PHP fatal error at
 * the end of the page, whatever the page shows. The requests carry no
 * cookies and no credentials of this connection.
 *
 * A request that fails, or a page without WordPress's report, such as one a
 * cache or firewall answered, leaves that page unchecked; only a reported
 * fatal error counts as broken. Both pages are always loaded, so one that
 * could not be checked never hides what the other one reports.
 *
 * The check changes nothing. What to do about a broken site is the caller's
 * to explain: site_manage never undoes a change by itself.
 */
final class Health_Check {

	/** The site loads without a PHP fatal error. */
	public const HEALTHY = 'healthy';

	/** The site stopped with a PHP fatal error. */
	public const FATAL = 'fatal';

	/** The check could not tell. */
	public const UNCHECKED = 'unchecked';

	/** Reference for a site that stops with a PHP fatal error after a site_manage change. */
	public const REF = 'UA-MCP-191';

	/** Least time worth starting the check with: both requests and their answers. */
	public const SECONDS = 5.0;

	/** Longest one request may take. */
	private const REQUEST_SECONDS = 10.0;

	/** Shortest time worth giving one request. */
	private const MIN_REQUEST_SECONDS = 2.0;

	/** Fatal error types, as WordPress reports them while scraping. */
	private const FATAL_TYPES = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );

	/**
	 * Load the home page and the REST API index and report what each showed.
	 * FATAL when either stopped with a PHP fatal error, UNCHECKED when none
	 * did but one could not be checked, HEALTHY when both loaded.
	 *
	 * @param Change_Session $session The session the requests run in.
	 * @param float          $reserve Seconds to keep for what follows, such as answering.
	 * @return array{0: string, 1: string} The result, and for FATAL or UNCHECKED what each page showed, as clauses joined by semicolons.
	 */
	public static function run( Change_Session $session, float $reserve ): array {
		$key   = md5( wp_generate_password( 32, false ) );
		$nonce = (string) wp_rand( 1, 2147483647 );
		// WordPress's own scrape key: the requests report a fatal error instead of the error page.
		set_transient( 'scrape_key_' . $key, $nonce, MINUTE_IN_SECONDS );
		$pages   = array(
			'the home page'      => home_url( '/' ),
			'the REST API index' => get_rest_url(),
		);
		$results = array();
		try {
			$left = count( $pages );
			foreach ( $pages as $page => $url ) {
				// Each page gets its share of the time left, so the last one is loaded too.
				$seconds = min( self::REQUEST_SECONDS, ( $session->budget()->remaining() - $reserve ) / $left );
				--$left;
				if ( $seconds < self::MIN_REQUEST_SECONDS ) {
					$results[] = array( self::UNCHECKED, $page, 'was not loaded, because too little time was left' );
					continue;
				}
				$url       = add_query_arg(
					array(
						'wp_scrape_key'   => $key,
						'wp_scrape_nonce' => $nonce,
					),
					$url
				);
				$results[] = self::load( $page, $url, $key, $seconds );
			}
		} finally {
			delete_transient( 'scrape_key_' . $key );
		}

		return self::summary( $results );
	}

	/**
	 * One result for both pages, naming what each page showed when either
	 * one did not load cleanly. Pages that showed the same are named together.
	 *
	 * @param array<int, array{0: string, 1: string, 2: string}> $results Result, page and what it showed, for each page.
	 * @return array{0: string, 1: string}
	 */
	private static function summary( array $results ): array {
		$states = array_column( $results, 0 );
		if ( ! in_array( self::FATAL, $states, true ) && ! in_array( self::UNCHECKED, $states, true ) ) {
			return array( self::HEALTHY, '' );
		}
		$pages = array();
		foreach ( $results as list( , $page, $showed ) ) {
			$pages[ $showed ][] = $page;
		}
		$clauses = array();
		foreach ( $pages as $showed => $names ) {
			$clauses[] = implode( ' and ', $names ) . ' ' . $showed;
		}

		return array( in_array( self::FATAL, $states, true ) ? self::FATAL : self::UNCHECKED, implode( '; ', $clauses ) );
	}

	/**
	 * Load one page and read WordPress's report from it.
	 *
	 * @param string $page    The page, for messages.
	 * @param string $url     Its address with the scrape key.
	 * @param string $key     The scrape key.
	 * @param float  $seconds How long the request may take.
	 * @return array{0: string, 1: string, 2: string} The result, the page, and what it showed.
	 */
	private static function load( string $page, string $url, string $key, float $seconds ): array {
		$headers = array( 'Cache-Control' => 'no-cache' );
		// As WordPress does for its own check: a site behind HTTP authentication lets itself in.
		if ( isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . wp_unslash( $_SERVER['PHP_AUTH_PW'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Passed on unchanged, as WordPress's own loopback check does.
		}
		if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
			// A PHP session held by this request would block the site's answer.
			session_write_close();
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => $seconds,
				'headers'   => $headers,
				/** This filter is documented in wp-includes/class-wp-http-streams.php */
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, applied as WordPress applies it.
				'sslverify' => apply_filters( 'https_local_ssl_verify', false, $url ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( self::UNCHECKED, $page, 'could not be loaded (WordPress could not complete the loopback request)' );
		}
		$body  = (string) wp_remote_retrieve_body( $response );
		$start = "###### wp_scraping_result_start:$key ######";
		$from  = strpos( $body, $start );
		$to    = false === $from ? false : strpos( $body, "###### wp_scraping_result_end:$key ######", $from );
		if ( false === $from || false === $to ) {
			return array( self::UNCHECKED, $page, sprintf( 'answered with HTTP %d but without WordPress\'s error report, possibly because a cache, firewall or maintenance page answered instead', (int) wp_remote_retrieve_response_code( $response ) ) );
		}
		$report = json_decode( trim( substr( $body, $from + strlen( $start ), $to - $from - strlen( $start ) ) ), true );
		if ( true === $report ) {
			return array( self::HEALTHY, $page, 'loaded without a PHP fatal error' );
		}
		if ( ! is_array( $report ) || ! isset( $report['type'] ) || ! in_array( (int) $report['type'], self::FATAL_TYPES, true ) ) {
			return array( self::UNCHECKED, $page, 'returned an error report Automator could not read' );
		}

		return array( self::FATAL, $page, sprintf( 'stopped with a PHP fatal error: %s', self::describe( $report ) ) );
	}

	/**
	 * A fatal error using only the error type and a safe location, never its text.
	 *
	 * @since 8.0.0
	 * @param array<string, mixed> $report The error, as WordPress reports it.
	 * @return string
	 */
	public static function describe( array $report ): string {
		$safe = self::safe_error( $report );
		if ( 0 === $safe['type'] && '' === $safe['file'] && 0 === $safe['line'] ) {
			return 'details unavailable';
		}
		$type  = 0 === $safe['type'] ? 'type unavailable' : 'type ' . $safe['type'];
		$where = '' === $safe['file'] ? '' : $safe['file'];
		if ( 0 < $safe['line'] ) {
			$where .= ( '' === $where ? '' : ', ' ) . 'line ' . $safe['line'];
		}

		return $type . ( '' === $where ? '' : ' (' . $where . ')' ) . '; error text omitted to protect private information';
	}

	/**
	 * Retain only fields that can be safely stored for an activation failure.
	 *
	 * @since 8.0.0
	 * @param array<string,mixed> $report WordPress's error report.
	 * @return array{type: int, file: string, line: int}
	 */
	public static function safe_error( array $report ): array {
		$type = is_int( $report['type'] ?? null ) && in_array( $report['type'], self::FATAL_TYPES, true ) ? $report['type'] : 0;
		$file = is_string( $report['file'] ?? null ) ? Site_Changes::text( $report['file'] ) : '';
		if ( 1 !== preg_match( '~^(?:(?:wp-content|wp-includes|wp-admin)/[A-Za-z0-9_./-]+|[A-Za-z0-9_.-]+\\.php)$~D', $file ) || false !== strpos( $file, '..' ) ) {
			$file = '';
		}
		$line = is_int( $report['line'] ?? null ) ? max( 0, $report['line'] ) : 0;

		return array(
			'type' => $type,
			'file' => $file,
			'line' => $line,
		);
	}
}

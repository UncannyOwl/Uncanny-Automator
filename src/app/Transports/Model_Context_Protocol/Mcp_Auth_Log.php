<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Atomic_Option_Lock;

/**
 * Bounded, secret-free authentication log for MCP connections.
 *
 * Support at scale needs "send us your logs" to be a real answer. Every
 * handshake, sign-in, consent, token exchange and bearer check records one
 * entry here: event, outcome, a short detail, who or what it concerned, and
 * the connecting software, and the hosted service's request ID when it sent
 * one. Never a token, code, verifier, nonce or secret.
 *
 * Storage is a ring buffer in one non-autoloaded option, so fifty thousand
 * sites gain forensics without a table, a migration, or unbounded growth.
 *
 * Reference codes: every distinct failure site carries a static UA-MCP-###
 * reference, so one grep finds the exact line a customer's log points at.
 * Ranges: 1xx handshake and transport, 2xx bearer token checks,
 * 3xx sign-in requests, 34x post-validation sign-in redirects,
 * 5xx token exchange, 6xx app registration. 7xx and 8xx are reserved.
 * One ref names one cause: never share it with another cause or reuse a
 * retired number.
 *
 * Accepted trade-off, on purpose: the ring buffer is written with a plain
 * read-modify-write, so a concurrent trusted request can drop an entry. This
 * is a diagnostic aid for support, not an audit trail. Unauthenticated MCP
 * failures pass through one atomic, fixed-option sampling gate and share one
 * ring-buffer slot, so they cannot cycle all 200 support entries.
 */
final class Mcp_Auth_Log {

	private const OPTION_NAME = 'automator_mcp_auth_log_v1';

	private const MAX_EVENTS = 200;

	private const MAX_FIELD_LENGTH = 160;

	private const UNAUTHENTICATED_GATE_OPTION = 'automator_mcp_auth_log_unauthenticated_gate_v1';

	private const UNAUTHENTICATED_LOG_INTERVAL = 60;

	/**
	 * Append one event, newest first.
	 *
	 * @param string $event   Funnel stage, for example handshake, authorization, token_exchange.
	 * @param string $outcome Short machine outcome, for example success, invalid_grant.
	 * @param array  $context Optional subject, detail and user_id.
	 * @return void
	 */
	public static function record( string $event, string $outcome, array $context = array() ) {
		$entry = array(
			't'       => time(),
			'event'   => sanitize_key( $event ),
			'outcome' => sanitize_key( $outcome ),
			'ref'     => self::clean( (string) ( $context['ref'] ?? '' ) ),
			'subject' => self::clean( (string) ( $context['subject'] ?? '' ) ),
			'detail'  => self::clean( (string) ( $context['detail'] ?? '' ) ),
			'user_id' => max( 0, (int) ( $context['user_id'] ?? 0 ) ),
			'ua'      => self::clean( self::user_agent() ),
			'rid'     => self::request_id(),
		);
		if ( 'php_fatal_error' === $entry['outcome'] ) {
			$entry['fatal_type'] = self::fatal_type( $context['fatal_type'] ?? null );
			$entry               = self::safe_fatal_entry( $entry );
		}
		if ( ! empty( $context['_unauthenticated_sample'] ) ) {
			$entry['_unauthenticated_sample'] = true;
		}

		$log = self::all();
		if ( isset( $entry['_unauthenticated_sample'] ) ) {
			$log = array_values(
				array_filter(
					$log,
					static function ( $stored_entry ): bool {
						return ! is_array( $stored_entry ) || empty( $stored_entry['_unauthenticated_sample'] );
					}
				)
			);
		}
		array_unshift( $log, $entry );
		if ( count( $log ) > self::MAX_EVENTS ) {
			$log = array_slice( $log, 0, self::MAX_EVENTS );
		}

		update_option( self::OPTION_NAME, $log, false );
	}

	/**
	 * Sample a pre-authentication failure without allowing public traffic to
	 * churn the bounded support log.
	 *
	 * One fixed, non-autoloaded option is held as a short lease. The shared atomic
	 * option lock uses unique inserts and compare-and-delete stale takeover, so
	 * concurrent requests cannot each acquire a fresh bucket or create
	 * attacker-controlled option names.
	 *
	 * @param string $event   Funnel stage.
	 * @param string $outcome Machine outcome.
	 * @param array  $context Optional safe diagnostic context.
	 * @return bool Whether the event was recorded.
	 */
	public static function record_unauthenticated_failure( string $event, string $outcome, array $context = array() ): bool {
		$now  = microtime( true );
		$gate = get_option( self::UNAUTHENTICATED_GATE_OPTION, array() );
		if ( is_array( $gate ) && isset( $gate['expires_at'] ) && (float) $gate['expires_at'] > $now ) {
			return false;
		}

		$owner = ( new Atomic_Option_Lock() )->acquire(
			self::UNAUTHENTICATED_GATE_OPTION,
			self::UNAUTHENTICATED_LOG_INTERVAL,
			0
		);
		if ( false === $owner ) {
			return false;
		}

		// The lease is intentionally not released: its lifetime is the sampling
		// interval. Atomic stale takeover opens exactly one new interval.
		$context['_unauthenticated_sample'] = true;
		self::record( $event, $outcome, $context );
		return true;
	}

	/**
	 * Return every stored event, newest first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		$log = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}

		foreach ( $log as &$entry ) {
			if ( is_array( $entry ) && ( 'php_fatal_error' === ( $entry['outcome'] ?? '' ) || 'UA-MCP-190' === ( $entry['ref'] ?? '' ) ) ) {
				$entry = self::safe_fatal_entry( $entry );
			}
		}
		unset( $entry );

		return array_values( $log );
	}

	/**
	 * Replace untrusted fatal text, including text in logs written by older versions.
	 *
	 * @since 8.0.0
	 * @param array<string,mixed> $entry A fatal log entry.
	 * @return array<string,mixed>
	 */
	private static function safe_fatal_entry( array $entry ): array {
		$type                = self::fatal_type( $entry['fatal_type'] ?? null );
		$entry['fatal_type'] = $type;
		$entry['detail']     = 0 === $type
			? 'PHP fatal error. Error text omitted to protect private information.'
			: sprintf( 'PHP fatal error (type %d). Error text omitted to protect private information.', $type );
		$entry['subject']    = self::safe_fatal_location( is_string( $entry['subject'] ?? null ) ? $entry['subject'] : '' );

		return $entry;
	}

	/**
	 * Keep only a location relative to WordPress's known folders.
	 *
	 * @since 8.0.0
	 */
	public static function safe_fatal_location( string $subject ): string {
		$subject = self::clean( $subject );
		if ( 1 !== preg_match( '~^(?:(?:wp-content|wp-includes|wp-admin)/[A-Za-z0-9_./-]+|[A-Za-z0-9_.-]+\\.php):[0-9]+$~D', $subject ) || false !== strpos( $subject, '..' ) ) {
			return 'location unavailable';
		}

		return $subject;
	}

	/**
	 * Accept only PHP's fatal error type numbers.
	 *
	 * @since 8.0.0
	 * @param mixed $type PHP error type.
	 */
	private static function fatal_type( $type ): int {
		if ( ! is_int( $type ) || ! in_array( $type, array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
			return 0;
		}

		return $type;
	}

	/**
	 * Remove every stored event.
	 */
	public static function clear(): bool {
		$cleared = delete_option( self::OPTION_NAME ) || array() === self::all();
		delete_option( self::UNAUTHENTICATED_GATE_OPTION );

		return $cleared;
	}

	/**
	 * Render the whole log as a plain-text report for support.
	 */
	public static function export_text(): string {
		$lines = array(
			'Uncanny Automator MCP authentication log',
			'Site: ' . home_url(),
			'Generated: ' . gmdate( 'c' ),
			'Timezone: UTC (every timestamp below)',
			'Plugin: ' . ( defined( 'AUTOMATOR_PLUGIN_VERSION' ) ? AUTOMATOR_PLUGIN_VERSION : 'unknown' ),
			'WordPress: ' . get_bloginfo( 'version' ),
			'Permalinks: ' . ( '' === (string) get_option( 'permalink_structure' ) ? 'plain' : 'pretty' ),
			'HTTPS: ' . ( 0 === strpos( ( new Mcp_Resource_Identifier() )->get(), 'https://' ) ? 'yes' : 'no' ),
			str_repeat( '-', 72 ),
		);

		foreach ( self::all() as $entry ) {
			$line = sprintf(
				'%s  %-11s %-18s %-18s user:%d  %s  %s  [%s]',
				gmdate( 'Y-m-d H:i:s', (int) ( $entry['t'] ?? 0 ) ),
				(string) ( $entry['ref'] ?? '' ),
				(string) ( $entry['event'] ?? '' ),
				(string) ( $entry['outcome'] ?? '' ),
				(int) ( $entry['user_id'] ?? 0 ),
				(string) ( $entry['subject'] ?? '' ),
				(string) ( $entry['detail'] ?? '' ),
				(string) ( $entry['ua'] ?? '' )
			);
			// Entries written before request IDs existed have no rid field.
			if ( '' !== (string) ( $entry['rid'] ?? '' ) ) {
				$line .= '  request:' . (string) $entry['rid'];
			}
			$lines[] = $line;
		}

		if ( array() === self::all() ) {
			$lines[] = '(no events recorded)';
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Sanitize, truncate, and redact anything shaped like a credential.
	 *
	 * Call sites only ever pass our own safe strings; this is the backstop
	 * that keeps a future mistake from ever persisting a secret.
	 */
	private static function clean( string $value ): string {
		$value = sanitize_text_field( $value );
		$value = (string) preg_replace(
			array(
				'/mcp_[a-f0-9\-]{36}_[A-Za-z0-9]+/',
				'/mac_[a-f0-9]{32}_[A-Za-z0-9]+/',
				'/mrt_[a-f0-9]{32}_[A-Za-z0-9]+/',
				'/Bearer\s+[A-Za-z0-9._~+\/-]{20,}/i',
			),
			'[redacted]',
			$value
		);

		if ( mb_strlen( $value, 'UTF-8' ) > self::MAX_FIELD_LENGTH ) {
			$value = mb_substr( $value, 0, self::MAX_FIELD_LENGTH - 1, 'UTF-8' ) . "\xE2\x80\xA6";
		}

		// A 3-byte utf8 options table strips 4-byte characters inside the
		// serialized log and corrupts the whole option; store them as entities
		// there, exactly as core does for post content.
		if ( isset( $GLOBALS['wpdb']->charset ) && 'utf8mb4' !== $GLOBALS['wpdb']->charset && function_exists( 'wp_encode_emoji' ) ) {
			$value = wp_encode_emoji( $value );
		}

		return $value;
	}

	/**
	 * The connecting software, the single most diagnostic support datum.
	 */
	private static function user_agent(): string {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) || ! is_string( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		return mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 120, 'UTF-8' );
	}

	/**
	 * The hosted service's X-Automator-Request-Id, which joins this entry to
	 * the hosted log line. Anything but 32 lowercase hex characters is dropped.
	 */
	private static function request_id(): string {
		$request_id = isset( $_SERVER['HTTP_X_AUTOMATOR_REQUEST_ID'] ) && is_string( $_SERVER['HTTP_X_AUTOMATOR_REQUEST_ID'] )
			? wp_unslash( $_SERVER['HTTP_X_AUTOMATOR_REQUEST_ID'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by the exact pattern below.
			: '';

		return 1 === preg_match( '/^[0-9a-f]{32}$/D', $request_id ) ? $request_id : '';
	}
}

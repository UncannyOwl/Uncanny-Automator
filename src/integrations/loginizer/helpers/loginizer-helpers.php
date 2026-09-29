<?php

namespace Uncanny_Automator\Integrations\Loginizer;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Loginizer_Helpers
 *
 * Shared logic for the Loginizer integration. Loginizer is procedural — no
 * classes, no singletons, and no public API for IP management — so every write
 * here replicates what the admin screens in `main/settings/brute-force.php` do:
 * read the serialized option, mutate the array, save it back.
 *
 * @package Uncanny_Automator\Integrations\Loginizer
 */
class Loginizer_Helpers extends Abstract_Helpers {

	/**
	 * "Any" sentinel for the role selector.
	 *
	 * @var string
	 */
	const ANY = '-1';

	/**
	 * Option holding the blacklisted IP ranges.
	 *
	 * @var string
	 */
	const BLACKLIST = 'loginizer_blacklist';

	/**
	 * Option holding the whitelisted IP ranges.
	 *
	 * @var string
	 */
	const WHITELIST = 'loginizer_whitelist';

	// =========================================================================
	// Remote_Data — role selector for the login trigger.
	//
	// Route: POST /wp-json/uap/v2/remote-data/loginizer/user_roles
	// =========================================================================

	/**
	 * Registered WP roles, with an "Any role" sentinel first.
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_user_roles( $request ): array {

		unset( $request );

		// get_editable_roles() lives in wp-admin/includes/user.php. remote_data runs
		// in an admin REST context where that is normally loaded, but the file is not
		// guaranteed — load it rather than fataling on a missing function.
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$options = array(
			array(
				'text'  => esc_html_x( 'Any role', 'Loginizer', 'uncanny-automator' ),
				'value' => self::ANY,
			),
		);

		foreach ( (array) get_editable_roles() as $slug => $role ) {
			$options[] = array(
				'text'  => translate_user_role( isset( $role['name'] ) ? $role['name'] : (string) $slug ),
				'value' => (string) $slug,
			);
		}

		return $this->remote_data_success( $options );
	}

	// =========================================================================
	// IP utilities.
	// =========================================================================

	/**
	 * The requesting client's IP, resolved the way Loginizer resolves it.
	 *
	 * @return string
	 */
	public function current_ip() {

		if ( function_exists( 'lz_getip' ) ) {
			return (string) lz_getip();
		}

		global $loginizer;

		if ( isset( $loginizer['current_ip'] ) ) {
			return (string) $loginizer['current_ip'];
		}

		$remote = automator_filter_input( 'REMOTE_ADDR', INPUT_SERVER );

		return is_string( $remote ) ? $remote : '';
	}

	/**
	 * Whether a string is a valid IPv4 or IPv6 address.
	 *
	 * @param string $ip
	 *
	 * @return bool
	 */
	public function is_valid_ip( $ip ) {

		$ip = trim( (string) $ip );

		if ( '' === $ip ) {
			return false;
		}

		if ( function_exists( 'lz_valid_ip' ) ) {
			return (bool) lz_valid_ip( $ip );
		}

		return false !== filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Whether `$ip` falls inside the inclusive range `$start`–`$end`.
	 *
	 * Comparison is done on the packed binary form so IPv4 and IPv6 are handled
	 * by the same code path. `strcmp()` on binary strings is an unsigned
	 * byte-wise compare, which is exactly the ordering an IP range needs.
	 *
	 * @param string $ip
	 * @param string $start
	 * @param string $end
	 *
	 * @return bool
	 */
	public function ip_in_range( $ip, $start, $end ) {

		$packed_ip    = $this->pack_ip( $ip );
		$packed_start = $this->pack_ip( $start );
		$packed_end   = $this->pack_ip( $end );

		if ( '' === $packed_ip || '' === $packed_start || '' === $packed_end ) {
			return false;
		}

		// Different address families never overlap.
		if ( strlen( $packed_ip ) !== strlen( $packed_start ) || strlen( $packed_ip ) !== strlen( $packed_end ) ) {
			return false;
		}

		return strcmp( $packed_ip, $packed_start ) >= 0 && strcmp( $packed_ip, $packed_end ) <= 0;
	}

	/**
	 * Packed binary form of an IP, or an empty string when it is not an IP.
	 *
	 * @param string $ip
	 *
	 * @return string
	 */
	private function pack_ip( $ip ) {

		$packed = @inet_pton( trim( (string) $ip ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_pton warns on malformed input; the false return is the signal.

		return false === $packed ? '' : $packed;
	}

	/**
	 * Whether two inclusive ranges intersect.
	 *
	 * @param string $a_start
	 * @param string $a_end
	 * @param string $b_start
	 * @param string $b_end
	 *
	 * @return bool
	 */
	private function ranges_overlap( $a_start, $a_end, $b_start, $b_end ) {

		$as = $this->pack_ip( $a_start );
		$ae = $this->pack_ip( $a_end );
		$bs = $this->pack_ip( $b_start );
		$be = $this->pack_ip( $b_end );

		if ( '' === $as || '' === $ae || '' === $bs || '' === $be ) {
			return false;
		}

		if ( strlen( $as ) !== strlen( $bs ) ) {
			return false;
		}

		return strcmp( $as, $be ) <= 0 && strcmp( $bs, $ae ) <= 0;
	}

	// =========================================================================
	// Blacklist / whitelist management.
	// =========================================================================

	/**
	 * The stored range entries for an option, always as an array.
	 *
	 * @param string $option Either self::BLACKLIST or self::WHITELIST.
	 *
	 * @return array
	 */
	public function get_ip_list( $option ) {

		$list = get_option( $option, array() );

		return is_array( $list ) ? $list : array();
	}

	/**
	 * Append a range to a list, replicating the admin screen's save routine.
	 *
	 * @param string $option Either self::BLACKLIST or self::WHITELIST.
	 * @param string $start  Start IP.
	 * @param string $end    End IP; falls back to the start IP when empty.
	 *
	 * @return array{success:bool,error:string,range:string}
	 */
	public function add_ip_range( $option, $start, $end ) {

		$start = trim( (string) $start );
		$end   = trim( (string) $end );

		if ( '' === $end ) {
			$end = $start;
		}

		$list  = $this->get_ip_list( $option );
		$error = $this->validate_ip_range( $start, $end, $list );

		if ( '' !== $error ) {
			return array(
				'success' => false,
				'error'   => $error,
				'range'   => '',
			);
		}

		$ids   = array_map( 'intval', array_keys( $list ) );
		$newid = empty( $ids ) ? 0 : max( $ids ) + 1;

		$list[ $newid ] = array(
			'start' => $start,
			'end'   => $end,
			'time'  => time(),
		);

		update_option( $option, $list );

		return array(
			'success' => true,
			'error'   => '',
			'range'   => $start === $end ? $start : $start . ' - ' . $end,
		);
	}

	/**
	 * Drop every entry whose range contains `$ip`.
	 *
	 * @param string $option Either self::BLACKLIST or self::WHITELIST.
	 * @param string $ip
	 *
	 * @return int Number of entries removed.
	 */
	public function remove_ip( $option, $ip ) {

		$list    = $this->get_ip_list( $option );
		$removed = 0;

		foreach ( $list as $id => $entry ) {

			$start = isset( $entry['start'] ) ? (string) $entry['start'] : '';
			$end   = isset( $entry['end'] ) ? (string) $entry['end'] : $start;

			if ( '' === $start ) {
				continue;
			}

			if ( $this->ip_in_range( $ip, $start, $end ) ) {
				unset( $list[ $id ] );
				++$removed;
			}
		}

		if ( $removed > 0 ) {
			update_option( $option, $list );
		}

		return $removed;
	}

	/**
	 * Validate a range before it is written.
	 *
	 * Prefers Loginizer's own `loginizer_iprange_validate()` so Automator writes
	 * exactly what the admin screen would. That function lives in an admin-only
	 * settings file which is NOT safe to include on a front-end request, so it is
	 * used only when something else already loaded it; otherwise an equivalent
	 * local check runs.
	 *
	 * @param string $start
	 * @param string $end
	 * @param array  $list  Existing entries.
	 *
	 * @return string Empty string when valid, otherwise the error message.
	 */
	private function validate_ip_range( $start, $end, $list ) {

		if ( function_exists( 'loginizer_iprange_validate' ) ) {

			$error = array();

			try {
				loginizer_iprange_validate( $start, $end, $list, $error );

				if ( ! empty( $error ) ) {
					return implode( ' ', array_map( 'wp_strip_all_tags', (array) $error ) );
				}

				return '';
			} catch ( \Throwable $e ) {
				// Signature drift between Loginizer versions — fall through to the
				// local equivalent rather than fataling the recipe.
				unset( $e );
			}
		}

		return $this->validate_ip_range_locally( $start, $end, $list );
	}

	/**
	 * Local equivalent of `loginizer_iprange_validate()`: format, ordering, and
	 * overlap against the existing entries.
	 *
	 * @param string $start
	 * @param string $end
	 * @param array  $list
	 *
	 * @return string
	 */
	private function validate_ip_range_locally( $start, $end, $list ) {

		if ( ! $this->is_valid_ip( $start ) ) {
			return sprintf( 'Invalid start IP: [%s].', $start );
		}

		if ( ! $this->is_valid_ip( $end ) ) {
			return sprintf( 'Invalid end IP: [%s].', $end );
		}

		$packed_start = $this->pack_ip( $start );
		$packed_end   = $this->pack_ip( $end );

		if ( strlen( $packed_start ) !== strlen( $packed_end ) ) {
			return 'The start and end IP must both be IPv4 or both be IPv6.';
		}

		if ( strcmp( $packed_start, $packed_end ) > 0 ) {
			return sprintf( 'The start IP [%1$s] is higher than the end IP [%2$s].', $start, $end );
		}

		foreach ( $list as $entry ) {

			$entry_start = isset( $entry['start'] ) ? (string) $entry['start'] : '';
			$entry_end   = isset( $entry['end'] ) ? (string) $entry['end'] : $entry_start;

			if ( '' === $entry_start ) {
				continue;
			}

			if ( $this->ranges_overlap( $start, $end, $entry_start, $entry_end ) ) {
				return sprintf( 'The range overlaps the existing entry [%1$s - %2$s].', $entry_start, $entry_end );
			}
		}

		return '';
	}

	// =========================================================================
	// Failed-login log table.
	// =========================================================================

	/**
	 * Fully-qualified name of Loginizer's log table.
	 *
	 * @return string
	 */
	public function logs_table() {

		global $wpdb;

		return $wpdb->prefix . 'loginizer_logs';
	}

	/**
	 * The log row for an IP. Loginizer keys the table by IP, so there is at most
	 * one row per address.
	 *
	 * @param string $ip
	 *
	 * @return array
	 */
	public function get_failed_log_row( $ip ) {

		global $wpdb;

		$table = $this->logs_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Loginizer owns this custom table; the name is built from $wpdb->prefix and the value is prepared.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE ip = %s", (string) $ip ),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? $row : array();
	}

	/**
	 * Delete every failed-login record for an IP.
	 *
	 * @param string $ip
	 *
	 * @return int Rows deleted.
	 */
	public function delete_failed_logs( $ip ) {

		global $wpdb;

		$deleted = $wpdb->delete( $this->logs_table(), array( 'ip' => (string) $ip ), array( '%s' ) );

		return false === $deleted ? 0 : (int) $deleted;
	}

	// =========================================================================
	// Derived context for the trigger payloads.
	// =========================================================================

	/**
	 * Which of Loginizer's four block paths produced a `wp_login_blocked` call.
	 *
	 * The hook carries only `$username` — the reason is implicit in which call
	 * site fired (`init.php:392/408/424/437`). Each of those branches returns,
	 * so they are mutually exclusive and strictly ordered; this re-derives the
	 * reason by replaying them in that same order, using only side-effect-free
	 * reads.
	 *
	 * `loginizer_user_blacklisted()` is deliberately NOT called for the 424
	 * branch, because its tail ADDS the caller's IP to the blacklist
	 * (`loginizer-security-bb/init.php:3575-3585`). Only its matching half is
	 * replicated here — read the pattern list, match, and confirm no real
	 * account owns the name (`init.php:3549-3574`).
	 *
	 * @param string $ip
	 * @param string $username The name the blocked attempt used. Optional only
	 *                         for back-compat; without it the 424 branch can't
	 *                         be distinguished from 437.
	 *
	 * @return string One of trusted_ip|blacklisted_ip|blacklisted_username|lockout_exceeded.
	 */
	public function derive_block_reason( $ip, $username = '' ) {

		$options = (array) get_option( 'loginizer_options', array() );

		// init.php:392 — trusted-IP mode is on and this IP is not whitelisted.
		if ( ! empty( $options['trusted_ips'] ) ) {
			if ( ! function_exists( 'loginizer_is_whitelisted' ) || ! loginizer_is_whitelisted() ) {
				return 'trusted_ip';
			}
		}

		// init.php:408 — the IP is blacklisted.
		if ( function_exists( 'loginizer_is_blacklisted' ) && loginizer_is_blacklisted() ) {
			return 'blacklisted_ip';
		}

		// init.php:424 — Pro username auto-blacklist. Must be tested BEFORE the
		// retries branch: Loginizer returns here first, and the two normally
		// co-occur because the auto-blacklist is reached THROUGH repeated
		// failures — so checking retries first would report every username
		// block as a lockout.
		if ( $this->username_is_blacklisted( $username ) ) {
			return 'blacklisted_username';
		}

		// init.php:437 — retries exhausted, Loginizer's final branch. Read the
		// counter directly instead of calling loginizer_can_login(), which
		// mutates lockout state.
		$row         = $this->get_failed_log_row( $ip );
		$max_retries = isset( $options['max_retries'] ) ? (int) $options['max_retries'] : 3;

		if ( ! empty( $row['count'] ) && (int) $row['count'] >= $max_retries ) {
			return 'lockout_exceeded';
		}

		// No branch proved itself. 424 is the only one that can hide from a
		// side-effect-free probe (its pattern list lives behind Pro), so keep
		// attributing the remainder to it rather than inventing a fifth value.
		return 'blacklisted_username';
	}

	/**
	 * Whether `$username` would trip Loginizer Pro's username auto-blacklist,
	 * determined without calling `loginizer_user_blacklisted()`.
	 *
	 * Mirrors `loginizer-security-bb/init.php:3549-3574` exactly, including the
	 * `*` -> `(.*?)` rewrite with no `preg_quote()` (Loginizer treats the stored
	 * entries as regex fragments, so quoting them here would diverge) and the
	 * final rule that a pattern matching a REAL account does not block.
	 *
	 * @param string $username
	 *
	 * @return bool
	 */
	private function username_is_blacklisted( $username ) {

		$username = (string) $username;

		// No name to test, or Pro isn't active so init.php:424 is unreachable
		// (the call site is wrapped in this same function_exists guard).
		if ( '' === $username || ! function_exists( 'loginizer_user_blacklisted' ) ) {
			return false;
		}

		$patterns = get_option( 'loginizer_username_blacklist' );
		$patterns = is_array( $patterns ) ? $patterns : array();

		foreach ( $patterns as $pattern ) {

			$regex = '/^' . str_replace( '*', '(.*?)', (string) $pattern ) . '$/is';

			if ( 1 !== @preg_match( $regex, $username ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A malformed admin-entered pattern must not emit a warning on every blocked login; Loginizer itself would already have warned once this request.
				continue;
			}

			// init.php:3569-3574 — the blacklist only bites names with no
			// account behind them.
			return ! get_user_by( 'login', $username );
		}

		return false;
	}

	/**
	 * Read a GET parameter the same way Loginizer reads it.
	 *
	 * `automator_filter_input()` wraps `filter_input()`, which reads the SAPI's
	 * copy of the request and ignores any later change to `$_GET`. Loginizer
	 * gates its own social flow on the superglobal (`init.php:368`,
	 * `main/social-login.php:60`), so falling back to it keeps this integration
	 * agreeing with the plugin it is reading — and keeps the value reachable in
	 * tests, where only `$_GET` can be populated.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private function read_query_arg( $key ) {

		$value = automator_filter_input( $key, INPUT_GET );

		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing hint on Loginizer's own OAuth callback; sanitized inline.
		$raw = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';

		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * How the current request authenticated.
	 *
	 * @return string One of social|sso|standard.
	 */
	public function detect_login_method() {

		if ( '' !== $this->social_provider() ) {
			return 'social';
		}

		if ( '' !== $this->read_query_arg( 'ssotoken' ) ) {
			return 'sso';
		}

		return 'standard';
	}

	/**
	 * The social provider on the current request, if any.
	 *
	 * @return string
	 */
	public function social_provider() {
		return $this->read_query_arg( 'lz_social_provider' );
	}

	/**
	 * The role Loginizer assigns to accounts created through social login.
	 *
	 * @return string
	 */
	public function social_default_role() {

		global $loginizer;

		if ( isset( $loginizer['social_settings']['general']['default_role'] ) ) {
			return (string) $loginizer['social_settings']['general']['default_role'];
		}

		return (string) get_option( 'default_role', '' );
	}

	/**
	 * The URL a login attempt was made against.
	 *
	 * @return string
	 */
	public function request_url() {

		$uri = automator_filter_input( 'REQUEST_URI', INPUT_SERVER );

		return is_string( $uri ) ? esc_url_raw( home_url( $uri ) ) : '';
	}
}

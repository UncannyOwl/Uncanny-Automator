<?php
/**
 * The database credentials the MCP database tools withhold: only ones known for certain.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

/**
 * Credential policy shared by mysql_select_from_table and mysql_get_cell_chunk.
 *
 * Guessing which values are secrets by name cannot be made reliable: a pattern
 * either misses a plugin's oddly named key or withholds harmless data, and the
 * harmless cases are what people notice (a page builder's design "tokens", a
 * cleanup cursor). So this withholds only what is certain:
 *
 * - Columns WordPress and WooCommerce use for credentials, by exact name.
 * - Settings (option_name, meta_key) by exact name: WordPress's salts and keys,
 *   user session tokens, and the credentials Automator itself stores, whose
 *   naming Automator owns.
 * - Key formats that are unmistakable on their own (Stripe, GitHub, Slack,
 *   AWS, Google API keys, PEM private keys), wherever they appear.
 *
 * Everything else is returned as stored. Another plugin's key under an ordinary
 * name is readable: the site owner's database-access switch is the boundary for
 * that, not this class. See "Redacting data from MCP responses" in CLAUDE.md
 * before adding a rule here.
 *
 * @since 8.1.0
 */
final class Database_Secrets {

	/** What a withheld value reads as. */
	public const MARKER = '[REDACTED]';

	/** Value columns of name/value tables, with the column that names each row. */
	public const VALUE_COLUMNS = array(
		'option_value' => 'option_name',
		'meta_value'   => 'meta_key',
	);

	/** Columns that hold a credential: WordPress users and posts, WooCommerce REST API keys. */
	private const SECRET_COLUMNS = array(
		'user_pass',
		'user_activation_key',
		'post_password',
		'consumer_key',
		'consumer_secret',
	);

	/** Settings that hold a credential, by exact option_name or meta_key. */
	private const SECRET_SETTINGS = array(
		// WordPress keys and salts, stored as options when wp-config.php does not define them.
		'auth_key',
		'secure_auth_key',
		'logged_in_key',
		'nonce_key',
		'auth_salt',
		'secure_auth_salt',
		'logged_in_salt',
		'nonce_salt',
		// WordPress login sessions (user meta).
		'session_tokens',
		// Automator's own signing and connection secrets.
		'mcp_jwt_secret',
		'automator_mcp_hosted_read_credentials_v1',
		'automator_platform_site_signing_secret',
		'automator_platform_site_signing_secret_previous',
		'automator_async_site_token',
		'automator_external_access_token',
		// Automator's encrypted MCP signing key record.
		'automator_mcp_site_signing_key',
		// WordPress application passwords (user meta).
		'_application_passwords',
		// Integration credentials Automator stores under names the pattern below does not cover.
		// Each is registered with set_credentials_option_name(), set_webhook_key_option_name() or a
		// credential constant, or listed in Automator_Options' array option keys.
		'automator_open_ai_secret',
		'automator_stripe_token',
		'automator_stripe_webhook_secret',
		'automator_stripe_webhook_secret_live',
		'automator_stripe_webhook_secret_test',
		'automator_telegram_bot_secret',
		'automator_trello_token',
		'automator_twitter_access_token_secret',
		'automator_salesforce_token',
		'automator_whatsapp_secret',
		'automator_whatsapp_webhook_key',
		'automator_clickup_client',
		'automator_helpscout_client',
		'automator_linkedin_client',
		'automator_convertkit_client',
		'automator_mailerlite_client',
		'automator_sendy_api',
		'zoho_campaigns_credentials',
		'quickbooks_credentials',
		'webhook_key_github',
		'_automator_hubspot_settings',
		'_uncannyowl_facebook_settings',
		'_uncannyowl_facebook_group_settings',
		'_uncannyowl_facebook_pages_settings',
		'_uncannyowl_google_sheet_settings',
		'_uncannyowl_gtm_settings',
		'_uncannyowl_gtt_settings',
		'_uncannyowl_gtw_settings',
		'_uncannyowl_mailchimp_settings',
		'_uncannyowl_slack_settings',
		'_uncannyowl_twitter_settings',
		'_uncannyowl_zoom_settings',
		'_uncannyowl_zoom_webinar_settings',
		'uap_active_campaign_webhook_key',
		'uap_helpscout_webhook_key',
		'uap_mailchimp_webhook_key',
		'uap_automator_gtm_api_consumer_secret',
		'uap_automator_gtt_api_consumer_secret',
		'uap_automator_gtw_api_consumer_secret',
		'uap_automator_twilio_api_auth_token',
		// Automator license keys.
		'uap_automator__license_key',
		'uap_automator_free_license_key',
		'uap_automator_pro_license_key',
	);

	/** Automator's integration credentials, automator_<integration>_<kind>: Automator owns this naming. */
	private const AUTOMATOR_CREDENTIAL_SUFFIXES = 'credentials|api_key|api_secret|api_token|access_token|refresh_token|app_password|password|client_secret';

	/**
	 * Credential formats recognisable on their own, wherever they are stored.
	 *
	 * Stripe secret, restricted and webhook keys; GitHub tokens; Slack tokens;
	 * AWS access key IDs; Google API keys; PEM private keys.
	 *
	 * Every format has a longest match, far above any real key: a prefix and up to
	 * 4096 characters, or a PEM block of up to 16000 bytes. No match is longer than
	 * MAX_FORMAT_BYTES, which is what lets a page cut from a large value be masked
	 * exactly as the whole value would be; see mask_window(). Keep FORMAT_START
	 * in step with the non-PEM formats.
	 */
	private const FORMAT_PATTERN = '/\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{8,4096}|\bwhsec_[A-Za-z0-9]{8,4096}|\bgh[pousr]_[A-Za-z0-9]{20,4096}|\bxox[abprs]-[A-Za-z0-9-]{10,4096}|\bAKIA[0-9A-Z]{16}\b|\bAIza[0-9A-Za-z_-]{35}|' . self::PEM_BEGIN . self::PEM_BODY_BYTE . '{0,16000}?' . self::PEM_END . '/';

	/** The longest text FORMAT_PATTERN can match, in bytes, rounded up. */
	public const MAX_FORMAT_BYTES = 16384;

	/** The longest key that is not a PEM block, in bytes, rounded up. Such a key holds only TOKEN_CHARS. */
	private const MAX_TOKEN_BYTES = 4200;

	/** Every character a key that is not a PEM block can hold. */
	private const TOKEN_CHARS = 'A-Za-z0-9_-';

	/** How each key that is not a PEM block starts. */
	private const FORMAT_START = '(?:sk|rk)_(?:live|test)_|whsec_|gh[pousr]_|xox[abprs]-|AKIA|AIza';

	private const PEM_BEGIN = '-----BEGIN [A-Z ]{0,64}PRIVATE KEY-----';

	private const PEM_END = '-----END [A-Z ]{0,64}PRIVATE KEY-----';

	/**
	 * ASCII text including line endings and escaped JSON newlines. A double quote
	 * ends a JSON or PHP serialized string, so a match cannot cross that boundary.
	 */
	private const PEM_BODY_BYTE = '[\x09\x0A\x0D\x20-\x21\x23-\x7E]';

	/**
	 * Mask key formats without changing the text's length.
	 *
	 * Each match becomes [REDACTED] padded with asterisks to its own length. The text keeps
	 * its length, so an offset means the same place in the stored value, in a read_rows
	 * preview and on every read_cell page, and serialized values stay valid (QA D102).
	 *
	 * @param string $text Text.
	 * @param int    $from Byte offset to look from. The text before it is left as it is.
	 * @return string
	 */
	public static function mask_formats( string $text, int $from = 0 ): string {
		if ( false === preg_match_all( self::FORMAT_PATTERN, $text, $found, PREG_OFFSET_CAPTURE, $from ) || PREG_NO_ERROR !== preg_last_error() ) {
			// The matcher gave up, so nothing after $from can be shown.
			return substr( $text, 0, $from ) . str_repeat( '*', self::chars( substr( $text, $from ) ) );
		}
		foreach ( array_reverse( $found[0] ) as $match ) {
			$length = strlen( $match[0] );
			$mask   = $length > strlen( self::MARKER ) ? self::MARKER . str_repeat( '*', $length - strlen( self::MARKER ) ) : str_repeat( '*', $length );
			$text   = substr_replace( $text, $mask, $match[1], $length );
		}
		return $text;
	}

	/**
	 * Mask a window cut from a large value exactly as mask_formats() masks the whole value.
	 *
	 * A window can begin in the middle of a key, where matching from its first character
	 * would miss the key or find a different one. So matching starts at a point that is
	 * certainly outside every key, at or before the page:
	 *
	 * - No key that is not a PEM block covers a point when no such key starts in the
	 *   unbroken run of key characters before it.
	 * - No PEM block covers a point when no block begins in the MAX_FORMAT_BYTES before
	 *   it, or the last one that begins there has ended.
	 *
	 * From such a point the matcher finds the same keys as it does in the whole value.
	 * The caller cuts the window with MAX_FORMAT_BYTES characters after the page, so
	 * every key that starts in the page is whole. Returns null when no such point is in
	 * the window.
	 *
	 * @param string $window         Text cut from the value.
	 * @param bool   $at_value_start Whether the window begins where the value begins.
	 * @param int    $page_chars     Characters in the window before the page.
	 * @return string|null The masked window, or null when it cannot be masked with certainty.
	 */
	public static function mask_window( string $window, bool $at_value_start, int $page_chars ): ?string {
		$point = strlen( (string) mb_substr( $window, 0, $page_chars, 'UTF-8' ) );
		$floor = $at_value_start ? 0 : self::MAX_FORMAT_BYTES;
		$head  = substr( $window, 0, $point + self::MAX_FORMAT_BYTES );
		if ( false === preg_match_all( '/(?=(' . self::PEM_BEGIN . '))/', $head, $begins, PREG_OFFSET_CAPTURE ) || false === preg_match_all( '/(?=(' . self::PEM_END . '))/', $head, $ends, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		while ( $point >= $floor ) {
			$moved = self::key_start_before( $window, $point ) ?? self::open_pem_before( $begins[1], $ends[1], $point );
			if ( null === $moved ) {
				return self::mask_formats( $window, $point );
			}
			// A key may cover this point. Its own start is the next point to try.
			$point = $moved;
		}
		return null;
	}

	/**
	 * Where a key that is not a PEM block could start and still cover a point, or null.
	 *
	 * @param string $text  Text.
	 * @param int    $point Byte offset.
	 * @return int|null Byte offset of the earliest such start.
	 */
	private static function key_start_before( string $text, int $point ): ?int {
		$from = max( 0, $point - self::MAX_TOKEN_BYTES );
		if ( 1 !== preg_match( '/[' . self::TOKEN_CHARS . ']*$/', substr( $text, $from, $point - $from ), $run ) || '' === $run[0] ) {
			return null;
		}
		$run_from = $point - strlen( $run[0] );
		// A start that straddles the point still begins before it.
		if ( 1 !== preg_match( '/' . self::FORMAT_START . '/', substr( $text, $run_from, strlen( $run[0] ) + 8 ), $start, PREG_OFFSET_CAPTURE ) || $start[0][1] >= strlen( $run[0] ) ) {
			return null;
		}
		return $run_from + $start[0][1];
	}

	/**
	 * Where a PEM block that may still be open at a point begins, or null.
	 *
	 * @param array $begins Every PEM start in the text, as [text, byte offset].
	 * @param array $ends   Every PEM end in the text, as [text, byte offset].
	 * @param int   $point  Byte offset.
	 * @return int|null Byte offset of the last block that begins before the point and has not ended by it.
	 */
	private static function open_pem_before( array $begins, array $ends, int $point ): ?int {
		$last = null;
		foreach ( $begins as $begin ) {
			if ( $begin[1] < $point && $begin[1] >= $point - self::MAX_FORMAT_BYTES ) {
				$last = $begin;
			}
		}
		if ( null === $last ) {
			return null;
		}
		$body_from = $last[1] + strlen( $last[0] );
		foreach ( $ends as $end ) {
			if ( $end[1] >= $body_from && $end[1] + strlen( $end[0] ) <= $point ) {
				return null;
			}
		}
		return $last[1];
	}

	/**
	 * @param string $text Text.
	 * @return int Its length in characters.
	 */
	private static function chars( string $text ): int {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/**
	 * Whether a column holds a credential.
	 *
	 * @param string $column Column name, without a table qualifier.
	 * @return bool
	 */
	public static function is_secret_column( string $column ): bool {
		return in_array( strtolower( $column ), self::SECRET_COLUMNS, true );
	}

	/**
	 * Whether a stored setting holds a credential, or the site owner redacted it.
	 *
	 * @param mixed    $name     The option_name or meta_key of the row.
	 * @param string[] $prefixes Lower-cased name prefixes the owner redacted for this table.
	 * @return bool
	 */
	public static function is_secret_setting( $name, array $prefixes = array() ): bool {
		if ( ! is_scalar( $name ) ) {
			return false;
		}
		$name = (string) $name;

		if ( in_array( $name, self::SECRET_SETTINGS, true )
			|| 1 === preg_match( '/^(?:automator|uap)_[a-z0-9_]+_(?:' . self::AUTOMATOR_CREDENTIAL_SUFFIXES . ')$/', $name )
			// Automator's MCP internal and stored tokens, per site on a network.
			|| 1 === preg_match( '/^automator_mcp_(?:internal_token|tokens_encrypted)(?:_site_[0-9]+)?$/', $name ) ) {
			return true;
		}
		$lower = strtolower( $name );
		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $lower, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * SQL that is true for every setting name not known to hold a credential.
	 *
	 * A grouped or aggregated query cannot withhold one row's value afterwards, so
	 * those rows are left out of it instead.
	 *
	 * @param string   $name_column A sanitized, backticked name column reference.
	 * @param string[] $prefixes    Lower-cased name prefixes the owner redacted for this table.
	 * @return string
	 */
	public static function sql_not_secret_setting( string $name_column, array $prefixes = array() ): string {
		global $wpdb;
		$names = implode( ', ', array_map( static fn( $name ) => "'" . $name . "'", self::SECRET_SETTINGS ) );
		$regex = "'^(automator|uap)_[a-z0-9_]+_(" . self::AUTOMATOR_CREDENTIAL_SUFFIXES . ")$|^automator_mcp_(internal_token|tokens_encrypted)(_site_[0-9]+)?$'";
		$owner = '';
		foreach ( $prefixes as $prefix ) {
			// A prefix's _ and % are literal, not LIKE wildcards.
			$owner .= ' AND ' . $wpdb->prepare( "{$name_column} NOT LIKE %s", $wpdb->esc_like( $prefix ) . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The column reference is sanitized and backticked by the caller.
		}

		return "( {$name_column} IS NULL OR ( {$name_column} NOT IN ( {$names} ) AND {$name_column} NOT REGEXP {$regex}{$owner} ) )";
	}

	/**
	 * The column that names a value column's row, or null for any other column.
	 *
	 * @param string $column Column name, without a table qualifier.
	 * @return string|null
	 */
	public static function name_column_for( string $column ): ?string {
		return self::VALUE_COLUMNS[ strtolower( $column ) ] ?? null;
	}

	/**
	 * One cell as a model may see it.
	 *
	 * A value whose row is a known credential setting is withheld whole. Otherwise
	 * only unmistakable key formats are masked, at their own length, so serialized
	 * and JSON values stay valid and offsets keep their meaning. Anything else
	 * comes back exactly as stored.
	 *
	 * @param mixed       $value        The cell.
	 * @param string|null $setting_name The row's option_name or meta_key, for a value column.
	 * @param string[]    $prefixes     Lower-cased name prefixes the owner redacted for this table.
	 * @return mixed
	 */
	public static function redact_cell( $value, ?string $setting_name = null, array $prefixes = array() ) {
		if ( null !== $setting_name && self::is_secret_setting( $setting_name, $prefixes ) ) {
			return self::MARKER;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		return self::mask_formats( $value );
	}
}

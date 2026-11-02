<?php
/**
 * WordPress.org packages and update offers, shared by the plugin, theme and translation commands.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Manage;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Update_Status;

/**
 * Packages install only from downloads.wordpress.org, and only when this
 * site's PHP and WordPress can run them. Update offers are WordPress's own,
 * refreshed when they are old, and reported as old when WordPress.org, or
 * the named item's own update server, could not be reached. A failed check
 * is not repeated for a while, and keeps the time of the answer it left.
 */
final class Packages {

	/** Statuses of items that were already as asked. */
	private const ALREADY = array( 'up to date', 'unchanged' );

	/** Status of an item no update source reports on. */
	public const NOT_TRACKED = 'not tracked';

	/** Status of an update after which the site stopped with a PHP fatal error. */
	public const SITE_ERROR = 'site error';

	/** Offers older than this are checked again before a command uses them. */
	private const FRESH_SECONDS = HOUR_IN_SECONDS;

	/** Longest an update check may take, whatever follows it. */
	public const CHECK_SECONDS = 5.0;

	/** A check that could not reach WordPress.org is not repeated for this long. */
	private const RETRY_SECONDS = 15 * MINUTE_IN_SECONDS;

	/** When a check of each type last failed, as a site transient. */
	private const FAILED_CHECK = 'automator_site_manage_check_failed_';

	/**
	 * Which update check refreshes which offers.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	private const CHECKS = array(
		'plugin' => array( 'update_plugins', 'wp_update_plugins' ),
		'theme'  => array( 'update_themes', 'wp_update_themes' ),
		'core'   => array( 'update_core', 'wp_version_check' ),
	);

	/**
	 * Whether the check has a recent, credible timestamp. This alone does
	 * not establish that a particular plugin's provider answered.
	 *
	 * @param mixed $check WordPress's update transient.
	 * @return bool
	 */
	public static function is_current( $check ): bool {
		$checked = is_object( $check ) && isset( $check->last_checked ) && is_numeric( $check->last_checked ) ? (int) $check->last_checked : 0;
		$age     = time() - $checked;

		return $checked > 0 && $age >= 0 && $age <= self::FRESH_SECONDS;
	}

	/**
	 * Whether a URL is a download from WordPress.org, exactly: https, the
	 * downloads.wordpress.org host, no user name or other port.
	 *
	 * @param mixed $url URL.
	 * @return bool
	 */
	public static function is_wordpress_org_download( $url ): bool {
		if ( ! is_string( $url ) || 1 !== preg_match( '#^https://downloads\.wordpress\.org/#', $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );

		return is_array( $parts )
			&& 'downloads.wordpress.org' === ( $parts['host'] ?? '' )
			&& ! isset( $parts['user'] )
			&& ! isset( $parts['pass'] )
			&& 443 === (int) ( $parts['port'] ?? 443 );
	}

	/**
	 * Whether WordPress.org made an update offer, so its package must come
	 * from downloads.wordpress.org too. Offers from other update servers
	 * carry their own package addresses.
	 *
	 * @param mixed $offer An offer from update_plugins or update_themes.
	 * @return bool
	 */
	public static function is_wordpress_org_offer( $offer ): bool {
		$offer = is_object( $offer ) ? get_object_vars( $offer ) : $offer;
		if ( ! is_array( $offer ) ) {
			return false;
		}
		$id  = isset( $offer['id'] ) && is_string( $offer['id'] ) ? $offer['id'] : '';
		$url = isset( $offer['url'] ) && is_string( $offer['url'] ) ? wp_parse_url( $offer['url'], PHP_URL_HOST ) : null;

		return 0 === strpos( $id, 'w.org/' ) || 'wordpress.org' === $url;
	}

	/**
	 * Refuse a WordPress.org package this site cannot run, or that does not
	 * come from WordPress.org.
	 *
	 * @param object $api  plugins_api() or themes_api() information.
	 * @param string $slug Requested slug.
	 * @param string $type "plugin" or "theme".
	 * @return void
	 *
	 * @throws Command_Error When it cannot be installed.
	 */
	public static function require_installable( $api, string $slug, string $type ): void {
		$requires_php = isset( $api->requires_php ) && is_scalar( $api->requires_php ) ? (string) $api->requires_php : '';
		if ( '' !== $requires_php && ! is_php_version_compatible( $requires_php ) ) {
			throw new Command_Error( sprintf( 'The %1$s %2$s needs PHP %3$s; this site runs PHP %4$s. Nothing was changed.', $type, $slug, $requires_php, PHP_VERSION ) );
		}
		$requires = isset( $api->requires ) && is_scalar( $api->requires ) ? (string) $api->requires : '';
		if ( '' !== $requires && ! is_wp_version_compatible( $requires ) ) {
			throw new Command_Error( sprintf( 'The %1$s %2$s needs WordPress %3$s; this site runs %4$s. Nothing was changed.', $type, $slug, $requires, get_bloginfo( 'version' ) ) );
		}
		if ( ! self::is_wordpress_org_download( $api->download_link ?? null ) ) {
			throw new Command_Error( sprintf( 'WordPress.org did not offer a download of the %1$s %2$s from downloads.wordpress.org, so it was not installed. A plugin on this site may be replacing WordPress.org\'s answer. Nothing was changed.', $type, $slug ) );
		}
	}

	/**
	 * A WordPress.org lookup failure as one line.
	 *
	 * @param \WP_Error $error The lookup's error.
	 * @param string    $slug  Requested slug.
	 * @param string    $type  "plugin" or "theme".
	 * @return string
	 */
	public static function lookup_failure( \WP_Error $error, string $slug, string $type ): string {
		$out_of_time = Change_Session::OUT_OF_TIME === $error->get_error_code();
		$data        = Site_Changes::text( $out_of_time ? $error->get_error_message() : $error->get_error_data() );
		if ( $out_of_time || ( '' !== $data && false !== strpos( $data, 'was not started' ) ) ) {
			// This command refused the request for lack of time.
			return sprintf( 'The %1$s %2$s could not be looked up on WordPress.org in time. %3$s Nothing was changed. Run the command again; a new request has the full time.', $type, $slug, $data );
		}
		if ( '' === $data ) {
			// WordPress.org answered, with its own error, such as "Plugin not found."
			return sprintf( 'WordPress.org answered "%1$s" for the %2$s slug %3$s. Nothing was changed. A slug is the last part of the %2$s\'s WordPress.org address, such as akismet in wordpress.org/plugins/akismet.', Site_Changes::text( $error->get_error_message() ), $type, $slug );
		}

		return sprintf( 'WordPress.org could not be reached to look up the %1$s %2$s: %3$s. Nothing was changed. Try again later.', $type, $slug, rtrim( $data, '. ' ) );
	}

	/**
	 * Refresh WordPress's offers for plugins, themes or core, as WordPress
	 * does, rechecking offers older than an hour. The check gets at most
	 * CHECK_SECONDS. Only a failure to reach WordPress.org, or one of the
	 * given hosts, makes the results old; other update servers that did not
	 * answer are named in a warning. After such a failure the check is not
	 * repeated for RETRY_SECONDS, and WordPress keeps the time of the answer
	 * it has, so nothing reports old offers as new.
	 *
	 * @param string         $type    "plugin", "theme" or "core".
	 * @param Change_Session $session The session the check runs in.
	 * @param string[]       $hosts   Update servers of the named item, besides WordPress.org.
	 * @return array{0: bool, 1: string[]} Whether the offers are current, and warnings.
	 */
	public static function refresh( string $type, Change_Session $session, array $hosts = array() ): array {
		list( $transient, $check ) = self::CHECKS[ $type ];
		$current                   = get_site_transient( $transient );
		$checked                   = is_object( $current ) && isset( $current->last_checked ) && is_numeric( $current->last_checked ) ? (int) $current->last_checked : 0;
		$stale                     = ! is_object( $current ) || time() - $checked > self::FRESH_SECONDS;
		if ( ! $stale && 'core' === $type ) {
			// wp_version_check() would ask WordPress.org again after a minute.
			return array( true, array() );
		}
		$failed_at = get_site_transient( self::FAILED_CHECK . $type );
		if ( $stale && is_numeric( $failed_at ) && (int) $failed_at > time() - self::RETRY_SECONDS ) {
			return array( false, array( sprintf( 'WordPress.org could not be reached for an update check at %1$s UTC, so it is not asked again before %2$s UTC; these results use WordPress\'s check from %3$s.', gmdate( 'H:i', (int) $failed_at ), gmdate( 'H:i', (int) $failed_at + self::RETRY_SECONDS ), self::checked_at( $checked ) ) ) );
		}
		// Before anything is saved: saving the offers runs update providers' hooks, which may ask their servers.
		$before = $session->failed_requests();
		$session->expect( max( 0.0, $session->budget()->remaining() - self::CHECK_SECONDS ), 0.0 );
		if ( is_object( $current ) && $stale ) {
			// WordPress rechecks from REST requests only twice a day; ask it now.
			$current->last_checked = 0;
			set_site_transient( $transient, $current );
		}

		call_user_func( $check );
		$failed   = array_diff_key( $session->failed_requests(), $before );
		$own      = array_flip( array_merge( array( 'api.wordpress.org' ), $hosts ) );
		$relevant = array_intersect_key( $failed, $own );
		$warnings = array();
		$others   = array_diff_key( $failed, $own );
		if ( array() !== $others ) {
			$warnings[] = sprintf( 'Some update servers did not answer (%s), so updates they offer may be missing or old.', self::reasons( $others ) );
		}
		if ( array() === $relevant ) {
			return array( true, $warnings );
		}

		// WordPress marked the check as done before it failed; keep the time of the answer it has.
		$after = get_site_transient( $transient );
		if ( is_object( $after ) ) {
			$after->last_checked = $checked;
			set_site_transient( $transient, $after );
		}
		if ( array() !== array_diff( array_keys( $relevant ), $session->refused_hosts() ) ) {
			// Not refused for lack of time here: the server did not answer.
			set_site_transient( self::FAILED_CHECK . $type, time(), self::RETRY_SECONDS );
		}
		array_unshift( $warnings, sprintf( 'Current updates could not be checked (%s), so these results use WordPress\'s check from %s.', self::reasons( $relevant ), self::checked_at( $checked ) ) );

		return array( false, $warnings );
	}

	/**
	 * What WordPress's last check says about one installed plugin or theme:
	 * "offered" or "withheld" with the newer version and the offer, "up to
	 * date" when a current check confirmed the installed version, NOT_TRACKED
	 * when a current check of the installed version got no answer about it
	 * from WordPress.org or any update provider, "stale" when its answer is
	 * not newer but cannot be confirmed, and "unknown" for malformed data.
	 *
	 * @param mixed  $check     WordPress's update transient.
	 * @param string $key       Plugin file or theme folder.
	 * @param string $installed Installed version.
	 * @param bool   $current   Whether refreshing the check reported no failure.
	 * @return array{0: string, 1: string, 2: array<string, mixed>|null} State, newer version, offer.
	 */
	public static function classify( $check, string $key, string $installed, bool $current ): array {
		$offers    = is_object( $check ) && isset( $check->response ) && is_array( $check->response ) ? $check->response : array();
		$withheld  = is_object( $check ) && isset( $check->no_update ) && is_array( $check->no_update ) ? $check->no_update : array();
		$offered   = array_key_exists( $key, $offers );
		$confirmed = $current && self::is_current( $check ) && isset( $check->checked ) && is_array( $check->checked ) && isset( $check->checked[ $key ] ) && is_scalar( $check->checked[ $key ] ) && (string) $check->checked[ $key ] === $installed;
		if ( ! $offered && ! array_key_exists( $key, $withheld ) ) {
			return array( $confirmed ? self::NOT_TRACKED : 'unknown', '', null );
		}
		$version = Update_Status::entries( $check, $offered ? 'response' : 'no_update' )[ $key ]['new_version'] ?? null;
		$offer   = $offered ? $offers[ $key ] : $withheld[ $key ];
		$offer   = is_object( $offer ) ? get_object_vars( $offer ) : $offer;
		if ( null === $version || ! is_array( $offer ) ) {
			return array( 'unknown', '', null );
		}
		if ( ! version_compare( $version, $installed, '>' ) ) {
			return array( $confirmed ? 'up to date' : 'stale', '', null );
		}

		return array( $offered ? 'offered' : 'withheld', $version, $offer );
	}

	/**
	 * Whether WordPress already offers a newer version of one item, so its
	 * update needs no new check.
	 *
	 * @param string $transient update_plugins or update_themes.
	 * @param string $key       Plugin file or theme folder.
	 * @param string $installed Installed version.
	 * @return bool
	 */
	public static function offers_newer( string $transient, string $key, string $installed ): bool {
		$version = Update_Status::entries( get_site_transient( $transient ), 'response' )[ $key ]['new_version'] ?? null;

		return null !== $version && version_compare( $version, $installed, '>' );
	}

	/**
	 * The hosts of the update server and package of one item's last offer or
	 * answer, and of its Update URI header, which WordPress asks instead of
	 * WordPress.org.
	 *
	 * @param string $transient  update_plugins or update_themes.
	 * @param string $key        Plugin file or theme folder.
	 * @param string $update_uri The item's Update URI header.
	 * @return string[]
	 */
	public static function update_hosts( string $transient, string $key, string $update_uri ): array {
		$check = get_site_transient( $transient );
		$hosts = array( wp_parse_url( $update_uri, PHP_URL_HOST ) );
		foreach ( array( 'response', 'no_update' ) as $list ) {
			$entry = is_object( $check ) && isset( $check->{$list} ) && is_array( $check->{$list} ) ? ( $check->{$list}[ $key ] ?? null ) : null;
			$entry = is_object( $entry ) ? get_object_vars( $entry ) : $entry;
			foreach ( array( 'url', 'package' ) as $field ) {
				$hosts[] = is_array( $entry ) && isset( $entry[ $field ] ) && is_string( $entry[ $field ] ) ? wp_parse_url( $entry[ $field ], PHP_URL_HOST ) : null;
			}
		}

		return array_values( array_unique( array_filter( $hosts, 'is_string' ) ) );
	}

	/**
	 * Failed hosts with why, as a clause.
	 *
	 * @param array<string, string> $failed Reasons by host.
	 * @return string
	 */
	private static function reasons( array $failed ): string {
		$reasons = array();
		foreach ( $failed as $host => $reason ) {
			$reasons[] = sprintf( '%s: %s', $host, rtrim( $reason, '. ' ) );
		}

		return implode( '; ', $reasons );
	}

	/**
	 * When WordPress last checked, for messages.
	 *
	 * @param int $checked Unix time, or 0.
	 * @return string
	 */
	private static function checked_at( int $checked ): string {
		return $checked > 0 ? gmdate( 'Y-m-d H:i', $checked ) . ' UTC' : 'an unknown time';
	}

	/**
	 * Drop updated items from WordPress's update offers, so listings stop
	 * offering them before WordPress next checks.
	 *
	 * @param string   $transient update_plugins or update_themes.
	 * @param string[] $keys      Updated plugin files or theme folders.
	 * @return void
	 */
	public static function forget_offers( string $transient, array $keys ): void {
		if ( array() === $keys ) {
			return;
		}
		$current = get_site_transient( $transient );
		if ( ! is_object( $current ) || ! isset( $current->response ) || ! is_array( $current->response ) ) {
			return;
		}
		foreach ( $keys as $key ) {
			unset( $current->response[ $key ] );
		}
		set_site_transient( $transient, $current );
	}

	/**
	 * Result rows with counts by status. The result is a failure when nothing
	 * was done and something failed or was not started. It says nothing was
	 * changed only when no item was started, or every failure happened before
	 * any file changed.
	 *
	 * @param array<int, array<string, string>> $rows     Result rows.
	 * @param bool                              $dry_run  Whether nothing was meant to change.
	 * @param string[]                          $warnings Warnings, such as old update information.
	 * @param string                            $done     The status of an item that was done, such as "updated".
	 * @param bool                              $fail_when_none_done Whether the result is a failure when nothing was done, unless everything already was.
	 * @return Command_Output
	 */
	public static function output( array $rows, bool $dry_run, array $warnings, string $done, bool $fail_when_none_done = false ): Command_Output {
		$counts = array_count_values( array_column( $rows, 'status' ) );
		ksort( $counts );
		$parts = array();
		foreach ( $counts as $status => $count ) {
			$parts[] = sprintf( '%d %s', $count, $status );
		}
		$line = array() === $parts ? 'Nothing to do.' : sprintf( 'By status: %s.', implode( ', ', $parts ) );
		if ( $dry_run ) {
			$line .= ' Dry run: nothing was changed.';
		} elseif ( self::changed_nothing( $rows ) ) {
			$line .= ' Nothing was changed.';
		}
		if ( ! $dry_run && isset( $counts['not started'] ) ) {
			$line .= ' Run the command again for the ones not started.';
		}

		$output = Command_Output::rows( $rows )->with_stats( $line );
		foreach ( $warnings as $warning ) {
			$output = $output->with_warning( $warning );
		}
		$settled = array_sum( array_intersect_key( $counts, array_flip( self::ALREADY ) ) ) === count( $rows );
		// A change after which the site stops with a PHP fatal error is a failure, whatever else was done.
		if ( ! $dry_run && ( isset( $counts[ self::SITE_ERROR ] ) || ( ! isset( $counts[ $done ] ) && ( ( $fail_when_none_done && ! $settled ) || isset( $counts['failed'] ) || isset( $counts['not started'] ) || isset( $counts['unknown'] ) ) ) ) ) {
			$output = $output->with_failure();
		}

		return $output;
	}

	/**
	 * Whether no row changed anything: each was skipped, up to date or not
	 * started, or failed before any file changed, as its detail says.
	 *
	 * @param array<int, array<string, string>> $rows Result rows.
	 * @return bool
	 */
	private static function changed_nothing( array $rows ): bool {
		foreach ( $rows as $row ) {
			$status = $row['status'] ?? '';
			if ( in_array( $status, array_merge( self::ALREADY, array( 'skipped', 'not started', 'unknown', self::NOT_TRACKED ) ), true ) ) {
				continue;
			}
			if ( 'failed' === $status && false !== strpos( $row['detail'] ?? '', 'Nothing was changed.' ) ) {
				continue;
			}
			return false;
		}

		return true;
	}
}

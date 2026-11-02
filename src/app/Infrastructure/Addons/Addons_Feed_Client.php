<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Addons;

use InvalidArgumentException;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon;
use WP_Error;

/**
 * HTTP client for the automatorplugin.com addons feed.
 *
 * One instance per request, reached through `instance()` — the constructor is
 * private because the mapped list is memoized on the object, and a second
 * instance would hold a second memo that `clear_cache()` on the first would
 * not reach.
 *
 * Owns the upstream URL (overridable via the AUTOMATOR_PLUGIN_ADDONS_URL /
 * _DIRECTORY constants) and the feed transient. Fetching is lazy — nothing
 * leaves the site until a consumer asks for addons. The transient holds the
 * raw upstream rows only, so install state is never served from cache.
 * Failures surface as WP_Error and are never cached.
 */
final class Addons_Feed_Client {

	/**
	 * Bump by hand when the consumed feed contract changes, to shed rows
	 * cached in the older shape.
	 *
	 * @var string
	 */
	const FEED_VERSION = '1';

	/**
	 * Transient key prefix; the upstream URL is hashed onto the end.
	 *
	 * @var string
	 */
	const FEED_TRANSIENT_PREFIX = 'automator_addons_feed_v' . self::FEED_VERSION . '_';

	/**
	 * Cache TTL for the feed transient.
	 *
	 * @var int
	 */
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Default upstream base URL.
	 *
	 * @var string
	 */
	const DEFAULT_API_BASE = AUTOMATOR_STORE_URL;

	/**
	 * Default upstream addons directory.
	 *
	 * @var string
	 */
	const DEFAULT_ADDONS_DIRECTORY = 'wp-content/uploads/automator-addons/';

	/**
	 * Feed file name.
	 *
	 * @var string
	 */
	const ADDONS_FILE = 'automator-addons.json';

	/**
	 * The filter third parties use to shape the feed rows.
	 *
	 * @var string
	 */
	const FEED_FILTER = 'automator_addons_feed_data';

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Per-request memo of the mapped addons.
	 *
	 * @var array<int,Addon>|null
	 */
	private ?array $addons = null;

	/**
	 * Per-request memo of a fetch failure. Without it every consumer in the
	 * request re-attempts the network, and one page load makes several.
	 *
	 * @var WP_Error|null
	 */
	private $failure = null;

	/**
	 * Private: reach the client through `instance()`.
	 */
	private function __construct() {
	}

	/**
	 * Transient key for the feed currently configured.
	 *
	 * Keyed on the upstream URL, so re-pointing AUTOMATOR_PLUGIN_ADDONS_URL
	 * reads the new host on the next request rather than the previous host's
	 * cached rows.
	 *
	 * @return string
	 */
	public static function transient_key(): string {
		return self::FEED_TRANSIENT_PREFIX . md5( self::feed_url() );
	}

	/**
	 * Shared-instance accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Fetch the published addons, transient-cached.
	 *
	 * @return array<int,Addon>|WP_Error Addons, or the failure.
	 */
	public function get_addons() {

		if ( null !== $this->failure ) {
			return $this->failure;
		}

		if ( null !== $this->addons ) {
			return $this->addons;
		}

		$rows = $this->get_rows();
		if ( is_wp_error( $rows ) ) {
			$this->failure = $rows;
			return $this->failure;
		}

		$filtered = Dispatcher::filter( self::FEED_FILTER, $rows );
		if ( is_array( $filtered ) && ! empty( $filtered ) ) {
			$rows = $filtered;
		}

		$this->addons = $this->map_rows( $rows );

		return $this->addons;
	}

	/**
	 * Drop the cached feed and the per-request memo.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->addons  = null;
		$this->failure = null;
		delete_transient( self::transient_key() );
	}

	/**
	 * Read the raw upstream rows, from cache when warm.
	 *
	 * @return array<int,mixed>|WP_Error
	 */
	private function get_rows() {

		$cached = get_transient( self::transient_key() );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get( self::feed_url() );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'addons_feed_unreachable', $response->get_error_message() );
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'addons_feed_unreachable',
				esc_html_x( 'The addons feed could not be fetched.', 'Addons', 'uncanny-automator' )
			);
		}

		$rows = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $rows ) ) {
			return new WP_Error(
				'addons_feed_invalid',
				esc_html_x( 'The addons feed returned invalid data.', 'Addons', 'uncanny-automator' )
			);
		}

		// An empty feed served at 200 is not cached, so a bad upstream deploy
		// costs one request rather than blanking the page for the whole TTL.
		if ( ! empty( $rows ) ) {
			set_transient( self::transient_key(), $rows, self::CACHE_TTL );
		}

		return $rows;
	}

	/**
	 * Map raw rows to value objects, skipping rows the feed malformed.
	 *
	 * @param array<int,mixed> $rows Raw upstream rows.
	 *
	 * @return array<int,Addon>
	 */
	private function map_rows( array $rows ): array {

		$addons = array();

		foreach ( $rows as $row ) {

			if ( ! is_array( $row ) ) {
				continue;
			}

			try {
				$addons[] = Addon::from_feed_array( $row );
			} catch ( InvalidArgumentException $e ) {
				continue;
			}
		}

		return $addons;
	}

	/**
	 * Upstream feed URL, honoring the wp-config override constants.
	 *
	 * @return string
	 */
	private static function feed_url(): string {

		$api_base = defined( 'AUTOMATOR_PLUGIN_ADDONS_URL' )
			? trailingslashit( AUTOMATOR_PLUGIN_ADDONS_URL )
			: self::DEFAULT_API_BASE;

		$directory = defined( 'AUTOMATOR_PLUGIN_ADDONS_DIRECTORY' )
			? trailingslashit( AUTOMATOR_PLUGIN_ADDONS_DIRECTORY )
			: self::DEFAULT_ADDONS_DIRECTORY;

		return trailingslashit( $api_base ) . $directory . self::ADDONS_FILE;
	}
}

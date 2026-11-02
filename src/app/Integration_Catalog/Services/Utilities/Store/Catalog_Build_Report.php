<?php
/**
 * Catalog Build Report
 *
 * Developer-only account of what a catalog build left out, and of how it
 * resolved the integrations the feed does not carry.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store;

use Uncanny_Automator\App\Events\Dispatcher;

/**
 * One spot for the gate and one buffer for everything a build skips: whole
 * records the store cannot build, and items the normalizer drops. It also
 * carries the discovery path - each integration resolved by discovery and
 * where its entry came from - and the build's split across feed scenarios.
 *
 * The buffer is static because the two contributors sit two layers apart and
 * the alternative is threading a report object through the builder into the
 * normalizer. The skip buffers live for one build - start() empties them,
 * write() drains them. The discovery buffers live as long as the feed
 * service's processed cache, which a second build in the same request reuses
 * without discovering again: start_discovery() empties them when that cache
 * is computed, and write() prints them without draining. Every method is a
 * no-op while the gate is off, so a site that has not asked for the report
 * pays a defined() check and nothing else.
 */
final class Catalog_Build_Report {

	/**
	 * Log file the snapshot is written to (see Utilities::log_snapshot()).
	 *
	 * @var string
	 */
	const LOG_FILE = 'integration-catalog';

	/**
	 * Skipped records: key (code, or post:<id>) => reason.
	 *
	 * @var array<string, string>
	 */
	private static array $integrations = array();

	/**
	 * Dropped items: one line each, already formatted.
	 *
	 * @var string[]
	 */
	private static array $items = array();

	/**
	 * Discovered integrations: one line each, already formatted.
	 *
	 * @var string[]
	 */
	private static array $discovered = array();

	/**
	 * Feed scenario split, or null when the feed service has not reported it.
	 *
	 * @var array{merged: int, discovered: int, feed_only: int}|null
	 */
	private static ?array $scenarios = null;

	/**
	 * Whether the report is switched on.
	 *
	 * Enable with `define( 'AUTOMATOR_DEBUG_INTEGRATION_CATALOG', true )` or the
	 * `automator_debug_integration_catalog` filter. Same shape as trigger tracing.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return ( defined( 'AUTOMATOR_DEBUG_INTEGRATION_CATALOG' ) && AUTOMATOR_DEBUG_INTEGRATION_CATALOG )
			|| (bool) Dispatcher::filter( 'automator_debug_integration_catalog', false );
	}

	/**
	 * Begin a build: forget the skips recorded since the last write.
	 *
	 * @return void
	 */
	public static function start(): void {
		self::$integrations = array();
		self::$items        = array();
	}

	/**
	 * Begin processing the feed: forget the discovered integrations and the
	 * scenario split recorded by the previous processing.
	 *
	 * @return void
	 */
	public static function start_discovery(): void {
		self::$discovered = array();
		self::$scenarios  = null;
	}

	/**
	 * Record a feed record the store could not build.
	 *
	 * @param string $key    Integration code, or post:<id> when there is no code.
	 * @param string $reason Why it was skipped.
	 *
	 * @return void
	 */
	public static function integration_skipped( string $key, string $reason ): void {
		if ( ! self::enabled() ) {
			return;
		}
		self::$integrations[ $key ] = $reason;
	}

	/**
	 * Record an item the normalizer dropped from a record that still built.
	 *
	 * @param string $integration_code Owning integration.
	 * @param string $label            Whatever identifies the item to a reader - its sentence, or a position.
	 * @param string $reason           Why it was dropped.
	 *
	 * @return void
	 */
	public static function item_skipped( string $integration_code, string $label, string $reason ): void {
		if ( ! self::enabled() ) {
			return;
		}
		self::$items[] = sprintf( '%s item "%s": %s', $integration_code, $label, $reason );
	}

	/**
	 * An integration the feed does not carry was resolved by discovery.
	 *
	 * @param string             $code        Integration code.
	 * @param string             $source      `cache`, `rebuilt` or `bypass` (rebuilt because the switch is on).
	 * @param string             $fingerprint The fingerprint the entry carries.
	 * @param array<string, int> $counts      `triggers`, `actions`, `conditions`, `loop_filters`.
	 * @param string             $version     The version the entry was built under, for the reader.
	 *
	 * @return void
	 */
	public static function integration_discovered( string $code, string $source, string $fingerprint, array $counts, string $version = '' ): void {
		if ( ! self::enabled() ) {
			return;
		}
		self::$discovered[] = sprintf(
			'%s: %s (fingerprint %s…, version %s) triggers %d, actions %d, conditions %d, loop filters %d',
			$code,
			$source,
			substr( $fingerprint, 0, 6 ),
			'' !== $version ? $version : 'unknown',
			$counts['triggers'] ?? 0,
			$counts['actions'] ?? 0,
			$counts['conditions'] ?? 0,
			$counts['loop_filters'] ?? 0
		);
	}

	/**
	 * Record how the build's integrations split across the feed scenarios.
	 *
	 * @param int $merged     Registered and in the feed.
	 * @param int $discovered Registered and not in the feed.
	 * @param int $feed_only  In the feed and not registered.
	 *
	 * @return void
	 */
	public static function scenarios( int $merged, int $discovered, int $feed_only ): void {
		if ( ! self::enabled() ) {
			return;
		}
		self::$scenarios = array(
			'merged'     => $merged,
			'discovered' => $discovered,
			'feed_only'  => $feed_only,
		);
	}

	/**
	 * Write the build's snapshot and drain the skip buffers.
	 *
	 * Forced past the debug-logs toggle: that switch is support's, this gate
	 * is ours. Overwrites the previous report, so the file never grows.
	 *
	 * @param int    $records How many records the build saw.
	 * @param int    $built   How many it produced.
	 * @param string $feed    Which feed file the build read, from Integration_Feed_Service::get_active_feed().
	 *
	 * @return void
	 */
	public static function write( int $records, int $built, string $feed = '' ): void {
		if ( ! self::enabled() ) {
			return;
		}

		$lines = array();
		if ( '' !== $feed ) {
			$lines[] = 'feed: ' . $feed;
		}
		$header = sprintf( 'records: %d | built: %d | skipped: %d | items skipped: %d', $records, $built, count( self::$integrations ), count( self::$items ) );
		if ( null !== self::$scenarios ) {
			$header .= sprintf( ' | merged: %d | discovered: %d | feed only: %d', self::$scenarios['merged'], self::$scenarios['discovered'], self::$scenarios['feed_only'] );
		}
		$lines[] = $header;
		foreach ( self::$integrations as $key => $reason ) {
			$lines[] = $key . ': ' . $reason;
		}
		$lines = array_merge( $lines, self::$items );
		if ( ! empty( self::$discovered ) ) {
			$lines[] = 'discovered:';
			$lines   = array_merge( $lines, self::$discovered );
		}

		automator_log_snapshot( implode( "\n", $lines ), 'Integration catalog build', true, self::LOG_FILE );
		self::start();
	}
}

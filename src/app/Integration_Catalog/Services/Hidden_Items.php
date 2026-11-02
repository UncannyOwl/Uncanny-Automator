<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Integration_Catalog\Services;

/**
 * The triggers and actions an integration hid on this site because their own
 * requirements are not met (`requirements_met()` false: Dropbox triggers
 * while "Enable triggers" is off, for one), with the sentence that says why.
 *
 * A hidden item never reaches the catalog, so the catalog alone cannot tell
 * it from an item that does not exist. A recipe that already holds one must
 * not read as usable (decision A4, 29 Oct): the availability resolver asks
 * here when the catalog has no such item.
 *
 * Recorded while the items register, so it lives for the request, like the
 * catalog it complements. `reset()` is the seam for tests.
 *
 * @since 8.0.0
 */
final class Hidden_Items {

	/**
	 * Why each hidden item is hidden, keyed by integration, item type and code.
	 *
	 * @var array<string, string>
	 */
	private static $reasons = array();

	/**
	 * Remember that an item was hidden by its requirements.
	 *
	 * @param string $integration The integration code.
	 * @param string $item_type   `trigger` or `action`.
	 * @param string $code        The item code.
	 * @param string $sentence    Why, for the site owner; '' when the item gives none.
	 *
	 * @return void
	 */
	public static function record( string $integration, string $item_type, string $code, string $sentence ): void {

		self::$reasons[ self::key( $integration, $item_type, $code ) ] = $sentence;
	}

	/**
	 * Why the item is hidden on this site, or null when it is not hidden.
	 *
	 * @param string $integration The integration code.
	 * @param string $item_type   `trigger` or `action`.
	 * @param string $code        The item code.
	 *
	 * @return string|null The sentence ('' when the item gives none), or null.
	 */
	public static function reason( string $integration, string $item_type, string $code ): ?string {

		return self::$reasons[ self::key( $integration, $item_type, $code ) ] ?? null;
	}

	/**
	 * Forget every hidden item.
	 *
	 * @return void
	 */
	public static function reset(): void {

		self::$reasons = array();
	}

	/**
	 * @param string $integration The integration code.
	 * @param string $item_type   The item type.
	 * @param string $code        The item code.
	 *
	 * @return string
	 */
	private static function key( string $integration, string $item_type, string $code ): string {

		return strtoupper( $integration ) . '|' . strtolower( $item_type ) . '|' . strtoupper( $code );
	}
}

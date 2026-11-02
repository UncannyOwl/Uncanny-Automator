<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Addons\Services;

use Uncanny_Automator\App\Infrastructure\Addons\Addons_Feed_Client;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon;
use WP_Error;

/**
 * Reads the published addon catalog: lists, lookups and per-tier counts.
 *
 * Tier membership in the feed is cumulative — an addon included in Plus also
 * carries `elite`. The one exception is the elite LIST, which is the strict
 * single-plan subset (the addons Elite alone unlocks), mirroring how the page
 * groups them.
 *
 * Feed failures are surfaced, not smoothed: list readers hand back the
 * WP_Error so the route can fail loud, while lookups and counts degrade to
 * "nothing known" for callers that only decorate a response.
 */
final class Addon_Catalog_Service {

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Feed client.
	 *
	 * @var Addons_Feed_Client
	 */
	private Addons_Feed_Client $feed;

	/**
	 * Construct with a lazy-default feed client.
	 *
	 * @param Addons_Feed_Client|null $feed Feed client.
	 */
	private function __construct( ?Addons_Feed_Client $feed = null ) {
		$this->feed = $feed ?? Addons_Feed_Client::instance();
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
	 * Build with explicit dependencies.
	 *
	 * @param Addons_Feed_Client|null $feed Feed client.
	 *
	 * @return self
	 */
	public static function create_with_dependencies( ?Addons_Feed_Client $feed = null ): self {
		return new self( $feed );
	}

	/**
	 * Every published addon.
	 *
	 * @return array<int,Addon>|WP_Error
	 */
	public function all() {
		return $this->feed->get_addons();
	}

	/**
	 * The addons belonging to a page list.
	 *
	 * @param string $list_code Either `plus` or `elite`.
	 *
	 * @return array<int,Addon>|WP_Error
	 */
	public function for_list_code( string $list_code ) {

		$addons = $this->all();
		if ( is_wp_error( $addons ) ) {
			return $addons;
		}

		if ( Addon::TIER_ELITE === $list_code ) {
			return array_values(
				array_filter(
					$addons,
					function ( Addon $addon ) {
						return $addon->requires_elite();
					}
				)
			);
		}

		if ( Addon::TIER_PLUS !== $list_code ) {
			return array();
		}

		return array_values(
			array_filter(
				$addons,
				function ( Addon $addon ) {
					return $addon->includes_plan( Addon::TIER_PLUS );
				}
			)
		);
	}

	/**
	 * Look up a single addon.
	 *
	 * @param int $addon_id Product id.
	 *
	 * @return Addon|null
	 */
	public function find( int $addon_id ): ?Addon {

		$addons = $this->all();
		if ( is_wp_error( $addons ) ) {
			return null;
		}

		foreach ( $addons as $addon ) {
			if ( $addon->id() === $addon_id ) {
				return $addon;
			}
		}

		return null;
	}

	/**
	 * How many addons exist.
	 *
	 * @return int
	 */
	public function total_count(): int {

		$addons = $this->all();

		return is_wp_error( $addons ) ? 0 : count( $addons );
	}

	/**
	 * How many addons a resolved plan includes.
	 *
	 * @param string $resolved_plan Resolved plan slug (lite|basic|plus|elite).
	 *
	 * @return int
	 */
	public function count_for_plan( string $resolved_plan ): int {

		if ( Addon::TIER_ELITE === $resolved_plan ) {
			return $this->total_count();
		}

		if ( ! in_array( $resolved_plan, array( Addon::TIER_BASIC, Addon::TIER_PLUS ), true ) ) {
			return 0;
		}

		$addons = $this->all();
		if ( is_wp_error( $addons ) ) {
			return 0;
		}

		$included = array_filter(
			$addons,
			function ( Addon $addon ) use ( $resolved_plan ) {
				return $addon->includes_plan( $resolved_plan );
			}
		);

		return count( $included );
	}
}

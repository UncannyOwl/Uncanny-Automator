<?php
/**
 * Integration Feed Registration Merger
 *
 * Merges feed data (complete.json) with registered integration data.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Store
 * @since 7.0.0
 */
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Integration\Utilities\Store;

use Uncanny_Automator\Api\Services\Integration\Integration_Discovery_Service;
use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;
use Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Integration_Manifest_Resolver;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;

/**
 * Merges data from complete.json feed with locally registered integrations.
 *
 * This class is ONLY called when an integration exists in BOTH sources:
 * - In the complete.json feed (from CDN)
 * - Registered locally on the site
 *
 * @since 7.0.0
 */
class Integration_Feed_Registration_Merger {

	/**
	 * Technical fields from discovered integration data.
	 *
	 * These fields are populated from registration/plugin discovery.
	 * Only filled if empty in feed data.
	 */
	const TECHNICAL_FIELDS = array(
		'integration_type',
		'integration_tier',
		'plugin_details',
	);

	/**
	 * Connection fields from discovered integration data.
	 *
	 * Static connection-related fields that can be cached.
	 * from the registry to avoid stale cached values.
	 */
	const CONNECTION_FIELDS = array(
		'settings_url',
	);

	/**
	 * Item fields that should always come from discovered data for third-party.
	 *
	 * Feed data may have incomplete/placeholder items, so we always use discovered items.
	 */
	const ITEM_FIELDS = array(
		'integration_triggers',
		'integration_actions',
		'integration_conditions',
		'integration_loop_filters',
	);

	/**
	 * Merge feed data with registered integration data.
	 *
	 * Routes to appropriate merge strategy based on integration type.
	 *
	 * @param array  $feed_entry       Entry from complete.json feed map (data + item_codes)
	 * @param array  $registered_data  Registered integration data (with item_codes)
	 * @param string $code             Integration code
	 *
	 * @return array Merged integration data with all fields populated
	 */
	public function merge( array $feed_entry, array $registered_data, string $code ) {
		$is_third_party = ( $feed_entry['data']['integration_type'] ?? '' ) === 'third_party';

		return $is_third_party
			? $this->merge_third_party( $feed_entry, $code )
			: $this->merge_standard( $feed_entry, $registered_data, $code );
	}

	/**
	 * Merge third-party integration.
	 *
	 * Steps:
	 * 1. Discover technical data from registration
	 * 2. Merge with correct priority (feed → discovered → items)
	 * 3. Apply manifest overrides
	 *
	 * @param array  $feed_entry Entry from complete.json feed map
	 * @param string $code       Integration code
	 *
	 * @return array Merged integration data
	 */
	private function merge_third_party( array $feed_entry, string $code ) {
		$discovery = Integration_Discovery_Service::get_instance();

		// Step 1: Discover technical data from registration.
		$discovered = $discovery->discover_integration_by_code( $code );

		// Step 2: Merge with correct priority (feed → discovered).
		$merged = $this->merge_third_party_integration( $feed_entry['data'], $discovered );

		// Step 3: Apply manifest overrides.
		return $this->apply_manifest_overrides( $merged, $code );
	}

	/**
	 * Merge standard integration.
	 *
	 * Steps:
	 * 1. Use complete.json feed as base (trusted source with all marketing data)
	 * 2. Check for missing items (items in registration but not in feed)
	 * 3. Discover and add missing items on-demand
	 * 4. Apply manifest overrides
	 *
	 * @param array  $feed_entry       Entry from complete.json feed map
	 * @param array  $registered_data  Registered integration data
	 * @param string $code             Integration code
	 *
	 * @return array Merged integration data
	 */
	private function merge_standard( array $feed_entry, array $registered_data, string $code ) {
		$feed_data        = $feed_entry['data'];
		$feed_codes       = $feed_entry['item_codes'];
		$registered_codes = $registered_data['item_codes'];
		$discovery        = Integration_Discovery_Service::get_instance();
		$item_types       = Integration_Item_Types::get_all();

		// Step 1: Start with feed data.
		$merged = $feed_data;

		// Step 2: Check for missing items (in registration but not in feed).
		$has_missing_items = false;
		foreach ( $item_types as $type ) {

			if ( Integration_Item_Types::CLOSURE === $type ) {
				continue;
			}

			$missing_codes = array_diff( $registered_codes[ $type ], $feed_codes[ $type ] );
			if ( ! empty( $missing_codes ) ) {
				$has_missing_items = true;
				// TODO @CURT - remove logging.
				// error_log( sprintf( 'Feed_Registration_Merger: %s has missing %s codes - %s', $code, $type, implode( ', ', $missing_codes ) ) );
			}
		}

		// Step 3: Discover and add missing items on-demand.
		if ( $has_missing_items ) {
			$discovered_items = array(
				Integration_Item_Types::TRIGGER          => $discovery->discover_triggers( $code, $feed_data['integration_name'] ),
				Integration_Item_Types::ACTION           => $discovery->discover_actions( $code, $feed_data['integration_name'] ),
				Integration_Item_Types::FILTER_CONDITION => $discovery->discover_conditions( $code, $feed_data['integration_name'] ),
				Integration_Item_Types::LOOP_FILTER      => $discovery->discover_loop_filters( $code, $feed_data['integration_name'] ),
			);

			foreach ( $item_types as $type ) {

				if ( Integration_Item_Types::CLOSURE === $type ) {
					continue;
				}

				$type_key = Integration_Item_Types::FILTER_CONDITION === $type
					? 'integration_conditions'
					: 'integration_' . $type . 's';

				$merged[ $type_key ] = $merged[ $type_key ] ?? array();
				$missing_codes       = array_diff( $registered_codes[ $type ], $feed_codes[ $type ] );

				foreach ( $missing_codes as $missing_code ) {
					$item = $this->find_item_by_code( $discovered_items[ $type ], $missing_code );
					if ( $item ) {
						$merged[ $type_key ][] = $item;
					}
				}
			}
		}

		// Step 4: Apply manifest overrides.
		return $this->apply_manifest_overrides( $merged, $code );
	}

	/**
	 * Merge third-party integration data with correct priority.
	 *
	 * Priority Order:
	 * 1. Complete.json feed data (base - marketing + any technical data)
	 * 2. Discovered plugin data (fill missing technical fields)
	 * 3. Discovered items (ALWAYS use - feed may have incomplete placeholders)
	 *
	 * @param array $feed_data Complete.json feed data
	 * @param array $discovered_data Discovered integration data
	 *
	 * @return array Merged integration data
	 */
	private function merge_third_party_integration( $feed_data, $discovered_data ) {
		// Priority 1: Start with feed data (complete.json)
		$merged = $feed_data;

		// ========================================================================
		// TEMPORARY: Set integration_id from discovered data
		// ========================================================================
		// Third-party integrations currently have empty integration_id in complete.json.
		// We use discovered data to populate this field until the feed is updated.
		//
		// TODO: Remove this block once complete.json includes integration_id for third-party integrations.
		// ========================================================================
		if ( ! empty( $discovered_data['integration_id'] ) ) {
			$merged['integration_id'] = $discovered_data['integration_id'];
		}

		// Priority 2: Fill missing technical fields from discovered data
		foreach ( self::TECHNICAL_FIELDS as $field ) {
			if ( empty( $merged[ $field ] ) && ! empty( $discovered_data[ $field ] ) ) {
				$merged[ $field ] = $discovered_data[ $field ];
			}
		}

		// Priority 2b: ALWAYS use settings_url from discovered data (static, can be cached)
		foreach ( self::CONNECTION_FIELDS as $field ) {
			if ( isset( $discovered_data[ $field ] ) ) {
				$merged[ $field ] = $discovered_data[ $field ];
			}
		}

		// Priority 3: ALWAYS use discovered items (feed may have incomplete placeholders)
		foreach ( self::ITEM_FIELDS as $field ) {
			if ( ! empty( $discovered_data[ $field ] ) ) {
				$merged[ $field ] = $discovered_data[ $field ];
			}
		}

		return $merged;
	}

	/**
	 * Apply manifest overrides to merged integration data.
	 *
	 * @param array  $merged Merged integration data
	 * @param string $code   Integration code
	 *
	 * @return array Integration data with manifest overrides applied
	 */
	private function apply_manifest_overrides( array $merged, string $code ) {
		$registry    = Integration_Registry_Service::get_instance();
		$integration = $registry->get_integration_full( $code );
		// Shouldn't happen, but just in case.
		if ( null === $integration ) {
			return $merged;
		}

		$manifest_resolver = new Integration_Manifest_Resolver();
		$manifest          = $manifest_resolver->extract_manifest( $integration );

		return $manifest_resolver->apply_overrides( $merged, $manifest );
	}

	/**
	 * Find item by code in items array.
	 *
	 * @param array $items Array of items
	 * @param string $code Item code
	 * @return array|null Item or null if not found
	 */
	private function find_item_by_code( array $items, string $code ) {
		foreach ( $items as $item ) {
			if ( ( $item['code'] ?? '' ) === $code ) {
				return $item;
			}
		}
		return null;
	}
}

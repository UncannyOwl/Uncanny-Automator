<?php
/**
 * Integration Details Normalizer
 *
 * Normalizes and extracts integration details from JSON data.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Store
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration\Utilities\Store;

use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;

/**
 * Handles normalization and extraction of integration details.
 *
 * @since 7.0.0
 */
class Integration_Details_Normalizer {

	/**
	 * Normalize details.
	 *
	 * @param array $data Integration data
	 * @return array Details array
	 */
	public function normalize_details( array $data ) {
		$details              = $this->extract_details( $data );
		$details['plugin']    = $this->extract_plugin_details( $data );
		$details['developer'] = $this->extract_developer_details( $data );
		$details['account']   = $this->extract_account_details( $data );
		return $details;
	}

	/**
	 * Normalize items array to keyed format expected by Integration component.
	 *
	 * Converts: [ {code, name, ...}, ... ]
	 * To: { CODE: {code, name, ...}, ... }
	 *
	 * Also extracts the required_tier and flattens description object.
	 *
	 * @param array $items Array of items with 'code' key
	 * @return array Keyed array of items
	 */
	public function normalize_items( array $items ) {
		if ( empty( $items ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $items as $item ) {
			$code = $item['code'] ?? '';
			if ( empty( $code ) ) {
				continue;
			}

			// Extract item name from sentence.short if not directly available.
			if ( empty( $item['name'] ) && ! empty( $item['sentence']['short'] ) ) {
				$item['name'] = $item['sentence']['short'];
			}

			// Extract description string from description object.
			// JSON has: description: { readable: "...", mcp: "..." }
			// We need: description: "..."
			if ( ! empty( $item['description'] ) && is_array( $item['description'] ) ) {
				$item['description'] = $item['description']['readable'] ?? '';
			}

			// Normalize legacy type field: 'condition' → 'filter_condition'.
			if ( isset( $item['type'] ) && 'condition' === $item['type'] ) {
				$item['type'] = Integration_Item_Types::FILTER_CONDITION;
			}

			$normalized[ $code ] = $item;
		}

		return $normalized;
	}

	/**
	 * Extract details from data.
	 *
	 * @param array $data Integration data
	 * @return array Details array
	 */
	private function extract_details( array $data ) {
		$description_data = $data['description'][0] ?? array();
		return array(
			'icon'              => $data['integration_icon'] ?? '',
			'description'       => $description_data['full'] ?? '',
			'short_description' => $description_data['short'] ?? '',
			'primary_color'     => $data['integration_color'] ?? '',
			'external_url'      => $data['integration_link'] ?? '',
			'taxonomies'        => array(
				'categories'  => $this->extract_taxonomy( $data, 'categories' ),
				'collections' => $this->extract_taxonomy( $data, 'collections' ),
				'tags'        => $this->extract_tags( $data ),
			),
		);
	}

	/**
	 * Extract plugin details from data.
	 *
	 * WordPress plugin-specific details with original integration_type.
	 *
	 * Type is preserved from raw JSON (plugin, addon, third_party) while
	 * main Integration type is normalized (addon/third_party → plugin).
	 *
	 * Returns null for non-plugin integrations (app, built-in).
	 *
	 * @param array $data Integration data
	 * @return array|null Plugin details array or null for non-plugin integrations
	 */
	private function extract_plugin_details( array $data ) {
		// Get original integration_type from raw JSON
		$integration_type = $data['integration_type'] ?? 'plugin';

		// Only return plugin data for plugin-related types
		if ( ! in_array( $integration_type, array( 'plugin', 'addon', 'third_party' ), true ) ) {
			return null;
		}

		$plugin_details = $data['plugin_details'] ?? array();

		return array(
			'type'                 => $integration_type,
			'plugin_file'          => $plugin_details['plugin_file'] ?? '',
			'plugin_required'      => $plugin_details['plugin_required'] ?? '',
			'plugin_variations'    => $plugin_details['plugin_variations'] ?? array(),
			'integration_required' => $plugin_details['integration_required'] ?? '',
			'distribution_type'    => $plugin_details['distribution_type'] ?? '',
		);
	}

	/**
	 * Extract developer details from data.
	 *
	 * Extracts developer information with fallbacks:
	 * - name: plugin_details.developer_name → integration_name
	 * - site: affiliate_url → integration_link
	 *
	 * @param array $data Integration data
	 * @return array Developer details array
	 */
	private function extract_developer_details( array $data ) {
		$plugin_details = $data['plugin_details'] ?? array();

		// Name: Use plugin developer name if available, else integration name
		$developer_name = ! empty( $plugin_details['developer_name'] )
			? $plugin_details['developer_name']
			: ( $data['integration_name'] ?? '' );

		// Site: Use affiliate URL if available, else integration link.
		$developer_site = ! empty( $data['affiliate_url'] ?? '' )
			? $data['affiliate_url']
			: ( $data['integration_link'] ?? '' );

		return array(
			'name' => $developer_name,
			'site' => $developer_site,
		);
	}

	/**
	 * Extract account details from data.
	 *
	 * App integration-specific account connection details.
	 * Also extracts for third-party integrations that utilize settings_url.
	 * Returns null for integrations that don't require account connection.
	 *
	 * @param array $data Integration data
	 *
	 * @return array|null Account details array or null for non-app/non-third-party integrations
	 */
	private function extract_account_details( array $data ) {
		// Get integration_type from raw JSON.
		$integration_type = $data['integration_type'] ?? 'plugin';
		$settings_url     = $data['settings_url'] ?? '';
		$is_app           = 'app' === $integration_type;
		$is_third_party   = 'third_party' === $integration_type;

		// Return account data for app integrations or third-party integrations that have settings_url.
		if ( $is_app || ( $is_third_party && ! empty( $settings_url ) ) ) {
			$plugin_details = $data['plugin_details'] ?? array();
			$account_icon   = $plugin_details['account_icon'] ?? null;
			$account_name   = $plugin_details['developer_name'] ?? null;

			// Backfill if missing from complete.json
			if ( $is_app && empty( $settings_url ) ) {
				$settings_url = $this->get_integration_settings_url( $data['integration_id'] );
			}

			return array(
				'settings_url' => $this->normalize_settings_url( $settings_url ),
				'icon'         => $account_icon ?? $data['integration_icon'],
				'name'         => $account_name ?? $data['integration_name'],
			);
		}

		return null;
	}

	/**
	 * Extract taxonomy from data.
	 *
	 * @param array $data Integration data with 'taxonomy' key
	 * @param string $taxonomy Taxonomy slug
	 * @return array Array of taxonomy items
	 */
	private function extract_taxonomy( array $data, string $taxonomy ) {
		if ( empty( $data[ $taxonomy ] ) || ! is_array( $data[ $taxonomy ] ) ) {
			return array();
		}

		$items = array();
		foreach ( $data[ $taxonomy ] as $item ) {
			if ( ! empty( $item['slug'] ) ) {
				$items[] = $item['slug'];
			}
		}

		return $items;
	}

	/**
	 * Extract tags from keywords array.
	 *
	 * Max 5 tags as per spec.
	 *
	 * @param array $data Integration data with 'keywords' key
	 *
	 * @return array Array of tag keywords (max 5)
	 */
	private function extract_tags( array $data ) {
		if ( empty( $data['keywords'] ) || ! is_array( $data['keywords'] ) ) {
			return array();
		}

		// Return max 5 tags.
		return array_slice( $data['keywords'], 0, 5 );
	}

	/**
	 * Get integration settings URL from integration registry.
	 *
	 * @param string $code Integration code
	 *
	 * @return string Integration settings URL
	 */
	private function get_integration_settings_url( string $code ) {
		$integration_data = Integration_Registry_Service::get_instance()->get_integration( $code );
		return $integration_data['settings_url'] ?? '';
	}

	/**
	 * Normalize settings URL to absolute format.
	 *
	 * @param string $url Settings URL (relative or absolute)
	 *
	 * @return string Absolute URL
	 */
	private function normalize_settings_url( string $url ) {
		if ( $this->is_url_with_protocol( $url ) ) {
			return $this->apply_settings_url_minimal_params( $url );
		}

		// Cache base URL to avoid recalculating.
		static $base = null;
		if ( null === $base ) {
			// Get the base URL and remove trailing slash our complete.json provides these starting with /wp-admin/...
			$base = rtrim( get_site_url(), '/' );
		}

		return $this->apply_settings_url_minimal_params( $base . $url );
	}

	/**
	 * Apply settings URL minimal parameters to URL.
	 *
	 * @param string $url Settings URL
	 *
	 * @return string URL with settings parameters
	 */
	private function apply_settings_url_minimal_params( string $url ) {
		return add_query_arg(
			array(
				'automator_minimal'            => '1',
				'automator_hide_settings_tabs' => '1',
			),
			$url
		);
	}

	/**
	 * Check if URL starts with protocol.
	 *
	 * @param string $url URL to check
	 *
	 * @return bool True if URL starts with protocol, false otherwise
	*/
	private function is_url_with_protocol( string $url ): bool {
		if ( empty( $url ) ) {
			return false;
		}

		return str_starts_with( $url, 'http://' ) || str_starts_with( $url, 'https://' );
	}
}

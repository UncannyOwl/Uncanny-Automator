<?php
/**
 * Integration Manifest Resolver
 *
 * Extracts and validates manifest data from integration registration.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Discovery
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration\Utilities\Discovery;

/**
 * Resolves manifest data from integration registration.
 *
 * Handles extraction, validation, and checking for crucial fields.
 *
 * @since 7.0.0
 */
class Integration_Manifest_Resolver {

	/**
	 * Check if the integration has a manifest.
	 *
	 * @param array $integration Integration data from registration
	 *
	 * @return bool True if the integration has a manifest
	 */
	public function has_manifest( array $integration ): bool {
		$manifest = $this->extract_manifest( $integration );
		return is_array( $manifest ) && ! empty( $manifest );
	}

	/**
	 * Extract manifest data from integration registration.
	 *
	 * @param array $integration Integration data from registration
	 *
	 * @return array Manifest data (empty if not present)
	 */
	public function extract_manifest( array $integration ): array {
		return $integration['manifest'] ?? array();
	}

	/**
	 * Check if we have all crucial fields from manifest.
	 *
	 * Crucial fields needed for UI:
	 * - Description (short or full from manifest)
	 * - External URL (developer_site from manifest)
	 *
	 * @param array $manifest Manifest data from registration
	 *
	 * @return bool True if all crucial fields are available
	 */
	public function has_all_crucial_fields( array $manifest ): bool {
		// Check if we have description
		$has_description = ! empty( $manifest['short_description'] ) || ! empty( $manifest['full_description'] );

		// Check if we have external URL
		$has_external_url = ! empty( $manifest['developer_site'] );

		// If we have both, we have all crucial fields
		return $has_description && $has_external_url;
	}

	/**
	 * Get description from manifest.
	 *
	 * Prioritizes full description, falls back to short description.
	 *
	 * @param array $manifest Manifest data
	 *
	 * @return string Description (empty if not available)
	 */
	public function get_description( array $manifest ): string {
		if ( ! empty( $manifest['full_description'] ) ) {
			return $manifest['full_description'];
		}
		if ( ! empty( $manifest['short_description'] ) ) {
			return $manifest['short_description'];
		}
		return '';
	}

	/**
	 * Get external URL from manifest.
	 *
	 * @param array $manifest Manifest data
	 *
	 * @return string External URL (empty if not available)
	 */
	public function get_external_url( array $manifest ): string {
		return $manifest['developer_site'] ?? '';
	}

	/**
	 * Get integration version from manifest.
	 *
	 * @param array $manifest Manifest data
	 *
	 * @return string Version (empty if not available)
	 */
	public function get_version( array $manifest ): string {
		return $manifest['integration_version'] ?? '';
	}

	/**
	 * Get plugin file path from manifest.
	 *
	 * @param array $manifest Manifest data
	 *
	 * @return string Plugin file path (empty if not available)
	 */
	public function get_plugin_file_path( array $manifest ): string {
		return $manifest['plugin_file_path'] ?? '';
	}

	/**
	 * Apply manifest overrides to integration data.
	 *
	 * All manifest fields take precedence over feed/discovered data (except integration_version).
	 * This allows developers to explicitly control integration metadata.
	 *
	 * @param array $data     Integration data to apply overrides to
	 * @param array $manifest Manifest data
	 *
	 * @return array Integration data with manifest overrides applied
	 */
	public function apply_overrides( array $data, array $manifest ): array {
		// integration_color
		if ( ! empty( $manifest['integration_color'] ) ) {
			$data['integration_color'] = $manifest['integration_color'];
		}

		// description (short/full)
		$manifest_description = $this->get_description( $manifest );
		if ( ! empty( $manifest_description ) ) {
			$data['description'] = $manifest_description;
		}

		// integration_link (developer_site)
		$manifest_external_url = $this->get_external_url( $manifest );
		if ( ! empty( $manifest_external_url ) ) {
			$data['integration_link'] = $manifest_external_url;
		}

		// Ensure plugin_details array exists
		if ( ! isset( $data['plugin_details'][0] ) ) {
			$data['plugin_details'][0] = array();
		}

		// developer_name
		if ( ! empty( $manifest['developer_name'] ) ) {
			$data['plugin_details'][0]['developer_name'] = $manifest['developer_name'];
		}

		// plugin_file_path
		if ( ! empty( $manifest['plugin_file_path'] ) ) {
			$data['plugin_details'][0]['plugin_file'] = $manifest['plugin_file_path'];
		}

		// plugin_required
		if ( ! empty( $manifest['plugin_required'] ) ) {
			$data['plugin_details'][0]['plugin_required'] = $manifest['plugin_required'];
		}

		// integration_required
		if ( ! empty( $manifest['integration_required'] ) ) {
			$data['plugin_details'][0]['integration_required'] = $manifest['integration_required'];
		}

		// distribution_type
		if ( ! empty( $manifest['distribution_type'] ) ) {
			$data['plugin_details'][0]['distribution_type'] = $manifest['distribution_type'];
		}

		// plugin_variations
		if ( ! empty( $manifest['plugin_variations'] ) ) {
			$data['plugin_details'][0]['plugin_variations'] = $manifest['plugin_variations'];
		}

		return $data;
	}
}

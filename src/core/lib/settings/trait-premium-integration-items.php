<?php
namespace Uncanny_Automator\Settings;

use Exception;

/**
 * Trait for discovering available actions and triggers in premium integrations
 *
 * @package Uncanny_Automator\Settings
 */
trait Premium_Integration_Items {

	/**
	 * Get available actions by scanning directories and applying filters
	 *
	 * @return array
	 */
	protected function get_available_actions() {
		return $this->get_available_items( 'actions' );
	}

	/**
	 * Get available triggers by scanning directories and applying filters
	 *
	 * @return array
	 */
	protected function get_available_triggers() {
		return $this->get_available_items( 'triggers' );
	}

	/**
	 * Get available items (actions or triggers) by scanning directories and applying filters
	 *
	 * @param string $type Either 'actions' or 'triggers'
	 * @return array
	 * @throws Exception If invalid item type.
	 */
	protected function get_available_items( $type ) {

		// Validate type.
		$this->validate_item_type( $type );

		// Get trigger or action items from the integration.
		$items = $this->get_items_from_classes( $type );

		// Adjust the ID for the filter name.
		$integration_id = strtolower( $this->get_id() );

		/**
		 * Filter the available items
		 *
		 * @param array $items The current items
		 * @return array
		 */
		$items = apply_filters( "automator_{$integration_id}_{$type}", $items );

		// Remove duplicates and reindex array
		return array_values( array_unique( $items ) );
	}

	/**
	 * Get items (actions or triggers) for this integration from the
	 * Integration_Query_Service, which merges the CDN feed with locally
	 * registered integrations and caches the result per request.
	 *
	 * Using the query service means items are present regardless of
	 * requirements_met() status, so disconnected integrations (e.g. Facebook
	 * Lead Ads) still show their available items on the settings page.
	 *
	 * @param string $type Either 'actions' or 'triggers'
	 * @return array
	 * @throws Exception If invalid item type.
	 */
	protected function get_items_from_classes( $type ) {

		// Validate type.
		$this->validate_item_type( $type );

		$query_service = \Uncanny_Automator\Api\Services\Integration\Integration_Query_Service::get_instance();
		$integration   = $query_service->get_integration( $this->get_integration() );

		if ( null === $integration ) {
			return array();
		}

		$integration_items = 'actions' === $type
			? $integration->get_items()->get_actions()
			: $integration->get_items()->get_triggers();

		$items = array();
		foreach ( $integration_items as $item ) {
			$data = $item->to_array();

			if ( ! empty( $data['is_deprecated'] ) ) {
				continue;
			}

			$sentence = $data['sentence']['short'] ?? '';
			if ( '' === $sentence ) {
				continue;
			}

			$items[] = $sentence;
		}

		return $items;
	}

	/**
	 * Validate item type
	 *
	 * @param string $type
	 * @return void
	 * @throws Exception
	 */
	private function validate_item_type( $type ) {
		if ( 'actions' !== $type && 'triggers' !== $type ) {
			throw new Exception( 'Invalid item type' );
		}
	}
}

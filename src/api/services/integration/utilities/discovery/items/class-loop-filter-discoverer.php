<?php
/**
 * Loop Filter Discoverer
 *
 * Discovers loop filters for an integration.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items;

use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_Registry_Service;

/**
 * Discovers loop filters for an integration.
 *
 * @since 7.0.0
 */
class Loop_Filter_Discoverer extends Integration_Item_Discoverer {

	/**
	 * Loop filter registry service.
	 *
	 * @var Filter_Registry_Service
	 */
	private $loop_filter_registry;

	/**
	 * Constructor.
	 *
	 * @param Filter_Registry_Service|null $loop_filter_registry Optional registry for DI/testing.
	 */
	public function __construct( ?Filter_Registry_Service $loop_filter_registry = null ) {
		$this->loop_filter_registry = $loop_filter_registry ?? Filter_Registry_Service::instance();
	}

	/**
	 * Discover loop filters for integration.
	 *
	 * @param string $code Integration code
	 * @param string $name Integration name
	 *
	 * @return array Loop filter data
	 */
	public function discover( string $code, string $name ): array {
		$this->current_integration_code = $code;
		$this->current_integration_name = $name;

		$integration_filters = $this->loop_filter_registry->get_filters_by_integration( $code );

		$discovered = array();

		foreach ( $integration_filters as $filter_code => $filter ) {
			// Ensure code is set, fallback to array key.
			$filter['code'] = $filter['code'] ?? $filter_code;
			$discovered[]   = $this->normalize_item( $filter, 'loop_filter' );
		}

		return $discovered;
	}

	/**
	 * Get loop filter code.
	 *
	 * @param array $item Loop filter data
	 *
	 * @return string Loop filter code
	 */
	protected function get_item_code( array $item ): string {
		return $item['code'] ?? '';
	}

	/**
	 * Get loop filter meta.
	 *
	 * @param array $item Loop filter data
	 * @param string $type Item type (always 'loop_filter')
	 *
	 * @return string Loop filter meta
	 */
	protected function get_item_meta( array $item, string $type ): string {
		return $item['meta'] ?? '';
	}

	/**
	 * Check if loop filter is deprecated.
	 *
	 * @param array $item Loop filter data
	 * @param string $type Item type
	 *
	 * @return bool True if deprecated (always false for loop filters)
	 */
	protected function is_deprecated( array $item, string $type ): bool {
		return false;
	}

	/**
	 * Get loop filter sentence.
	 *
	 * @param array $item Loop filter data
	 * @param string $type Item type
	 *
	 * @return array Sentence data
	 */
	protected function get_sentence( array $item, string $type ): array {
		return array(
			'short'   => $item['sentence'] ?? '',
			'dynamic' => $item['sentence_readable'] ?? $item['sentence'] ?? '',
		);
	}

	/**
	 * Check if loop filter requires user data.
	 *
	 * @param array $item Loop filter data
	 * @param string $type Item type
	 *
	 * @return bool True if requires user data (always false for loop filters)
	 */
	protected function requires_user_data( array $item, string $type ): bool {
		return false;
	}

	/**
	 * Get fallback description from loop filter data.
	 *
	 * @param array $item Loop filter data
	 *
	 * @return string Raw fallback description
	 */
	protected function get_fallback_description_raw( array $item ): string {
		return $item['sentence'] ?? '';
	}

	/**
	 * Get required tier for loop filter.
	 *
	 * Loop filters are Pro-only features, so default to pro-basic minimum.
	 *
	 * @param array $item Loop filter data
	 * @param string $type Item type
	 *
	 * @return string Required tier ('pro-elite' if is_elite, otherwise 'pro-basic')
	 */
	protected function get_required_tier( array $item, string $type ): string {
		if ( ! empty( $item['is_elite'] ) ) {
			return 'pro-elite';
		}
		return 'pro-basic';
	}
}

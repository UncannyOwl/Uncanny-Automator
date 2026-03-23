<?php
/**
 * Condition Discoverer
 *
 * Discovers conditions for an integration.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items;

use Uncanny_Automator\Api\Services\Condition\Services\Condition_Registry_Service;

/**
 * Discovers conditions for an integration.
 *
 * @since 7.0.0
 */
class Filter_Condition_Discoverer extends Integration_Item_Discoverer {

	/**
	 * Condition registry service.
	 *
	 * @var Condition_Registry_Service
	 */
	private $condition_registry;

	/**
	 * Constructor.
	 *
	 * @param Condition_Registry_Service|null $condition_registry Optional registry for DI/testing.
	 */
	public function __construct( ?Condition_Registry_Service $condition_registry = null ) {
		$this->condition_registry = $condition_registry ?? Condition_Registry_Service::get_instance();
	}

	/**
	 * Discover conditions for integration.
	 *
	 * @param string $code Integration code
	 *
	 * @return array Condition data
	 */
	public function discover( string $code, string $name ): array {
		$this->current_integration_code = $code;
		$this->current_integration_name = $name;

		$result = $this->condition_registry->list_conditions( array( 'integration' => $code ) );

		if ( is_wp_error( $result ) ) {
			return array();
		}

		$integration_conditions = $result['conditions'] ?? array();
		$conditions             = array();

		foreach ( $integration_conditions as $condition ) {
			$conditions[] = $this->normalize_item( $condition, 'filter_condition' );
		}

		return $conditions;
	}

	/**
	 * Get condition code.
	 *
	 * @param array $item Condition data
	 *
	 * @return string Condition code
	 */
	protected function get_item_code( array $item ): string {
		return $item['condition_code'] ?? '';
	}

	/**
	 * Get condition meta.
	 *
	 * @param array $item Condition data
	 * @param string $type Item type (always 'condition')
	 *
	 * @return string Condition meta (always empty for conditions)
	 */
	protected function get_item_meta( array $item, string $type ): string {
		return '';
	}

	/**
	 * Check if condition is deprecated.
	 *
	 * @param array $item Condition data
	 * @param string $type Item type
	 *
	 * @return bool True if deprecated
	 */
	protected function is_deprecated( array $item, string $type ): bool {
		return ! empty( $item['deprecated'] );
	}

	/**
	 * Get condition sentence.
	 *
	 * @param array $item Condition data
	 * @param string $type Item type
	 *
	 * @return array Sentence data
	 */
	protected function get_sentence( array $item, string $type ): array {
		return array(
			'short'   => $item['name'] ?? '',
			'dynamic' => $item['dynamic_name'] ?? $item['name'] ?? '',
		);
	}

	/**
	 * Get required tier for condition.
	 *
	 * @param array $item Condition data
	 * @param string $type Item type
	 *
	 * @return string Required tier
	 */
	protected function get_required_tier( array $item, string $type ): string {
		if ( ! empty( $item['is_elite'] ) ) {
			return 'pro-elite';
		}
		if ( ! empty( $item['is_pro'] ) ) {
			return 'pro-basic';
		}
		return 'lite';
	}

	/**
	 * Check if condition requires user data.
	 *
	 * @param array $item Condition data
	 * @param string $type Item type
	 *
	 * @return bool True if requires user data
	 */
	protected function requires_user_data( array $item, string $type ): bool {
		return $item['requires_user'] ?? false;
	}

	/**
	 * Get fallback description from condition data.
	 *
	 * @param array $item Condition data
	 *
	 * @return string Raw fallback description
	 */
	protected function get_fallback_description_raw( array $item ): string {
		return $item['name'] ?? '';
	}
}

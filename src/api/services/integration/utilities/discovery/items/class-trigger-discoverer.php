<?php
/**
 * Trigger Discoverer
 *
 * Discovers triggers for an integration.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items;

use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service;

/**
 * Discovers triggers for an integration.
 *
 * @since 7.0.0
 */
class Trigger_Discoverer extends Integration_Item_Discoverer {

	/**
	 * Trigger registry service.
	 *
	 * @var Trigger_Registry_Service
	 */
	private $trigger_registry;

	/**
	 * Constructor.
	 *
	 * @param Trigger_Registry_Service|null $trigger_registry Optional registry for DI/testing.
	 */
	public function __construct( ?Trigger_Registry_Service $trigger_registry = null ) {
		$this->trigger_registry = $trigger_registry ?? Trigger_Registry_Service::get_instance();
	}

	/**
	 * Discover triggers for integration.
	 *
	 * @param string $code Integration code
	 * @param string $name Integration name
	 *
	 * @return array Trigger data
	 */
	public function discover( string $code, string $name ): array {
		$this->current_integration_code = $code;
		$this->current_integration_name = $name;

		$result = $this->trigger_registry->get_triggers_by_integration( $code, false );

		if ( is_wp_error( $result ) ) {
			return array();
		}

		$integration_triggers = $result['triggers'] ?? array();
		$triggers             = array();

		foreach ( $integration_triggers as $trigger_key => $trigger ) {
			// Ensure trigger_code is set, fallback to array key.
			$trigger['trigger_code'] = $trigger['trigger_code'] ?? $trigger_key;
			$triggers[]              = $this->normalize_item( $trigger, 'trigger' );
		}

		return $triggers;
	}

	/**
	 * Get trigger code.
	 *
	 * @param array $item Trigger data
	 *
	 * @return string Trigger code
	 */
	protected function get_item_code( array $item ): string {
		return $item['trigger_code'] ?? '';
	}

	/**
	 * Get trigger meta.
	 *
	 * @param array $item Trigger data
	 * @param string $type Item type (always 'trigger')
	 *
	 * @return string Trigger meta
	 */
	protected function get_item_meta( array $item, string $type ): string {
		return $item['trigger_meta_code'] ?? '';
	}

	/**
	 * Check if trigger is deprecated.
	 *
	 * @param array $item Trigger data
	 * @param string $type Item type
	 *
	 * @return bool True if deprecated
	 */
	protected function is_deprecated( array $item, string $type ): bool {
		return ! empty( $item['is_deprecated'] );
	}

	/**
	 * Get trigger sentence.
	 *
	 * @param array $item Trigger data
	 * @param string $type Item type
	 *
	 * @return array Sentence data
	 */
	protected function get_sentence( array $item, string $type ): array {
		return array(
			'short'   => $item['sentence_human_readable'] ?? $item['select_option_name'] ?? '',
			'dynamic' => $item['sentence'] ?? '',
		);
	}

	/**
	 * Get required tier for trigger.
	 *
	 * @param array $item Trigger data
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
	 * Check if trigger requires user data.
	 *
	 * @param array $item Trigger data
	 * @param string $type Item type
	 *
	 * @return bool True if requires user data
	 */
	protected function requires_user_data( array $item, string $type ): bool {
		$local_type = $item['trigger_type'] ?? 'user';
		return ( 'user' === $local_type || 'logged_in' === $local_type );
	}

	/**
	 * Get fallback description from trigger data.
	 *
	 * @param array $item Trigger data
	 *
	 * @return string Raw fallback description
	 */
	protected function get_fallback_description_raw( array $item ): string {
		return $item['select_option_name'] ?? '';
	}
}

<?php
/**
 * Action Discoverer
 *
 * Discovers actions for an integration.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items;

use Uncanny_Automator\Api\Services\Action\Services\Action_Registry_Service;

/**
 * Discovers actions for an integration.
 *
 * @since 7.0.0
 */
class Action_Discoverer extends Integration_Item_Discoverer {

	/**
	 * Action registry service.
	 *
	 * @var Action_Registry_Service
	 */
	private $action_registry;

	/**
	 * Constructor.
	 *
	 * @param Action_Registry_Service|null $action_registry Optional registry for DI/testing.
	 */
	public function __construct( ?Action_Registry_Service $action_registry = null ) {
		$this->action_registry = $action_registry ?? Action_Registry_Service::instance();
	}

	/**
	 * Discover actions for integration.
	 *
	 * @param string $code Integration code
	 *
	 * @return array Action data
	 */
	public function discover( string $code, string $name ): array {
		$this->current_integration_code = $code;
		$this->current_integration_name = $name;

		$integration_actions = $this->action_registry->get_available_actions( $code, false );

		if ( is_wp_error( $integration_actions ) ) {
			return array();
		}

		$actions = array();

		foreach ( $integration_actions as $action_key => $action ) {
			// Ensure action_code is set, fallback to array key.
			$action['action_code'] = $action['action_code'] ?? $action_key;
			$actions[]             = $this->normalize_item( $action, 'action' );
		}

		return $actions;
	}

	/**
	 * Get action code.
	 *
	 * @param array $item Action data
	 *
	 * @return string Action code
	 */
	protected function get_item_code( array $item ): string {
		return $item['action_code'] ?? '';
	}

	/**
	 * Get action meta.
	 *
	 * @param array $item Action data
	 * @param string $type Item type (always 'action')
	 *
	 * @return string Action meta
	 */
	protected function get_item_meta( array $item, string $type ): string {
		return $item['action_meta_code'] ?? '';
	}

	/**
	 * Check if action is deprecated.
	 *
	 * @param array $item Action data
	 * @param string $type Item type
	 *
	 * @return bool True if deprecated
	 */
	protected function is_deprecated( array $item, string $type ): bool {
		return ! empty( $item['is_deprecated'] );
	}

	/**
	 * Get action sentence.
	 *
	 * @param array $item Action data
	 * @param string $type Item type
	 *
	 * @return array Sentence data
	 */
	protected function get_sentence( array $item, string $type ): array {
		return array(
			'short'   => $item['select_option_name'] ?? '',
			'dynamic' => $item['sentence'] ?? '',
		);
	}

	/**
	 * Get required tier for action.
	 *
	 * @param array $item Action data
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
	 * Check if action requires user data.
	 *
	 * @param array $item Action data
	 * @param string $type Item type
	 *
	 * @return bool True if requires user data
	 */
	protected function requires_user_data( array $item, string $type ): bool {
		return $item['requires_user'] ?? true;
	}

	/**
	 * Get fallback description from action data.
	 *
	 * @param array $item Action data
	 *
	 * @return string Raw fallback description
	 */
	protected function get_fallback_description_raw( array $item ): string {
		return $item['select_option_name'] ?? '';
	}
}

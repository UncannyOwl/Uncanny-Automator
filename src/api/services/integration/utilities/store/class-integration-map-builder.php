<?php
/**
 * Integration Map Builder
 *
 * Builds indexed maps for fast code comparison.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Store
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration\Utilities\Store;

use Uncanny_Automator\Api\Components\Trigger\Registry\WP_Trigger_Registry;
use Uncanny_Automator\Api\Components\Action\Registry\WP_Action_Registry;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\Api\Services\Loop_Filter\Services\Loop_Filter_Registry_Service;
use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;

/**
 * Builds indexed maps of integration codes for fast comparison.
 *
 * @since 7.0.0
 */
class Integration_Map_Builder {

	/**
	 * Build indexed map of complete.json data.
	 *
	 * Returns array keyed by lowercase integration_id with:
	 * - Full integration data
	 * - item_codes array with trigger/action/condition/loop_filter codes
	 *
	 * @param array $json_data Raw complete.json data
	 * @return array Indexed map
	 */
	public function build_complete_json_map( array $json_data ) {
		$map = array();

		foreach ( $json_data as $entry ) {
			$code = $entry['integration_id'] ?? null;

			// Skip entries without a code.
			if ( empty( $code ) ) {
				continue;
			}

			$code_lower = strtolower( $code );

			// Extract item codes for fast comparison
			$item_codes = array(
				Integration_Item_Types::TRIGGER          => $this->extract_item_codes_from_array( $entry['integration_triggers'] ?? array() ),
				Integration_Item_Types::ACTION           => $this->extract_item_codes_from_array( $entry['integration_actions'] ?? array() ),
				Integration_Item_Types::FILTER_CONDITION => $this->extract_item_codes_from_array( $entry['integration_conditions'] ?? array() ),
				Integration_Item_Types::LOOP_FILTER      => $this->extract_item_codes_from_array( $entry['integration_loop_filters'] ?? array() ),
			);

			$map[ $code_lower ] = array(
				'data'       => $entry,
				'item_codes' => $item_codes,
			);
		}

		return $map;
	}

	/**
	 * Build indexed map of registered integrations.
	 *
	 * Fast extraction of item codes only - no full discovery.
	 * Returns array keyed by integration code with item_codes only.
	 *
	 * @return array Indexed map
	 */
	public function build_registered_map() {
		$registry            = Integration_Registry_Service::get_instance();
		$trigger_registry    = new WP_Trigger_Registry();
		$action_registry     = new WP_Action_Registry();
		$condition_service   = Condition_Registry_Service::get_instance();
		$loop_filter_service = Loop_Filter_Registry_Service::get_instance();
		$all_integrations    = $registry->get_all_integrations();
		$map                 = array();

		foreach ( $all_integrations as $code => $integration ) {
			// Fast code extraction only - no full discovery
			$map[ $code ] = array(
				'item_codes' => array(
					Integration_Item_Types::TRIGGER          => $this->extract_trigger_codes( $code, $trigger_registry ),
					Integration_Item_Types::ACTION           => $this->extract_action_codes( $code, $action_registry ),
					Integration_Item_Types::FILTER_CONDITION => $this->extract_condition_codes( $code, $condition_service ),
					Integration_Item_Types::LOOP_FILTER      => $this->extract_loop_filter_codes( $code, $loop_filter_service ),
				),
			);
		}

		return $map;
	}

	/**
	 * Extract item codes from items array.
	 *
	 * @param array $items Array of items with 'code' key
	 * @return array Array of codes
	 */
	private function extract_item_codes_from_array( array $items ) {
		return array_column( $items, 'code' );
	}

	/**
	 * Extract trigger codes for integration.
	 *
	 * Fast extraction - no full discovery.
	 *
	 * @param string $integration_code Integration code
	 * @param WP_Trigger_Registry $registry Trigger registry
	 * @return array Array of trigger codes
	 */
	private function extract_trigger_codes( string $integration_code, $registry ) {
		$triggers = $registry->get_triggers_by_integration( $integration_code );
		return array_keys( $triggers );
	}

	/**
	 * Extract action codes for integration.
	 *
	 * Fast extraction - no full discovery.
	 *
	 * @param string $integration_code Integration code
	 * @param WP_Action_Registry $registry Action registry
	 * @return array Array of action codes
	 */
	private function extract_action_codes( string $integration_code, $registry ) {
		$actions = $registry->get_actions_by_integration( $integration_code );
		return array_keys( $actions );
	}

	/**
	 * Extract condition codes for integration.
	 *
	 * Fast extraction - no full discovery.
	 *
	 * @param string $integration_code Integration code
	 * @param Condition_Registry_Service $service Condition registry service
	 * @return array Array of condition codes
	 */
	private function extract_condition_codes( string $integration_code, $service ) {
		$result = $service->list_conditions( array( 'integration' => $integration_code ) );

		if ( is_wp_error( $result ) ) {
			return array();
		}

		$conditions = $result['conditions'] ?? array();

		// Extract condition_code property.
		return ! empty( $conditions )
			? array_column( $conditions, 'condition_code' )
			: array();
	}

	/**
	 * Extract loop filter codes for integration.
	 *
	 * Fast extraction - no full discovery.
	 *
	 * @param string $integration_code Integration code
	 * @param Loop_Filter_Registry_Service $service Loop filter registry service
	 * @return array Array of loop filter codes
	 */
	private function extract_loop_filter_codes( string $integration_code, $service ) {
		$loop_filters = $service->get_loop_filters_by_integration( $integration_code );
		return array_keys( $loop_filters );
	}
}

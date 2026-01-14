<?php
/**
 * Action Formatter Service
 *
 * Handles action result formatting with availability checking and plan restrictions.
 * Decorates search results with availability information for the MCP layer.
 *
 * @package Uncanny_Automator\Api\Services\Action
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Action\Utilities;

use Uncanny_Automator\Api\Components\Search\Shared\Component_Availability;
use Uncanny_Automator\Api\Services\Action\Services\Action_Registry_Service;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;

/**
 * Service for formatting action results with availability data.
 *
 * @since 7.0.0
 */
class Action_Formatter {

	/**
	 * Check action availability including plan restrictions.
	 *
	 * @param array $action Action data.
	 * @return Component_Availability Availability value object.
	 */
	public function check_action_availability( array $action ): Component_Availability {
		$service = Action_Registry_Service::instance();

		// Prepare action data for availability check.
		$action_data = array(
			'integration_id' => $action['integration_id'] ?? $action['integration'] ?? '',
			'code'           => $action['code'] ?? $action['action_code'] ?? '',
			'required_tier'  => $action['required_tier'] ?? $action['plans'][0] ?? 'lite',
		);

		// Get integration availability.
		$integration_availability = $service->check_action_integration_availability( $action_data );

		// Start with integration availability.
		$availability_data = array(
			'available' => $integration_availability['available'],
			'message'   => $integration_availability['message'],
			'blockers'  => $integration_availability['blockers'],
		);

		// Add plan-based restrictions if needed.
		if ( ! $this->user_can_access_action_plan( $action['plans'] ?? array() ) ) {
			$availability_data['available']  = false;
			$availability_data['blockers'][] = 'This action is not available for your plan. Please upgrade to a higher plan to use it. Refer to this link for more information: https://automatorplugin.com/pricing/';
			$availability_data['message']    = implode( ' AND ', $availability_data['blockers'] ) . '.';
		}

		return Component_Availability::from_array( $availability_data );
	}

	/**
	 * Check if user can access action based on plan hierarchy.
	 *
	 * @param array $action_plans Array of plans that can access the action.
	 * @return bool True if user can access the action.
	 */
	private function user_can_access_action_plan( array $action_plans ) {
		$plan_service = new Plan_Service();
		return $plan_service->user_can_access( $action_plans );
	}
}

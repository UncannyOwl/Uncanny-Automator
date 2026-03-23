<?php
/**
 * Trigger Formatter Service
 *
 * Handles trigger result formatting with availability checking and integration restrictions.
 * Decorates search results with availability information for the MCP layer.
 *
 * @package Uncanny_Automator\Api\Services\Trigger
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Trigger\Utilities;

use Uncanny_Automator\Api\Components\Search\Shared\Component_Availability;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;

/**
 * Service for formatting trigger results with availability data.
 *
 * @since 7.0.0
 */
class Trigger_Formatter {

	private $plan_service;

	/**
	 * Constructor.
	 *
	 * @param Plan_Service|null $plan_service Plan service instance.
	 */
	public function __construct( ?Plan_Service $plan_service = null ) {
		$this->plan_service = $plan_service ?? new Plan_Service();
	}

	/**
	 * Format trigger results with availability information.
	 *
	 * @param array $triggers Array of trigger data from search.
	 * @return array Formatted results with availability info.
	 */
	public function format_trigger_results( array $triggers ) {

		$formatted_results = array();

		foreach ( $triggers as $trigger ) {
			$availability = $this->check_trigger_availability( $trigger );

			$formatted_results[] = array(
				'item'         => $trigger,
				'availability' => $availability,
			);
		}

		return $formatted_results;
	}

	/**
	 * Check trigger availability including integration and plan restrictions.
	 *
	 * @param array $trigger Trigger data.
	 * @return Component_Availability Availability value object.
	 */
	public function check_trigger_availability( array $trigger ): Component_Availability {

		$availability_data = $this->check_integration_availability( $trigger );

		// Add plan-based feedback if needed.
		if ( ! $this->is_available( $trigger ) ) {
			$availability_data['blockers'][] = 'This trigger is not available for your plan. Please upgrade to a higher plan to use it. Refer to this link for more information: https://automatorplugin.com/pricing/';

			// Update the message to reflect plan restrictions.
			if ( ! empty( $availability_data['blockers'] ) ) {
				$availability_data['message'] = implode( ' AND ', $availability_data['blockers'] ) . '.';
			}

			// Mark as unavailable.
			$availability_data['available'] = false;
		}

		return Component_Availability::from_array( $availability_data );
	}

	/**
	 * Check if trigger's integration is available.
	 *
	 * @param array $trigger Trigger data.
	 * @return array Integration availability info.
	 */
	private function check_integration_availability( array $trigger ) {

		$service = Trigger_Registry_Service::get_instance();

		// Map trigger array keys to expected format.
		$trigger_data = array(
			'integration_id' => $trigger['integration_id'] ?? $trigger['integration'] ?? '',
			'code'           => $trigger['code'] ?? $trigger['trigger_code'] ?? '',
			'required_tier'  => $trigger['required_tier'] ?? $trigger['plans'][0] ?? 'lite',
		);

		return $service->check_trigger_integration_availability( $trigger_data );
	}

	/**
	 * Check if user can access trigger based on plan hierarchy.
	 *
	 * @param array $trigger_plans Array of plans that can access the trigger.
	 * @return bool True if user can access the trigger.
	 */
	private function is_available( $trigger ) {
		return $this->plan_service->user_can_access( $trigger['plans'] ?? array() );
	}
}

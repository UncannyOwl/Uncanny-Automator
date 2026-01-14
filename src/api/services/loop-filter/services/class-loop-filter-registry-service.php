<?php
/**
 * Loop Filter Registry Service
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Api\Services\Loop_Filter\Services;

use Uncanny_Automator\Traits\Singleton;

/**
 * Loop Filter Registry Service Class
 *
 * Handles loop filter discovery operations.
 */
class Loop_Filter_Registry_Service {

	use Singleton;

	/**
	 * Get all loop filters (raw data).
	 *
	 * @since 7.0.0
	 *
	 * @return array All loop filters from registry, nested by integration
	 */
	public function get_all_loop_filters() {
		return Automator()->get_loop_filters();
	}

	/**
	 * Get loop filters by integration code.
	 *
	 * @since 7.0.0
	 * @param string $integration Integration code
	 *
	 * @return array Loop filters for the integration
	 */
	public function get_loop_filters_by_integration( string $integration ): array {
		$all_loop_filters = $this->get_all_loop_filters();

		// Loop filters are nested by integration
		return $all_loop_filters[ $integration ] ?? array();
	}
}

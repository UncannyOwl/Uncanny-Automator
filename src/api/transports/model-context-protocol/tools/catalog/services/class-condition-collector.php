<?php
/**
 * Condition Collector Service.
 *
 * Handles collection of condition components for the Automator Explorer.
 * Returns Condition_Search_Result_Collection value objects instead of raw arrays.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Services;

use Uncanny_Automator\Api\Components\Search\Shared\Component_Availability;
use Uncanny_Automator\Api\Components\Search\Condition\Condition_Search_Result;
use Uncanny_Automator\Api\Components\Search\Condition\Condition_Search_Result_Collection;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Registry_Service;

/**
 * Service for collecting condition search results.
 */
class Condition_Collector {

	/**
	 * Fetch limit for search results.
	 */
	private const FETCH_LIMIT = 10;

	/**
	 * Collect condition candidates for a search query.
	 *
	 * @param string $query Search term.
	 * @return Condition_Search_Result_Collection Collection of condition search results.
	 */
	public function collect_conditions( string $query ): Condition_Search_Result_Collection {
		$service = Condition_Registry_Service::get_instance();
		$result  = $service->find_conditions( $query, array(), self::FETCH_LIMIT );

		if ( is_wp_error( $result ) ) {
			return Condition_Search_Result_Collection::empty();
		}

		$raw_conditions = $result['conditions'] ?? array();

		if ( empty( $raw_conditions ) ) {
			return Condition_Search_Result_Collection::empty();
		}

		$items = $this->build_search_results( $raw_conditions );

		return new Condition_Search_Result_Collection( $items, count( $raw_conditions ) );
	}

	/**
	 * Build search result value objects from raw condition data.
	 *
	 * Skips items that fail validation (e.g., empty code, invalid tier).
	 * This is defensive - bad data in RAG index should not break search.
	 *
	 * @param array $raw_conditions Raw condition data from registry/RAG.
	 * @return Condition_Search_Result[] Array of search result value objects.
	 */
	private function build_search_results( array $raw_conditions ): array {
		$items = array();

		foreach ( $raw_conditions as $condition ) {
			try {
				$availability = $this->get_condition_availability( $condition );
				$items[]      = Condition_Search_Result::from_rag_result( $condition, $availability );
			} catch ( \InvalidArgumentException $e ) {
				// Skip items with invalid data (empty code, bad tier, etc.).
				continue;
			}
		}

		return $items;
	}

	/**
	 * Build condition availability info.
	 *
	 * @param array $condition Condition data.
	 * @return Component_Availability Availability value object.
	 */
	private function get_condition_availability( array $condition ): Component_Availability {
		$integration   = $condition['integration_id'] ?? $condition['integration_code'] ?? '';
		$code          = $condition['code'] ?? $condition['condition_code'] ?? '';
		$required_tier = $condition['required_tier'] ?? 'lite';

		$registry       = Condition_Registry_Service::get_instance();
		$condition_data = array(
			'integration_id' => $integration,
			'code'           => $code,
			'required_tier'  => $required_tier,
		);

		$availability_data = $registry->check_condition_integration_availability( $condition_data );

		return Component_Availability::from_array( $availability_data );
	}
}

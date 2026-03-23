<?php
/**
 * Loop Filter Collector Service.
 *
 * Handles collection of loop filter components for the Automator Explorer.
 * Uses RAG search API for semantic search of loop filters.
 * Returns Loop_Filter_Search_Result_Collection value objects instead of raw arrays.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Services;

use Uncanny_Automator\Api\Components\Search\Shared\Component_Availability;
use Uncanny_Automator\Api\Components\Search\Loop_Filter\Loop_Filter_Search_Result;
use Uncanny_Automator\Api\Components\Search\Loop_Filter\Loop_Filter_Search_Result_Collection;
use Uncanny_Automator\Api\Services\Rag\Rag_Search_Service;

/**
 * Service for collecting loop filter search results.
 */
class Loop_Filter_Collector {

	/**
	 * Fetch limit for search results.
	 */
	private const FETCH_LIMIT = 10;

	/**
	 * RAG search service.
	 *
	 * @var Rag_Search_Service
	 */
	private Rag_Search_Service $rag_service;

	/**
	 * Constructor.
	 *
	 * @param Rag_Search_Service|null $rag_service Optional RAG service instance.
	 */
	public function __construct( ?Rag_Search_Service $rag_service = null ) {
		$this->rag_service = $rag_service ?? new Rag_Search_Service();
	}

	/**
	 * Collect loop filter candidates for a search query.
	 *
	 * Uses RAG semantic search API with type=loop-filter.
	 *
	 * @param string $query Search term.
	 * @return Loop_Filter_Search_Result_Collection Collection of loop filter search results.
	 */
	public function collect_loop_filters( string $query ): Loop_Filter_Search_Result_Collection {
		$result = $this->rag_service->search( $query, 'loop-filter', null, self::FETCH_LIMIT );

		if ( is_wp_error( $result ) ) {
			return Loop_Filter_Search_Result_Collection::empty();
		}

		$raw_filters = $result['results'] ?? array();

		if ( empty( $raw_filters ) ) {
			return Loop_Filter_Search_Result_Collection::empty();
		}

		$items = $this->build_search_results( $raw_filters );

		return new Loop_Filter_Search_Result_Collection( $items, count( $raw_filters ) );
	}

	/**
	 * Collect all loop filters by integration.
	 *
	 * Uses RAG list API to get loop filters for a specific integration.
	 *
	 * @param string $integration Integration code.
	 * @param int    $limit       Max results.
	 * @return Loop_Filter_Search_Result_Collection Collection of loop filter results.
	 */
	public function collect_loop_filters_by_integration( string $integration, int $limit = 50 ): Loop_Filter_Search_Result_Collection {
		$result = $this->rag_service->list_by_integration( $integration, 'loop-filter', $limit );

		if ( is_wp_error( $result ) ) {
			return Loop_Filter_Search_Result_Collection::empty();
		}

		$raw_filters = $result['results'] ?? array();

		if ( empty( $raw_filters ) ) {
			return Loop_Filter_Search_Result_Collection::empty();
		}

		$items = $this->build_search_results( $raw_filters );

		return new Loop_Filter_Search_Result_Collection( $items, count( $raw_filters ) );
	}

	/**
	 * Build search result value objects from raw filter data.
	 *
	 * @param array $raw_filters Raw filter data from RAG API.
	 * @return Loop_Filter_Search_Result[] Array of search result value objects.
	 */
	private function build_search_results( array $raw_filters ): array {
		$items = array();

		foreach ( $raw_filters as $filter ) {
			try {
				$availability = $this->get_filter_availability( $filter );
				$items[]      = Loop_Filter_Search_Result::from_rag_result( $filter, $availability );
			} catch ( \InvalidArgumentException $e ) {
				// Skip items with invalid data (empty code, etc.).
				continue;
			}
		}

		return $items;
	}

	/**
	 * Build filter availability info.
	 *
	 * @param array $filter Filter data.
	 * @return Component_Availability Availability value object.
	 */
	private function get_filter_availability( array $filter ): Component_Availability {
		$required_tier = $filter['required_tier'] ?? 'lite';
		$is_available  = $this->rag_service->user_has_tier_access( $required_tier );

		return Component_Availability::from_array(
			array(
				'available' => $is_available,
				'reason'    => $is_available ? null : sprintf( 'Requires %s plan', ucfirst( $required_tier ) ),
			)
		);
	}
}

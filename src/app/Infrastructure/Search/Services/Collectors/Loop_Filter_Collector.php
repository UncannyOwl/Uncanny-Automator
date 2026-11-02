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

namespace Uncanny_Automator\App\Infrastructure\Search\Services\Collectors;

use Uncanny_Automator\App\Infrastructure\Plan\Plan_Implementation;
use Uncanny_Automator\App\Infrastructure\Search\Shared\Component_Availability;
use Uncanny_Automator\App\Infrastructure\Search\Loop_Filter\Loop_Filter_Search_Result;
use Uncanny_Automator\App\Infrastructure\Search\Loop_Filter\Loop_Filter_Search_Result_Collection;
use Uncanny_Automator\App\Infrastructure\Search\Services\Rag\Rag_Search_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_Registry_Service;

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
	 * The loop filters registered on this site.
	 *
	 * @var Filter_Registry_Service
	 */
	private Filter_Registry_Service $filters;

	/**
	 * Constructor.
	 *
	 * @param Rag_Search_Service|null      $rag_service Optional RAG service instance.
	 * @param Filter_Registry_Service|null $filters     Optional registry of this site's loop filters.
	 */
	public function __construct( ?Rag_Search_Service $rag_service = null, ?Filter_Registry_Service $filters = null ) {
		$this->rag_service = $rag_service ?? new Rag_Search_Service();
		$this->filters     = $filters ?? Filter_Registry_Service::instance();
	}

	/**
	 * Collect loop filter candidates for a search query.
	 *
	 * Uses RAG semantic search API with type=loop-filter.
	 *
	 * @param string $query Search term.
	 * @return Loop_Filter_Search_Result_Collection|\WP_Error Collection of loop filter search results.
	 */
	public function collect_loop_filters( string $query ) {
		$result = $this->rag_service->search( $query, 'loop-filter', null, self::FETCH_LIMIT );

		if ( is_wp_error( $result ) ) {
			// A failed catalog request does not mean the catalog is empty.
			return $result;
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
	 * @return Loop_Filter_Search_Result_Collection|\WP_Error Collection of loop filter results.
	 */
	public function collect_loop_filters_by_integration( string $integration, int $limit = 50 ) {
		$result = $this->rag_service->list_by_integration( $integration, 'loop-filter', $limit );

		if ( is_wp_error( $result ) ) {
			// A failed catalog request does not mean the catalog is empty.
			return $result;
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
	 * The catalog lists every loop filter there is. Which loops one applies to, and
	 * whether this site has it, are this site's answers, so they come from its registry.
	 *
	 * @param array $raw_filters Raw filter data from RAG API.
	 * @return Loop_Filter_Search_Result[] Array of search result value objects.
	 */
	private function build_search_results( array $raw_filters ): array {
		$items = array();

		foreach ( $raw_filters as $filter ) {
			try {
				$registered = $this->filters->get_filter_definition( (string) ( $filter['code'] ?? '' ) );
				if ( is_array( $registered ) ) {
					$filter['iteration_types'] = $registered['iteration_types'] ?? array();
				}
				$availability = $this->get_filter_availability( $filter, is_array( $registered ) );
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
	 * @param array $filter     Filter data.
	 * @param bool  $registered Whether this site has the filter.
	 * @return Component_Availability Availability value object.
	 * @throws \InvalidArgumentException If the filter's tier is not a plan.
	 */
	private function get_filter_availability( array $filter, bool $registered ): Component_Availability {
		// The tier the result reports, so availability and required_tier always agree.
		$required_tier = Loop_Filter_Search_Result::required_tier_from( $filter )->get_value();

		if ( ! $this->rag_service->user_has_tier_access( $required_tier ) ) {
			return Component_Availability::from_array(
				array(
					'available' => false,
					'message'   => sprintf( 'Requires %s plan', ( new Plan_Implementation( $required_tier ) )->get_name() ),
				)
			);
		}

		if ( ! $registered ) {
			return Component_Availability::from_array(
				array(
					'available' => false,
					'message'   => sprintf( '%s is not active on this site', $filter['integration_name'] ?? $filter['integration_id'] ?? 'Its integration' ),
				)
			);
		}

		return Component_Availability::from_array( array( 'available' => true ) );
	}
}

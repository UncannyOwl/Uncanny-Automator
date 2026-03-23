<?php
/**
 * Action Collector Service.
 *
 * Handles collection of action components for the Automator Explorer.
 * Returns Action_Search_Result_Collection value objects instead of raw arrays.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Services;

use Uncanny_Automator\Api\Components\Search\Action\Action_Search_Result;
use Uncanny_Automator\Api\Components\Search\Action\Action_Search_Result_Collection;
use Uncanny_Automator\Api\Services\Action\Utilities\Action_Formatter;
use Uncanny_Automator\Api\Services\Action\Services\Action_Registry_Service;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;
use Uncanny_Automator\Api\Services\Rag\Rag_Search_Service;

/**
 * Service for collecting action search results.
 */
class Action_Collector {

	/**
	 * Action formatter.
	 *
	 * @var Action_Formatter
	 */
	private Action_Formatter $formatter;

	/**
	 * Constructor.
	 *
	 * @param Action_Registry_Service $registry     Action registry service.
	 * @param Plan_Service            $plan_service Plan service.
	 */
	public function __construct( Action_Registry_Service $registry, Plan_Service $plan_service ) {
		$this->formatter = new Action_Formatter();
	}

	/**
	 * Fetch limit for search results.
	 */
	private const FETCH_LIMIT = 10;

	/**
	 * Collect action candidates for a search query.
	 *
	 * @param string $query Search term.
	 * @return Action_Search_Result_Collection Collection of action search results.
	 */
	public function collect_actions( string $query ): Action_Search_Result_Collection {
		$service = Action_Registry_Service::instance();
		$result  = $service->find_actions( $query, '', self::FETCH_LIMIT );

		if ( is_wp_error( $result ) ) {
			return Action_Search_Result_Collection::empty();
		}

		$raw_actions = $result['actions'] ?? $result;

		if ( empty( $raw_actions ) || ! is_array( $raw_actions ) ) {
			return Action_Search_Result_Collection::empty();
		}

		$items = $this->build_search_results( $raw_actions );

		return new Action_Search_Result_Collection( $items, count( $raw_actions ) );
	}

	/**
	 * Collect all actions for a specific integration.
	 *
	 * Uses RAG pkl file directly (no FAISS semantic search).
	 * Returns actions regardless of installation status.
	 *
	 * @param string $integration Integration ID (e.g., "WC", "LD", "GF").
	 * @param int    $limit       Maximum actions to return.
	 * @return Action_Search_Result_Collection Collection of action search results.
	 */
	public function collect_actions_by_integration( string $integration, int $limit = 50 ): Action_Search_Result_Collection {
		$rag_service = new Rag_Search_Service();
		$result      = $rag_service->list_by_integration( $integration, 'action', $limit );

		if ( is_wp_error( $result ) ) {
			return Action_Search_Result_Collection::empty();
		}

		$raw_actions = $result['results'] ?? array();

		if ( empty( $raw_actions ) ) {
			return Action_Search_Result_Collection::empty();
		}

		$items = $this->build_search_results( $raw_actions );

		return new Action_Search_Result_Collection( $items, count( $raw_actions ) );
	}

	/**
	 * Build search result value objects from raw action data.
	 *
	 * Skips items that fail validation (e.g., empty code, invalid tier).
	 * This is defensive - bad data in RAG index should not break search.
	 *
	 * @param array $raw_actions Raw action data from registry/RAG.
	 * @return Action_Search_Result[] Array of search result value objects.
	 */
	private function build_search_results( array $raw_actions ): array {
		$items = array();

		foreach ( $raw_actions as $action ) {
			try {
				$availability = $this->formatter->check_action_availability( $action );
				$items[]      = Action_Search_Result::from_rag_result( $action, $availability );
			} catch ( \InvalidArgumentException $e ) {
				// Skip items with invalid data (empty code, bad tier, etc.).
				continue;
			}
		}

		return $items;
	}
}

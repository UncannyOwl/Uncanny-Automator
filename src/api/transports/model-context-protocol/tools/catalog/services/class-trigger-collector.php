<?php
/**
 * Trigger Collector Service.
 *
 * Handles collection of trigger components for the Automator Explorer.
 * Returns Trigger_Search_Result_Collection value objects instead of raw arrays.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Services;

use Uncanny_Automator\Api\Components\Search\Shared\Component_Availability;
use Uncanny_Automator\Api\Components\Search\Trigger\Alternative_Triggers_Info;
use Uncanny_Automator\Api\Components\Search\Trigger\Trigger_Search_Result;
use Uncanny_Automator\Api\Components\Search\Trigger\Trigger_Search_Result_Collection;
use Uncanny_Automator\Api\Services\Trigger\Utilities\Trigger_Formatter;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;
use Uncanny_Automator\Api\Services\Rag\Rag_Search_Service;

/**
 * Service for collecting trigger search results.
 */
class Trigger_Collector {

	/**
	 * Trigger formatter.
	 *
	 * @var Trigger_Formatter
	 */
	private Trigger_Formatter $formatter;

	/**
	 * Constructor.
	 *
	 * @param Trigger_Registry_Service $registry     Trigger registry service.
	 * @param Plan_Service             $plan_service Plan service.
	 */
	public function __construct( Trigger_Registry_Service $registry, Plan_Service $plan_service ) {
		$this->formatter = new Trigger_Formatter( $plan_service );
	}

	/**
	 * Fetch limit for search results.
	 */
	private const FETCH_LIMIT = 10;

	/**
	 * Collect trigger candidates for a search query.
	 *
	 * @param string      $query     Search term.
	 * @param string|null $user_type User type filter for recipe compatibility ('user', 'anonymous', or null).
	 * @return Trigger_Search_Result_Collection Collection of trigger search results.
	 */
	public function collect_triggers( string $query, ?string $user_type = null ): Trigger_Search_Result_Collection {
		$filters = array();
		if ( null !== $user_type ) {
			$filters['user_type'] = $user_type;
		}

		$service = Trigger_Registry_Service::get_instance();
		$result  = $service->find_triggers( $query, $filters, self::FETCH_LIMIT );

		if ( is_wp_error( $result ) ) {
			return Trigger_Search_Result_Collection::empty();
		}

		$raw_triggers         = $result['triggers'] ?? array();
		$alternative_triggers = $this->build_alternative_triggers_info( $result['alternative_triggers'] ?? null );

		if ( empty( $raw_triggers ) ) {
			return new Trigger_Search_Result_Collection( array(), 0, $alternative_triggers );
		}

		$items = $this->build_search_results( $raw_triggers );

		return new Trigger_Search_Result_Collection( $items, count( $raw_triggers ), $alternative_triggers );
	}

	/**
	 * Collect all triggers for a specific integration filtered by user_type.
	 *
	 * Uses RAG pkl file directly (no FAISS semantic search).
	 * Returns triggers regardless of installation status.
	 *
	 * @param string      $integration Integration ID (e.g., "WPFORMS", "GF").
	 * @param string|null $user_type   User type filter ('user' or 'anonymous').
	 * @param int         $limit       Maximum triggers to return.
	 * @return Trigger_Search_Result_Collection Collection of trigger search results.
	 */
	public function collect_triggers_by_integration( string $integration, ?string $user_type = null, int $limit = 50 ): Trigger_Search_Result_Collection {
		$rag_service = new Rag_Search_Service();
		$result      = $rag_service->list_by_integration( $integration, 'trigger', $limit );

		if ( is_wp_error( $result ) ) {
			return Trigger_Search_Result_Collection::empty();
		}

		$raw_triggers = $result['results'] ?? array();

		if ( empty( $raw_triggers ) ) {
			return Trigger_Search_Result_Collection::empty();
		}

		// Filter by user_type if provided.
		// RAG data uses requires_user_data (bool).
		if ( null !== $user_type ) {
			$raw_triggers = array_filter(
				$raw_triggers,
				function ( $trigger ) use ( $user_type ) {
					$requires_user = $trigger['requires_user_data'] ?? true;

					if ( 'user' === $user_type ) {
						return true === $requires_user;
					}

					return false === $requires_user; // anonymous
				}
			);

			$raw_triggers = array_values( $raw_triggers );
		}

		$items = $this->build_search_results( $raw_triggers );

		return new Trigger_Search_Result_Collection( $items, count( $raw_triggers ) );
	}

	/**
	 * Build search result value objects from raw trigger data.
	 *
	 * Skips items that fail validation (e.g., empty code, invalid tier).
	 * This is defensive - bad data in RAG index should not break search.
	 *
	 * @param array $raw_triggers Raw trigger data from registry/RAG.
	 * @return Trigger_Search_Result[] Array of search result value objects.
	 */
	private function build_search_results( array $raw_triggers ): array {
		$items = array();

		foreach ( $raw_triggers as $trigger ) {
			try {
				$availability = $this->formatter->check_trigger_availability( $trigger );
				$items[]      = Trigger_Search_Result::from_rag_result( $trigger, $availability );
			} catch ( \InvalidArgumentException $e ) {
				// Skip items with invalid data (empty code, bad tier, etc.).
				continue;
			}
		}

		return $items;
	}

	/**
	 * Build Alternative_Triggers_Info value object from array data.
	 *
	 * @param array|null $data Alternative triggers data.
	 * @return Alternative_Triggers_Info|null
	 */
	private function build_alternative_triggers_info( ?array $data ): ?Alternative_Triggers_Info {
		if ( empty( $data ) ) {
			return null;
		}

		return Alternative_Triggers_Info::from_array( $data );
	}
}

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

namespace Uncanny_Automator\App\Infrastructure\Search\Services\Collectors;

use Uncanny_Automator\App\Infrastructure\Search\Action\Action_Search_Result;
use Uncanny_Automator\App\Infrastructure\Search\Action\Action_Search_Result_Collection;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Integration_Required_Tier;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Utilities\Action_Formatter;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Action_Registry_Service;
use Uncanny_Automator\App\Plan\Services\Plan_Service;
use Uncanny_Automator\App\Infrastructure\Search\Services\Rag\Rag_Search_Service;

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
	 * @return Action_Search_Result_Collection|\WP_Error Collection of action search results.
	 */
	public function collect_actions( string $query ) {
		$service = Action_Registry_Service::instance();
		$result  = $service->find_actions( $query, '', self::FETCH_LIMIT );

		if ( is_wp_error( $result ) ) {
			// A failed catalog request does not mean the catalog is empty.
			return $result;
		}

		$raw_actions = $result['actions'] ?? $result;

		if ( empty( $raw_actions ) || ! is_array( $raw_actions ) ) {
			return Action_Search_Result_Collection::empty();
		}

		$items = $this->build_search_results( $raw_actions );

		return new Action_Search_Result_Collection( $items, count( $items ) );
	}

	/**
	 * Collect all actions for a specific integration.
	 *
	 * Uses RAG pkl file directly (no FAISS semantic search).
	 * Returns actions regardless of installation status.
	 *
	 * @param string $integration Integration ID (e.g., "WC", "LD", "GF").
	 * @param int    $limit       Maximum actions to return.
	 * @return Action_Search_Result_Collection|\WP_Error Collection of action search results.
	 */
	public function collect_actions_by_integration( string $integration, int $limit = 50 ) {
		$rag_service = new Rag_Search_Service();
		$result      = $rag_service->list_by_integration( $integration, 'action', $limit );

		if ( is_wp_error( $result ) ) {
			// A failed catalog request does not mean the catalog is empty.
			return $result;
		}

		$raw_actions = $result['results'] ?? array();

		if ( empty( $raw_actions ) ) {
			return Action_Search_Result_Collection::empty();
		}

		$items = $this->build_search_results( $raw_actions );

		return new Action_Search_Result_Collection( $items, count( $items ) );
	}

	/**
	 * Collect every registered action, for the deterministic listing.
	 *
	 * Reads the local registry rather than the hosted index, and returns the
	 * same item as a search, so an Agent reads `code` and `integration_id`
	 * whichever mode found the action.
	 *
	 * @since 8.0.0
	 *
	 * @return Action_Search_Result_Collection|\WP_Error Collection, or the registry's failure.
	 */
	public function collect_registered_actions() {
		$rows = Action_Registry_Service::instance()->get_available_actions( '', false );

		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$candidates = array();
		foreach ( $rows as $row ) {
			$integration  = (string) ( $row['integration'] ?? '' );
			$sentence     = '' !== ( $row['select_option_name'] ?? '' ) ? $row['select_option_name'] : ( $row['sentence_readable'] ?? '' );
			$candidates[] = array(
				'code'             => $row['action_code'] ?? '',
				// Definitions store their name escaped for HTML; an Agent reads text.
				'sentence'         => wp_specialchars_decode( (string) $sentence, ENT_QUOTES ),
				'integration_id'   => $integration,
				'integration_name' => Integration_Registry_Service::get_instance()->get_integration( $integration )['name'] ?? $integration,
				'required_tier'    => Integration_Required_Tier::from_registry_flags( $row )->get_value(),
				'is_deprecated'    => ! empty( $row['is_deprecated'] ),
			);
		}

		$items = $this->build_search_results( $candidates );

		return new Action_Search_Result_Collection( $items, count( $items ) );
	}

	/**
	 * Build search result value objects from raw action data.
	 *
	 * Skips items that fail validation (e.g., empty code, invalid tier).
	 * This is defensive - bad data in RAG index should not break search.
	 *
	 * Skips deprecated actions too, which are not for new work. A recipe that
	 * already uses one still reads its schema through get_component_schema.
	 *
	 * Trade-off: left out rather than flagged, because a flag can be ignored and
	 * the old action can rank above its replacement. So an Agent can't find a
	 * deprecated action by search; it learns the code from the recipe using it.
	 *
	 * Trade-off: a refused sentence, such as one over the length limit, is left
	 * out with no error rather than failing the whole list.
	 *
	 * @param array $raw_actions Raw action data from registry/RAG.
	 * @return Action_Search_Result[] Array of search result value objects.
	 */
	private function build_search_results( array $raw_actions ): array {
		$items = array();

		foreach ( $raw_actions as $action ) {
			if ( $this->is_deprecated( $action ) ) {
				continue;
			}

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

	/**
	 * Whether this action is deprecated on this site.
	 *
	 * Registry rows say so themselves. The hosted catalog doesn't know, so its
	 * rows are checked against the site's definition.
	 *
	 * Trade-off: an action this site hasn't registered, such as one from an
	 * inactive plugin, has no definition to check. It stays in the results, so an
	 * Agent can say what to activate, even if that action is deprecated.
	 *
	 * @param array $action Action data.
	 * @return bool
	 */
	private function is_deprecated( array $action ): bool {
		if ( isset( $action['is_deprecated'] ) ) {
			return (bool) $action['is_deprecated'];
		}

		$definition = Action_Registry_Service::instance()->get_action_definition( (string) ( $action['code'] ?? '' ), false );

		return ! is_wp_error( $definition ) && ! empty( $definition['is_deprecated'] );
	}
}

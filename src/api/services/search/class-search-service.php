<?php
/**
 * Search Service.
 *
 * Application-layer service that orchestrates search and listing operations
 * across triggers, actions, conditions, loopable tokens, and loop filters.
 *
 * Absorbs orchestration logic previously in Search_Tool, delegating to
 * collectors (semantic search) and registry services (deterministic listing).
 *
 * @package Uncanny_Automator
 * @since   7.1.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Search;

use Uncanny_Automator\Api\Services\Action\Services\Action_Registry_Service;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_Registry_Service;
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service;
use WP_Error;

/**
 * Orchestrates search and listing across component types.
 */
class Search_Service {

	private const SUPPORTED_TYPES = array( 'trigger', 'action', 'condition', 'loopable_token', 'loop_filter' );
	private const SUPPORTED_MODES = array( 'search', 'list' );

	/**
	 * @var Collector_Factory
	 */
	private Collector_Factory $factory;

	/**
	 * @var Filter_Registry_Service
	 */
	private Filter_Registry_Service $filter_registry;

	/**
	 * @var Loop_CRUD_Service
	 */
	private Loop_CRUD_Service $loop_service;

	/**
	 * Constructor.
	 *
	 * @param Collector_Factory|null $factory         Explorer factory for collectors.
	 * @param Filter_Registry_Service|null    $filter_registry Filter registry for deterministic loop_filter listing.
	 * @param Loop_CRUD_Service|null          $loop_service    Loop service for resolving loop_id to loop type.
	 */
	public function __construct(
		?Collector_Factory $factory = null,
		?Filter_Registry_Service $filter_registry = null,
		?Loop_CRUD_Service $loop_service = null
	) {
		$this->factory         = $factory ?? new Collector_Factory();
		$this->filter_registry = $filter_registry ?? Filter_Registry_Service::instance();
		$this->loop_service    = $loop_service ?? Loop_CRUD_Service::instance();
	}

	/**
	 * Main entry point. Dispatches to search or list based on mode.
	 *
	 * @param string      $type        Component type (trigger, action, condition, loopable_token, loop_filter).
	 * @param string      $mode        "search" or "list".
	 * @param string      $query       Search query (required for mode=search unless integration is set).
	 * @param string|null $integration Optional integration code filter.
	 * @param int|null    $loop_id     Optional loop ID (for loop_filter with mode=list).
	 * @param int         $limit       Max results.
	 * @param int         $offset      Results offset for pagination.
	 *
	 * @return array|WP_Error Array with 'message' and 'data' keys on success, WP_Error on failure.
	 */
	public function search( string $type, string $mode, string $query, ?string $integration, ?int $loop_id, int $limit, int $offset ) {
		if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
			return new WP_Error( 'invalid_type', sprintf( 'Unsupported type: %s', $type ) );
		}

		if ( ! in_array( $mode, self::SUPPORTED_MODES, true ) ) {
			return new WP_Error( 'invalid_mode', 'Invalid mode. Use "search" or "list".' );
		}

		if ( 'search' === $mode && '' === $query && ! $integration ) {
			return new WP_Error( 'missing_query', 'mode=search requires a query or integration parameter.' );
		}

		if ( 'search' === $mode ) {
			return $this->execute_search( $type, $query, $integration, $limit, $offset );
		}

		return $this->execute_list( $type, $integration, $loop_id, $limit, $offset );
	}

	/**
	 * Fuzzy semantic search via collectors.
	 *
	 * @param string      $type        Component type.
	 * @param string      $query       Search query.
	 * @param string|null $integration Optional integration filter.
	 * @param int         $limit       Max results.
	 * @param int         $offset      Results offset.
	 *
	 * @return array Search results with 'message' and 'data' keys.
	 */
	private function execute_search( string $type, string $query, ?string $integration, int $limit, int $offset ): array {

		// If integration is provided, delegate to integration-based listing.
		if ( $integration ) {
			return $this->list_by_integration( $type, $integration, $limit, $offset );
		}

		$items                = array();
		$alternative_triggers = null;

		switch ( $type ) {
			case 'trigger':
				$collection = $this->factory->get_trigger_collector()->collect_triggers( $query );
				$items      = $collection->to_array();
				if ( $collection->has_alternative_triggers() ) {
					$alternative_triggers = $collection->get_alternative_triggers()->to_array();
				}
				break;

			case 'action':
				$collection = $this->factory->get_action_collector()->collect_actions( $query );
				$items      = $collection->to_array();
				break;

			case 'condition':
				$collection = $this->factory->get_condition_collector()->collect_conditions( $query );
				$items      = $collection->to_array();
				break;

			case 'loopable_token':
				$collection = $this->factory->get_loopable_token_collector()->collect_loopable_tokens( $query );
				$items      = $collection->to_array();
				break;

			case 'loop_filter':
				$collection = $this->factory->get_loop_filter_collector()->collect_loop_filters( $query );
				$items      = $collection->to_array();
				break;
		}

		$sliced = array_values( array_slice( $items, $offset, $limit ) );

		$response_data = array(
			'query' => $query,
			'type'  => $type,
			'items' => $sliced,
			'total' => count( $items ),
		);

		if ( $alternative_triggers ) {
			$response_data['alternative_triggers'] = $alternative_triggers;
		}

		if ( empty( $sliced ) ) {
			return array(
				'message' => sprintf( "No %s components found for '%s'.", $type, $query ),
				'data'    => $response_data,
			);
		}

		return array(
			'message' => sprintf( 'Found %d %s(s). Use get_component_schema to inspect fields before configuring.', count( $sliced ), $type ),
			'data'    => $response_data,
		);
	}

	/**
	 * Deterministic catalog listing via registry services.
	 *
	 * @param string      $type        Component type.
	 * @param string|null $integration Optional integration filter.
	 * @param int|null    $loop_id     Loop ID for loop_filter type.
	 * @param int         $limit       Max results.
	 * @param int         $offset      Results offset.
	 *
	 * @return array|WP_Error List results.
	 */
	private function execute_list( string $type, ?string $integration, ?int $loop_id, int $limit, int $offset ) {

		if ( 'trigger' === $type ) {
			return $this->list_triggers( $integration, $limit, $offset );
		}

		if ( 'action' === $type ) {
			return $this->list_actions( $integration, $limit, $offset );
		}

		if ( 'condition' === $type ) {
			return $this->list_conditions( $integration, $limit, $offset );
		}

		if ( 'loopable_token' === $type ) {
			return $this->list_loopable_tokens( $limit, $offset );
		}

		if ( 'loop_filter' === $type ) {
			// If integration is provided, use the collector's integration-based listing.
			if ( $integration ) {
				$collection = $this->factory->get_loop_filter_collector()->collect_loop_filters_by_integration( $integration, $limit );
				$items      = $collection->to_array();
				$sliced     = array_values( array_slice( $items, $offset, $limit ) );

				return array(
					'message' => sprintf( 'Found %d loop filter(s) for integration %s.', count( $sliced ), $integration ),
					'data'    => array(
						'type'        => 'loop_filter',
						'integration' => $integration,
						'items'       => $sliced,
						'total'       => count( $items ),
					),
				);
			}

			return $this->list_loop_filters( $loop_id, $limit, $offset );
		}

		return new WP_Error( 'unsupported_type', sprintf( 'Unsupported type for mode=list: %s', $type ) );
	}

	/**
	 * List triggers from Trigger_Registry_Service.
	 *
	 * @param string|null $integration Optional integration filter.
	 * @param int         $limit       Max results.
	 * @param int         $offset      Results offset.
	 *
	 * @return array|WP_Error List results.
	 */
	private function list_triggers( ?string $integration, int $limit, int $offset ) {

		if ( $integration ) {
			$collection = $this->factory->get_trigger_collector()->collect_triggers_by_integration( $integration, null, $limit );
			$items      = $collection->to_array();
		} else {
			$service = Trigger_Registry_Service::instance();
			$result  = $service->list_triggers( array(), false );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$items = $result['triggers'] ?? $result;
		}

		$sliced = array_values( array_slice( $items, $offset, $limit ) );

		return array(
			'message' => sprintf( 'Found %d trigger(s).', count( $sliced ) ),
			'data'    => array(
				'type'  => 'trigger',
				'items' => $sliced,
				'total' => count( $items ),
			),
		);
	}

	/**
	 * List actions from Action_Registry_Service.
	 *
	 * @param string|null $integration Optional integration filter.
	 * @param int         $limit       Max results.
	 * @param int         $offset      Results offset.
	 *
	 * @return array|WP_Error List results.
	 */
	private function list_actions( ?string $integration, int $limit, int $offset ) {

		if ( $integration ) {
			$collection = $this->factory->get_action_collector()->collect_actions_by_integration( $integration, $limit );
			$items      = $collection->to_array();
		} else {
			$service = Action_Registry_Service::instance();
			$result  = $service->get_available_actions( '', false );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$items = $result['actions'] ?? $result;
		}

		$sliced = array_values( array_slice( $items, $offset, $limit ) );

		return array(
			'message' => sprintf( 'Found %d action(s).', count( $sliced ) ),
			'data'    => array(
				'type'  => 'action',
				'items' => $sliced,
				'total' => count( $items ),
			),
		);
	}

	/**
	 * List conditions from Condition_Registry_Service.
	 *
	 * @param string|null $integration Optional integration filter.
	 * @param int         $limit       Max results.
	 * @param int         $offset      Results offset.
	 *
	 * @return array|WP_Error List results.
	 */
	private function list_conditions( ?string $integration, int $limit, int $offset ) {

		$service = Condition_Registry_Service::instance();
		$filters = $integration ? array( 'integration' => $integration ) : array();
		$result  = $service->list_conditions( $filters );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$items  = $result['conditions'] ?? $result;
		$sliced = array_values( array_slice( $items, $offset, $limit ) );

		return array(
			'message' => sprintf( 'Found %d condition type(s).', count( $sliced ) ),
			'data'    => array(
				'type'  => 'condition',
				'items' => $sliced,
				'total' => count( $items ),
			),
		);
	}

	/**
	 * List loopable tokens via the collector.
	 *
	 * @param int $limit  Max results.
	 * @param int $offset Results offset.
	 *
	 * @return array List results.
	 */
	private function list_loopable_tokens( int $limit, int $offset ): array {

		$collection = $this->factory->get_loopable_token_collector()->collect_loopable_tokens( '' );
		$items      = $collection->to_array();
		$sliced     = array_values( array_slice( $items, $offset, $limit ) );

		return array(
			'message' => sprintf( 'Found %d loopable token(s).', count( $sliced ) ),
			'data'    => array(
				'type'  => 'loopable_token',
				'items' => $sliced,
				'total' => count( $items ),
			),
		);
	}

	/**
	 * List available loop filters from Filter_Registry_Service.
	 *
	 * If loop_id is provided, resolves loop type via Loop_CRUD_Service,
	 * then queries Filter_Registry_Service for filters applicable to that type.
	 *
	 * @param int|null $loop_id Loop ID to determine loop type.
	 * @param int      $limit   Max results.
	 * @param int      $offset  Results offset.
	 *
	 * @return array|WP_Error List results.
	 */
	private function list_loop_filters( ?int $loop_id, int $limit, int $offset ) {

		$loop_type = null;

		// Resolve loop type from loop_id.
		if ( null !== $loop_id ) {
			$loop_result = $this->loop_service->get_loop( $loop_id );

			if ( is_wp_error( $loop_result ) ) {
				return new WP_Error(
					'loop_not_found',
					$loop_result->get_error_message() . ' Use loop_list with recipe_id to find available loops.'
				);
			}

			$loop      = $loop_result['loop'] ?? array();
			$loop_type = $loop['iterable_expression']['type'] ?? $loop['type'] ?? null;
		}

		$options = array( 'include_schema' => true );
		$filters = $this->get_filters_for_type( $loop_type, $options );

		$formatted = array();
		foreach ( $filters as $code => $filter ) {
			$filter_code = $filter['code'] ?? $code;

			$formatted[] = array(
				'code'             => $filter_code,
				'integration_code' => $filter['integration_code'] ?? $filter['integration'] ?? '',
				'sentence'         => $filter['sentence_readable'] ?? $filter['sentence'] ?? $filter_code,
				'iteration_types'  => $filter['iteration_types'] ?? array(),
			);
		}

		$sliced = array_slice( $formatted, $offset, $limit );

		$message = sprintf(
			'Found %d available filter type(s)%s.',
			count( $sliced ),
			$loop_type ? " for '{$loop_type}' loops" : ''
		);

		$response_data = array(
			'type'  => 'loop_filter',
			'items' => $sliced,
			'total' => count( $formatted ),
		);

		if ( null !== $loop_id ) {
			$response_data['loop_id']   = $loop_id;
			$response_data['loop_type'] = $loop_type;
		}

		return array(
			'message' => $message,
			'data'    => $response_data,
		);
	}

	/**
	 * Get filters for a specific loop type or all filters.
	 *
	 * @param string|null $loop_type Loop type (users, posts, token) or null for all.
	 * @param array       $options   Format options.
	 *
	 * @return array Filters from registry.
	 */
	private function get_filters_for_type( ?string $loop_type, array $options ): array {
		if ( null === $loop_type ) {
			return $this->filter_registry->get_all_filters( $options );
		}

		if ( 'users' === $loop_type ) {
			return $this->filter_registry->get_user_filters( $options );
		}

		if ( 'posts' === $loop_type ) {
			return $this->filter_registry->get_post_filters( $options );
		}

		if ( 'token' === $loop_type ) {
			return $this->filter_registry->get_token_filters( $options );
		}

		return $this->filter_registry->get_all_filters( $options );
	}

	/**
	 * List components by integration via collectors.
	 *
	 * Used when mode=search but integration is provided.
	 *
	 * @param string $type        Component type.
	 * @param string $integration Integration code.
	 * @param int    $limit       Max results.
	 * @param int    $offset      Results offset.
	 *
	 * @return array|WP_Error Search results.
	 */
	private function list_by_integration( string $type, string $integration, int $limit, int $offset ) {

		$items = array();

		if ( 'trigger' === $type ) {
			$collection = $this->factory->get_trigger_collector()->collect_triggers_by_integration( $integration, null, $limit );
			$items      = $collection->to_array();
		} elseif ( 'action' === $type ) {
			$collection = $this->factory->get_action_collector()->collect_actions_by_integration( $integration, $limit );
			$items      = $collection->to_array();
		} elseif ( 'loop_filter' === $type ) {
			$collection = $this->factory->get_loop_filter_collector()->collect_loop_filters_by_integration( $integration, $limit );
			$items      = $collection->to_array();
		} elseif ( 'condition' === $type || 'loopable_token' === $type ) {
			return new WP_Error(
				'unsupported_integration_listing',
				sprintf( 'Integration-based listing is not supported for type=%s. Use mode=list instead.', $type )
			);
		}

		$sliced = array_values( array_slice( $items, $offset, $limit ) );

		if ( empty( $sliced ) ) {
			return array(
				'message' => sprintf( "No %s components found for integration '%s'.", $type, $integration ),
				'data'    => array(
					'type'        => $type,
					'integration' => $integration,
					'items'       => array(),
					'total'       => 0,
				),
			);
		}

		return array(
			'message' => sprintf( 'Found %d %s(s) for integration %s.', count( $sliced ), $type, $integration ),
			'data'    => array(
				'type'        => $type,
				'integration' => $integration,
				'items'       => $sliced,
				'total'       => count( $items ),
			),
		);
	}
}

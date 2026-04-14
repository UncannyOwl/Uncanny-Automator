<?php
/**
 * Consolidated search/discovery tool.
 *
 * Replaces: search_components, loop_get_loopable_tokens, loop_list_available_filters.
 * Two modes: "search" (fuzzy semantic via collectors) and "list" (deterministic catalog via registry services).
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Search\Search_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Consolidated discovery tool.
 *
 * mode=search: fuzzy semantic discovery via Collector_Factory collectors.
 * mode=list:   deterministic catalog via registry services.
 *
 * @since 7.1.0
 */
class Search_Tool extends Abstract_MCP_Tool {

	private const LIST_LIMIT      = 50;
	private const SUPPORTED_TYPES = array( 'trigger', 'action', 'condition', 'loopable_token', 'loop_filter' );
	private const SUPPORTED_MODES = array( 'search', 'list' );

	/**
	 * @var Search_Service
	 */
	private Search_Service $search_service;

	/**
	 * Constructor.
	 *
	 * @param Search_Service|null $search_service Search service instance.
	 */
	public function __construct( ?Search_Service $search_service = null ) {
		$this->search_service = $search_service ?? new Search_Service();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'search';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return 'Discover available triggers, actions, conditions, loopable tokens, or loop filters. '
			. 'Two modes: "search" (default) uses semantic matching — pass a query like "send email when user registers". '
			. '"list" returns the complete deterministic catalog for the given type. '
			. 'For loop_filter with mode=list, pass loop_id to get filters specific to that loop\'s type. '
			. 'After finding a component, call get_component_schema to inspect its fields.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'type'        => array(
					'type'        => 'string',
					'enum'        => self::SUPPORTED_TYPES,
					'description' => 'Component type to search for.',
				),
				'mode'        => array(
					'type'        => 'string',
					'enum'        => self::SUPPORTED_MODES,
					'default'     => 'search',
					'description' => '"search" = fuzzy semantic matching (requires query). "list" = deterministic complete catalog.',
				),
				'query'       => array(
					'type'        => 'string',
					'description' => 'Natural language search query (e.g., "purchase subscription", "submit form"). Used in mode=search.',
				),
				'integration' => array(
					'type'        => 'string',
					'description' => 'Filter results by integration code (e.g., "WC", "LD"). Works in both modes.',
				),
				'loop_id'     => array(
					'type'        => 'integer',
					'description' => 'For loop_filter with mode=list: determines the loop type and returns only filters applicable to that type.',
					'minimum'     => 1,
				),
				'limit'       => array(
					'type'        => 'integer',
					'default'     => 20,
					'maximum'     => 100,
					'minimum'     => 1,
					'description' => 'Maximum results to return.',
				),
				'offset'      => array(
					'type'        => 'integer',
					'default'     => 0,
					'minimum'     => 0,
					'description' => 'Number of results to skip (for pagination).',
				),
			),
			'required'   => array( 'type' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'type'                  => array( 'type' => 'string' ),
				'items'                 => array( 'type' => 'array' ),
				'total'                 => array( 'type' => 'integer' ),
				'query'                 => array( 'type' => 'string' ),
				'alternative_triggers'  => array( 'type' => 'array' ),
				'integration'           => array( 'type' => 'string' ),
				'loop_id'               => array( 'type' => 'integer' ),
				'loop_type'             => array( 'type' => 'string' ),
			),
			'required'   => array( 'type', 'items', 'total' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$type        = $params['type'] ?? '';
		$mode        = $params['mode'] ?? 'search';
		$query       = trim( $params['query'] ?? '' );
		$integration = isset( $params['integration'] ) ? trim( $params['integration'] ) : null;
		$loop_id     = isset( $params['loop_id'] ) ? (int) $params['loop_id'] : null;
		$limit       = (int) ( $params['limit'] ?? 20 );
		$offset      = (int) ( $params['offset'] ?? 0 );

		if ( empty( $type ) ) {
			return Json_Rpc_Response::create_error_response( 'Parameter "type" is required.' );
		}

		$result = $this->search_service->search( $type, $mode, $query, $integration, $loop_id, $limit, $offset );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response( $result['message'] ?? 'Search complete', $result['data'] ?? $result );
	}
}

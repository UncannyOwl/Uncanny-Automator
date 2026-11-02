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

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Infrastructure\Search\Services\Search_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Catalog;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Action_Availability_Policy;

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
	private const SUPPORTED_TYPES = array( 'trigger', 'action', 'condition', 'loopable_token', 'loop_filter', 'ability' );
	private const SUPPORTED_MODES = array( 'search', 'list' );

	/**
	 * @var Search_Service
	 */
	private Search_Service $search_service;

	/**
	 * Site-owner switches and the banned list, applied to action results.
	 *
	 * @var Action_Availability_Policy
	 */
	private Action_Availability_Policy $availability;

	/**
	 * WordPress abilities the site owner switched on.
	 *
	 * @var Ability_Catalog
	 */
	private Ability_Catalog $abilities;

	/**
	 * Constructor.
	 *
	 * @param Search_Service|null             $search_service Search service instance.
	 * @param Action_Availability_Policy|null $availability   Availability policy; defaults to the stored switches.
	 * @param Ability_Catalog|null            $abilities      Ability catalog; defaults to the stored switches.
	 */
	public function __construct( ?Search_Service $search_service = null, ?Action_Availability_Policy $availability = null, ?Ability_Catalog $abilities = null ) {
		$this->search_service = $search_service ?? new Search_Service();
		$this->availability   = $availability ?? new Action_Availability_Policy();
		$this->abilities      = $abilities ?? new Ability_Catalog();
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
		return 'Discover available triggers, actions, conditions (for filter blocks via save_filter_condition), loopable tokens, loop filters, or WordPress abilities. '
			. 'Two modes: "search" (default) uses semantic matching — pass a query like "send email when user registers". '
			. '"list" returns a deterministic catalog for the given type. Integration-filtered hosted catalogs contain at most 100 candidates; catalog_may_be_truncated warns when the upstream limit is reached. '
			. 'For loop_filter with mode=list, pass loop_id to get filters specific to that loop\'s type. '
			. 'Type "ability" lists the WordPress abilities the site owner switched on for AI apps (e.g. from WooCommerce); run one with run_ability. '
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
					'description' => '"search" = fuzzy semantic matching (requires query). "list" = deterministic catalog (hosted integration results are capped at 100).',
				),
				'query'       => array(
					'type'        => 'string',
					'description' => 'Natural language search query (e.g., "purchase subscription", "submit form"). Used in mode=search.',
				),
				'integration' => array(
					'type'        => 'string',
					'description' => 'Filter catalog listings by integration code (e.g., "WC", "LD"). In mode=search with a query, this parameter is ignored; include the integration name in the query and select relevant matches from the results. Without a query, returns the integration catalog. For type "ability", filters by plugin slug (e.g., "woocommerce") in both modes.',
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
				'type'                     => array( 'type' => 'string' ),
				'items'                    => array( 'type' => 'array' ),
				'total'                    => array(
					'type'        => 'integer',
					'description' => 'Number of matching candidates available for paging; may be a lower bound when catalog_may_be_truncated is true.',
				),
				'catalog_may_be_truncated' => array(
					'type'        => 'boolean',
					'description' => 'The hosted catalog reached its 100-candidate limit. Later candidates cannot be retrieved through offset.',
				),
				'query'                    => array( 'type' => 'string' ),
				'alternative_triggers'     => array(
					'type'       => 'object',
					'properties' => array(
						'count'       => array( 'type' => 'integer' ),
						'recipe_type' => array( 'type' => 'string' ),
					),
					'required'   => array( 'count', 'recipe_type' ),
				),
				'integration'              => array( 'type' => 'string' ),
				'loop_id'                  => array( 'type' => 'integer' ),
				'loop_type'                => array( 'type' => 'string' ),
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

		if ( 'ability' === $type ) {
			return $this->search_abilities( $user_context, $mode, $query, $integration, $limit, $offset );
		}

		// Some actions are refused for every caller, including Uncanny Agent.
		$filter_actions = 'action' === $type;

		// Apply availability to the candidate set before paging. The local action
		// registry is finite; integration listings are capped at 100 by /api/rag/list.
		// Semantic search already returns a bounded candidate set of its own.
		$fetch_limit  = $filter_actions ? ( $integration ? 100 : PHP_INT_MAX ) : $limit;
		$fetch_offset = $filter_actions ? 0 : $offset;
		$result       = $this->search_service->search( $type, $mode, $query, $integration, $loop_id, $fetch_limit, $fetch_offset );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		if ( $filter_actions && isset( $result['data']['items'] ) && is_array( $result['data']['items'] ) ) {
			$kept                    = $this->availability->filter_action_items( $result['data']['items'], $user_context );
			$result['data']['items'] = array_values( array_slice( $kept, $offset, $limit ) );
			$result['data']['total'] = count( $kept );
			$result['message']       = sprintf( 'Found %d action(s). Use get_component_schema to inspect fields before configuring.', count( $result['data']['items'] ) );
		}

		return Json_Rpc_Response::create_success_response( $result['message'] ?? 'Search complete', $result['data'] ?? $result );
	}

	/**
	 * WordPress abilities the site owner switched on, for Agent and connected AI apps.
	 *
	 * The set is small and owner-curated, so both modes match locally instead
	 * of using the hosted search.
	 *
	 * @param User_Context $user_context Caller.
	 * @param string       $mode         "search" or "list".
	 * @param string       $query        Words to match in search mode.
	 * @param string|null  $provider     Plugin slug filter.
	 * @param int          $limit        Page size.
	 * @param int          $offset       Rows to skip.
	 * @return array
	 */
	private function search_abilities( User_Context $user_context, string $mode, string $query, ?string $provider, int $limit, int $offset ): array {
		$found = $this->abilities->search( $mode, $query, $provider, $limit, $offset );
		$data  = array(
			'type'  => 'ability',
			'items' => $found['items'],
			'total' => $found['total'],
		);

		if ( 'search' === $mode ) {
			$data['query'] = $query;
		}

		if ( null !== $provider && '' !== $provider ) {
			$data['integration'] = $provider;
		}

		if ( empty( $found['items'] ) ) {
			return Json_Rpc_Response::create_success_response( 'No matching abilities are switched on for AI apps on this site.', $data );
		}

		$off     = count( array_filter( $found['items'], static fn( array $item ): bool => false === ( $item['availability']['available'] ?? true ) ) );
		$message = sprintf( 'Found %d ability(ies). Use get_component_schema (component_type "ability") for the arguments, then run_ability.', count( $found['items'] ) );
		if ( $off > 0 ) {
			$message .= sprintf( ' %d of them are off and controlled by their plugin; each one\'s availability says which plugin.', $off );
		}

		return Json_Rpc_Response::create_success_response( $message, $data );
	}
}

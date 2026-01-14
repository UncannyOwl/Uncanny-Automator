<?php
/**
 * Automator Explorer Tool.
 *
 * Consolidated discovery tool for triggers, actions, and conditions.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\Api\Components\Search\Trigger\Trigger_Search_Result_Collection;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Services\Automator_Explorer_Factory;

/**
 * Unified discovery tool used by MCP agents.
 */
class Search_Components_Tool extends Abstract_MCP_Tool {

	private const RESPONSE_LIMIT    = 7;
	private const INTEGRATION_LIMIT = 50;
	private const SUPPORTED_TYPES   = array( 'trigger', 'action', 'condition', 'all' );

	/**
	 * Automator explorer factory.
	 *
	 * @var Automator_Explorer_Factory
	 */
	private Automator_Explorer_Factory $factory;

	/**
	 * Constructor.
	 *
	 * @param Automator_Explorer_Factory $factory Factory for explorer services.
	 */
	public function __construct( ?Automator_Explorer_Factory $factory = null ) {
		$this->factory = $factory ?? new Automator_Explorer_Factory();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'search_components';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return 'Search for triggers, actions, or conditions. SEMANTIC mode: use query alone to find by meaning (top 7 matches). LIST mode: use integration alone to get all components (up to 50). Do NOT combine query with integration. After finding a component, call get_component_schema then get_field_options.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'query'     => array(
					'type'        => 'string',
					'description' => 'Natural language search query (e.g., "purchase subscription with variation", "submit form", "complete course"). Searches ALL integrations semantically - do NOT specify integration when using query, let the search find the right integration automatically. Use descriptive phrases, not single words.',
				),
				'type'      => array(
					'type'        => 'string',
					'enum'        => array( 'trigger', 'action', 'condition', 'all' ),
					'description' => 'Optional component filter. Defaults to all.',
					'default'     => 'all',
				),
				'user_type'   => array(
					'type'        => 'string',
					'enum'        => array( 'user', 'anonymous' ),
					'description' => 'Optional filter for trigger compatibility. "user" = logged-in user recipes, "anonymous" = any visitor recipes. If omitted, returns all triggers regardless of recipe type.',
				),
				'integration' => array(
					'type'        => 'string',
					'description' => 'LIST MODE ONLY: Integration ID (e.g., "WPF", "LD", "WC"). Returns up to 50 components for that exact integration. WARNING: This bypasses semantic search entirely. Only use when user explicitly asks to "list all triggers for WooCommerce" or similar. Do NOT use with query parameter - if you need to search, use query alone.',
				),
			),
			'required'   => array(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$query = trim( $params['query'] ?? '' );

		// Extract integration filter for direct listing (bypasses semantic search)
		$integration = isset( $params['integration'] ) ? trim( $params['integration'] ) : null;

		// Validate: require either query OR integration
		if ( '' === $query && ! $integration ) {
			return Json_Rpc_Response::create_error_response( 'Either query or integration parameter is required' );
		}

		$type = strtolower( $params['type'] ?? 'all' );
		if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
			return Json_Rpc_Response::create_error_response( 'Invalid type parameter' );
		}

		// Extract user_type filter for recipe compatibility (optional)
		$user_type = isset( $params['user_type'] ) ? strtolower( $params['user_type'] ) : null;
		if ( $user_type && ! in_array( $user_type, array( 'user', 'anonymous' ), true ) ) {
			$user_type = null; // Invalid value, ignore filter
		}

		// If integration is provided for trigger search, use direct listing
		if ( $integration && 'trigger' === $type ) {
			return $this->list_triggers_by_integration( $integration, $user_type );
		}

		// If integration is provided for action search, use direct listing
		if ( $integration && 'action' === $type ) {
			return $this->list_actions_by_integration( $integration );
		}

		$collection = $this->collect_results( $query, $type, $user_type );

		if ( ! empty( $collection['errors'] ) && empty( $collection['items'] ) ) {
			return Json_Rpc_Response::create_error_response( implode( '; ', $collection['errors'] ) );
		}

		// Extract alternative triggers info for discovery messaging
		$alternative_triggers = $collection['alternative_triggers'] ?? null;

		if ( empty( $collection['items'] ) ) {
			$response_data = array(
				'query'   => $query,
				'type'    => $type,
				'results' => array(),
			);

			// Include alternative triggers info so AI can inform user
			if ( $alternative_triggers ) {
				$response_data['alternative_triggers'] = $alternative_triggers;
			}

			return Json_Rpc_Response::create_success_response(
				"No Automator components found for '{$query}'",
				$response_data
			);
		}

		// RAG results are already semantically ranked - just slice to limit.
		$top = array_slice( $collection['items'], 0, self::RESPONSE_LIMIT );

		$response_data = array(
			'query'   => $query,
			'type'    => $type,
			'results' => $top,
		);

		// Include alternative triggers info so AI can inform user about other options
		if ( $alternative_triggers ) {
			$response_data['alternative_triggers'] = $alternative_triggers;
		}

		$message = sprintf(
			"Showing top %d matches for '%s'. Use the get_component_schema tool to inspect the fields of a chosen component before making decisions. Some components support repeater fields which allow configuring multiple dynamic values.",
			count( $top ),
			$query
		);

		return Json_Rpc_Response::create_success_response(
			$message,
			$response_data
		);
	}

	/**
	 * Collect search results from registry services.
	 *
	 * @param string      $query     Search term.
	 * @param string      $type      Component filter ('trigger', 'action', 'condition', 'all').
	 * @param string|null $user_type User type filter for recipe compatibility ('user', 'anonymous', or null).
	 * @return array
	 */
	private function collect_results( string $query, string $type, ?string $user_type = null ): array {
		$items                = array();
		$alternative_triggers = null;

		if ( 'all' === $type || 'trigger' === $type ) {
			$collection = $this->factory->get_trigger_collector()->collect_triggers( $query, $user_type );

			// Collection returns value objects - convert to arrays for ranking.
			$items = array_merge( $items, $collection->to_array() );

			// Capture alternative triggers info for discovery.
			if ( $collection->has_alternative_triggers() ) {
				$alternative_triggers = $collection->get_alternative_triggers()->to_array();
			}
		}

		if ( 'all' === $type || 'action' === $type ) {
			$collection = $this->factory->get_action_collector()->collect_actions( $query );
			$items      = array_merge( $items, $collection->to_array() );
		}

		if ( 'all' === $type || 'condition' === $type ) {
			$collection = $this->factory->get_condition_collector()->collect_conditions( $query );
			$items      = array_merge( $items, $collection->to_array() );
		}

		$result = array(
			'items'  => $items,
			'errors' => array(), // Errors now logged at collector level.
		);

		if ( $alternative_triggers ) {
			$result['alternative_triggers'] = $alternative_triggers;
		}

		return $result;
	}

	/**
	 * List all actions for a specific integration.
	 *
	 * Bypasses semantic search and returns up to INTEGRATION_LIMIT actions
	 * directly from the registry.
	 *
	 * @param string $integration Integration ID (e.g., "WC", "LD").
	 * @return array JSON-RPC response.
	 */
	private function list_actions_by_integration( string $integration ): array {
		$collection = $this->factory->get_action_collector()->collect_actions_by_integration(
			$integration,
			self::INTEGRATION_LIMIT
		);

		if ( $collection->is_empty() ) {
			return Json_Rpc_Response::create_success_response(
				sprintf( "No actions found for integration '%s'", $integration ),
				array(
					'integration' => $integration,
					'type'        => 'action',
					'results'     => array(),
				)
			);
		}

		$items = $collection->to_array();

		return Json_Rpc_Response::create_success_response(
			sprintf( "Found %d actions for '%s'", count( $items ), $integration ),
			array(
				'integration' => $integration,
				'type'        => 'action',
				'results'     => $items,
			)
		);
	}

	/**
	 * List all triggers for a specific integration filtered by user_type.
	 *
	 * Bypasses semantic search and returns up to INTEGRATION_LIMIT triggers
	 * directly from the registry.
	 *
	 * @param string      $integration Integration ID (e.g., "WPFORMS").
	 * @param string|null $user_type   User type filter ('user' or 'anonymous').
	 * @return array JSON-RPC response.
	 */
	private function list_triggers_by_integration( string $integration, ?string $user_type ): array {
		$collection = $this->factory->get_trigger_collector()->collect_triggers_by_integration(
			$integration,
			$user_type,
			self::INTEGRATION_LIMIT
		);

		if ( $collection->is_empty() ) {
			return Json_Rpc_Response::create_success_response(
				sprintf( "No %s triggers found for integration '%s'", $user_type ?? 'any', $integration ),
				array(
					'integration' => $integration,
					'user_type'   => $user_type,
					'type'        => 'trigger',
					'results'     => array(),
				)
			);
		}

		$items = $collection->to_array();

		return Json_Rpc_Response::create_success_response(
			sprintf( "Found %d %s triggers for '%s'", count( $items ), $user_type ?? '', $integration ),
			array(
				'integration' => $integration,
				'user_type'   => $user_type,
				'type'        => 'trigger',
				'results'     => $items,
			)
		);
	}
}

<?php
/**
 * RAG Search Service
 *
 * Handles semantic search operations for automation components using RAG API.
 *
 * @package Uncanny_Automator\Api\Services\Rag
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Rag;

use Uncanny_Automator\Api\Application\Mcp\Mcp_Client;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;
use Uncanny_Automator\Api\Infrastructure\Plan\Plan_Implementation;
use WP_Error;

/**
 * RAG Search Service for semantic search operations.
 *
 * @since 7.0.0
 */
class Rag_Search_Service {

	/**
	 * Call RAG search service for content discovery.
	 *
	 * @param string $query       Search query.
	 * @param string $type        Content type to search for.
	 * @param string $integration Optional integration filter.
	 * @param int    $limit       Maximum results to return.
	 * @param array  $context     Additional context (installed_integrations, user_plan).
	 * @return array|\WP_Error Search results or error.
	 */
	public function search( $query, $type, $integration = null, $limit = 10, array $context = array() ) {

		$request_params = $this->build_request_params( $query, $type, $integration, $limit, $context );
		$response       = $this->make_api_request( $request_params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$validated_data = $this->validate_response( $response );
		if ( is_wp_error( $validated_data ) ) {
			return $validated_data;
		}

		return $this->transform_results( $validated_data );
	}

	/**
	 * Build request parameters for RAG search.
	 *
	 * @param string $query       Search query.
	 * @param string $type        Content type.
	 * @param string $integration Optional integration filter.
	 * @param int    $limit       Result limit.
	 * @param array  $context     Additional context data.
	 * @return array Request parameters.
	 */
	private function build_request_params( $query, $type, $integration, $limit, array $context ) {

		$params = array(
			'query' => $query,
			'type'  => $type,
			'limit' => $limit,
		);

		// Add context data if provided
		if ( ! empty( $context['installed_integrations'] ) ) {
			$params['installed_integrations'] = is_array( $context['installed_integrations'] )
				? implode( ',', $context['installed_integrations'] )
				: $context['installed_integrations'];
		}

		if ( ! empty( $context['user_plan'] ) ) {
			$params['user_plan'] = $context['user_plan'];
		}

		if ( ! empty( $integration ) ) {
			$params['integration'] = $integration;
		}

		// Add user_type filter for recipe compatibility
		if ( ! empty( $context['user_type'] ) ) {
			$params['user_type'] = $context['user_type'];
		}

		return $params;
	}

	/**
	 * Make HTTP request to RAG API.
	 *
	 * @param array $params Request parameters.
	 * @return array|\WP_Error Response data or error.
	 */
	private function make_api_request( array $params ) {

		$rag_url     = Mcp_Client::get_inference_url() . '/api/rag/search';
		$request_url = add_query_arg( $params, $rag_url );


		$response = wp_remote_get(
			$request_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'rag_request_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to connect to the RAG service: %s', 'RAG service error', 'uncanny-automator' ),
					$response->get_error_message()
				)
			);
		}

		return $response;
	}

	/**
	 * Validate RAG API response.
	 *
	 * @param array $response HTTP response.
	 * @return array|\WP_Error Validated data or error.
	 */
	private function validate_response( array $response ) {

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			$body = wp_remote_retrieve_body( $response );
			return new WP_Error(
				'rag_request_failed',
				sprintf(
					/* translators: 1: HTTP status code, 2: Response body (trimmed). */
					esc_html_x( 'RAG service returned status %1$d: %2$s', 'RAG service error', 'uncanny-automator' ),
					$status_code,
					substr( $body, 0, 200 )
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'rag_request_failed',
				sprintf(
					/* translators: %s JSON error message. */
					esc_html_x( 'Invalid JSON response from the RAG service: %s', 'RAG service error', 'uncanny-automator' ),
					json_last_error_msg()
				)
			);
		}

		return $data;
	}

	/**
	 * Transform RAG results to standardized format.
	 *
	 * Only includes fields used by the unified search schema:
	 * - code, sentence, integration_id, integration_name, required_tier, type
	 * - is_incompatible, incompatibility_reason (conditional for triggers)
	 *
	 * @param array $data Response data from RAG service.
	 * @return array Transformed results.
	 */
	private function transform_results( array $data ) {

		$results     = $data['results'] ?? array();
		$transformed = array(
			'results' => array(),
			'count'   => $data['count'] ?? 0,
			'latency' => $data['latency_ms'] ?? 0,
		);

		foreach ( $results as $item ) {
			$result_item = array(
				'type'             => $item['type'] ?? '',
				'code'             => $item['code'] ?? '',
				'sentence'         => $item['sentence'] ?? '',
				'integration_id'   => $item['integration_id'] ?? '',
				'integration_name' => $item['integration_name'] ?? '',
				'required_tier'    => $item['required_tier'] ?? 'lite',
			);

			// Include trigger_type for recipe compatibility filtering
			if ( ! empty( $item['trigger_type'] ) ) {
				$result_item['trigger_type'] = $item['trigger_type'];
			}

			// Include incompatibility info for triggers that don't match recipe type
			if ( ! empty( $item['is_incompatible'] ) ) {
				$result_item['is_incompatible']        = true;
				$result_item['incompatibility_reason'] = $item['incompatibility_reason'] ?? '';
			}

			$transformed['results'][] = $result_item;
		}

		// Include alternative triggers discovery info if available
		if ( ! empty( $data['alternative_triggers'] ) ) {
			$transformed['alternative_triggers'] = array(
				'count'       => $data['alternative_triggers']['count'] ?? 0,
				'recipe_type' => $data['alternative_triggers']['recipe_type'] ?? '',
			);
		}

		return $transformed;
	}

	/**
	 * List components by integration - direct pkl filter, no semantic search.
	 *
	 * Use this for explicit integration listing (e.g., "show all GF actions").
	 * Returns components regardless of installation status.
	 *
	 * @since 7.0.0
	 * @param string      $integration Integration ID (e.g., "GF", "WC", "LD").
	 * @param string|null $type        Component type ('action', 'trigger', 'condition').
	 * @param int         $limit       Maximum results to return.
	 * @return array|\WP_Error Results array or error.
	 */
	public function list_by_integration( string $integration, ?string $type = null, int $limit = 50 ) {
		if ( empty( $integration ) ) {
			return new WP_Error(
				'missing_integration',
				esc_html_x( 'Integration ID is required.', 'RAG service error', 'uncanny-automator' )
			);
		}

		$params = array(
			'integration' => $integration,
			'limit'       => $limit,
		);

		if ( null !== $type ) {
			$params['type'] = $type;
		}

		$rag_url     = Mcp_Client::get_inference_url() . '/api/rag/list';
		$request_url = add_query_arg( $params, $rag_url );

		$response = wp_remote_get(
			$request_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'rag_request_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to connect to the RAG service: %s', 'RAG service error', 'uncanny-automator' ),
					$response->get_error_message()
				)
			);
		}

		$validated_data = $this->validate_response( $response );
		if ( is_wp_error( $validated_data ) ) {
			return $validated_data;
		}

		return array(
			'results' => $validated_data['results'] ?? array(),
			'count'   => $validated_data['count'] ?? 0,
		);
	}

	/**
	 * Check if user has access to component based on tier.
	 *
	 * @param string $required_tier Component's required tier.
	 * @return bool True if user has access.
	 */
	public function user_has_tier_access( $required_tier ) {

		try {
			$plan_service  = new Plan_Service();
			$current_plan  = $plan_service->get_current();
			$required_plan = new Plan_Implementation( $required_tier );

			return $current_plan->is_at_least( $required_plan );

		} catch ( \Exception $e ) {
			// Return false if plan check fails for any reason
			return false;
		}
	}
}

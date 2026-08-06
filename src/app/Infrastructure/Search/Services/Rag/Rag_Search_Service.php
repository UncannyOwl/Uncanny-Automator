<?php
/**
 * RAG Search Service
 *
 * Handles semantic search operations for automation components using RAG API.
 *
 * @package Uncanny_Automator\App\Infrastructure\Search\Services\Rag
 * @since   7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Search\Services\Rag;

use Uncanny_Automator\App\Application\Mcp\Mcp_Client;
use Uncanny_Automator\App\Infrastructure\Plan\Plan_Implementation;
use Uncanny_Automator\App\Plan\Services\License\License_Service;
use Uncanny_Automator\App\Plan\Services\Plan_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Client_Payload_Service;
use WP_Error;

/**
 * RAG Search Service for semantic search operations.
 *
 * @since 7.0.0
 */
class Rag_Search_Service {


	// ── Authenticated request contract ──────────────────────────────────────

	/**
	 * Header carrying the encrypted Automator license identity.
	 */
	private const AUTHENTICATION_HEADER = 'X-Automator-License-Payload';

	/**
	 * Intended recipient of the encrypted credential.
	 */
	private const AUTHENTICATION_AUDIENCE = 'automator-component-rag';

	/**
	 * Encrypted payload generator.
	 *
	 * @var Client_Payload_Service
	 */
	private Client_Payload_Service $payload_service;

	/**
	 * Automator license source.
	 *
	 * @var License_Service
	 */
	private License_Service $license_service;

	/**
	 * UTC epoch provider.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Optional logger callback.
	 *
	 * @var callable|null
	 */
	private $logger;

	/**
	 * Create the authenticated RAG client.
	 *
	 * @param Client_Payload_Service|null $payload_service Encrypted payload generator.
	 * @param License_Service|null        $license_service Automator license source.
	 * @param callable|null               $clock           UTC epoch provider.
	 * @param callable|null               $logger          Logger callback.
	 */
	public function __construct(
		?Client_Payload_Service $payload_service = null,
		?License_Service $license_service = null,
		?callable $clock = null,
		?callable $logger = null
	) {
		$this->payload_service = $payload_service ?? Client_Payload_Service::builder()->build();
		$this->license_service = $license_service ?? new License_Service();
		$this->clock           = $clock ?? 'time';
		$this->logger          = $logger;
	}

	/**
	 * Call RAG search service for content discovery.
	 *
	 * @param  string $query       Search query.
	 * @param  string $type        Content type to search for.
	 * @param  string $integration Optional integration filter.
	 * @param  int    $limit       Maximum results to return.
	 * @param  array  $context     Additional context (installed_integrations, user_plan).
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
	 * @param  string $query       Search query.
	 * @param  string $type        Content type.
	 * @param  string $integration Optional integration filter.
	 * @param  int    $limit       Result limit.
	 * @param  array  $context     Additional context data.
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
	 * @param  array $params Request parameters.
	 * @return array|\WP_Error Response data or error.
	 */
	private function make_api_request( array $params ) {

		$rag_url      = Mcp_Client::get_inference_url() . '/api/rag/search';
		$request_args = $this->build_authenticated_request_args( $params, 15 );

		if ( is_wp_error( $request_args ) ) {
			return $request_args;
		}

		$response = wp_remote_post( $rag_url, $request_args );

		if ( is_wp_error( $response ) ) {
			$this->log_request_failure( 'transport_failed' );

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
	 * @param  array $response HTTP response.
	 * @return array|\WP_Error Validated data or error.
	 */
	private function validate_response( array $response ) {

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			$this->log_request_failure( 'downstream_rejected', $status_code );

			return new WP_Error(
				'rag_request_failed',
				sprintf(
				/* translators: %d HTTP status code. */
					esc_html_x( 'RAG service returned status %d.', 'RAG service error', 'uncanny-automator' ),
					$status_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log_request_failure( 'invalid_response' );

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
	 * @param  array $data Response data from RAG service.
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
	 * @since  7.0.0
	 * @param  string      $integration Integration ID (e.g., "GF", "WC", "LD").
	 * @param  string|null $type        Component type ('action', 'trigger', 'condition').
	 * @param  int         $limit       Maximum results to return.
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

		$rag_url      = Mcp_Client::get_inference_url() . '/api/rag/list';
		$request_args = $this->build_authenticated_request_args( $params, 10 );

		if ( is_wp_error( $request_args ) ) {
			return $request_args;
		}

		$response = wp_remote_post( $rag_url, $request_args );

		if ( is_wp_error( $response ) ) {
			$this->log_request_failure( 'transport_failed' );

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
	 * Build request arguments containing the encrypted license credential.
	 *
	 * Authentication fails closed locally so Automator never calls a protected
	 * RAG endpoint without a credential. The POST and credential contract must
	 * be released with a compatible downstream service and Agent version gate.
	 * Server-side controls remain responsible for request resource limits.
	 *
	 * @param  array<string,mixed> $params  RAG request values.
	 * @param  int                 $timeout Request timeout in seconds.
	 * @return array<string,mixed>|\WP_Error Authenticated request arguments or error.
	 */
	private function build_authenticated_request_args( array $params, int $timeout ) {
		$body = $this->encode_request_body( $params );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$encrypted_license = $this->build_encrypted_license_credential( $body );

		if ( is_wp_error( $encrypted_license ) ) {
			return $encrypted_license;
		}

		return array(
			'timeout' => $timeout,
			'body'    => $body,
			'headers' => array(
				'Accept'                    => 'application/json',
				'Content-Type'              => 'application/json',
				self::AUTHENTICATION_HEADER => $encrypted_license,
			),
		);
	}

	/**
	 * Encode the exact request body that the credential binds to.
	 *
	 * @param  array<string,mixed> $params RAG request values.
	 * @return string|\WP_Error Encoded body or error.
	 */
	private function encode_request_body( array $params ) {
		$body = wp_json_encode( $params );

		if ( false === $body ) {
			$this->log_request_failure( 'body_encoding_failed' );

			return new WP_Error(
				'rag_request_failed',
				esc_html_x( 'Unable to encode the RAG request.', 'RAG service error', 'uncanny-automator' )
			);
		}

		return $body;
	}

	/**
	 * Encrypt a short-lived credential bound to one request body.
	 *
	 * The shared audience is deliberate: search and list expose the same
	 * read-only component metadata under the same authorization and resource
	 * controls. Exact-request reuse within the server's freshness window cannot
	 * alter the bound body or cross into a stronger capability. Revisit route
	 * binding or one-time credentials if these endpoints later diverge in
	 * privilege, data sensitivity, mutation, or material processing cost.
	 *
	 * The envelope version and Agent key version identify the required decryption contract.
	 * Request freshness and identity remain authoritative downstream.
	 *
	 * @param  string $body Exact JSON body sent to the RAG endpoint.
	 * @return string|\WP_Error Encrypted credential or error.
	 */
	private function build_encrypted_license_credential( string $body ) {
		try {
			$claims = $this->build_license_claims( $body );

			if ( is_wp_error( $claims ) ) {
				return $claims;
			}

			$encrypted_license = $this->payload_service->generate_encrypted_package( $claims );
		} catch ( \Throwable $error ) {
			unset( $error );
			$this->log_request_failure( 'credential_generation_failed' );

			return $this->authentication_error();
		}

		if ( '' === $encrypted_license ) {
			$this->log_request_failure( 'credential_unavailable' );

			return $this->authentication_error();
		}

		return $encrypted_license;
	}

	/**
	 * Build the encrypted credential claims for a single request.
	 *
	 * Unix epoch time is UTC by definition and is unaffected by the WordPress
	 * site's configured timezone.
	 *
	 * @param  string $body Exact JSON body sent to the RAG endpoint.
	 * @return array<string,mixed>|\WP_Error Credential claims or error.
	 */
	private function build_license_claims( string $body ) {
		$license_key = sanitize_text_field( $this->license_service->get_license_key() );

		if ( '' === $license_key ) {
			$this->log_request_failure( 'missing_license' );

			return $this->authentication_error();
		}

		return array(
			'license_key' => $license_key,
			'license_id'  => absint( $this->license_service->get_license_id() ),
			'issued_at'   => (int) call_user_func( $this->clock ),
			'audience'    => self::AUTHENTICATION_AUDIENCE,
			'body_sha256' => hash( 'sha256', $body ),
		);
	}

	// ── Secret-safe failure observability ──────────────────────────────────

	/**
	 * Log a bounded reason code without request, credential or response data.
	 *
	 * @param  string   $reason Failure reason code.
	 * @param  int|null $status Optional downstream HTTP status.
	 * @return void
	 */
	private function log_request_failure( string $reason, ?int $status = null ): void {
		$message = 'rag_request_failed | reason=' . sanitize_key( $reason );

		if ( null !== $status ) {
			$message .= ' | status=' . absint( $status );
		}

		if ( is_callable( $this->logger ) ) {
			call_user_func( $this->logger, $message, 'RAG request' );
			return;
		}

		if ( function_exists( 'automator_log' ) ) {
			\automator_log( $message, 'RAG request' );
		}
	}

	/**
	 * Build the stable local authentication failure returned to RAG callers.
	 *
	 * @return \WP_Error
	 */
	private function authentication_error(): WP_Error {
		return new WP_Error(
			'rag_authentication_failed',
			esc_html_x( 'Unable to authenticate the RAG request.', 'RAG service error', 'uncanny-automator' )
		);
	}

	/**
	 * Check if user has access to component based on tier.
	 *
	 * @param  string $required_tier Component's required tier.
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

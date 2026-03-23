<?php
/**
 * MCP tool for listing available loop filter types.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_Registry_Service;

/**
 * Loop Filter List Available Tool.
 *
 * Lists available loop filter types that can be used with loop_filter_add.
 * Retrieves filters from the actual registry (not hardcoded).
 *
 * IMPORTANT: Available filters depend on the loop type (users, posts, token).
 * Pass loop_id to get filters specific to that loop's type.
 *
 * @since 7.0.0
 */
class Loop_Filter_List_Available_Tool extends Abstract_MCP_Tool {

	/**
	 * Loop CRUD service.
	 *
	 * @var Loop_CRUD_Service
	 */
	private Loop_CRUD_Service $loop_service;

	/**
	 * Filter registry service.
	 *
	 * @var Filter_Registry_Service
	 */
	private Filter_Registry_Service $registry_service;

	/**
	 * Constructor.
	 *
	 * @param Loop_CRUD_Service|null       $loop_service     Optional loop service instance.
	 * @param Filter_Registry_Service|null $registry_service Optional registry service instance.
	 */
	public function __construct(
		?Loop_CRUD_Service $loop_service = null,
		?Filter_Registry_Service $registry_service = null
	) {
		$this->loop_service     = $loop_service ?? Loop_CRUD_Service::instance();
		$this->registry_service = $registry_service ?? Filter_Registry_Service::instance();
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'loop_list_available_filters';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'List available loop filter types from the registry. Call this BEFORE using loop_filter_add to discover what filters can be added. Pass loop_id to get filters specific to that loop\'s type, or loop_type to filter by type directly.';
	}

	/**
	 * Define the input schema.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'loop_id'   => array(
					'type'        => 'integer',
					'description' => 'Loop ID to get available filters for. The tool will determine the loop type and return relevant filters.',
					'minimum'     => 1,
				),
				'loop_type' => array(
					'type'        => 'string',
					'description' => 'Loop type to filter by. Use this if you know the type but don\'t have a loop_id yet. Values: "users", "posts", "token".',
					'enum'        => array( 'users', 'posts', 'token' ),
				),
			),
			'required'   => array(),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters.
	 * @return array MCP response.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$loop_id   = isset( $params['loop_id'] ) ? (int) $params['loop_id'] : null;
		$loop_type = $params['loop_type'] ?? null;

		// If loop_id provided, get the loop type from it.
		if ( null !== $loop_id ) {
			$loop_result = $this->loop_service->get_loop( $loop_id );

			if ( is_wp_error( $loop_result ) ) {
				return Json_Rpc_Response::create_error_response(
					$loop_result->get_error_message() . ' Use loop_list with recipe_id to find available loops.'
				);
			}

			$loop      = $loop_result['loop'] ?? array();
			$loop_type = $loop['iterable_expression']['type'] ?? $loop['type'] ?? null;
		}

		// Get filters based on type.
		$options = array( 'include_schema' => true );
		$filters = $this->get_filters_for_type( $loop_type, $options );

		// Format response.
		$formatted_filters = $this->format_filters_for_response( $filters );

		$message = sprintf(
			'Found %d available filter type(s)%s',
			count( $formatted_filters ),
			$loop_type ? " for '{$loop_type}' loops" : ''
		);

		$response_data = array(
			'filter_count' => count( $formatted_filters ),
			'filters'      => $formatted_filters,
			'usage'        => 'Use loop_filter_add(loop_id=X, filter_code="CODE", integration_code="INTEGRATION", fields={...})',
		);

		// Include loop context if loop_id was provided.
		if ( null !== $loop_id ) {
			$response_data['loop_id']   = $loop_id;
			$response_data['loop_type'] = $loop_type;
		}

		return Json_Rpc_Response::create_success_response( $message, $response_data );
	}

	/**
	 * Get filters for a specific loop type or all filters.
	 *
	 * @param string|null $loop_type Loop type (users, posts, token) or null for all.
	 * @param array       $options   Format options.
	 * @return array Filters from registry.
	 */
	private function get_filters_for_type( ?string $loop_type, array $options ): array {
		if ( null === $loop_type ) {
			return $this->registry_service->get_all_filters( $options );
		}

		switch ( $loop_type ) {
			case 'users':
				return $this->registry_service->get_user_filters( $options );
			case 'posts':
				return $this->registry_service->get_post_filters( $options );
			case 'token':
				return $this->registry_service->get_token_filters( $options );
			default:
				return $this->registry_service->get_all_filters( $options );
		}
	}

	/**
	 * Format filters for API response.
	 *
	 * @param array $filters Raw filters from registry.
	 * @return array Formatted filters.
	 */
	private function format_filters_for_response( array $filters ): array {
		$formatted = array();

		foreach ( $filters as $code => $filter ) {
			$filter_code = $filter['code'] ?? $code;

			$formatted[] = array(
				'code'             => $filter_code,
				'integration_code' => $filter['integration_code'] ?? $filter['integration'] ?? '',
				'sentence'         => $filter['sentence_readable'] ?? $filter['sentence'] ?? $filter_code,
				'iteration_types'  => $filter['iteration_types'] ?? array(),
				'is_pro'           => true, // Loop filters are always Pro features.
				'fields'           => $this->format_fields( $filter ),
			);
		}

		return $formatted;
	}

	/**
	 * Format filter fields for response.
	 *
	 * @param array $filter Filter definition.
	 * @return array Formatted fields.
	 */
	private function format_fields( array $filter ): array {
		// Try to get fields from inputSchema (if include_schema was true).
		if ( isset( $filter['inputSchema']['properties'] ) ) {
			$fields = array();
			foreach ( $filter['inputSchema']['properties'] as $field_code => $field_schema ) {
				$fields[ $field_code ] = array(
					'type'        => $field_schema['type'] ?? 'string',
					'description' => $field_schema['description'] ?? '',
					'required'    => in_array( $field_code, $filter['inputSchema']['required'] ?? array(), true ),
				);

				if ( isset( $field_schema['enum'] ) ) {
					$fields[ $field_code ]['options'] = $field_schema['enum'];
				}
			}
			return $fields;
		}

		// Fall back to meta_structure.
		$meta_structure = $filter['meta_structure'] ?? array();
		$fields         = array();

		foreach ( $meta_structure as $field_code => $field_config ) {
			$fields[ $field_code ] = array(
				'type'        => $field_config['type'] ?? 'text',
				'description' => $field_config['description'] ?? $field_config['label'] ?? '',
				'required'    => ! empty( $field_config['required'] ),
			);

			if ( ! empty( $field_config['options'] ) ) {
				$fields[ $field_code ]['options'] = array_keys( $field_config['options'] );
			}
		}

		return $fields;
	}
}

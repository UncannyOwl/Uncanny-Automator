<?php
/**
 * Action Registry Service
 *
 * Core business logic service for action discovery and registry operations.
 * Single source of truth for action search, listing, and schema operations.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Action\Services;

use Uncanny_Automator\Api\Components\Action\Registry\WP_Action_Registry;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Code;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;
use Uncanny_Automator\Services\Integrations\Fields;
use Uncanny_Automator\Api\Services\Availability\Availability_Data;
use Uncanny_Automator\Api\Services\Availability\Availability_Checker;
use Uncanny_Automator\Api\Components\Field\Field_Json_Schema;
use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;
use WP_Error;

/**
 * Action Registry Service Class
 *
 * Handles all action discovery operations with clean OOP architecture.
 */
class Action_Registry_Service {

	/**
	 * Service instance (singleton pattern).
	 *
	 * @var Action_Registry_Service|null
	 */
	private static $instance = null;

	/**
	 * Action registry instance.
	 *
	 * @var WP_Action_Registry
	 */
	private $action_registry;

	/**
	 * Integration registry service.
	 *
	 * @var Integration_Registry_Service
	 */
	private $integration_registry;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 */
	private function __construct() {
		$this->action_registry      = new WP_Action_Registry();
		$this->integration_registry = Integration_Registry_Service::get_instance();
	}

	/**
	 * Get installed integration IDs.
	 *
	 * @since 7.0.0
	 *
	 * @return array List of integration IDs (e.g., ['WP', 'WC', 'EMAILS']).
	 */
	private function get_installed_integration_ids() {
		// Use Automator's active integrations list.
		$integration_codes = \Uncanny_Automator\Set_Up_Automator::$active_integrations_code;

		return $integration_codes;
	}


	/**
	 * Call RAG search API for semantic action discovery.
	 *
	 * @since 7.0.0
	 *
	 * @param string $query      Search query.
	 * @param string $type       Type filter ('trigger' or 'action').
	 * @param string $integration Optional integration filter.
	 * @param int    $limit      Result limit.
	 * @return array|\WP_Error RAG response or error.
	 */
	private function call_rag_search( $query, $type, $integration = null, $limit = 10 ) {

		$plan_service = new Plan_Service();

		$context = array(
			'installed_integrations' => $this->get_installed_integration_ids(),
			'user_plan'              => $plan_service->get_current_plan_id(),
		);

		$rag_service = new \Uncanny_Automator\Api\Services\Rag\Rag_Search_Service();

		return $rag_service->search( $query, $type, $integration, $limit, $context );
	}

	/**
	 * Get service instance (singleton).
	 *
	 * @since 7.0.0
	 * @return Action_Registry_Service
	 */
	public static function instance(): Action_Registry_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get all available actions across all integrations.
	 *
	 * @since 7.0.0
	 * @param string $integration Optional. Filter by integration code.
	 * @param bool   $include_schema Optional. Include schema definitions. Default false.
	 * @return array|\WP_Error Array of action definitions or error.
	 */
	public function get_available_actions( string $integration = '', bool $include_schema = false ) {
		try {
			$options = array( 'include_schema' => $include_schema );
			$actions = $this->action_registry->get_available_actions( $options );

			// Filter by integration if specified
			if ( ! empty( $integration ) ) {
				$actions = array_filter(
					$actions,
					function ( $action ) use ( $integration ) {
						return $action['integration'] === $integration;
					}
				);
			}

			return array_values( $actions ); // Re-index array

		} catch ( \Exception $e ) {
			return new WP_Error(
				'action_registry_error',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to load actions: %s', 'Action registry error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Search for actions using RAG semantic search.
	 *
	 * @since 7.0.0
	 * @param string $query Search query.
	 * @param string $integration Optional. Filter by integration code.
	 * @param int    $limit Optional. Maximum results to return. Default 10, max 50.
	 * @return array|\WP_Error Array of matching actions or error.
	 */
	public function find_actions( string $query, string $integration = '', int $limit = 10 ) {
		if ( empty( $query ) ) {
			return new WP_Error(
				'missing_query',
				esc_html_x( 'Search query is required.', 'Action registry error', 'uncanny-automator' )
			);
		}

		$limit = intval( $limit );

		try {
			// Call RAG search API with context awareness.
			$rag_response = $this->call_rag_search( $query, 'action', $integration, $limit );

			if ( is_wp_error( $rag_response ) ) {
				return array();
			}

			// Extract results from RAG response.
			$rag_results = $rag_response['results'] ?? array();

			if ( empty( $rag_results ) ) {
				return array();
			}

			// Return RAG results untouched
			return $rag_results;

		} catch ( \Exception $e ) {
			return array();
		}
	}


	/**
	 * Get detailed action definition by action code.
	 *
	 * @since 7.0.0
	 * @param string $action_code Action code.
	 * @param bool   $include_schema Optional. Include configuration schema. Default true.
	 * @return array|\WP_Error Action definition with schema or error.
	 */
	public function get_action_definition( string $action_code, bool $include_schema = true ) {
		if ( empty( $action_code ) ) {
			return new WP_Error(
				'missing_action_code',
				esc_html_x( 'Action code is required.', 'Action registry error', 'uncanny-automator' )
			);
		}

		try {
			$action_code_vo    = new Action_Code( $action_code );
			$action_definition = $this->action_registry->get_action_definition( $action_code_vo );

			if ( null === $action_definition ) {
				return new WP_Error(
					'action_not_found',
					sprintf(
						/* translators: %s Action code. */
						esc_html_x( "Action '%s' not found in registry. Use the explorer tool to discover available actions.", 'Action registry error', 'uncanny-automator' ),
						$action_code
					)
				);
			}

			if ( ! $include_schema ) {
				return $action_definition;
			}

			// Get configuration schema
			$fields = new Fields();
			$fields->set_config(
				array(
					'object_type' => 'actions',
					'code'        => $action_code,
				)
			);
			$configuration_fields = $fields->get();

			// Convert to JSON Schema format.
			$converter    = new Field_Json_Schema();
			$input_schema = $converter->convert_fields_to_schema( $configuration_fields );

			return array(
				'name'        => $action_code,
				'description' => $action_definition['sentence_readable'] ?? $action_definition['sentence'] ?? '',
				'inputSchema' => $input_schema,
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'action_definition_error',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to load action definition: %s', 'Action registry error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Check if an action exists in the registry.
	 *
	 * @since 7.0.0
	 * @param string $action_code Action code to check.
	 * @return bool True if action exists, false otherwise.
	 */
	public function action_exists( string $action_code ): bool {
		$definition = $this->get_action_definition( $action_code, false );
		return ! is_wp_error( $definition );
	}

	/**
	 * Get all actions for a specific integration.
	 *
	 * @since 7.0.0
	 * @param string $integration Integration code (e.g., "WC", "LD").
	 * @return array|\WP_Error Array with 'actions' key or error.
	 */
	public function get_actions_by_integration( string $integration ) {
		if ( empty( $integration ) ) {
			return new WP_Error(
				'missing_integration',
				esc_html_x( 'Integration code is required.', 'Action registry error', 'uncanny-automator' )
			);
		}

		$all_actions = $this->get_available_actions( $integration );

		if ( is_wp_error( $all_actions ) ) {
			return $all_actions;
		}

		return array( 'actions' => $all_actions );
	}

	/**
	 * Check action integration availability.
	 *
	 * @since 7.0.0
	 * @param array $action_data {
	 *     Action data to check availability for.
	 *
	 *     @type string $integration_id Integration code.
	 *     @type string $code           Action code.
	 *     @type string $required_tier  Required tier for this action.
	 * }
	 * @return array {
	 *     Availability information.
	 *
	 *     @type bool   $available  Whether the feature is available for use.
	 *     @type string $message    Human-readable availability message.
	 *     @type array  $blockers   Array of blocking issues (empty if available).
	 * }
	 */
	public function check_action_integration_availability( array $action_data ): array {
		$integration_code     = $action_data['integration_id'] ?? '';
		$action_code          = $action_data['code'] ?? '';
		$action_required_tier = $action_data['required_tier'] ?? '';

		$integration = $this->integration_registry->get_integration( $integration_code );
		$action      = $this->action_registry->get_action_definition( new Action_Code( $action_code ), array() );

		$is_integration_registered = ! empty( $integration );
		$is_action_registered      = null !== $action;
		$is_app                    = ! empty( $integration['settings_url'] );
		$is_connected              = ! empty( $integration['connected'] );

		$plan         = new Plan_Service();
		$user_tier_id = $plan->get_current_plan_id();

		$availability_data = Availability_Data::builder()
			->integration( $integration_code )
			->code( $action_code )
			->type( 'action' )
			->integration_registered( $is_integration_registered )
			->feature_registered( $is_action_registered )
			->app( $is_app )
			->connected( $is_connected )
			->user_tier( $user_tier_id )
			->requires_tier( $action_required_tier )
			->settings_url( $integration['settings_url'] ?? '' )
			->build();

		$checker = new Availability_Checker();
		$message = $checker->check( $availability_data );

		return array(
			'available'    => $checker->is_available( $availability_data ),
			'message'      => $message,
			'blockers'     => $checker->get_blockers( $availability_data ),
			'feature_data' => $availability_data->to_array(), // Added for debugging/context
		);
	}
}

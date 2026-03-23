<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Trigger\Services;

use Uncanny_Automator\Api\Components\Plan\Domain\Feature_Type;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Code;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_User_Type;
use Uncanny_Automator\Api\Components\Trigger\Registry\WP_Trigger_Registry;
use Uncanny_Automator\Api\Services\Availability\Availability_Checker;
use Uncanny_Automator\Api\Services\Availability\Availability_Data;
use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;
use Uncanny_Automator\Api\Services\Traits\Service_Response_Formatter;
use Uncanny_Automator\Api\Services\Trigger\Utilities\Trigger_Schema_Converter;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;
use Uncanny_Automator\Services\Integrations\Fields;
use WP_Error;

/**
 * Trigger Registry Service - Core Business Logic.
 *
 * Centralized service for trigger discovery and registry operations.
 * Single source of truth for trigger registry business logic that both
 * MCP tools and functions consume.
 *
 * @since 7.0.0
 */
class Trigger_Registry_Service {

	use Service_Response_Formatter;

	/**
	 * Singleton instance.
	 *
	 * @var Trigger_Registry_Service|null
	 */
	private static $instance = null;

	/**
	 * Trigger registry.
	 *
	 * @var WP_Trigger_Registry
	 */
	private $trigger_registry;

	/**
	 * Integration registry.
	 *
	 * @var Integration_Registry_Service
	 */
	private $integration_registry;

	/**
	 * Schema converter.
	 *
	 * @var Trigger_Schema_Converter
	 */
	private $schema_converter;

	/**
	 * Constructor.
	 *
	 * @param WP_Trigger_Registry|null          $trigger_registry Trigger registry implementation.
	 * @param Integration_Registry_Service|null $integration_registry Integration registry implementation.
	 * @param Trigger_Schema_Converter|null     $schema_converter Schema converter implementation.
	 */
	public function __construct( $trigger_registry = null, $integration_registry = null, $schema_converter = null ) {
		$this->trigger_registry = $trigger_registry ?? new WP_Trigger_Registry();

		// Only create Integration_Registry_Service if we have WordPress loaded
		if ( null !== $integration_registry ) {
			$this->integration_registry = $integration_registry;
		} elseif ( function_exists( 'Automator' ) ) {
			$this->integration_registry = Integration_Registry_Service::get_instance();
		}

		$this->schema_converter = $schema_converter ?? new Trigger_Schema_Converter();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Trigger_Registry_Service Service instance.
	 */
	public static function get_instance(): Trigger_Registry_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
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
		// This is populated during WordPress init and contains all available integrations.
		if ( ! class_exists( '\Uncanny_Automator\Set_Up_Automator' ) ||
			! isset( \Uncanny_Automator\Set_Up_Automator::$active_integrations_code ) ||
			! is_array( \Uncanny_Automator\Set_Up_Automator::$active_integrations_code ) ) {
			return array();
		}

		$active_codes = \Uncanny_Automator\Set_Up_Automator::$active_integrations_code;

		// Add built-in Pro integrations that don't require external plugins.
		// These are always available when Automator Pro is active.
		if ( defined( 'AUTOMATOR_PRO_FILE' ) ) {
			// Default built-in integrations that don't depend on external plugins:
			// - WEBHOOKS: incoming/outgoing webhooks
			// - URL: URL parameter detection triggers
			// Pro can filter this to add more (IFTTT, ZAPIER, INTEGRATELY, etc.).
			$builtin_pro = array( 'WEBHOOKS', 'URL' );

			/**
			 * Filter the list of built-in Pro integrations.
			 *
			 * Allows Pro to add additional built-in integrations like IFTTT, ZAPIER, etc.
			 *
			 * @since 7.0.0
			 *
			 * @param array $builtin_pro Default: ['WEBHOOKS'].
			 */
			$builtin_pro = apply_filters( 'automator_builtin_pro_integrations', $builtin_pro );

			foreach ( $builtin_pro as $code ) {
				if ( ! in_array( $code, $active_codes, true ) ) {
					$active_codes[] = $code;
				}
			}
		}

		return $active_codes;
	}


	/**
	 * Get trigger by code.
	 *
	 * @param string $trigger_code Trigger code.
	 * @return array|\WP_Error Trigger on success, WP_Error on failure.
	 */
	public function get_trigger_by_code( string $trigger_code ) {
		return Automator()->get_trigger( $trigger_code );
	}

	/**
	 * Call RAG search service for trigger discovery.
	 *
	 * @param string      $query       Search query.
	 * @param string      $type        Content type to search for.
	 * @param string|null $integration Optional integration filter.
	 * @param int         $limit       Maximum results to return.
	 * @param string|null $user_type   Optional user type filter ('user' or 'anonymous').
	 * @return array|\WP_Error Search results or error.
	 */
	public function call_rag_search( $query, $type, $integration = null, $limit = 3, $user_type = null ) {

		$plan_service = new Plan_Service();

		$context = array(
			'installed_integrations' => $this->get_installed_integration_ids(),
			'user_plan'              => $plan_service->get_current_plan_id(),
		);

		// Add user_type filter if provided.
		if ( $user_type ) {
			$context['user_type'] = $user_type;
		}

		$rag_service = new \Uncanny_Automator\Api\Services\Rag\Rag_Search_Service();

		return $rag_service->search( $query, $type, $integration, $limit, $context );
	}


	/**
	 * List all available triggers.
	 *
	 * @param array $filters Optional filters (type, integration, search).
	 * @param bool  $include_schema Whether to include MCP schema.
	 * @return array|\WP_Error List of triggers or error.
	 */
	public function list_triggers( array $filters = array(), bool $include_schema = false ) {

		try {
			// Get all triggers with schema if requested
			$options      = array( 'include_schema' => $include_schema );
			$all_triggers = $this->trigger_registry->get_available_triggers( $options );

			// Apply filters
			$filtered_triggers = $this->apply_filters( $all_triggers, $filters );

			// Apply limit if specified
			$limit = $filters['limit'] ?? 3;
			if ( $limit > 0 && count( $filtered_triggers ) > $limit ) {
				$filtered_triggers = array_slice( $filtered_triggers, 0, $limit, true );
			}

			return array(
				'success'  => true,
				'triggers' => $filtered_triggers,
				'count'    => count( $filtered_triggers ),
				'filters'  => $filters,
			);

		} catch ( \Throwable $e ) {
			return $this->error_response( 'trigger_list_failed', 'Failed to list triggers: ' . $e->getMessage() );
		}
	}

	/**
	 * Find triggers by search query using RAG semantic search.
	 *
	 * @param string $query Search query.
	 * @param array  $filters Additional filters.
	 * @param int    $limit Result limit.
	 * @return array|\WP_Error Search results or error.
	 */
	public function find_triggers( string $query, array $filters = array(), int $limit = 3 ) {

		try {
			// Extract integration filter if present.
			$integration_filter = $filters['integration'] ?? null;

			// Extract user_type filter for recipe compatibility.
			$user_type = $filters['user_type'] ?? null;

			// Call RAG search API with context awareness.
			$rag_response = $this->call_rag_search( $query, 'trigger', $integration_filter, $limit, $user_type );

			if ( is_wp_error( $rag_response ) ) {
				// Fallback to keyword search if RAG fails.
				return $this->find_triggers_fallback( $query, $filters, $limit );
			}

			// Extract results from RAG response.
			$rag_results = $rag_response['results'] ?? array();

			if ( empty( $rag_results ) ) {
				$empty_result = array(
					'success'  => true,
					'query'    => $query,
					'triggers' => array(),
					'count'    => 0,
					'limit'    => $limit,
					'source'   => 'rag',
				);

				// Include alternative triggers info even for empty results
				if ( ! empty( $rag_response['alternative_triggers'] ) ) {
					$empty_result['alternative_triggers'] = $rag_response['alternative_triggers'];
				}

				return $empty_result;
			}

			// Return RAG results untouched
			$result = array(
				'success'  => true,
				'query'    => $query,
				'triggers' => $rag_results,
				'count'    => count( $rag_results ),
				'limit'    => $limit,
				'source'   => 'rag',
				'total'    => count( $rag_results ),
			);

			// Include alternative triggers info for discovery
			if ( ! empty( $rag_response['alternative_triggers'] ) ) {
				$result['alternative_triggers'] = $rag_response['alternative_triggers'];
			}

			return $result;

		} catch ( \Throwable $e ) {
			// RAG search failed, falling back to keyword-based search
			return $this->find_triggers_fallback( $query, $filters, $limit );
		}
	}

	/**
	 * Fallback to keyword-based search if RAG fails.
	 *
	 * @param string $query Search query.
	 * @param array  $filters Additional filters.
	 * @param int    $limit Result limit.
	 * @return array|\WP_Error Search results or error.
	 */
	private function find_triggers_fallback( string $query, array $filters = array(), int $limit = 10 ) {
		try {
			// Get all triggers.
			$all_triggers = $this->trigger_registry->get_available_triggers();

			// Apply any additional filters first.
			$filtered_triggers = $this->apply_filters( $all_triggers, $filters );

			// Convert triggers to searchable format.
			$trigger_arrays = array();
			foreach ( $filtered_triggers as $code => $trigger ) {
				if ( is_array( $trigger ) ) {
					$trigger_arrays[ $code ] = $trigger;
				}
			}

			// Search triggers with similarity (old keyword-based method).
			$results = $this->search_triggers_with_similarity( $query, $trigger_arrays, $limit );

			return array(
				'success'  => true,
				'query'    => $query,
				'triggers' => $results,
				'count'    => count( $results ),
				'limit'    => $limit,
				'source'   => 'fallback',
			);

		} catch ( \Throwable $e ) {
			return $this->error_response( 'trigger_search_failed', 'Failed to search triggers: ' . $e->getMessage() );
		}
	}

	/**
	 * Get specific trigger definition.
	 *
	 * @param string $trigger_code Trigger code.
	 * @param bool   $include_schema Whether to include MCP schema.
	 * @return array|\WP_Error Trigger definition or error.
	 */
	public function get_trigger_definition( string $trigger_code, bool $include_schema = true ) {

		if ( empty( $trigger_code ) ) {
			return $this->error_response( 'trigger_missing_code', 'Trigger code is required' );
		}

		try {

			$trigger_code_vo = new Trigger_Code( $trigger_code );
			$definition      = $this->trigger_registry->get_trigger_definition( $trigger_code_vo );

			if ( null === $definition ) {
				return $this->error_response(
					'trigger_not_found',
					"Trigger '{$trigger_code}' not found in registry."
					. ' Use the search_components tool to discover available triggers.'
				);
			}

			if ( ! $include_schema ) {
				return $definition;
			}

			// Get configuration schema using Fields class like actions do
			$fields = new Fields();
			$fields->set_config(
				array(
					'object_type' => 'triggers',
					'code'        => $trigger_code,
				)
			);

			$configuration_fields = $fields->get();

			// Convert to JSON Schema format using schema converter
			$input_schema = $this->schema_converter->convert_fields_to_schema( $configuration_fields );

			$trigger_definition = array(
				'name'        => $trigger_code,
				'description' => $definition['sentence_human_readable'] ?? $definition['sentence'] ?? '',
				'inputSchema' => $input_schema,
			);

			return array(
				'success' => true,
				'trigger' => $trigger_definition,
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'trigger_definition_failed', $e->getMessage() );
		}
	}

	/**
	 * Get triggers by type (user/anonymous).
	 *
	 * @param string $type Trigger type.
	 * @param bool   $include_schema Whether to include MCP schema.
	 * @return array|\WP_Error Triggers by type or error.
	 */
	public function get_triggers_by_type( string $type, bool $include_schema = false ) {
		try {
			$trigger_type = new Trigger_User_Type( $type );
			$options      = array( 'include_schema' => $include_schema );
			$triggers     = $this->trigger_registry->get_triggers_by_type( $trigger_type, $options );

			return array(
				'success'  => true,
				'type'     => $type,
				'triggers' => $triggers,
				'count'    => count( $triggers ),
			);

		} catch ( \Throwable $e ) {
			return $this->error_response( 'trigger_by_type_failed', 'Failed to get triggers by type: ' . $e->getMessage() );
		}
	}

	/**
	 * Get triggers by integration.
	 *
	 * @param string $integration Integration name.
	 * @param bool   $include_schema Whether to include MCP schema.
	 * @return array|\WP_Error Triggers by integration or error.
	 */
	public function get_triggers_by_integration( string $integration, bool $include_schema = false ) {
		try {
			$triggers = $this->trigger_registry->get_triggers_by_integration( $integration );

			// Add schema if requested
			if ( $include_schema ) {
				$options = array( 'include_schema' => true );
				foreach ( $triggers as $code => $existing_definition ) {
					$trigger_code_vo        = new Trigger_Code( $code );
					$definition_with_schema = $this->trigger_registry->get_trigger_definition( $trigger_code_vo, $options );
					if ( $definition_with_schema ) {
						$triggers[ $code ] = $this->format_trigger_for_mcp( $definition_with_schema, $code );
					}
				}
			}

			return array(
				'success'     => true,
				'integration' => $integration,
				'triggers'    => $triggers,
				'count'       => count( $triggers ),
			);

		} catch ( \Throwable $e ) {
			return $this->error_response( 'trigger_by_integration_failed', 'Failed to get triggers by integration: ' . $e->getMessage() );
		}
	}

	/**
	 * Check if trigger exists.
	 *
	 * @param string $trigger_code Trigger code.
	 * @return array|\WP_Error Check result or error.
	 */
	public function trigger_exists( string $trigger_code ) {
		try {
			$trigger_code_vo = new Trigger_Code( $trigger_code );
			$exists          = $this->trigger_registry->is_registered( $trigger_code_vo );

			return array(
				'success'      => true,
				'trigger_code' => $trigger_code,
				'exists'       => $exists,
			);

		} catch ( \Throwable $e ) {
			return $this->error_response( 'trigger_exists_check_failed', 'Failed to check if trigger exists: ' . $e->getMessage() );
		}
	}

	/**
	 * Check integration availability for trigger usage.
	 *
	 * Centralized availability check for integrations - determines if triggers
	 * from a specific integration can be used based on plugin installation,
	 * app connection status, and tier requirements.
	 *
	 * @since 7.0.0
	 * @param array $trigger_data {
	 *     Trigger data to check availability for.
	 *
	 *     @type string $integration_id Integration code.
	 *     @type string $code           Trigger code.
	 *     @type string $required_tier  Required tier for this trigger.
	 * }
	 * @return array {
	 *     Availability information.
	 *
	 *     @type bool   $available  Whether the feature is available for use.
	 *     @type string $message    Human-readable availability message.
	 *     @type array  $blockers   Array of blocking issues (empty if available).
	 * }
	 */
	/**
	 * Check trigger integration availability.
	 *
	 * @param array $trigger_data The data.
	 * @return array
	 */
	public function check_trigger_integration_availability( array $trigger_data ): array {

		$integration_code      = $trigger_data['integration_id'] ?? '';
		$trigger_code          = $trigger_data['code'] ?? '';
		$trigger_required_tier = $trigger_data['required_tier'] ?? '';

		// Get the integration from the registry.
		$integration = $this->integration_registry->get_integration( $integration_code );
		$trigger     = $this->trigger_registry->get_trigger( new Trigger_Code( $trigger_code ) );

		// Determines whether the integration is registered in the registry.
		$is_integration_registered = ! empty( $integration );
		// Determines whether the trigger is registered in the registry.
		$is_trigger_registered = ! empty( $trigger );

		// Determines whether the integration is an app.
		$is_app = ! empty( $integration['settings_url'] );

		$is_connected = ! empty( $integration['connected'] );

		$plan         = new Plan_Service();
		$user_tier_id = $plan->get_current_plan_id();

		// Prepare availability data using builder pattern.
		$availability_data = Availability_Data::builder()
			->integration( $integration_code )
			->code( $trigger_code )
			->type( 'trigger' )
			->integration_registered( $is_integration_registered )
			->feature_registered( $is_trigger_registered )
			->app( $is_app )
			->connected( $is_connected )
			->user_tier( $user_tier_id )
			->requires_tier( $trigger_required_tier )
			->settings_url( $integration['settings_url'] ?? '' )
			->build();

		// Use availability checker to get the message.
		$checker = new Availability_Checker();
		$message = $checker->check( $availability_data );

		return array(
			'available' => $checker->is_available( $availability_data ),
			'message'   => $message,
			'blockers'  => $checker->get_blockers( $availability_data ),
		);
	}

	/**
	 * Validate trigger configuration.
	 *
	 * @param string $trigger_code Trigger code.
	 * @param array  $config Configuration to validate.
	 * @return array|\WP_Error Validation result or error.
	 */
	public function validate_trigger_configuration( string $trigger_code, array $config ) {
		try {
			// Check if trigger exists
			$trigger_code_vo = new Trigger_Code( $trigger_code );
			if ( ! $this->trigger_registry->is_registered( $trigger_code_vo ) ) {
				return $this->error_response(
					'trigger_not_found',
					'Trigger not found: '
					. $trigger_code
					. 'use the search_components tool to discover available triggers.'
				);
			}

			// Get trigger definition with schema
			$options    = array( 'include_schema' => true );
			$definition = $this->trigger_registry->get_trigger_definition( $trigger_code_vo, $options );

			if ( ! $definition ) {
				return $this->error_response(
					'trigger_definition_not_found',
					'Trigger definition not found: '
					. $trigger_code
					. 'use the search_components tool to discover available triggers.'
				);
			}

			// Validate configuration against schema
			$validation_errors = $this->validate_config_against_schema( $config, $definition );

			return array(
				'success' => true,
				'valid'   => empty( $validation_errors ),
				'errors'  => $validation_errors,
				'trigger' => $trigger_code,
			);

		} catch ( \Throwable $e ) {
			return $this->error_response( 'trigger_validation_failed', 'Failed to validate trigger configuration: ' . $e->getMessage() );
		}
	}

	/**
	 * Apply filters to triggers array.
	 *
	 * @param array $triggers Triggers array.
	 * @param array $filters Filters to apply.
	 * @return array Filtered triggers.
	 */
	private function apply_filters( array $triggers, array $filters ): array {
		$filtered = $triggers;

		// Filter by type
		if ( ! empty( $filters['type'] ) ) {
			$filtered = array_filter(
				$filtered,
				function ( $trigger ) use ( $filters ) {
					return ( $trigger['trigger_type'] ?? 'user' ) === $filters['type'];
				}
			);
		}

		// Filter by integration
		if ( ! empty( $filters['integration'] ) ) {
			$filtered = array_filter(
				$filtered,
				function ( $trigger ) use ( $filters ) {
					return ( $trigger['integration'] ?? '' ) === $filters['integration'];
				}
			);
		}

		// Filter by search term
		if ( ! empty( $filters['search'] ) ) {
			$search_term = strtolower( $filters['search'] );
			$filtered    = array_filter(
				$filtered,
				function ( $trigger ) use ( $search_term ) {
					$searchable_text = strtolower(
						( $trigger['trigger_code'] ?? '' ) . ' ' .
						( $trigger['sentence'] ?? '' ) . ' ' .
						( $trigger['sentence_human_readable'] ?? '' ) . ' ' .
						( $trigger['integration'] ?? '' )
					);
					return strpos( $searchable_text, $search_term ) !== false;
				}
			);
		}

		return $filtered;
	}

	/**
	 * Search triggers with similarity ranking.
	 *
	 * @param string $query Search query.
	 * @param array  $triggers Triggers to search.
	 * @param int    $limit Result limit.
	 * @return array Ranked search results.
	 */
	private function search_triggers_with_similarity( string $query, array $triggers, int $limit ): array {
		$query_lower = strtolower( trim( $query ) );
		$results     = array();

		foreach ( $triggers as $code => $trigger ) {
			$score = $this->calculate_trigger_similarity( $query_lower, $trigger );
			if ( $score > 0 ) {
				$results[] = array(
					'trigger' => $trigger,
					'score'   => $score,
					'code'    => $code,
				);
			}
		}

		// Sort by score (highest first)
		usort(
			$results,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		// Limit results and extract triggers
		$limited_results = array_slice( $results, 0, $limit );
		return array_map(
			function ( $result ) {
				return $result['trigger'];
			},
			$limited_results
		);
	}

	/**
	 * Calculate similarity score between query and trigger.
	 *
	 * @param string $query Search query (lowercase).
	 * @param array  $trigger Trigger data.
	 * @return float Similarity score (0-100).
	 */
	private function calculate_trigger_similarity( string $query, array $trigger ): float {
		$score = 0;

		// Exact match in trigger code (highest priority)
		$trigger_code = strtolower( $trigger['trigger_code'] ?? '' );
		if ( strpos( $trigger_code, $query ) !== false ) {
			$score += 50;
			if ( $trigger_code === $query ) {
				$score += 30; // Exact match bonus
			}
		}

		// Match in sentences
		$sentence          = strtolower( $trigger['sentence'] ?? '' );
		$readable_sentence = strtolower( $trigger['sentence_human_readable'] ?? '' );

		if ( strpos( $sentence, $query ) !== false ) {
			$score += 20;
		}
		if ( strpos( $readable_sentence, $query ) !== false ) {
			$score += 25; // Human readable is more important
		}

		// Match in integration with space/underscore normalization
		$integration = strtolower( $trigger['integration'] ?? '' );
		if ( strpos( $integration, $query ) !== false ) {
			$score += 15;
		}
		// Also try with spaces replaced with underscores
		$query_with_underscores = str_replace( ' ', '_', $query );
		if ( $query_with_underscores !== $query && strpos( $integration, $query_with_underscores ) !== false ) {
			$score += 15;
		}

		// Word boundary matches (more precise)
		$query_words = explode( ' ', $query );
		foreach ( $query_words as $word ) {
			if ( strlen( $word ) > 2 ) { // Skip very short words
				if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/i', $sentence ) ) {
					$score += 10;
				}
				if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/i', $readable_sentence ) ) {
					$score += 12;
				}
			}
		}

		// Semantic matching for common phrases
		$score += $this->calculate_semantic_matches( $query, $trigger );

		return $score;
	}

	/**
	 * Calculate semantic similarity for common phrase patterns.
	 *
	 * @param string $query Search query (lowercase).
	 * @param array  $trigger Trigger data.
	 * @return float Additional score for semantic matches.
	 */
	private function calculate_semantic_matches( string $query, array $trigger ): float {
		$score = 0;

		// Common semantic mappings
		$semantic_mappings = array(
			'run now'          => array( 'manual', 'trigger manually', 'run_now' ),
			'manual trigger'   => array( 'run now', 'trigger manually', 'run_now' ),
			'trigger manually' => array( 'run now', 'manual trigger', 'run_now' ),
			'user login'       => array( 'logs in', 'logged in', 'signs in' ),
			'user logout'      => array( 'logs out', 'logged out', 'signs out' ),
			'form submit'      => array( 'submits', 'submitted', 'form submission' ),
			'order complete'   => array( 'purchase', 'completed', 'order completed' ),
		);

		$sentence          = strtolower( $trigger['sentence'] ?? '' );
		$readable_sentence = strtolower( $trigger['sentence_human_readable'] ?? '' );
		$integration       = strtolower( $trigger['integration'] ?? '' );

		if ( isset( $semantic_mappings[ $query ] ) ) {
			foreach ( $semantic_mappings[ $query ] as $semantic_phrase ) {
				if ( strpos( $sentence, $semantic_phrase ) !== false ) {
					$score += 25; // High score for semantic match
				}
				if ( strpos( $readable_sentence, $semantic_phrase ) !== false ) {
					$score += 30; // Higher for human readable
				}
				if ( strpos( $integration, $semantic_phrase ) !== false ) {
					$score += 20; // Integration match
				}
			}
		}

		return $score;
	}

	/**
	 * Format trigger for MCP response.
	 *
	 * @param array  $definition Trigger definition.
	 * @param string $trigger_code Trigger code.
	 * @return array Formatted trigger.
	 */
	private function format_trigger_for_mcp( array $definition, string $trigger_code ): array {
		// If already in MCP format, return as is
		if ( isset( $definition['name'] ) && isset( $definition['inputSchema'] ) ) {
			return $definition;
		}

		// Convert to MCP format
		return array(
			'name'        => $trigger_code,
			'description' => $definition['sentence_human_readable'] ?? $definition['sentence'] ?? 'A trigger event',
			'inputSchema' => $this->schema_converter->build_input_schema( $definition ),
			'metadata'    => array(
				'integration' => $definition['integration'] ?? '',
				'type'        => $definition['trigger_type'] ?? 'user',
				'sentence'    => $definition['sentence'] ?? '',
				'hook'        => $definition['hook'] ?? array(),
				'tokens'      => $definition['tokens'] ?? array(),
			),
		);
	}

	/**
	 * Validate configuration against schema.
	 *
	 * @param array $config Configuration to validate.
	 * @param array $definition Trigger definition with schema.
	 * @return array Validation errors.
	 */
	private function validate_config_against_schema( array $config, array $definition ): array {
		$errors = array();

		// Basic validation - check required fields if schema exists
		if ( ! empty( $definition['fields'] ) ) {
			foreach ( $definition['fields'] as $field_code => $field_def ) {
				if ( ( $field_def['required'] ?? false ) && ! isset( $config[ $field_code ] ) ) {
					$errors[] = "Required field '{$field_code}' is missing";
				}
			}
		}

		return $errors;
	}
}

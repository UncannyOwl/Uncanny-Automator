<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Filter\Registry;

use Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects\Code;
use Uncanny_Automator\Api\Components\Loop\Iterable_Expression\Enums\Iteration_Type;

/**
 * WP_Registry.
 *
 * WordPress implementation of filter registry using existing system.
 * Retrieves filter definitions from the Automator Pro loop filters system.
 *
 * @since 7.0.0
 */
class WP_Registry implements Registry {

	/**
	 * Loaded filters.
	 *
	 * @var array
	 */
	private array $filters = array();

	/**
	 * Initialization flag.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Meta structure converter.
	 *
	 * @var Meta_Structure_Converter
	 */
	private Meta_Structure_Converter $meta_converter;

	/**
	 * Constructor.
	 *
	 * @param Meta_Structure_Converter|null $meta_converter Optional meta structure converter.
	 */
	public function __construct( ?Meta_Structure_Converter $meta_converter = null ) {
		$this->meta_converter = $meta_converter ?? new Meta_Structure_Converter();
	}

	/**
	 * Get all available filter types.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of filter definitions.
	 */
	public function get_available_filters( array $options = array() ): array {
		$this->ensure_initialized();

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $this->filters;
		}

		return $this->add_schema_to_filters( $this->filters );
	}

	/**
	 * Get specific filter definition.
	 *
	 * @param Code  $code    Filter code.
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array|null Filter definition or null if not found.
	 */
	public function get_filter_definition( Code $code, array $options = array() ): ?array {
		$this->ensure_initialized();

		$filter = $this->filters[ $code->get_value() ] ?? null;

		if ( null === $filter ) {
			return null;
		}

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $filter;
		}

		return $this->add_schema_to_filter( $filter );
	}

	/**
	 * Get filters by integration.
	 *
	 * @param string $integration Integration name.
	 * @return array Array of filters for the integration.
	 */
	public function get_filters_by_integration( string $integration ): array {
		$this->ensure_initialized();

		return array_filter(
			$this->filters,
			function ( $filter ) use ( $integration ) {
				return ( $filter['integration'] ?? '' ) === $integration;
			}
		);
	}

	/**
	 * Register a filter type.
	 *
	 * @param string $code       Filter code.
	 * @param array  $definition Filter definition.
	 */
	public function register_filter( string $code, array $definition ): void {
		$this->filters[ strtoupper( $code ) ] = $this->normalize_filter_definition( $definition );
	}

	/**
	 * Check if filter is registered.
	 *
	 * @param Code $code Filter code.
	 * @return bool True if registered.
	 */
	public function is_registered( Code $code ): bool {
		$this->ensure_initialized();
		return array_key_exists( $code->get_value(), $this->filters );
	}

	/**
	 * Get filters for users iteration type.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of filters for users iteration.
	 */
	public function get_user_filters( array $options = array() ): array {
		$this->ensure_initialized();

		$user_filters = array_filter(
			$this->filters,
			function ( $filter ) {
				$types = $filter['iteration_types'] ?? array();
				return in_array( Iteration_Type::USERS, $types, true );
			}
		);

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $user_filters;
		}

		return $this->add_schema_to_filters( $user_filters );
	}

	/**
	 * Get filters for posts iteration type.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of filters for posts iteration.
	 */
	public function get_post_filters( array $options = array() ): array {
		$this->ensure_initialized();

		$post_filters = array_filter(
			$this->filters,
			function ( $filter ) {
				$types = $filter['iteration_types'] ?? array();
				return in_array( Iteration_Type::POSTS, $types, true );
			}
		);

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $post_filters;
		}

		return $this->add_schema_to_filters( $post_filters );
	}

	/**
	 * Get filters for token iteration type.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of filters for token iteration.
	 */
	public function get_token_filters( array $options = array() ): array {
		$this->ensure_initialized();

		$token_filters = array_filter(
			$this->filters,
			function ( $filter ) {
				$types = $filter['iteration_types'] ?? array();
				return in_array( Iteration_Type::TOKEN, $types, true );
			}
		);

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $token_filters;
		}

		return $this->add_schema_to_filters( $token_filters );
	}

	/**
	 * Ensure filters are loaded.
	 */
	private function ensure_initialized(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->load_filters_from_wordpress();
		$this->initialized = true;
	}

	/**
	 * Load filters from existing WordPress/Automator system.
	 *
	 * Pro registers filters via 'automator_integration_loop_filters' hook
	 * with structure: $filters[$integration][$meta] = $filter_data
	 */
	private function load_filters_from_wordpress(): void {
		/**
		 * Filter to get registered loop filters from Pro.
		 *
		 * Pro structure: $filters[$integration][$meta] = [
		 *     'integration' => 'WP',
		 *     'meta' => 'WP_USER_HAS_ROLE',
		 *     'loop_type' => 'users',
		 *     'sentence' => '...',
		 *     'sentence_readable' => '...',
		 *     'fields' => [...],
		 * ]
		 *
		 * @param array $filters Registered loop filters keyed by integration then meta.
		 */
		$pro_filters = apply_filters( 'automator_integration_loop_filters', array() );

		// Pro returns nested structure: $filters[$integration][$meta] = $filter_data
		foreach ( $pro_filters as $integration_code => $integration_filters ) {
			if ( ! is_array( $integration_filters ) ) {
				continue;
			}

			foreach ( $integration_filters as $meta => $filter ) {
				if ( ! is_array( $filter ) ) {
					continue;
				}

				// Map Pro's 'meta' to domain's 'code'
				$filter['code'] = $filter['meta'] ?? $meta;

				// Filters from Pro hook are always pro features.
				$filter['is_pro'] = true;

				$this->filters[ strtoupper( $filter['code'] ) ] = $this->normalize_filter_definition( $filter );
			}
		}

		/**
		 * Allow additional loop filters to be registered.
		 *
		 * @param array $filters Existing filters array.
		 */
		$additional_filters = apply_filters( 'automator_api_register_loop_filters', array() );

		foreach ( $additional_filters as $code => $filter ) {
			$this->register_filter( $code, $filter );
		}
	}

	/**
	 * Normalize filter definition to domain format.
	 *
	 * Maps Pro's structure to domain format:
	 * - Pro's 'meta' -> domain's 'code'
	 * - Pro's 'loop_type' (string) -> domain's 'iteration_types' (array)
	 * - Pro's 'sentence_readable' -> domain's 'sentence_readable'
	 *
	 * @param array $definition Raw filter definition.
	 * @return array Normalized definition with domain keys.
	 */
	private function normalize_filter_definition( array $definition ): array {
		// Map Pro's loop_type (string) to iteration_types (array).
		$iteration_types = $definition['iteration_types'] ?? null;
		if ( null === $iteration_types && ! empty( $definition['loop_type'] ) ) {
			$iteration_types = array( $definition['loop_type'] );
		}
		if ( null === $iteration_types ) {
			$iteration_types = array( Iteration_Type::USERS );
		}

		return array(
			'code'              => strtoupper( $definition['code'] ?? $definition['meta'] ?? '' ),
			'integration_code'  => strtoupper( $definition['integration'] ?? $definition['integration_code'] ?? '' ),
			'integration'       => $definition['integration'] ?? '',
			'iteration_types'   => $iteration_types,
			'sentence'          => $definition['sentence'] ?? '',
			'sentence_readable' => $definition['sentence_readable'] ?? $definition['sentence_human_readable'] ?? $definition['sentence'] ?? '',
			'meta_structure'    => $this->extract_meta_structure( $definition ),
			'callback'          => $definition['callback'] ?? null,
			'is_pro'            => ! empty( $definition['is_pro'] ),
			'is_deprecated'     => ! empty( $definition['is_deprecated'] ),
		);
	}

	/**
	 * Extract meta structure from filter definition.
	 *
	 * @param array $definition Filter definition.
	 * @return array Meta structure for the filter.
	 */
	private function extract_meta_structure( array $definition ): array {
		if ( isset( $definition['meta'] ) && is_array( $definition['meta'] ) && ! empty( $definition['meta'] ) ) {
			return $definition['meta'];
		}

		if ( isset( $definition['fields'] ) && is_array( $definition['fields'] ) && ! empty( $definition['fields'] ) ) {
			return $this->meta_converter->convert( $definition['fields'] );
		}

		if ( isset( $definition['options'] ) && is_array( $definition['options'] ) && ! empty( $definition['options'] ) ) {
			return $this->meta_converter->convert( $definition['options'] );
		}

		return array();
	}

	/**
	 * Add schema information to filters array.
	 *
	 * @param array $filters Filters array.
	 * @return array Filters with schema.
	 */
	private function add_schema_to_filters( array $filters ): array {
		$filters_with_schema = array();

		foreach ( $filters as $code => $filter ) {
			$filters_with_schema[ $code ] = $this->add_schema_to_filter( $filter );
		}

		return $filters_with_schema;
	}

	/**
	 * Add schema information to single filter.
	 *
	 * Merges inputSchema into the existing filter definition,
	 * preserving all original fields.
	 *
	 * @param array $filter Filter definition.
	 * @return array Filter with schema added.
	 */
	private function add_schema_to_filter( array $filter ): array {
		$meta_structure = $filter['meta_structure'] ?? array();

		$schema_properties = array();
		$required_fields   = array();

		foreach ( $meta_structure as $field_code => $field_config ) {
			$schema_properties[ $field_code ] = array(
				'type'        => $field_config['type'] ?? 'string',
				'description' => $field_config['description'] ?? $field_config['label'] ?? '',
			);

			// Add enum for select fields.
			if ( ! empty( $field_config['options'] ) ) {
				$schema_properties[ $field_code ]['enum'] = array_keys( $field_config['options'] );
			}

			// Track required fields.
			if ( $field_config['required'] ?? false ) {
				$required_fields[] = $field_code;
			}
		}

		// Merge inputSchema into the existing filter, preserving all original fields.
		$filter['inputSchema'] = array(
			'type'       => 'object',
			'properties' => $schema_properties,
			'required'   => $required_fields,
		);

		return $filter;
	}
}

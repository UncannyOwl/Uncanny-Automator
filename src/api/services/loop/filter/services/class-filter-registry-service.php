<?php
/**
 * Loop Filter Registry Service
 *
 * Service for discovering and retrieving loop filter definitions from the registry.
 * Part of the Filter bounded context within the Loop domain.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Loop\Filter\Services;

use Uncanny_Automator\Api\Components\Loop\Filter\Registry\Registry;
use Uncanny_Automator\Api\Components\Loop\Filter\Registry\WP_Registry;
use Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects\Code;

/**
 * Filter Registry Service Class
 *
 * Handles loop filter discovery and registry operations.
 */
class Filter_Registry_Service {

	/**
	 * Service instance (singleton pattern).
	 *
	 * @var Filter_Registry_Service|null
	 */
	private static ?Filter_Registry_Service $instance = null;

	/**
	 * Filter registry.
	 *
	 * @var Registry
	 */
	private Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param Registry|null $registry Optional registry instance.
	 */
	private function __construct( ?Registry $registry = null ) {
		$this->registry = $registry ?? new WP_Registry();
	}

	/**
	 * Get service instance (singleton).
	 *
	 * @since 7.0.0
	 * @return Filter_Registry_Service
	 */
	public static function instance(): Filter_Registry_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create a service instance with explicit dependencies.
	 *
	 * Primarily useful for testing where registry should be replaced with mocks.
	 *
	 * @since 7.0.0
	 * @param Registry|null $registry Custom registry instance.
	 * @return self
	 */
	public static function create_with_dependencies( ?Registry $registry = null ): self {
		return new self( $registry );
	}

	/**
	 * Get all available loop filters.
	 *
	 * @since 7.0.0
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array All loop filters from registry.
	 */
	public function get_all_filters( array $options = array() ): array {
		return $this->registry->get_available_filters( $options );
	}

	/**
	 * Get a specific filter definition by code.
	 *
	 * @since 7.0.0
	 * @param string $code   Filter code.
	 * @param array  $options Format options: ['include_schema' => bool].
	 * @return array|null Filter definition or null if not found.
	 */
	public function get_filter_definition( string $code, array $options = array() ): ?array {
		try {
			$code_vo = new Code( $code );
			return $this->registry->get_filter_definition( $code_vo, $options );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Get loop filters by integration code.
	 *
	 * @since 7.0.0
	 * @param string $integration Integration code.
	 * @return array Loop filters for the integration.
	 */
	public function get_filters_by_integration( string $integration ): array {
		return $this->registry->get_filters_by_integration( $integration );
	}

	/**
	 * Get filters for users iteration type.
	 *
	 * @since 7.0.0
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of filters for users iteration.
	 */
	public function get_user_filters( array $options = array() ): array {
		return $this->registry->get_user_filters( $options );
	}

	/**
	 * Get filters for posts iteration type.
	 *
	 * @since 7.0.0
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of filters for posts iteration.
	 */
	public function get_post_filters( array $options = array() ): array {
		return $this->registry->get_post_filters( $options );
	}

	/**
	 * Get filters for token iteration type.
	 *
	 * @since 7.0.0
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of filters for token iteration.
	 */
	public function get_token_filters( array $options = array() ): array {
		return $this->registry->get_token_filters( $options );
	}

	/**
	 * Check if a filter is registered.
	 *
	 * @since 7.0.0
	 * @param string $code Filter code.
	 * @return bool True if registered.
	 */
	public function is_registered( string $code ): bool {
		try {
			$code_vo = new Code( $code );
			return $this->registry->is_registered( $code_vo );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Validate filter code and get definition.
	 *
	 * @since 7.0.0
	 * @param string $code Filter code to validate.
	 * @return array|\WP_Error Filter definition or WP_Error if invalid.
	 */
	public function validate_filter_code_and_get_definition( string $code ) {
		$definition = $this->get_filter_definition( $code );

		if ( null === $definition ) {
			return new \WP_Error(
				'filter_not_found',
				sprintf(
					/* translators: %s Filter code. */
					esc_html_x( "Loop filter '%s' not found in registry.", 'Filter registry error', 'uncanny-automator' ),
					$code
				)
			);
		}

		return $definition;
	}
}

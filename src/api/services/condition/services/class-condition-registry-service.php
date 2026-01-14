<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Condition\Services;

use Uncanny_Automator\Api\Components\Condition\Registry\WP_Action_Condition_Registry;
use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;
use Uncanny_Automator\Api\Services\Availability\Availability_Data;
use Uncanny_Automator\Api\Services\Availability\Availability_Checker;
use WP_Error;

class Condition_Registry_Service {

	private static ?Condition_Registry_Service $instance = null;

	private WP_Action_Condition_Registry $condition_registry;

	private $integration_registry;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->condition_registry   = new WP_Action_Condition_Registry();
		$this->integration_registry = Integration_Registry_Service::get_instance();
	}
	/**
	 * Get instance.
	 *
	 * @return Condition_Registry_Service
	 */
	public static function get_instance(): Condition_Registry_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
	/**
	 * Find conditions.
	 *
	 * @param string $query The query.
	 * @param array $filters The filter.
	 * @param int $limit The limit.
	 * @return mixed
	 */
	public function find_conditions( string $query, array $filters = array(), int $limit = 3 ) {
		try {
			$integration_filter = $filters['integration'] ?? null;
			$rag_response       = $this->call_rag_search( $query, 'condition', $integration_filter, $limit );

			if ( is_wp_error( $rag_response ) ) {
				return $this->find_conditions_fallback( $query, $filters, $limit );
			}

			$rag_results = $rag_response['results'] ?? array();

			return array(
				'success'    => true,
				'query'      => $query,
				'conditions' => $rag_results,
				'count'      => count( $rag_results ),
				'limit'      => $limit,
				'source'     => 'rag',
			);

		} catch ( \Throwable $e ) {
			return $this->find_conditions_fallback( $query, $filters, $limit );
		}
	}
	/**
	 * Find conditions fallback.
	 *
	 * @param string $query The query.
	 * @param array $filters The filter.
	 * @param int $limit The limit.
	 * @return mixed
	 */
	private function find_conditions_fallback( string $query, array $filters = array(), int $limit = 3 ) {
		try {
			$integration_filter = $filters['integration'] ?? '';
			$results            = $this->condition_registry->search_conditions( $query, $integration_filter );
			$results            = array_slice( $results, 0, $limit );

			return array(
				'success'    => true,
				'query'      => $query,
				'conditions' => $results,
				'count'      => count( $results ),
				'limit'      => $limit,
				'source'     => 'fallback',
			);

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'condition_search_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to search conditions: %s', 'Condition registry error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}
	/**
	 * Get condition definition.
	 *
	 * @param string $integration_code The integration code.
	 * @param string $condition_code The condition.
	 * @return mixed
	 */
	public function get_condition_definition( string $integration_code, string $condition_code ) {
		$definition = $this->condition_registry->get_condition_definition( $integration_code, $condition_code );

		if ( null === $definition ) {
			return new WP_Error(
				'condition_not_found',
				sprintf(
					/* translators: 1: integration code, 2: condition code. */
					esc_html_x( 'Condition not found: %1$s/%2$s', 'Condition registry error', 'uncanny-automator' ),
					$integration_code,
					$condition_code
				)
			);
		}

		return array(
			'success'   => true,
			'condition' => $definition,
		);
	}
	/**
	 * Check condition integration availability.
	 *
	 * @param array $condition_data The data.
	 * @return array
	 */
	public function check_condition_integration_availability( array $condition_data ): array {
		$integration_code        = $condition_data['integration_id'] ?? '';
		$condition_code          = $condition_data['code'] ?? '';
		$condition_required_tier = $condition_data['required_tier'] ?? 'pro-basic'; // Conditions are Pro-only

		// Get integration data
		$integration = $this->integration_registry->get_integration( $integration_code );

		// Build Availability_Data using builder pattern
		$availability_data = Availability_Data::builder()
			->integration( $integration_code )
			->code( $condition_code )
			->type( 'condition' )
			->integration_registered( ! empty( $integration ) )
			->feature_registered( true ) // Conditions don't have separate registration check
			->app( ! empty( $integration['settings_url'] ) )
			->connected( ! empty( $integration['connected'] ) )
			->user_tier( $this->get_user_tier_id() )
			->requires_tier( $condition_required_tier )
			->settings_url( $integration['settings_url'] ?? '' )
			->build();

		$checker = new Availability_Checker();

		return array(
			'available'    => $checker->is_available( $availability_data ),
			'message'      => $checker->check( $availability_data ),
			'blockers'     => $checker->get_blockers( $availability_data ),
			'feature_data' => $availability_data->to_array(),
		);
	}
	/**
	 * Get user tier id.
	 *
	 * @return string
	 */
	private function get_user_tier_id(): string {
		$plan_service = new Plan_Service();
		return $plan_service->get_current_plan_id();
	}
	/**
	 * List conditions.
	 *
	 * @param array $filters The filter.
	 * @return mixed
	 */
	public function list_conditions( array $filters = array() ) {
		try {
			$all_conditions = $this->condition_registry->get_all_conditions();

			if ( ! empty( $filters['integration'] ) ) {
				$integration    = $filters['integration'];
				$all_conditions = array( $integration => $all_conditions[ $integration ] ?? array() );
			}

			$conditions_list = array();

			foreach ( $all_conditions as $integration_code => $conditions ) {
				foreach ( $conditions as $condition_code => $condition ) {
					$conditions_list[] = array(
						'integration_code' => $integration_code,
						'condition_code'   => $condition_code,
						'name'             => $condition['name'] ?? '',
						'dynamic_name'     => $condition['dynamic_name'] ?? '',
						'is_pro'           => $condition['is_pro'] ?? true,
						'requires_user'    => $condition['requires_user'] ?? false,
						'deprecated'       => $condition['deprecated'] ?? false,
						'manifest'         => $condition['manifest'] ?? array(),
					);
				}
			}

			return array(
				'success'    => true,
				'conditions' => $conditions_list,
				'count'      => count( $conditions_list ),
				'filters'    => $filters,
			);

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'condition_list_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to list conditions: %s', 'Condition registry error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}
	/**
	 * Condition exists.
	 *
	 * @param string $integration_code The integration code.
	 * @param string $condition_code The condition.
	 * @return mixed
	 */
	public function condition_exists( string $integration_code, string $condition_code ) {
		$exists = $this->condition_registry->condition_exists( $integration_code, $condition_code );

		return array(
			'success' => true,
			'exists'  => $exists,
		);
	}

	/**
	 * Check if a condition exists by condition code only.
	 * Parses the condition code to extract integration code.
	 *
	 * @param string $condition_code Condition code in format INTEGRATION_CONDITION (e.g., 'WP_USER_LOGGED_IN').
	 * @return array|\WP_Error Result with exists boolean.
	 */
	public function condition_exists_by_code( string $condition_code ) {
		try {
			// Parse condition code to extract integration
			$parts = explode( '_', $condition_code, 2 );
			if ( count( $parts ) !== 2 ) {
				return new WP_Error(
					'invalid_condition_code',
					esc_html_x( 'Invalid condition code format. Expected INTEGRATION_CONDITION.', 'Condition registry error', 'uncanny-automator' )
				);
			}

			$integration_code = $parts[0];
			$condition_part   = $parts[1];

			return $this->condition_exists( $integration_code, $condition_part );

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'condition_check_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to check condition existence: %s', 'Condition registry error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}
	/**
	 * Call rag search.
	 *
	 * @param mixed $query The query.
	 * @param mixed $type The type.
	 * @param mixed $integration The integration.
	 * @param mixed $limit The limit.
	 * @return mixed
	 */
	private function call_rag_search( $query, $type, $integration = null, $limit = 3 ) {

		$plan_service = new Plan_Service();

		$context = array(
			'installed_integrations' => $this->get_installed_integration_ids(),
			'user_plan'              => $plan_service->get_current_plan_id(),
		);

		$rag_service = new \Uncanny_Automator\Api\Services\Rag\Rag_Search_Service();

		return $rag_service->search( $query, $type, $integration, $limit, $context );
	}
	/**
	 * Get installed integration ids.
	 *
	 * @return mixed
	 */
	private function get_installed_integration_ids() {
		if ( class_exists( '\\Uncanny_Automator\\Set_Up_Automator' ) && isset( \Uncanny_Automator\Set_Up_Automator::$active_integrations_code ) && is_array( \Uncanny_Automator\Set_Up_Automator::$active_integrations_code ) ) {
			return \Uncanny_Automator\Set_Up_Automator::$active_integrations_code;
		}

		return array();
	}

	/**
	 * Get integration code by condition code.
	 *
	 * Efficiently finds the integration code for a given condition code
	 * without loading all conditions into memory.
	 *
	 * @since 7.0.0
	 * @param string $condition_code Condition code (e.g., 'WP_USER_LOGGED_IN').
	 * @return string|\WP_Error Integration code or error.
	 */
	public function get_integration_by_condition_code( string $condition_code ) {
		try {
			$all_conditions = $this->condition_registry->get_all_conditions();

			foreach ( $all_conditions as $integration_code => $conditions ) {
				foreach ( $conditions as $code => $definition ) {
					if ( 0 === strcasecmp( $code, $condition_code ) ) {
						return $integration_code;
					}
				}
			}

			return new WP_Error(
				'condition_not_found',
				esc_html_x( 'Condition not found in registry. Use the search_components tool to discover available conditions.', 'Condition registry error', 'uncanny-automator' )
			);

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'condition_lookup_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to look up condition: %s', 'Condition registry error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Get field schema for a condition.
	 *
	 * Retrieves the field definitions for a specific condition,
	 * delegating to the internal condition registry.
	 *
	 * @since 7.0.0
	 * @param string $integration_code Integration code (e.g., 'WP').
	 * @param string $condition_code Condition code (e.g., 'WP_USER_LOGGED_IN').
	 * @return array Field schema array, empty array if no fields.
	 */
	public function get_condition_field_schema( string $integration_code, string $condition_code ): array {
		try {
			return $this->condition_registry->get_condition_fields( $integration_code, $condition_code );
		} catch ( \Throwable $e ) {
			return array();
		}
	}
}

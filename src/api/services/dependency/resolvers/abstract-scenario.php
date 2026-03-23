<?php
/**
 * Abstract Scenario
 *
 * Base class for all dependency scenario services.
 *
 * @package Uncanny_Automator\Api\Services\Dependency\Resolvers
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Dependency\Resolvers;

use Uncanny_Automator\Api\Services\Dependency\Dependency_Context;
use Uncanny_Automator\Api\Components\Dependency\Value_Objects\Dependency_Cta;

/**
 * Abstract base for scenario services.
 *
 * Provides common functionality for scenario configuration and presentation.
 *
 * @since 7.0.0
 */
abstract class Abstract_Scenario {

	/**
	 * Dependency context.
	 *
	 * @var Dependency_Context
	 */
	protected $context;

	/**
	 * Scenario configurations cache.
	 *
	 * @var array|null
	 */
	protected $scenarios = null;

	/**
	 * Constructor.
	 *
	 * @param Dependency_Context $context Dependency resolution context
	 *
	 * @return void
	 */
	public function __construct( Dependency_Context $context ) {
		$this->context = $context;
	}

	/**
	 * Get all scenario configurations.
	 *
	 * Must be implemented by concrete scenario classes to define their scenarios.
	 *
	 * @return array Keyed array of scenario configurations
	 */
	abstract protected function get_scenarios();

	/**
	 * Get scenario ID.
	 *
	 * Concrete implementations may accept different parameters based on their needs.
	 * Use variadic parameters to allow flexibility.
	 *
	 * @param mixed ...$args Variable parameters based on scenario type
	 *
	 * @return string Scenario ID
	 */
	abstract public function get_scenario_id( ...$args );

	/**
	 * Get scenario name.
	 *
	 * @param string $scenario_id Scenario ID
	 *
	 * @return string Scenario name
	 */
	abstract public function get_name( string $scenario_id );

	/**
	 * Get scenario description.
	 *
	 * @param string $scenario_id Scenario ID
	 * @param string $name Integration or item name
	 *
	 * @return string Translated description
	 */
	abstract public function get_description( string $scenario_id, string $name );

	/**
	 * Get CTA configuration array.
	 *
	 * Concrete implementations return array with CTA configuration.
	 * Use variadic parameters to allow flexibility.
	 *
	 * @param string $scenario_id Scenario ID
	 * @param string $name Integration or item name
	 * @param mixed ...$args Additional parameters based on scenario type
	 *
	 * @return array CTA configuration array with 'type', 'label', 'url' keys
	 */
	abstract protected function get_cta_config( string $scenario_id, string $name, ...$args );

	/**
	 * Create CTA for scenario.
	 *
	 * Calls get_cta_config() and instantiates Dependency_Cta object.
	 *
	 * @param string $scenario_id Scenario ID
	 * @param string $name Integration or item name
	 * @param mixed ...$args Additional parameters based on scenario type
	 *
	 * @return Dependency_Cta
	 */
	public function create_cta( string $scenario_id, string $name, ...$args ) {
		$config = $this->get_cta_config( $scenario_id, $name, ...$args );
		return new Dependency_Cta( $config );
	}

	/**
	 * Get scenario configuration by scenario ID.
	 *
	 * @param string $scenario_id Scenario ID
	 *
	 * @return array|null Scenario configuration or null if not found
	 */
	protected function get_scenario_config( string $scenario_id ) {
		$scenarios = $this->get_scenarios();

		// Find config by scenario_id
		foreach ( $scenarios as $config ) {
			if ( isset( $config['scenario_id'] ) && $config['scenario_id'] === $scenario_id ) {
				return $config;
			}
		}

		return null;
	}
}

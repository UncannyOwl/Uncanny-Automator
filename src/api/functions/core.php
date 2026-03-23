<?php
/**
 * Core Functions
 *
 * Database factory functions, service singletons, and system diagnostics.
 * The foundation that everything else depends on.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Functions
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) && ! defined( 'PHPUNIT_COMPOSER_INSTALL' ) && ! defined( 'WP_TESTS_DIR' ) ) {
	exit;
}

// Import classes
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\Api\Database\Stores\WP_Action_Store;
use Uncanny_Automator\Api\Components\Trigger\Registry\WP_Trigger_Registry;
use Uncanny_Automator\Api\Components\Action\Registry\WP_Action_Registry;
use Uncanny_Automator\Api\Services\Recipe\Services\Recipe_CRUD_Service;
use Uncanny_Automator\Api\Services\Recipe\Services\Recipe_Query_Service;
use Uncanny_Automator\Api\Services\Recipe\Services\Recipe_Log_Service;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\Api\Services\Action\Services\Action_Registry_Service;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Management_Service;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Query_Service;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;
use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;

// =============================================================================
// DATABASE FACTORY FUNCTIONS
// =============================================================================

/**
 * Get Recipe Store singleton instance.
 *
 * @return WP_Recipe_Store
 */
function automator_get_recipe_store(): WP_Recipe_Store {
	static $store = null;

	if ( null === $store ) {
		$store = new WP_Recipe_Store();
	}

	return $store;
}

/**
 * Get Recipe Trigger Store singleton instance.
 *
 * @return WP_Recipe_Trigger_Store
 */
function automator_get_recipe_trigger_store(): WP_Recipe_Trigger_Store {
	static $store = null;

	if ( null === $store ) {
		global $wpdb;
		$store = new WP_Recipe_Trigger_Store( $wpdb );
	}

	return $store;
}

/**
 * Get Action Store singleton instance.
 *
 * @return WP_Action_Store
 */
function automator_get_action_store(): WP_Action_Store {
	return \Uncanny_Automator\Api\Database\Database::get_action_store();
}

/**
 * Get Trigger Registry singleton instance.
 *
 * @return WP_Trigger_Registry
 */
function automator_get_trigger_registry(): WP_Trigger_Registry {
	static $registry = null;

	if ( null === $registry ) {
		$registry = new WP_Trigger_Registry();
	}

	return $registry;
}

/**
 * Get Action Registry singleton instance.
 *
 * @return WP_Action_Registry
 */
function automator_get_action_registry(): WP_Action_Registry {
	static $registry = null;

	if ( null === $registry ) {
		$registry = new WP_Action_Registry();
	}

	return $registry;
}

// =============================================================================
// SERVICE SINGLETONS
// =============================================================================

/**
 * Get Recipe CRUD Service singleton instance.
 *
 * @return Recipe_CRUD_Service
 */
function automator_get_recipe_crud_service(): Recipe_CRUD_Service {
	static $service = null;

	if ( null === $service ) {
		$service = new Recipe_CRUD_Service();
	}

	return $service;
}

/**
 * Get Recipe Query Service singleton instance.
 *
 * @return Recipe_Query_Service
 */
function automator_get_recipe_query_service(): Recipe_Query_Service {
	static $service = null;

	if ( null === $service ) {
		$service = new Recipe_Query_Service();
	}

	return $service;
}

/**
 * Get Recipe Log Service singleton instance.
 *
 * @return Recipe_Log_Service
 */
function automator_get_recipe_log_service(): Recipe_Log_Service {
	static $service = null;

	if ( null === $service ) {
		$service = new Recipe_Log_Service();
	}

	return $service;
}

/**
 * Get Trigger CRUD Service singleton instance.
 *
 * @return Trigger_CRUD_Service
 */
function automator_get_trigger_crud_service(): Trigger_CRUD_Service {
	return Trigger_CRUD_Service::instance();
}

/**
 * Get Trigger Registry Service singleton instance.
 *
 * @return Trigger_Registry_Service
 */
function automator_get_trigger_registry_service(): Trigger_Registry_Service {
	return Trigger_Registry_Service::get_instance();
}

/**
 * Get Action CRUD Service singleton instance.
 *
 * @return Action_CRUD_Service
 */
function automator_get_action_crud_service(): Action_CRUD_Service {
	return Action_CRUD_Service::instance();
}

/**
 * Get Action Registry Service singleton instance.
 *
 * @return Action_Registry_Service
 */
function automator_get_action_registry_service(): Action_Registry_Service {
	return Action_Registry_Service::instance();
}

/**
 * Get Condition Registry Service singleton instance.
 *
 * @return Condition_Registry_Service
 */
function automator_get_condition_registry_service(): Condition_Registry_Service {
	return Condition_Registry_Service::get_instance();
}

/**
 * Get Condition Management Service singleton instance.
 *
 * @return Condition_Management_Service
 */
function automator_get_condition_management_service(): Condition_Management_Service {
	static $service = null;

	if ( null === $service ) {
		$recipe_store       = automator_get_recipe_store();
		$repository         = new \Uncanny_Automator\Api\Database\Stores\Action_Condition_Store( $recipe_store );
		$condition_registry = new \Uncanny_Automator\Api\Components\Condition\Registry\WP_Action_Condition_Registry();
		$action_service     = automator_get_action_crud_service();
		$validator          = new \Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Validator( $condition_registry, $action_service );
		$assembler          = new \Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Factory( $validator );
		$group_locator      = new \Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Locator();

		$service = new Condition_Management_Service( $repository, $assembler, $group_locator );
	}

	return $service;
}

/**
 * Get Condition Query Service singleton instance.
 *
 * @return Condition_Query_Service
 */
function automator_get_condition_query_service(): Condition_Query_Service {
	static $service = null;

	if ( null === $service ) {
		$recipe_store = automator_get_recipe_store();
		$repository   = new \Uncanny_Automator\Api\Database\Stores\Action_Condition_Store( $recipe_store );
		$service      = new Condition_Query_Service( $repository );
	}

	return $service;
}

/**
 * Get Recipe Condition Service singleton instance.
 *
 * @return Recipe_Condition_Service
 */
function automator_get_recipe_condition_service(): Recipe_Condition_Service {
	return Recipe_Condition_Service::instance();
}

/**
 * Get Plan Service singleton instance.
 *
 * @return Plan_Service
 */
function automator_get_plan_service(): Plan_Service {
	static $service = null;

	if ( null === $service ) {
		$service = new Plan_Service();
	}

	return $service;
}

/**
 * Get Integration Registry Service singleton instance.
 *
 * @return Integration_Registry_Service
 */
function automator_get_integration_registry_service(): Integration_Registry_Service {
	return Integration_Registry_Service::get_instance();
}

// =============================================================================
// SYSTEM DIAGNOSTICS
// =============================================================================

/**
 * Check if all core services are available.
 *
 * @return array {
 *     Service availability check results.
 *
 *     @type bool   $available Whether all services are available.
 *     @type array  $missing   List of missing service classes.
 * }
 */
function automator_check_core_services(): array {
	$services = array(
		WP_Recipe_Store::class,
		WP_Recipe_Trigger_Store::class,
		WP_Action_Store::class,
		WP_Trigger_Registry::class,
		WP_Action_Registry::class,
		Recipe_CRUD_Service::class,
		Recipe_Query_Service::class,
		Recipe_Log_Service::class,
		Trigger_CRUD_Service::class,
		Trigger_Registry_Service::class,
		Action_CRUD_Service::class,
		Action_Registry_Service::class,
		Condition_Registry_Service::class,
		Condition_Management_Service::class,
		Condition_Query_Service::class,
		Plan_Service::class,
		Integration_Registry_Service::class,
	);

	$missing = array();

	foreach ( $services as $service ) {
		if ( ! class_exists( $service ) ) {
			$missing[] = $service;
		}
	}

	return array(
		'available' => empty( $missing ),
		'missing'   => $missing,
	);
}

/**
 * Get system information for diagnostics.
 *
 * @return array System information array.
 */
function automator_get_system_info(): array {
	global $wp_version;

	$services_check = automator_check_core_services();

	return array(
		'version'            => defined( 'UNCANNY_AUTOMATOR_VERSION' ) ? UNCANNY_AUTOMATOR_VERSION : 'unknown',
		'php_version'        => phpversion(),
		'wordpress_version'  => $wp_version,
		'services_available' => $services_check['available'],
		'missing_services'   => $services_check['missing'],
		'timestamp'          => time(),
	);
}

/**
 * Verify that all core functions are available.
 *
 * @return bool True if all functions exist.
 */
function automator_verify_core_functions(): bool {
	$functions = array(
		'automator_get_recipe_store',
		'automator_get_recipe_trigger_store',
		'automator_get_action_store',
		'automator_get_trigger_registry',
		'automator_get_action_registry',
		'automator_get_recipe_crud_service',
		'automator_get_recipe_query_service',
		'automator_get_recipe_log_service',
		'automator_get_trigger_crud_service',
		'automator_get_trigger_registry_service',
		'automator_get_action_crud_service',
		'automator_get_action_registry_service',
		'automator_get_condition_registry_service',
		'automator_get_condition_management_service',
		'automator_get_condition_query_service',
		'automator_get_recipe_condition_service',
		'automator_get_plan_service',
		'automator_get_integration_registry_service',
		'automator_check_core_services',
		'automator_get_system_info',
	);

	foreach ( $functions as $function ) {
		if ( ! function_exists( $function ) ) {
			return false;
		}
	}

	return true;
}

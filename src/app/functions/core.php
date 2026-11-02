<?php
/**
 * Core Functions
 *
 * Database factory functions, service singletons, and system diagnostics.
 * The foundation that everything else depends on.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Functions
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Import classes
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Center;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Notices\Recipe_Notice_Center;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Trigger_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Background_Job_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Registry\Trigger_Registry;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Condition_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Registry\WP_Trigger_Registry;
use Uncanny_Automator\App\Recipe_Builder\Action\Registry\WP_Action_Registry;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Query_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Integrations_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Log_Service;
use Uncanny_Automator\App\Recipe_Logs\Services\Recipe_Log_Query_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Prepopulation_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Services\Recipe_Settings_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Wire_Service;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Services\User_Selector_Service;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Throttle_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Action_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\App\Plan\Services\Plan_Service;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Registry_Service;
use Uncanny_Automator\App\Infrastructure\Database\Database;

// =============================================================================
// DATABASE FACTORY FUNCTIONS
// =============================================================================

/**
 * Get Recipe Store singleton instance.
 *
 * @return Recipe_Store
 */
function automator_get_recipe_store(): Recipe_Store {
	static $store = null;

	if ( null === $store ) {
		$store = new WP_Recipe_Store();
	}

	return $store;
}

/**
 * Get Recipe Trigger Store singleton instance.
 *
 * @return Recipe_Trigger_Store
 */
function automator_get_recipe_trigger_store(): Recipe_Trigger_Store {
	static $store = null;

	if ( null === $store ) {
		global $wpdb;
		$store = new WP_Recipe_Trigger_Store( $wpdb );
	}

	return $store;
}

/**
 * Get the background job store.
 *
 * @return Background_Job_Store
 */
function automator_get_background_job_store(): Background_Job_Store {
	return Database::get_background_job_store();
}

/**
 * Get Action Store singleton instance.
 *
 * @return Action_Store
 */
function automator_get_action_store(): Action_Store {
	return Database::get_action_store();
}

/**
 * Get Block Store singleton instance.
 *
 * @return Block_Store
 */
function automator_get_block_store(): Block_Store {
	return Database::get_block_store();
}

/**
 * Get Block Modifier Store singleton instance.
 *
 * @return Block_Modifier_Store
 */
function automator_get_block_modifier_store(): Block_Modifier_Store {
	return Database::get_block_modifier_store();
}

/**
 * Get Trigger Registry singleton instance.
 *
 * @return Trigger_Registry
 */
function automator_get_trigger_registry(): Trigger_Registry {
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
 * Get the Recipe Integrations Service singleton instance.
 *
 * @return Recipe_Integrations_Service
 */
function automator_get_recipe_integrations_service(): Recipe_Integrations_Service {
	static $service = null;

	if ( null === $service ) {
		$service = new Recipe_Integrations_Service();
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
 * Get the Recipe Log Query Service singleton instance.
 *
 * Reads one run as the 8.0 run tree the recipe log dialog shows.
 *
 * @since 8.0.0
 *
 * @return Recipe_Log_Query_Service
 */
function automator_get_recipe_log_query_service(): Recipe_Log_Query_Service {
	return Recipe_Log_Query_Service::instance();
}

/**
 * Get Recipe Settings Service singleton instance.
 *
 * SSoT read service for recipe-level settings (throttle config, total_times,
 * times_per_user, recipe_type, trigger_logic) and the set_setting/set_status writers.
 *
 * @return Recipe_Settings_Service
 */
function automator_get_recipe_settings_service(): Recipe_Settings_Service {
	return Recipe_Settings_Service::instance();
}

/**
 * Get the User Selector Service singleton instance.
 *
 * Run-as (user selector) reads over every stored shape, and the one write.
 *
 * @since 8.0.0
 *
 * @return User_Selector_Service
 */
function automator_get_user_selector_service(): User_Selector_Service {
	return User_Selector_Service::instance();
}

/**
 * Get the Recipe Wire Service singleton instance.
 *
 * Reads a recipe as the builder and REST API see it: the `ModelRecipe` wire
 * array, projected from the cached recipe tree.
 *
 * @since 8.0.0
 *
 * @return Recipe_Wire_Service
 */
function automator_get_recipe_wire_service(): Recipe_Wire_Service {
	return Recipe_Wire_Service::instance();
}

/**
 * Get the Recipe Prepopulation Service singleton instance.
 *
 * Composes the partner quick-link recipe bootstrap (set type, add trigger,
 * optional option value + publish) over the settings + trigger CRUD services.
 *
 * @return Recipe_Prepopulation_Service
 */
function automator_get_recipe_prepopulation_service(): Recipe_Prepopulation_Service {
	return Recipe_Prepopulation_Service::instance();
}

/**
 * Get Recipe Throttle Service instance (runtime throttle checks).
 *
 * @return Recipe_Throttle_Service
 */
function automator_get_recipe_throttle_service(): Recipe_Throttle_Service {
	static $service = null;

	if ( null === $service ) {
		$service = new Recipe_Throttle_Service();
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
	return Trigger_Registry_Service::instance();
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
	return Condition_Registry_Service::instance();
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
		Recipe_Store::class,
		Recipe_Trigger_Store::class,
		Action_Store::class,
		Trigger_Registry::class,
		WP_Action_Registry::class,
		Recipe_CRUD_Service::class,
		Recipe_Query_Service::class,
		Recipe_Log_Service::class,
		Trigger_CRUD_Service::class,
		Trigger_Registry_Service::class,
		Action_CRUD_Service::class,
		Action_Registry_Service::class,
		Condition_Registry_Service::class,
		Plan_Service::class,
		Integration_Registry_Service::class,
	);

	$missing = array();

	foreach ( $services as $service ) {
		// The store getters intentionally return interfaces (Recipe_Store, Recipe_Trigger_Store,
		// Action_Store, Trigger_Registry) — consumers depend on the contracts, not the concrete
		// WP_* stores. class_exists() returns false for interfaces, so this diagnostic must also
		// check interface_exists() or it reports the contracts missing in every environment.
		if ( ! class_exists( $service ) && ! interface_exists( $service ) ) {
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

/**
 * The one notice center: every Automator admin notice is added here (or
 * through its `automator_admin_notices` filter) and shown by it.
 *
 * @since 8.0.0
 *
 * @return Notice_Center
 */
function automator_notice_center(): Notice_Center {
	static $center = null;
	if ( null === $center ) {
		$center = new Notice_Center();
	}
	return $center;
}

/**
 * The one recipe notice center: every notice about one recipe is added here
 * (or through its `automator_recipe_notices` filter) and sent on the recipe wire.
 *
 * @since 8.0.0
 *
 * @return Recipe_Notice_Center
 */
function automator_recipe_notice_center(): Recipe_Notice_Center {
	static $center = null;
	if ( null === $center ) {
		$center = new Recipe_Notice_Center();
	}
	return $center;
}

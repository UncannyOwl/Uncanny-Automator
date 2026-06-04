<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Api_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Closure_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Trigger_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Run_Snapshot_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Action_Error_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Api_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Closure_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Execution_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Run_Snapshot_Store;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Registry\Trigger_Registry;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Registry\WP_Trigger_Registry;

/**
 * Database Factory.
 *
 * Factory class for creating database store instances.
 * Returns interface types — consumers depend on contracts, not WordPress implementations.
 *
 * @since 7.0.0
 */
final class Database {

	/** @var WP_Recipe_Store|null */
	private static $recipe_store = null;

	/** @var WP_Recipe_Trigger_Store|null */
	private static $recipe_trigger_store = null;

	/** @var WP_Action_Store|null */
	private static $action_store = null;

	/** @var WP_Trigger_Registry|null */
	private static $trigger_registry = null;

	/** @var WP_Closure_Store|null */
	private static $closure_store = null;

	/** @var WP_Api_Log_Store|null */
	private static $api_log_store = null;

	/** @var WP_Execution_Log_Store|null */
	private static $execution_log_store = null;

	/** @var WP_Action_Error_Store|null */
	private static $action_error_store = null;

	/** @var WP_Run_Snapshot_Store|null */
	private static $run_snapshot_store = null;

	/**
	 * @since 7.0.0
	 * @return Recipe_Store
	 */
	public static function get_recipe_store(): Recipe_Store {
		if ( null === self::$recipe_store ) {
			self::$recipe_store = new WP_Recipe_Store();
		}
		return self::$recipe_store;
	}

	/**
	 * @since 7.0.0
	 * @return Action_Store
	 */
	public static function get_action_store(): Action_Store {
		if ( null === self::$action_store ) {
			global $wpdb;
			self::$action_store = new WP_Action_Store( $wpdb );
		}
		return self::$action_store;
	}

	/**
	 * @since 7.0.0
	 * @return Recipe_Trigger_Store
	 */
	public static function get_recipe_trigger_store(): Recipe_Trigger_Store {
		if ( null === self::$recipe_trigger_store ) {
			global $wpdb;
			self::$recipe_trigger_store = new WP_Recipe_Trigger_Store( $wpdb );
		}
		return self::$recipe_trigger_store;
	}

	/**
	 * @since 7.0.0
	 * @return Trigger_Registry
	 */
	public static function get_trigger_registry(): Trigger_Registry {
		if ( null === self::$trigger_registry ) {
			self::$trigger_registry = new WP_Trigger_Registry();
		}
		return self::$trigger_registry;
	}

	/**
	 * @since 7.0
	 * @return Closure_Store
	 */
	public static function get_closure_store(): Closure_Store {
		if ( null === self::$closure_store ) {
			global $wpdb;
			self::$closure_store = new WP_Closure_Store( $wpdb );
		}
		return self::$closure_store;
	}

	/**
	 * @since 7.4.0
	 * @return Api_Log_Store
	 */
	public static function get_api_log_store(): Api_Log_Store {
		if ( null === self::$api_log_store ) {
			global $wpdb;
			self::$api_log_store = new WP_Api_Log_Store( $wpdb );
		}
		return self::$api_log_store;
	}

	/**
	 * @since 7.4.0
	 * @return Execution_Log_Store
	 */
	public static function get_execution_log_store(): Execution_Log_Store {
		if ( null === self::$execution_log_store ) {
			global $wpdb;
			self::$execution_log_store = new WP_Execution_Log_Store( $wpdb );
		}
		return self::$execution_log_store;
	}

	/**
	 * @since 7.4.0
	 * @return Action_Error_Store
	 */
	public static function get_action_error_store(): Action_Error_Store {
		if ( null === self::$action_error_store ) {
			global $wpdb;
			self::$action_error_store = new WP_Action_Error_Store( $wpdb );
		}
		return self::$action_error_store;
	}

	/**
	 * @since 7.4.0
	 * @return Run_Snapshot_Store
	 */
	public static function get_run_snapshot_store(): Run_Snapshot_Store {
		if ( null === self::$run_snapshot_store ) {
			global $wpdb;
			self::$run_snapshot_store = new WP_Run_Snapshot_Store( $wpdb );
		}
		return self::$run_snapshot_store;
	}
}

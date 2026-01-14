<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database;

use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\Api\Database\Stores\WP_Action_Store;
use Uncanny_Automator\Api\Components\Trigger\Registry\WP_Trigger_Registry;
use Uncanny_Automator\Api\Database\Stores\WP_Closure_Store;

/**
 * Database Factory.
 *
 * Factory class for creating database store instances.
 * Provides static methods to get singleton instances of various stores.
 *
 * @since 7.0.0
 */
class Database {

	/**
	 * Recipe store instance.
	 *
	 * @var WP_Recipe_Store|null
	 */
	private static $recipe_store = null;

	/**
	 * Recipe trigger store instance.
	 *
	 * @var WP_Recipe_Trigger_Store|null
	 */
	private static $recipe_trigger_store = null;

	/**
	 * Action store instance.
	 *
	 * @var WP_Action_Store|null
	 */
	private static $action_store = null;

	/**
	 * Trigger registry instance.
	 *
	 * @var WP_Trigger_Registry|null
	 */
	private static $trigger_registry = null;

	/**
	 * Closure store instance.
	 *
	 * @var WP_Closure_Store|null
	 */
	private static $closure_store = null;

	/**
	 * Get recipe store instance.
	 *
	 * @since 7.0.0
	 * @return WP_Recipe_Store Recipe store instance.
	 */
	public static function get_recipe_store() {
		if ( null === self::$recipe_store ) {
			self::$recipe_store = new WP_Recipe_Store();
		}
		return self::$recipe_store;
	}

	/**
	 * Get action store instance.
	 *
	 * @since 7.0.0
	 * @return Action_Store Action store instance.
	 */
	public static function get_action_store() {
		if ( null === self::$action_store ) {
			global $wpdb;
			self::$action_store = new WP_Action_Store( $wpdb );
		}
		return self::$action_store;
	}

	/**
	 * Get recipe trigger store instance.
	 *
	 * @since 7.0.0
	 * @return WP_Recipe_Trigger_Store Recipe trigger store instance.
	 */
	public static function get_recipe_trigger_store() {
		if ( null === self::$recipe_trigger_store ) {
			global $wpdb;
			self::$recipe_trigger_store = new WP_Recipe_Trigger_Store( $wpdb );
		}
		return self::$recipe_trigger_store;
	}

	/**
	 * Get trigger registry instance.
	 *
	 * @since 7.0.0
	 * @return WP_Trigger_Registry Trigger registry instance.
	 */
	public static function get_trigger_registry() {
		if ( null === self::$trigger_registry ) {
			self::$trigger_registry = new WP_Trigger_Registry();
		}
		return self::$trigger_registry;
	}

	/**
	 * Get closure store instance.
	 *
	 * @since 7.0
	 * @return WP_Closure_Store Closure store instance.
	 */
	public static function get_closure_store() {
		if ( null === self::$closure_store ) {
			global $wpdb;
			self::$closure_store = new WP_Closure_Store( $wpdb );
		}
		return self::$closure_store;
	}
}

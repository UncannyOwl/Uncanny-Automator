<?php

namespace Uncanny_Automator;

/**
 * Class DB_Tables
 *
 * Manages database table names for Uncanny Automator plugin.
 * Provides a centralized way to register and access all Automator-related database tables.
 *
 * @package Uncanny_Automator
 * @since 6.6.0
 */
class DB_Tables {

	/**
	 * Contains all registered Automator database tables.
	 *
	 * @var object|null
	 * @since 6.6.0
	 */
	protected static $tables = null;

	/**
	 * Loads and registers the Automator database tables.
	 *
	 * This method applies the 'automator_database_tables' filter to allow developers
	 * to add custom tables or modify existing table names.
	 *
	 * @since 6.6.0
	 *
	 * @example
	 * ```php
	 * // Example 1: Adding a custom table
	 * add_filter( 'automator_database_tables', function( $tables ) {
	 *     $tables->custom_integration = 'uap_custom_integration_log';
	 *     $tables->custom_meta = 'uap_custom_integration_meta';
	 *     return $tables;
	 * });
	 *
	 * // Example 2: Modifying existing table names (advanced use case)
	 * add_filter( 'automator_database_tables', function( $tables ) {
	 *     // Override default recipe table name
	 *     $tables->recipe = 'custom_recipe_log';
	 *     return $tables;
	 * });
	 *
	 * // Example 3: Adding multiple custom tables for a new integration
	 * add_filter( 'automator_database_tables', function( $tables ) {
	 *     $custom_tables = array(
	 *         'webhook_log'      => 'uap_webhook_log',
	 *         'webhook_meta'     => 'uap_webhook_meta',
	 *         'subscription_log' => 'uap_subscription_log',
	 *         'payment_log'      => 'uap_payment_log',
	 *     );
	 *
	 *     foreach ( $custom_tables as $key => $table_name ) {
	 *         $tables->$key = $table_name;
	 *     }
	 *
	 *     return $tables;
	 * });
	 *
	 * // Example 4: Accessing registered tables after registration
	 * function get_custom_table_name() {
	 *     global $wpdb;
	 *     $tables = DB_Tables::get_automator_tables();
	 *
	 *     // Access your custom table with proper prefix
	 *     if ( isset( $tables->custom_integration ) ) {
	 *         return $wpdb->prefix . $tables->custom_integration;
	 *     }
	 *
	 *     return null;
	 * }
	 * ```
	 *
	 * @hook automator_database_tables Filters the array of database table names.
	 *
	 * @return void
	 */
	/**
	 * Register tables.
	 */
	public static function register_tables() {
		global $wpdb;

		self::$tables = (object) apply_filters(
			'automator_database_tables',
			(object) array(
				'recipe'            => 'uap_recipe_log',
				'recipe_meta'       => 'uap_recipe_log_meta',
				'trigger'           => 'uap_trigger_log',
				'trigger_meta'      => 'uap_trigger_log_meta',
				'action'            => 'uap_action_log',
				'action_meta'       => 'uap_action_log_meta',
				'closure'           => 'uap_closure_log',
				'closure_meta'      => 'uap_closure_log_meta',
				'api'               => 'uap_api_log',
				'api_meta'          => 'uap_api_log_meta',
				'recipe_logs'       => 'uap_recipe_logs_view',
				'trigger_logs'      => 'uap_trigger_logs_view',
				'action_logs'       => 'uap_action_logs_view',
				'api_logs'          => 'uap_api_logs_view',
				'api_response_logs' => 'uap_api_log_response',
				'tokens_logs'       => 'uap_tokens_log',
				'recipe_count'      => 'uap_recipe_count',
				'uap_options'       => 'uap_options',
				'recipe_throttle'   => 'uap_recipe_throttle_log',
			)
		);
		// Add all tables to $wpdb object
		foreach ( self::$tables as $key => $table ) {
			$wpdb->$table = $wpdb->prefix . $table;
		}
	}

	/**
	 * Returns the registered Automator database tables.
	 *
	 * @since 6.6.0
	 *
	 * @example
	 * ```php
	 * // Get all registered tables
	 * $tables = DB_Tables::get_automator_tables();
	 *
	 * // Access specific table names
	 * $recipe_table = $tables->recipe;           // 'uap_recipe_log'
	 * $trigger_table = $tables->trigger;         // 'uap_trigger_log'
	 * $action_table = $tables->action;           // 'uap_action_log'
	 *
	 * // Use with global $wpdb for database operations
	 * global $wpdb;
	 * $results = $wpdb->get_results(
	 *     $wpdb->prepare(
	 *         "SELECT * FROM {$wpdb->prefix}{$tables->recipe} WHERE user_id = %d",
	 *         get_current_user_id()
	 *     )
	 * );
	 * ```
	 *
	 * @return object Object containing all registered table names.
	 */
	/**
	 * Get automator tables.
	 *
	 * @return mixed
	 */
	public static function get_automator_tables() {
		if ( empty( self::$tables ) ) {
			self::register_tables();
		}
		return self::$tables;
	}
}

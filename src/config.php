<?php

namespace Uncanny_Automator;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * This class is used to run any configurations before the plugin is initialized
 *
 * @package    Private_Plugin_Boilerplate
 */
class Config {


	/**
	 * The instance of the class
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Boot
	 */
	private static $instance = null;

	/**
	 * Creates singleton instance of class
	 *
	 * @return Config $instance The UncannyLearnDashGroupManagement Class
	 * @since 1.0.0
	 *
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the class and setup its properties.
	 *
	 * @param string $plugin_name The name of the plugin
	 * @param string $prefix The variable used to prefix filters and actions
	 * @param string $version The version of this plugin
	 * @param string $file The main plugin file __FILE__
	 * @param bool $debug Whether debug log in php and js files are enabled
	 *
	 * @since    1.0.0
	 *
	 */
	public function configure_plugin_before_boot( $plugin_name, $prefix, $version, $file, $debug ) {

		$this->define_constants( $plugin_name, $prefix, $version, $file, $debug );

		do_action( Utilities::get_prefix() . '_define_constants_after' );

		register_activation_hook( Utilities::get_plugin_file(), array( $this, 'activation' ) );

		register_deactivation_hook( Utilities::get_plugin_file(), array( $this, 'deactivation' ) );

		do_action( Utilities::get_prefix() . '_config_setup_after' );

	}

	/**
	 *
	 * Setting constants that maybe used through out the plugin or in the pro version
	 * Needs revision
	 *
	 * @param string $plugin_name The name of the plugin
	 * @param string $prefix Variable used to prefix filters and actions
	 * @param string $version The version of this plugin.
	 * @param string $plugin_file The main plugin file __FILE__
	 * @param string $debug_mode Whether debug log in php and js files are enabled
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 */
	private function define_constants( $plugin_name, $prefix, $version, $plugin_file, $debug_mode ) {


		// Set and define version
		if ( ! defined( strtoupper( $prefix ) . '_PLUGIN_NAME' ) ) {
			define( strtoupper( $prefix ) . '_PLUGIN_NAME', $plugin_name );
			Utilities::set_plugin_name( $plugin_name );
		}

		// Set and define version
		if ( ! defined( strtoupper( $prefix ) . '_VERSION' ) ) {
			define( strtoupper( $prefix ) . '_VERSION', $version );
			Utilities::set_version( $version );
		}

		// Set and define prefix
		if ( ! defined( strtoupper( $prefix ) . '_PREFIX' ) ) {
			define( strtoupper( $prefix ) . '_PREFIX', $prefix );
			Utilities::set_prefix( $prefix );
		}

		// Set and define the main plugin file path
		if ( ! defined( $prefix . '_FILE' ) ) {
			define( strtoupper( $prefix ) . '_FILE', $plugin_file );
			Utilities::set_plugin_file( $plugin_file );
		}

		// Set and define debug mode
		if ( ! defined( $prefix . '_DEBUG_MODE' ) ) {
			define( strtoupper( $prefix ) . '_DEBUG_MODE', $debug_mode );
			Utilities::set_debug_mode( $debug_mode );
		}

		// Set and define the server initialization ( Server time and not to be confused with WP current_time() )
		if ( ! defined( $prefix . '_SERVER_INITIALIZATION' ) ) {
			$time = current_time( 'timestamp' );
			define( strtoupper( $prefix ) . '_SERVER_INITIALIZATION', $time );
			Utilities::set_plugin_initialization( $time );
		}

		Utilities::log(
			array(
				'get_plugin_name'           => Utilities::get_plugin_name(),
				'get_version'               => Utilities::get_version(),
				'get_prefix'                => Utilities::get_prefix(),
				'get_plugin_file'           => Utilities::get_plugin_file(),
				'get_debug_mode'            => Utilities::get_debug_mode(),
				'get_plugin_initialization' => date( Utilities::get_date_time_format(), Utilities::get_plugin_initialization() ),

			),
			'Configuration Variables'
		);

	}

	/**
	 * The code that runs during plugin activation.
	 *
	 * Update DB code to use InnoDB Engine instead of MyISAM.
	 * Indexes updated
	 *
	 * @since    1.0.0
	 * @version 2.5
	 * @author Saad
	 */
	public function activation() {

		do_action( Utilities::get_prefix() . '_activation_before' );

		if ( InitializePlugin::DATABASE_VERSION !== get_option( 'uap_database_version', 0 ) ) {
			global $wpdb;
			$wpdb_collate = $wpdb->collate;

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Automator Recipe log
			$table_name = $wpdb->prefix . 'uap_recipe_log';


			//$sql = 'ALTER TABLE ' . $table_name . ' DROP INDEX user_id, DROP INDEX automator_recipe_id, DROP INDEX completed;';
			//dbDelta( $sql );

			$sql = 'CREATE TABLE ' . $table_name . ' (
				ID mediumint(8) unsigned NOT NULL auto_increment,
				date_time datetime DEFAULT "0000-00-00 00:00:00" NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				automator_recipe_id bigint(20) unsigned NOT NULL,
				completed tinyint(1) NOT NULL,
				run_number mediumint(8) unsigned NOT NULL DEFAULT "1",
				PRIMARY KEY  (ID),
				KEY completed (completed),
				KEY user_id (user_id),
				KEY automator_recipe_id (automator_recipe_id)
				) ENGINE=InnoDB COLLATE ' . $wpdb_collate . ';';

			dbDelta( $sql );

			//$sql = 'ALTER TABLE ' . $table_name . ' DROP count;';
			//dbDelta( $sql );
			//Change Engine to MyISAM
			//$sql = 'ALTER TABLE ' . $table_name . ' ENGINE=MyISAM;';
			//dbDelta( $sql );

			// Automator Trigger log
			$table_name = $wpdb->prefix . 'uap_trigger_log';

			//$sql = 'ALTER TABLE ' . $table_name . ' DROP INDEX user_id, DROP INDEX automator_trigger_id, DROP INDEX automator_recipe_id, DROP INDEX automator_recipe_log_id, DROP INDEX completed;';
			//dbDelta( $sql );

			$sql = 'CREATE TABLE ' . $table_name . ' (
				ID mediumint(8) unsigned NOT NULL auto_increment,
				date_time datetime DEFAULT "0000-00-00 00:00:00" NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				automator_trigger_id bigint(20) unsigned NOT NULL,
				automator_recipe_id bigint(20) unsigned NOT NULL,
				automator_recipe_log_id bigint(20) unsigned NULL,
				completed tinyint(1) unsigned NOT NULL,
				PRIMARY KEY  (ID),
				KEY user_id (user_id),
				KEY completed (completed),
				KEY automator_recipe_id (automator_recipe_id),
				KEY automator_recipe_log_id (automator_recipe_log_id)
				) ENGINE=InnoDB COLLATE ' . $wpdb_collate . ';';

			dbDelta( $sql );
			//Change Engine to MyISAM
			//$sql = 'ALTER TABLE ' . $table_name . ' ENGINE=MyISAM;';
			//dbDelta( $sql );

			//Automator trigger meta data log
			$table_name = $wpdb->prefix . 'uap_trigger_log_meta';

			//$sql = 'ALTER TABLE ' . $table_name . ' DROP INDEX user_id, DROP INDEX automator_trigger_log_id, DROP INDEX automator_trigger_id, DROP INDEX meta_key;';
			//dbDelta( $sql );

			$sql = 'CREATE TABLE ' . $table_name . ' (
				ID mediumint(8) unsigned NOT NULL auto_increment,
				user_id bigint(20) unsigned NOT NULL,
				automator_trigger_log_id bigint(20) unsigned NULL,
				automator_trigger_id bigint(20) unsigned NOT NULL,
				meta_key varchar(255) DEFAULT "" NOT NULL,
				meta_value longtext DEFAULT "" NOT NULL,
				run_number mediumint(8) unsigned NOT NULL DEFAULT "1",
				PRIMARY KEY  (ID),
				KEY user_id (user_id),
				KEY automator_trigger_id (automator_trigger_id),
				KEY automator_trigger_log_id (automator_trigger_log_id),
				KEY meta_key (meta_key(20))
				) ENGINE=InnoDB COLLATE ' . $wpdb_collate . ';';

			dbDelta( $sql );
			//Change Engine to MyISAM
			//$sql = 'ALTER TABLE ' . $table_name . ' ENGINE=MyISAM;';
			//dbDelta( $sql );

			// Automator Action log
			$table_name = $wpdb->prefix . 'uap_action_log';
			global $wpdb;
			if ( $wpdb->get_results( "SHOW TABLES LIKE '$table_name';" ) ) {
				if ( $wpdb->get_results( "SHOW INDEX FROM $table_name WHERE Key_name = 'error_message';" ) ) {
					$sql = 'ALTER TABLE ' . $table_name . ' DROP INDEX `error_message`;';
				}
				$wpdb->query( $sql );
			}

			$sql = 'CREATE TABLE ' . $table_name . ' (
				ID mediumint(8) unsigned NOT NULL auto_increment,
				date_time datetime DEFAULT "0000-00-00 00:00:00" NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				automator_action_id bigint(20) unsigned NOT NULL,
				automator_recipe_id bigint(20) unsigned NOT NULL,
				automator_recipe_log_id bigint(20) unsigned NULL,
				completed tinyint(1) unsigned NOT NULL,
				error_message longtext DEFAULT "" NOT NULL,
				PRIMARY KEY  (ID),
				KEY user_id (user_id),
				KEY completed (completed),
				KEY automator_recipe_log_id (automator_recipe_log_id),
				KEY automator_recipe_id (automator_recipe_id)
				) ENGINE=InnoDB COLLATE ' . $wpdb_collate . ';';

			dbDelta( $sql );

			//Change Engine to MyISAM
			//$sql = 'ALTER TABLE ' . $table_name . ' ENGINE=MyISAM;';
			//dbDelta( $sql );

			//Automator action meta data log
			$table_name = $wpdb->prefix . 'uap_action_log_meta';

			//$sql = 'ALTER TABLE ' . $table_name . ' DROP INDEX user_id, DROP INDEX automator_action_id, DROP INDEX automator_action_log_id, DROP INDEX meta_key;';
			//dbDelta( $sql );

			$sql = 'CREATE TABLE ' . $table_name . ' (
				ID mediumint(8) unsigned NOT NULL auto_increment,
				user_id bigint(20) unsigned NOT NULL,
				automator_action_log_id bigint(20) unsigned NULL,
				automator_action_id bigint(20) unsigned NOT NULL,
				meta_key varchar(255) DEFAULT "" NOT NULL,
				meta_value longtext DEFAULT "" NOT NULL,
				PRIMARY KEY  (ID),
				KEY user_id (user_id),
				KEY automator_action_log_id (automator_action_log_id),
				KEY meta_key (meta_key(20))
				) ENGINE=InnoDB COLLATE ' . $wpdb_collate . ';';

			dbDelta( $sql );
			//Change Engine to MyISAM
			//$sql = 'ALTER TABLE ' . $table_name . ' ENGINE=MyISAM;';
			//dbDelta( $sql );

			// Automator Closure Log
			$table_name = $wpdb->prefix . 'uap_closure_log';

			//$sql = 'ALTER TABLE ' . $table_name . ' DROP INDEX user_id, DROP INDEX automator_closure_id, DROP INDEX automator_recipe_id, DROP INDEX completed;';
			//dbDelta( $sql );

			$sql = 'CREATE TABLE ' . $table_name . ' (
				ID mediumint(8) unsigned NOT NULL auto_increment,
				date_time datetime DEFAULT "0000-00-00 00:00:00" NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				automator_closure_id bigint(20) unsigned NOT NULL,
				automator_recipe_id bigint(20) unsigned NOT NULL,
				completed tinyint(1) unsigned NOT NULL,
				PRIMARY KEY  (ID),
				KEY user_id (user_id),
				KEY automator_recipe_id (automator_recipe_id),
				KEY completed (completed)
				) ENGINE=InnoDB COLLATE ' . $wpdb_collate . ';';

			dbDelta( $sql );
			//Change Engine to MyISAM
			//$sql = 'ALTER TABLE ' . $table_name . ' ENGINE=MyISAM;';
			//dbDelta( $sql );

			//Automator closure meta data log
			$table_name = $wpdb->prefix . 'uap_closure_log_meta';

			//$sql = 'ALTER TABLE ' . $table_name . ' DROP INDEX user_id, DROP INDEX automator_closure_id, DROP INDEX meta_key;';
			//dbDelta( $sql );

			$sql = 'CREATE TABLE ' . $table_name . ' (
				ID mediumint(8) unsigned NOT NULL auto_increment,
				user_id bigint(20) unsigned NOT NULL,
				automator_closure_id bigint(20) unsigned NOT NULL,
				meta_key varchar(255) DEFAULT "" NOT NULL,
				meta_value longtext DEFAULT "" NOT NULL,
				PRIMARY KEY  (ID),
				KEY user_id (user_id),
				KEY meta_key (meta_key(15))
				)  ENGINE=InnoDB COLLATE ' . $wpdb_collate . ';';

			dbDelta( $sql );
			//Change Engine to MyISAM
			//$sql = "ALTER TABLE {$table_name} ENGINE=MyISAM;";
			//dbDelta( $sql );


			//$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = replace(meta_key, 'trigger_code', 'code')" );
			//$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = replace(meta_key, 'trigger_integration', 'integration')" );
			//$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = replace(meta_key, 'action_code', 'code')" );
			//$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = replace(meta_key, 'action_integration', 'integration')" );
			//$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = replace(meta_key, 'closure_code', 'code')" );
			//$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = replace(meta_key, 'closure_integration', 'integration')" );

			update_option( 'uap_database_version', InitializePlugin::DATABASE_VERSION );
		}

		do_action( Utilities::get_prefix() . '_activation_after' );
	}


	/**
	 * The code that runs during plugin deactivation.
	 * @since    1.0.0
	 */
	public function deactivation() {

		do_action( Utilities::get_prefix() . '_deactivation_before' );

		do_action( Utilities::get_prefix() . '_deactivation_after' );

	}
}
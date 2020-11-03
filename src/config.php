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
	 * @return Config $instance
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
	 * @version 2.5
	 * @version 2.9
	 * @author Saad
	 * @since 1.0.0
	 */
	public function activation() {
		$db_version = get_option( 'uap_database_version', null );
		if ( null !== $db_version && (string) InitializePlugin::DATABASE_VERSION === (string) $db_version ) {
			// bail. No db upgrade needed!
			return;
		}

		do_action( Utilities::get_prefix() . '_activation_before' );

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		// Automator Recipe log
		$tbl_recipe_log = $wpdb->prefix . 'uap_recipe_log';
		//Automator trigger log
		$tbl_trigger_log = $wpdb->prefix . 'uap_trigger_log';
		//Automator trigger meta data log
		$tbl_trigger_log_meta = $wpdb->prefix . 'uap_trigger_log_meta';
		// Automator Action log
		$tbl_action_log = $wpdb->prefix . 'uap_action_log';
		//Automator action meta data log
		$tbl_action_log_meta = $wpdb->prefix . 'uap_action_log_meta';
		// Automator Closure Log
		$tbl_closure_log = $wpdb->prefix . 'uap_closure_log';
		//Automator closure meta data log
		$tbl_closure_log_meta = $wpdb->prefix . 'uap_closure_log_meta';

		$sql = "CREATE TABLE {$tbl_recipe_log} (
`ID` bigint unsigned NOT NULL auto_increment,
`date_time` datetime DEFAULT CURRENT_TIMESTAMP,
`user_id` bigint unsigned NOT NULL,
`automator_recipe_id` bigint unsigned NOT NULL,
`completed` tinyint(1) NOT NULL,
`run_number` mediumint unsigned NOT NULL DEFAULT 1,
PRIMARY KEY  (`ID`),
KEY completed (`completed`),
KEY user_id (`user_id`),
KEY automator_recipe_id (`automator_recipe_id`)
) ENGINE=InnoDB {$charset_collate};
CREATE TABLE {$tbl_trigger_log} (
`ID` bigint unsigned NOT NULL auto_increment,
`date_time` datetime DEFAULT CURRENT_TIMESTAMP,
`user_id` bigint unsigned NOT NULL,
`automator_trigger_id` bigint unsigned NOT NULL,
`automator_recipe_id` bigint unsigned NOT NULL,
`automator_recipe_log_id` bigint unsigned NULL,
`completed` tinyint(1) unsigned NOT NULL,
PRIMARY KEY  (`ID`),
KEY user_id (`user_id`),
KEY completed (`completed`),
KEY automator_recipe_id (`automator_recipe_id`),
KEY automator_recipe_log_id (`automator_recipe_log_id`)
) ENGINE=InnoDB {$charset_collate};
CREATE TABLE {$tbl_trigger_log_meta} (
`ID` bigint unsigned NOT NULL auto_increment,
`user_id` bigint unsigned NOT NULL,
`automator_trigger_log_id` bigint unsigned NULL,
`automator_trigger_id` bigint unsigned NOT NULL,
`meta_key` varchar(255) DEFAULT '' NOT NULL,
`meta_value` longtext NULL,
`run_number` mediumint unsigned NOT NULL DEFAULT 1,
`run_time` datetime DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (`ID`),
KEY user_id (`user_id`),
KEY automator_trigger_id (`automator_trigger_id`),
KEY automator_trigger_log_id (`automator_trigger_log_id`),
KEY meta_key (meta_key(20))
) ENGINE=InnoDB {$charset_collate};
CREATE TABLE {$tbl_action_log} (
`ID` bigint unsigned NOT NULL auto_increment,
`date_time` datetime DEFAULT CURRENT_TIMESTAMP,
`user_id` bigint unsigned NOT NULL,
`automator_action_id` bigint unsigned NOT NULL,
`automator_recipe_id` bigint unsigned NOT NULL,
`automator_recipe_log_id` bigint unsigned NULL,
`completed` tinyint(1) unsigned NOT NULL,
`error_message` longtext NULL,
PRIMARY KEY  (`ID`),
KEY user_id (`user_id`),
KEY completed (`completed`),
KEY automator_recipe_log_id (`automator_recipe_log_id`),
KEY automator_recipe_id (`automator_recipe_id`)
) ENGINE=InnoDB {$charset_collate};
CREATE TABLE {$tbl_action_log_meta} (
`ID` bigint unsigned NOT NULL auto_increment,
`user_id` bigint unsigned NOT NULL,
`automator_action_log_id` bigint unsigned NULL,
`automator_action_id` bigint unsigned NOT NULL,
`meta_key` varchar(255) DEFAULT '' NOT NULL,
`meta_value` longtext NULL,
PRIMARY KEY  (`ID`),
KEY user_id (`user_id`),
KEY automator_action_log_id (`automator_action_log_id`),
KEY meta_key (meta_key(20))
) ENGINE=InnoDB {$charset_collate};
CREATE TABLE {$tbl_closure_log} (
`ID` bigint unsigned NOT NULL auto_increment,
`date_time` datetime DEFAULT CURRENT_TIMESTAMP,
`user_id` bigint unsigned NOT NULL,
`automator_closure_id` bigint unsigned NOT NULL,
`automator_recipe_id` bigint unsigned NOT NULL,
`completed` tinyint(1) unsigned NOT NULL,
PRIMARY KEY  (`ID`),
KEY user_id (`user_id`),
KEY automator_recipe_id (`automator_recipe_id`),
KEY completed (`completed`)
) ENGINE=InnoDB {$charset_collate};
CREATE TABLE {$tbl_closure_log_meta} (
`ID` bigint unsigned NOT NULL auto_increment,
`user_id` bigint unsigned NOT NULL,
`automator_closure_id` bigint unsigned NOT NULL,
`meta_key` varchar(255) DEFAULT '' NOT NULL,
`meta_value` longtext NULL,
PRIMARY KEY  (`ID`),
KEY user_id (`user_id`),
KEY meta_key (meta_key(15))
) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'uap_database_version', InitializePlugin::DATABASE_VERSION );

		do_action( Utilities::get_prefix() . '_activation_after' );
	}

	/**
	 * Added this to fix MySQL 8 AUTO_INCREMENT issue
	 * with already created tables
	 *
	 * @since 2.9
	 * @author Saad S.
	 */
	public function mysql_8_auto_increment_fix() {
		global $wpdb;

		$wpdb->query( "ANALYZE TABLE `{$wpdb->prefix}uap_recipe_log`;" );
		$wpdb->query( "ANALYZE TABLE `{$wpdb->prefix}uap_action_log`;" );
		$wpdb->query( "ANALYZE TABLE `{$wpdb->prefix}uap_action_log_meta`;" );
		$wpdb->query( "ANALYZE TABLE `{$wpdb->prefix}uap_closure_log`;" );
		$wpdb->query( "ANALYZE TABLE `{$wpdb->prefix}uap_closure_log_meta`;" );
		$wpdb->query( "ANALYZE TABLE `{$wpdb->prefix}uap_trigger_log`;" );
		$wpdb->query( "ANALYZE TABLE `{$wpdb->prefix}uap_trigger_log_meta`;" );
	}

	/**
	 * Call views instead of complex queries on log pages
	 *
	 * @version 2.5.1
	 * @author Saad
	 */
	public function automator_generate_views() {

		do_action( Utilities::get_prefix() . '_database_views_before' );

		if ( InitializePlugin::DATABASE_VIEWS_VERSION !== get_option( 'uap_database_views_version', 0 ) ) {
			global $wpdb;

			$recipe_view       = "{$wpdb->prefix}uap_recipe_logs_view";
			$recipe_view_query = "SELECT r.user_id, 
							r.date_time AS recipe_date_time, 
							r.completed AS recipe_completed, 
							r.run_number, 
							r.completed, 
							r.automator_recipe_id, 
							u.user_email, 
							u.display_name, 
							p.post_title AS recipe_title 
					FROM {$wpdb->prefix}uap_recipe_log r
					LEFT JOIN {$wpdb->users} u
					ON u.ID = r.user_id  
					JOIN {$wpdb->posts} p
					ON p.ID = r.automator_recipe_id";
			$wpdb->query( "CREATE OR REPLACE VIEW $recipe_view AS $recipe_view_query" );

			$trigger_view       = "{$wpdb->prefix}uap_trigger_logs_view";
			$trigger_view_query = "SELECT u.ID AS user_id, u.user_email, 
                            u.display_name, 
                            t.automator_trigger_id, 
                            t.date_time AS trigger_date, 
                            t.completed AS trigger_completed, 
                            t.automator_recipe_id, 
                            t.ID, 
                            pt.post_title AS trigger_title, 
                            tm.meta_value AS trigger_sentence, 
                            tm.run_number AS trigger_run_number,
                            tm.run_time AS trigger_run_time,
                            pm.meta_value AS trigger_total_times,
                            p.post_title AS recipe_title, 
                            r.date_time AS recipe_date_time, 
                            r.completed AS recipe_completed, 
                            r.run_number AS recipe_run_number
                        FROM {$wpdb->prefix}uap_trigger_log t
                        LEFT JOIN {$wpdb->users} u
                        ON u.ID = t.user_id
                        LEFT JOIN {$wpdb->posts} p
                        ON p.ID = t.automator_recipe_id
                        LEFT JOIN {$wpdb->posts} pt
                        ON pt.ID = t.automator_trigger_id
                        LEFT JOIN {$wpdb->prefix}uap_trigger_log_meta tm
						ON tm.automator_trigger_log_id = t.ID AND tm.meta_key = 'sentence_human_readable'
                        LEFT JOIN {$wpdb->prefix}uap_recipe_log r
                        ON t.automator_recipe_log_id = r.ID
                        LEFT JOIN {$wpdb->postmeta} pm
                        ON pm.post_id = t.automator_trigger_id AND pm.meta_key = 'NUMTIMES'";

			$wpdb->query( "CREATE OR REPLACE VIEW $trigger_view AS $trigger_view_query" );

			$action_view       = "{$wpdb->prefix}uap_action_logs_view";
			$action_view_query = "SELECT a.automator_action_id,
					a.date_time AS action_date, 
					a.completed AS action_completed, 
					a.error_message, 
					a.automator_recipe_id,
					r.date_time AS recipe_date_time, 
					r.completed AS recipe_completed, 
					r.run_number AS recipe_run_number,
					pa.post_title AS action_title,
					am.meta_value AS action_sentence, 
					p.post_title AS recipe_title, 
					u.ID AS user_id, 
					u.user_email, 
					u.display_name            
			FROM {$wpdb->prefix}uap_action_log a
			LEFT JOIN {$wpdb->prefix}uap_recipe_log r
			ON a.automator_recipe_log_id = r.ID
			LEFT JOIN {$wpdb->posts} p
			ON p.ID = a.automator_recipe_id
			JOIN {$wpdb->posts} pa
			ON pa.ID = a.automator_action_id
			LEFT JOIN {$wpdb->prefix}uap_action_log_meta am
			ON am.automator_action_id = a.automator_action_id  AND am.meta_key = 'sentence_human_readable'
			LEFT JOIN {$wpdb->users} u
			ON a.user_id = u.ID
			GROUP BY a.ID";

			$wpdb->query( "CREATE OR REPLACE VIEW $action_view AS $action_view_query" );

			update_option( 'uap_database_views_version', InitializePlugin::DATABASE_VIEWS_VERSION );

		}

		do_action( Utilities::get_prefix() . '_activation_views_after' );

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
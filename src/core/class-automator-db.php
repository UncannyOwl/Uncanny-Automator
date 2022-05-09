<?php

namespace Uncanny_Automator;

/**
 * This class is used to run any configurations before the plugin is initialized
 *
 * @package Uncanny_Automator
 */
class Automator_DB {

	/**
	 * The instance of the class
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      \Uncanny_Automator\Automator_DB
	 */
	private static $instance;

	/**
	 * Creates singleton instance of class
	 *
	 * @return Automator_DB $instance
	 * @since 1.0.0
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Validates if all Automator tables exists
	 *
	 * @param false $execute
	 *
	 * @return array
	 * @since 3.0
	 */
	public static function verify_base_tables( $execute = false ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( $execute ) {
			self::create_tables();
			self::create_views();
		}
		$queries        = dbDelta( self::get_schema(), false );
		$missing_tables = array();
		foreach ( $queries as $table_name => $result ) {
			if ( "Created table $table_name" === $result ) {
				$missing_tables[] = $table_name;
			}
		}

		if ( 0 < count( $missing_tables ) ) {
			update_option( 'automator_schema_missing_tables', $missing_tables );
		} else {
			update_option( 'uap_database_version', AUTOMATOR_DATABASE_VERSION );
			delete_option( 'automator_schema_missing_tables' );
			delete_option( 'automator_schema_missing_views' );
		}

		return $missing_tables;
	}

	/**
	 * @return array
	 */
	public static function verify_base_views() {
		$missing_views = self::all_views( true );

		if ( ! empty( $missing_views ) ) {
			update_option( 'automator_schema_missing_views', $missing_views );
		}

		return $missing_views;
	}

	/**
	 * Return create queries for Automator tables
	 *
	 * @return string
	 * @since 3.0
	 */
	public static function get_schema() {
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

		return "CREATE TABLE {$tbl_recipe_log} (
`ID` bigint unsigned NOT NULL auto_increment,
`date_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
`date_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`user_id` bigint unsigned NOT NULL,
`automator_trigger_id` bigint unsigned NOT NULL,
`automator_recipe_id` bigint unsigned NOT NULL,
`automator_recipe_log_id` bigint unsigned NULL,
`completed` tinyint(1) unsigned NOT NULL,
PRIMARY KEY  (`ID`),
KEY user_id (`user_id`),
KEY completed (`completed`),
KEY automator_recipe_id (`automator_recipe_id`),
KEY automator_trigger_id (`automator_trigger_id`),
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
`run_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (`ID`),
KEY user_id (`user_id`),
KEY run_number (`run_number`),
KEY automator_trigger_id (`automator_trigger_id`),
KEY automator_trigger_log_id (`automator_trigger_log_id`),
KEY meta_key (meta_key(20))
) ENGINE=InnoDB {$charset_collate};
CREATE TABLE {$tbl_action_log} (
`ID` bigint unsigned NOT NULL auto_increment,
`date_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`user_id` bigint unsigned NOT NULL,
`automator_action_id` bigint unsigned NOT NULL,
`automator_recipe_id` bigint unsigned NOT NULL,
`automator_recipe_log_id` bigint unsigned NULL,
`completed` tinyint(1) unsigned NOT NULL,
`error_message` longtext NULL,
PRIMARY KEY  (`ID`),
KEY user_id (`user_id`),
KEY completed (`completed`),
KEY automator_action_id (`automator_action_id`),
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
KEY automator_action_id (`automator_action_id`),
KEY meta_key (meta_key(20))
) ENGINE=InnoDB {$charset_collate};
CREATE TABLE {$tbl_closure_log} (
`ID` bigint unsigned NOT NULL auto_increment,
`date_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
`user_id` bigint unsigned NOT NULL,
`automator_closure_id` bigint unsigned NOT NULL,
`automator_recipe_id` bigint unsigned NOT NULL,
`automator_recipe_log_id` bigint unsigned NOT NULL,
`completed` tinyint(1) unsigned NOT NULL,
PRIMARY KEY  (`ID`),
KEY user_id (`user_id`),
KEY automator_recipe_id (`automator_recipe_id`),
KEY automator_closure_id (`automator_closure_id`),
KEY completed (`completed`)
) ENGINE=InnoDB {$charset_collate};
CREATE TABLE {$tbl_closure_log_meta} (
`ID` bigint unsigned NOT NULL auto_increment,
`user_id` bigint unsigned NOT NULL,
`automator_closure_id` bigint unsigned NOT NULL,
`automator_closure_log_id` bigint unsigned NOT NULL,
`meta_key` varchar(255) DEFAULT '' NOT NULL,
`meta_value` longtext NULL,
PRIMARY KEY  (`ID`),
KEY user_id (`user_id`),
KEY automator_closure_id (`automator_closure_id`),
KEY meta_key (meta_key(15))
) ENGINE=InnoDB {$charset_collate};";
	}

	/**
	 * The code that runs during plugin activation.
	 *
	 * Update DB code to use InnoDB Engine instead of MyISAM.
	 * Indexes updated
	 *
	 * @since    1.0.0
	 * @version  2.5
	 * @author   Saad
	 */
	public static function activation() {

		update_option( 'automator_over_time', array( 'installed_date' => time() ) );

		$db_version = get_option( 'uap_database_version', null );
		if ( null !== $db_version && (string) AUTOMATOR_DATABASE_VERSION === (string) $db_version ) {
			// bail. No db upgrade needed!
			return;
		}

		do_action( 'automator_activation_before' );
		self::create_tables();

		do_action( 'automator_activation_after' );
	}

	/**
	 * Create tables
	 *
	 * @since 3.0
	 */
	public static function create_tables() {
		$sql = self::get_schema();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'uap_database_version', AUTOMATOR_DATABASE_VERSION );
	}

	/**
	 * Added this to fix MySQL 8 AUTO_INCREMENT issue
	 * with already created tables
	 *
	 * @since  2.9
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
	 * @author  Saad
	 */
	public function automator_generate_views() {

		do_action( 'automator_database_views_before' );

		if ( AUTOMATOR_DATABASE_VIEWS_VERSION !== get_option( 'uap_database_views_version', 0 ) ) {
			self::create_views();
		}

		do_action( 'automator_activation_views_after' );

	}

	/**
	 * Generate VIEWS
	 *
	 * @since 3.0
	 */
	public static function create_views() {
		global $wpdb;

		$recipe_view       = "{$wpdb->prefix}uap_recipe_logs_view";
		$recipe_view_query = self::recipe_log_view_query();
		$wpdb->query( "CREATE OR REPLACE VIEW $recipe_view AS $recipe_view_query" ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$trigger_view       = "{$wpdb->prefix}uap_trigger_logs_view";
		$trigger_view_query = self::trigger_log_view_query();

		$wpdb->query( "CREATE OR REPLACE VIEW $trigger_view AS $trigger_view_query" ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$action_view       = "{$wpdb->prefix}uap_action_logs_view";
		$action_view_query = self::action_log_view_query();

		$wpdb->query( "CREATE OR REPLACE VIEW $action_view AS $action_view_query" ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		update_option( 'uap_database_views_version', AUTOMATOR_DATABASE_VIEWS_VERSION );
	}

	/**
	 * @return string
	 */
	public static function recipe_log_view_query() {
		global $wpdb;

		return apply_filters(
			'automator_recipe_log_view_query',
			"SELECT
       r.ID AS recipe_log_id,
       r.user_id,
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
        ON p.ID = r.automator_recipe_id"
		);
	}

	/**
	 * @return string
	 */
	public static function trigger_log_view_query() {
		global $wpdb;

		return apply_filters(
			'automator_trigger_log_view_query',
			"SELECT u.ID AS user_id, u.user_email,
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
                            t.automator_recipe_log_id AS recipe_log_id,
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
                        ON pm.post_id = t.automator_trigger_id AND pm.meta_key = 'NUMTIMES'"
		);
	}

	/**
	 * @param $group_by
	 *
	 * @return string
	 */
	public static function action_log_view_query( $group_by = true ) {
		global $wpdb;
		$qry = "SELECT a.automator_action_id,
					a.date_time AS action_date,
					a.completed AS action_completed,
					a.error_message,
					a.automator_recipe_id,
					a.ID AS action_log_id,
					a.automator_recipe_log_id AS recipe_log_id,
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
			ON a.automator_action_id = am.automator_action_id AND am.automator_action_log_id = a.ID AND am.user_id = a.user_id AND am.meta_key = 'sentence_human_readable_html'
			LEFT JOIN {$wpdb->users} u
			ON a.user_id = u.ID";
		if ( $group_by ) {
			$qry .= ' GROUP BY a.ID';
		}

		return apply_filters(
			'automator_action_log_view_query',
			$qry
		);
	}

	/**
	 * Check if specific VIEW is missing.
	 *
	 * @param $type
	 *
	 * @return bool
	 */
	public static function is_view_exists( $type = 'recipe' ) {
		global $wpdb;
		$recipe_view = '';
		if ( 'recipe' === $type ) {
			$recipe_view = "{$wpdb->prefix}uap_recipe_logs_view";
		}
		if ( 'trigger' === $type ) {
			$recipe_view = "{$wpdb->prefix}uap_trigger_logs_view";
		}
		if ( 'action' === $type ) {
			$recipe_view = "{$wpdb->prefix}uap_action_logs_view";
		}

		if ( empty( $recipe_view ) ) {
			return false;
		}
		$results = self::all_views( true );
		if ( ! in_array( $recipe_view, $results, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if all Automator VIEWS exists. Return empty if all VIEWS exists else only the ones that are missing.
	 *
	 * @param $return_missing
	 *
	 * @return array
	 * @version 3.0
	 */
	public static function all_views( $return_missing = false ) {
		global $wpdb;
		$db      = DB_NAME;
		$results = $wpdb->get_results( "SHOW FULL TABLES IN `$db` WHERE TABLE_TYPE LIKE '%VIEW%'" ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$return  = array(
			"{$wpdb->prefix}uap_recipe_logs_view",
			"{$wpdb->prefix}uap_trigger_logs_view",
			"{$wpdb->prefix}uap_action_logs_view",
		);

		if ( ! $results ) {
			return $return_missing ? $return : array();
		}
		foreach ( $results as $r ) {
			if ( ! is_object( $r ) ) {
				continue;
			}
			foreach ( $r as $rr ) {
				$return = array_diff( $return, array( $rr ) );
			}
		}

		return $return;
	}
}

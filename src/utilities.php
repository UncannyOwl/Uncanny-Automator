<?php

namespace Uncanny_Automator;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * This class stores helper functions that can be used statically in all of WP after plugins loaded hook
 *
 * Use the Utilites::get_% function to retrieve the variable. The following is a list of calls
 *
 * @package    Private_Plugin_Boilerplate
 */
class Utilities {

	/**
	 * The name of this plugin that is set in the main plugin file
	 *
	 * @use get_plugin_name()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private static $plugin_name;

	/**
	 * The prefix of this plugin that is set in the main plugin file
	 *
	 * @use get_version()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private static $prefix;

	/**
	 * The plugins version number that is set in the main plugin file
	 *
	 * @use get_version()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private static $version;

	/**
	 * The main plugin file path that is set in the main plugin file
	 *
	 * @use get_plugin_file()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private static $plugin_file;

	/**
	 * The references to autoloaded class instances
	 *
	 * @use get_autoloaded_class_instance()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array
	 */
	private static $class_instances = array();

	/**
	 * The references to autoloaded options class instances
	 *
	 * @use get_autoloaded_class_instance()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array
	 */
	private static $helper_instances = array();

	/**
	 * The plugin specific debug mode that is set in the main plugin file
	 *
	 * @use get_debug_mode()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool
	 */
	private static $debug_mode = false;

	/**
	 * The plugin date and time format
	 *
	 * @use get_date_time_format()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool
	 */
	private static $date_time_format;

	/**
	 * The plugin date format
	 *
	 * @use get_date_format()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool
	 */
	private static $date_format;

	/**
	 * The plugin time format
	 *
	 * @use get_time_format()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool
	 */
	private static $time_format;

	/**
	 * The server time when the plugin was initialized
	 *
	 * @use get_time_format()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool
	 */
	private static $plugin_initialization;

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
	 * @return Utilities $instance
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
	 * Set the name of the plugin
	 *
	 * @param string $plugin_name The name of the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function set_plugin_name( $plugin_name ) {
		if ( null === self::$prefix ) {
			self::$plugin_name = $plugin_name;
		}

		return self::$plugin_name;
	}

	/**
	 * Get the name of the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function get_plugin_name() {
		return self::$plugin_name;
	}

	/**
	 * Set the prefix for the plugin
	 *
	 * @param string $prefix Variable used to prefix filters and actions
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function set_prefix( $prefix ) {
		if ( null === self::$prefix ) {
			self::$prefix = $prefix;
		}

		return self::$prefix;
	}

	/**
	 * Get the prefix for the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function get_prefix() {
		return self::$prefix;
	}

	/**
	 * Set the version for the plugin
	 *
	 * @param string $version Variable used to prefix filters and actions
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function set_version( $version ) {
		if ( null === self::$version ) {
			self::$version = $version;
		}

		return self::$version;
	}

	/**
	 * Get the version for the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function get_version() {
		return self::$version;
	}


	/**
	 * Set the main plugin file path
	 *
	 * @param string $plugin_file The main plugin file path
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function set_plugin_file( $plugin_file ) {
		if ( null === self::$plugin_file ) {
			self::$plugin_file = $plugin_file;
		}

		return self::$plugin_file;
	}

	/**
	 * Get the main plugin file path
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function get_plugin_file() {
		return self::$plugin_file;
	}

	/**
	 * Adds the autoloaded class in an accessible object
	 *
	 * @param string $class_name The name of the class instance
	 * @param object $class_instance The reference to the class instance
	 *
	 * @since    1.0.0
	 *
	 */
	public static function add_class_instance( $class_name, $class_instance ) {

		self::$class_instances[ $class_name ] = $class_instance;

	}

	/**
	 * Adds the autoloaded class in an accessible object
	 *
	 * @param string $integration The name of the class instance
	 * @param object $class_instance The reference to the class instance
	 *
	 * @since    2.1.0
	 *
	 */
	public static function add_helper_instance( $integration, $class_instance ) {

		self::$helper_instances[ $integration ] = $class_instance;

	}

	/**
	 * Get all class instances
	 *
	 * @return array
	 * @since    1.0.0
	 *
	 */
	public static function get_all_class_instances() {
		return self::$class_instances;
	}

	/**
	 * Get all class instances
	 *
	 * @return array
	 * @since    2.1.0
	 *
	 */
	public static function get_all_helper_instances() {
		return self::$helper_instances;
	}

	/**
	 * Get a specific class instance
	 *
	 * @param string $class_name The name of the class instance
	 *
	 * @return object | bool
	 * @since    1.0.0
	 *
	 */
	public static function get_class_instance( $class_name ) {
		if ( isset( self::$class_instances[ $class_name ] ) ) {
			return self::$class_instances[ $class_name ];
		} else {
			return false;
		}
	}

	/**
	 * Set the default date and time format
	 *
	 * @param string $date Date format
	 * @param string $time Time format
	 * @param string $separator The separator between the date and time format
	 *
	 * @return bool
	 * @since    1.0.0
	 *
	 */
	public static function set_date_time_format( $date = 'F j, Y', $time = ' g:i a', $separator = ' ' ) {

		$date      = apply_filters( self::$prefix . '_date_time_format', $date );
		$time      = apply_filters( self::$prefix . '_date_time_format', $time );
		$separator = apply_filters( self::$prefix . '_date_time_format', $separator );

		if ( null === self::$date_time_format ) {
			self::$date_time_format = $date . $separator . $time;
		}

		if ( null === self::$date_format ) {
			self::$date_format = $date;
		}

		if ( null === self::$time_format ) {
			self::$time_format = $time;
		}

		return self::$date_time_format;
	}

	/**
	 * Get the date and time format for the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function get_date_time_format() {
		return self::$date_time_format;
	}

	/**
	 * Get the date format for the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function get_date_format() {
		return self::$date_time_format;
	}

	/**
	 * Get the time format for the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function get_time_format() {
		return self::$date_time_format;
	}

	/**
	 * Set debug mode
	 *
	 * @param bool $debug_mode
	 *
	 * @return bool
	 * @since    1.0.0
	 *
	 */
	public static function set_debug_mode( $debug_mode ) {

		if ( null === self::$debug_mode ) {

			self::$debug_mode = $debug_mode;
		}

		return self::$debug_mode;
	}

	/**
	 * Get debug mode
	 *
	 * @return bool
	 * @since    1.0.0
	 *
	 */
	public static function get_debug_mode() {
		return self::$debug_mode;
	}

	/**
	 * Set the server time when the plugin was initialized
	 *
	 * @param int $time Timestamp
	 *
	 * @return int
	 * @since    1.0.0
	 *
	 */
	public static function set_plugin_initialization( $time ) {

		if ( null === self::$plugin_initialization ) {
			self::$plugin_initialization = $time;
		}

		return self::$plugin_initialization;
	}

	/**
	 * Get the server time when the plugin was initialized
	 *
	 * @return int Timestamp
	 * @since    1.0.0
	 *
	 */
	public static function get_plugin_initialization() {
		return self::$plugin_initialization;
	}

	/**
	 * Returns the full url for the passed CSS file
	 *
	 * @param string $file_name
	 *
	 * @return string $asset_url
	 * @since    1.0.0
	 *
	 */
	public static function get_css( $file_name ) {
		$asset_url = plugins_url( 'assets/css/' . $file_name, __FILE__ );

		return $asset_url;
	}

	/**
	 * Returns the full url for the passed JS file
	 *
	 * @param string $file_name
	 *
	 * @return string $asset_url
	 * @since    1.0.0
	 *
	 */
	public static function get_js( $file_name ) {
		$asset_url = plugins_url( 'assets/js/' . $file_name, __FILE__ );

		return $asset_url;
	}

	/**
	 * Returns the full url for the recipe UI dist directory
	 *
	 * @param string $file_name
	 *
	 * @return string $asset_url
	 * @since    1.0.0
	 *
	 */
	public static function get_recipe_dist( $file_name ) {
		$asset_url = plugins_url( 'recipe-ui/dist/' . $file_name, __FILE__ );

		return $asset_url;
	}

	/**
	 * Returns the full url for the passed Icon within recipe UI
	 *
	 * @param string $file_name
	 *
	 * @return string $asset_url
	 * @since    1.0.0
	 *
	 */
	public static function get_integration_icon( $file_name ) {
		$asset_url = plugins_url( 'recipe-ui/dist/media/integrations/' . $file_name, __FILE__ );

		return $asset_url;
	}

	/**
	 * Returns the full url for the passed media file
	 *
	 * @param string $file_name
	 *
	 * @return string $asset_url
	 * @since    1.0.0
	 *
	 */
	public static function get_media( $file_name ) {
		$asset_url = plugins_url( 'assets/img/' . $file_name, __FILE__ );

		return $asset_url;
	}

	/**
	 * Returns the full url for the passed vendor file
	 *
	 * @since    1.0.0
	 *
	 */
	public static function get_vendor_asset( $file_name ) {
		$asset_url = plugins_url( 'assets/vendor/' . $file_name, __FILE__ );

		return $asset_url;
	}

	/**
	 * Enqueues global JS and CSS files
	 *
	 * @param string $file_name
	 *
	 * @return string $asset_url
	 * @since    1.0.0
	 *
	 */
	public static function enqueue_global_assets() {
		wp_enqueue_style( 'uap-admin-global', Utilities::get_css( 'admin/global.css' ), array(), Utilities::get_version() );
		wp_enqueue_script( 'uap-admin-global', Utilities::get_js( 'admin/global.js' ), array( 'jquery' ), Utilities::get_version(), true );

		wp_localize_script( 'uap-admin-global', 'UncannyAutomatorGlobal', [
			'rest' => [
				'url'   => esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ),
				'nonce' => \wp_create_nonce( 'wp_rest' ),
			]
		] );
	}

	/**
	 * Returns the full server path for the passed view file
	 *
	 * @param string $file_name
	 *
	 * @return string
	 */
	public static function get_view( $file_name ) {

		$views_directory = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;

		/**
		 * Filters the directory path to the view file
		 *
		 * This can be used for view overrides by modifying the path to go to a directory in the theme or another plugin.
		 *
		 * @param string $views_directory Path to the plugins view folder
		 * @param string $file_name The file name of the view file
		 *
		 * @since 1.0.0
		 *
		 */
		$views_directory = apply_filters( Utilities::get_prefix() . '_view_path', $views_directory, $file_name );

		$asset_path = $views_directory . $file_name;

		return $asset_path;
	}

	/**
	 * Returns the full server path for the passed include file
	 *
	 * @param string $file_name
	 *
	 * @return string
	 */
	public static function get_include( $file_name ) {

		$includes_directory = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;

		/**
		 * Filters the director path to the include file
		 *
		 * This can be used for include overrides by modifying the path to go to a directory in the theme or another plugin.
		 *
		 * @param string $includes_directory Path to the plugins include folder
		 * @param string $file_name The file name of the include file
		 *
		 * @since 1.0.0
		 *
		 */
		$includes_directory = apply_filters( Utilities::get_prefix() . '_includes_path_to', $includes_directory, $file_name );

		$asset_path = $includes_directory . $file_name;

		return $asset_path;
	}

	/**
	 * !!! ALPHA FUNCTION - NEEDS TESTING/BENCHMARKING
	 *
	 * Get User data with meta keys' value
	 *
	 * In some cases we need to loop a lot of users' data. If we need 1000 user with there user meta values we would
	 * normally run WP User Query, then loop the user and run get_user_meta() on each iteration which will return the
	 * specified user meta and also collect/store ALL the user meta. In case above, WP will run 1 query for the user loop
	 * and 1000 user meta queries; 1001 queries will run. WP will also store all the data collected in memory, if each
	 * user has 100 metas stores then 1000 x 100 metas is 100 000 values.
	 *
	 * With this function if we run the same scenrio as above, 2 quieries will run and only the amount of data points
	 * that are specifically needed. 1000 users
	 *
	 * Todo Maybe add optional transient
	 * Todo Benchmarking needed
	 *
	 * Only Returns this first meta_key value. Does not support multiple meta_values per single key.
	 *
	 * @param array $exact_meta_keys
	 * @param array $fuzzy_meta_keys
	 * @param array $included_user_ids
	 *
	 * @return array
	 */
	function get_users_with_meta( $exact_meta_keys = array(), $fuzzy_meta_keys = array(), $included_user_ids = array() ) {

		global $wpdb;

		// Collect all possible meta_key values
		$keys = $wpdb->get_col( "SELECT distinct meta_key FROM $wpdb->usermeta" );

		//then prepare the meta keys query as fields which we'll join to the user table fields
		$meta_columns = '';
		foreach ( $keys as $key ) {

			// Collect exact matches
			if ( ! empty( $exact_meta_keys ) ) {
				if ( in_array( $key, $exact_meta_keys ) ) {
					$meta_columns .= " MAX(CASE WHEN um1.meta_key = '$key' THEN um1.meta_value ELSE NULL END) AS '$key', \n";
					continue;
				}
			}

			// Collect fuzzy matches ... ex. "example" would match "example_947"
			// ToDo allow for SQL "LIKE" syntax ... ex "example%947"
			// ToDo allow for regex
			if ( ! empty( $fuzzy_meta_keys ) ) {
				foreach ( $fuzzy_meta_keys as $fuzzy_key ) {
					if ( false !== strpos( $key, $fuzzy_key ) ) {
						$meta_columns .= " MAX(CASE WHEN um1.meta_key = '$key' THEN um1.meta_value ELSE NULL END) AS '$key', \n";
					}
				}

			}


		}

		$user_ids = '';

		if ( ! empty( $include_user_ids ) ) {
			$user_ids = 'WHERE u.ID IN (\'' . implode( ',', $include_user_ids ) . '\')';
		}
		//then write the main query with all of the regular fields and use a simple left join on user users.ID and usermeta.user_id
		$query = "
					SELECT  
					    u.ID,
					    u.user_login,
					    u.user_pass,
					    u.user_nicename,
					    u.user_email,
					    u.user_url,
					    u.user_registered,
					    u.user_activation_key,
					    u.user_status,
					    u.display_name,
					    " . rtrim( $meta_columns, ", \n" ) . " 
					FROM 
					    $wpdb->users u
					LEFT JOIN 
					    $wpdb->usermeta um ON (um.user_id = u.ID)    
					$user_ids
					GROUP BY 
					    u.ID";

		$users = $wpdb->get_results( $query, ARRAY_A );

		return array(
			'query'   => $query,
			'results' => $users
		);


	}

	/**
	 * Create and store logs @ wp-content/{plugin_folder_name}/uo-{$file_name}.log
	 *
	 * @param string $trace_message The message logged
	 * @param string $trace_heading The heading of the current trace
	 * @param bool $force_log Create log even if debug mode is off
	 * @param string $file_name The file name of the log file
	 *
	 * @return bool $error_log Was the log successfully created
	 * @since    1.0.0
	 *
	 */
	public static function log( $trace_message = '', $trace_heading = '', $force_log = false, $file_name = 'logs' ) {

		// Only return log if debug mode is on OR if log is forced
		if ( ! $force_log ) {

			if ( ! self::get_debug_mode() ) {
				return false;
			}
		}

		$timestamp = date( self::get_date_time_format(), current_time( 'timestamp' ) );

		$current_page_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

		$trace_start = "\n===========================<<<< $timestamp >>>>===========================\n";

		$trace_heading = "* Heading: $trace_heading \n";

		$trace_heading .= "* Current Page: $current_page_link \n";


		$trace_heading .= "* Plugin Initialized: " . date( self::get_date_time_format(), self::get_plugin_initialization() ) . "\n";

		$trace_end = "\n===========================<<<< TRACE END >>>>===========================\n\n";

		$trace_message = print_r( $trace_message, true );

		//$file = dirname( self::get_plugin_file() ) . '/uo-' . $file_name . '.log';
		$file = WP_CONTENT_DIR . '/uo-' . $file_name . '.log';

		error_log( $trace_start . $trace_heading . $trace_message . $trace_end, 3, $file );
	}

}
<?php

namespace Uncanny_Automator;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * This class stores helper functions that can be used statically in all of WP after plugins loaded hook
 *
 * Use the Utilites::automator_get_% function to retrieve the variable. The following is a list of calls
 *
 */
class Utilities {

	/**
	 * The references to autoloaded class instances
	 *
	 * @use      get_autoloaded_class_instance()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array
	 */
	private static $class_instances = array();

	/**
	 * The references to autoloaded options class instances
	 *
	 * @use      get_autoloaded_class_instance()
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array
	 */
	private static $helper_instances = array();

	/**
	 * The instance of the class
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      \Uncanny_Automator\Utilities
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
	 * Utilities constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'automator_enqueue_frontend_assets' ) );
	}

	/**
	 * Adds the autoloaded class in an accessible object
	 *
	 * @param $class_name
	 * @param object $class_instance
	 *
	 * @since    1.0.0
	 */
	public static function automator_add_class_instance( $class_name, $class_instance ) {
		self::$class_instances[ $class_name ] = $class_instance;
	}

	/**
	 * Adds the autoloaded class in an accessible object
	 *
	 * @param $integration
	 * @param object $class_instance
	 *
	 * @since    2.1.0
	 *
	 */
	public static function automator_add_helper_instance( $integration, $class_instance ) {
		self::$helper_instances[ $integration ] = $class_instance;
	}

	/**
	 * Get all class instances
	 *
	 * @return array
	 * @since    1.0.0
	 *
	 */
	public static function automator_get_all_class_instances() {
		return self::$class_instances;
	}

	/**
	 * Get all class instances
	 *
	 * @return array
	 * @since    2.1.0
	 *
	 */
	public static function automator_get_all_helper_instances() {
		return self::$helper_instances;
	}

	/**
	 * Get a specific class instance
	 *
	 * @param $class_name
	 *
	 * @return object | bool
	 * @since    1.0.0
	 */
	public static function automator_get_class_instance( $class_name ) {
		return isset( self::$class_instances[ $class_name ] ) ? self::$class_instances[ $class_name ] : false;
	}

	/**
	 * Get the date format for the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function automator_get_date_format() {
		return apply_filters( 'automator_date_format', 'F j, Y' );
	}

	/**
	 * Get the time format for the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function automator_get_time_format() {
		return apply_filters( 'automator_time_format', 'g:i a' );
	}

	/**
	 * Returns the full url for the recipe UI dist directory
	 *
	 * @param $file_name
	 *
	 * @return $asset_url
	 * @since    1.0.0
	 */
	public static function automator_get_recipe_dist( $file_name ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			$asset_url = plugins_url( 'src/recipe-ui/dist/' . $file_name, AUTOMATOR_BASE_FILE );
		} else {
			$asset_url = plugins_url( 'src/recipe-ui/dist/' . $file_name, AUTOMATOR_BASE_FILE );

		}

		return $asset_url;
	}

	/**
	 * Returns the full url for the passed Icon within recipe UI
	 *
	 * @param $file_name
	 *
	 * @return $asset_url
	 * @since    1.0.0
	 *
	 */
	public static function automator_get_integration_icon( $file_name, $plugin_path = AUTOMATOR_BASE_FILE ) {
		/**
		 * Integration icons are now moved in to integrations itself
		 * @since 3.0
		 */
		if ( ! empty( $file_name ) && is_dir( dirname( $file_name ) ) ) {
			$icon = basename( $file_name ); // icon with extension.
			if ( version_compare( PHP_VERSION, '7.0', '>=' ) ) {
				$integration_dir = basename( dirname( $file_name ) ); // integration folder path.
			} else {
				$integration_dir = basename( dirname( $file_name ) ); // integration folder path.
			}
			$path      = self::cleanup_icon_path( AUTOMATOR_BASE_FILE, $icon, $file_name ); // path relative to plugin.
			$path      = apply_filters( 'automator_integration_icon_path', $path . $icon, $icon, $integration_dir, $plugin_path );
			$base_path = apply_filters( 'automator_integration_icon_base_path', $plugin_path, $path, $icon, $integration_dir );

			return plugins_url( $path, $base_path );
		}

		// fallback
		$path      = apply_filters( 'automator_integration_icon_path_legacy', 'src/recipe-ui/dist/media/integrations/' . $file_name, $file_name, $plugin_path );
		$base_path = apply_filters( 'automator_integration_icon_base_path_legacy', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $plugin_path, $file_name );

		return plugins_url( $path, $base_path );
	}

	/**
	 * @param $dirname
	 * @param $icon
	 * @param $file_name
	 *
	 * @return array|string|string[]
	 */
	public static function cleanup_icon_path( $dirname, $icon, $file_name ) {
		// path relative to plugin
		if ( file_exists( $file_name ) ) {
			$val = str_replace(
				array(
					dirname( $dirname ),
					$icon,
				),
				'',
				$file_name
			);
		} else {
			$val = '/src/recipe-ui/dist/media/integrations/';
		}

		return $val;
	}

	/**
	 * @param $file_name
	 *
	 * @return string
	 * @deprecated use automator_get_integration_icon()
	 */
	public static function get_integration_icon( $file_name ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'get_integration_icon', 'Please use Utilities::automator_get_integration_icon()', '3.0' );
		}

		return self::automator_get_integration_icon( $file_name );
	}

	/**
	 * Returns the full url for the passed media file
	 *
	 * @param $file_name
	 *
	 * @return $asset_url
	 * @since    1.0.0
	 *
	 */
	public static function automator_get_media( $file_name ) {
		return plugins_url( 'src/assets/backend/dist/img/' . $file_name, AUTOMATOR_BASE_FILE );
	}

	/**
	 * Returns the full url for the passed vendor file
	 *
	 * @since    1.0.0
	 *
	 */
	public static function automator_get_vendor_asset( $file_name ) {
		return plugins_url( 'src/assets/legacy/vendor/' . $file_name, AUTOMATOR_BASE_FILE );
	}

	/**
	 * Enqueues global JS and CSS files
	 *
	 * @param $file_name
	 *
	 * @since    1.0.0
	 *
	 */
	public static function legacy_automator_enqueue_global_assets() {
		wp_enqueue_style( 'uap-admin-global-fonts', 'https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap', array(), Utilities::automator_get_version() );

		wp_enqueue_style( 'uap-admin-global', Utilities::automator_get_asset( 'legacy/css/admin/global.css' ), array( 'uap-admin-global-fonts' ), Utilities::automator_get_version() );
		self::automator_enqueue_frontend_assets();

		wp_enqueue_script( 'uap-admin-global', Utilities::automator_get_asset( 'legacy/js/admin/global.js' ), array( 'jquery' ), Utilities::automator_get_version(), true );
	}

	/**
	 * Enqueues frontend JS and CSS files
	 *
	 * @since    3.1.1
	 *
	 */
	public static function automator_enqueue_frontend_assets() {
		wp_enqueue_style( 'uap-automator-css', Utilities::automator_get_asset( 'legacy/css/automator.css' ), null, Utilities::automator_get_version() );
	}

	/**
	 * Returns the full url for the passed CSS file
	 *
	 * @param $file_name
	 *
	 * @return $asset_url
	 * @since    1.0.0
	 *
	 */
	public static function automator_get_css( $file_name ) {
		return plugins_url( 'src/assets/css/' . $file_name, AUTOMATOR_BASE_FILE );
	}

	/**
	 * Returns the full url for the passed asset
	 *
	 * @param $file_name
	 *
	 * @return $asset_url
	 * @since    3.2.0.2
	 *
	 */
	public static function automator_get_asset( $file_name ) {
		return plugins_url( 'src/assets/' . $file_name, AUTOMATOR_BASE_FILE );
	}

	/**
	 * Get the version for the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function automator_get_version() {
		return AUTOMATOR_PLUGIN_VERSION;
	}

	/**
	 * Returns the full url for the passed JS file
	 *
	 * @param $file_name
	 *
	 * @return $asset_url
	 * @since    1.0.0
	 *
	 */
	public static function automator_get_js( $file_name ) {
		return plugins_url( 'src/assets/js/' . $file_name, AUTOMATOR_BASE_FILE );
	}

	/**
	 * Returns the full server path for the passed view file
	 *
	 * @param $file_name
	 *
	 * @return string
	 */
	public static function automator_get_view( $file_name ) {

		$views_directory = UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;

		// Replace separator in the file name
		$file_name = str_replace( '/', DIRECTORY_SEPARATOR, $file_name );

		/**
		 * Filters the directory path to the view file
		 *
		 * This can be used for view overrides by modifying the path to go to a directory in the theme or another plugin.
		 *
		 * @param $views_directory Path to the plugins view folder
		 * @param $file_name The file name of the view file
		 *
		 * @since 1.0.0
		 *
		 */
		$views_directory = apply_filters( 'automator_view_path', $views_directory, $file_name );

		return $views_directory . $file_name;
	}

	/**
	 * Adds the autoloaded class in an accessible object
	 *
	 * @param $class_name
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
	 * @param $integration
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
	 * @param $class_name
	 *
	 * @return object | bool
	 * @since    1.0.0
	 *
	 */
	public static function get_class_instance( $class_name ) {
		return isset( self::$class_instances[ $class_name ] ) ? self::$class_instances[ $class_name ] : false;
	}

	/**
	 * Get a specific class instance
	 *
	 * @param $class_name
	 *
	 * @return array
	 * @since    1.0.0
	 *
	 */
	public static function get_pro_items_list() {
		include Utilities::automator_get_include( 'pro-items-list.php' );

		return automator_pro_items_list();
	}

	/**
	 * Returns the full server path for the passed include file
	 *
	 * @param $file_name
	 *
	 * @return string
	 */
	public static function automator_get_include( $file_name ) {

		$includes_directory = UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;

		/**
		 * Filters the director path to the include file
		 *
		 * This can be used for include overrides by modifying the path to go to a directory in the theme or another plugin.
		 *
		 * @param $includes_directory Path to the plugins include folder
		 * @param $file_name The file name of the include file
		 *
		 * @since 1.0.0
		 *
		 */
		$includes_directory = apply_filters( 'automator_includes_path_to', $includes_directory, $file_name );

		return $includes_directory . $file_name;
	}

	/**
	 * Create and store logs @ wp-content/uo-{$file_name}.log
	 *
	 * @param $trace_message The message logged
	 * @param $trace_heading The heading of the current trace
	 * @param $backtrace Create log even if debug mode is off
	 * @param $file_name The file name of the log file
	 *
	 * @return $error_log Was the log successfully created
	 * @since    1.0.0
	 *
	 */
	public static function log( $trace_message = '', $trace_heading = '', $force_log = false, $file_name = 'logs', $backtrace = false ) {

		// Only return log if debug mode is on OR if log is forced
		if ( ! self::automator_get_debug_mode() && false === $force_log ) {
			return false;
		}

		$timestamp           = current_time( self::automator_get_date_time_format() ); //phpcs ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$current_host        = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$current_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$current_page_link   = "https://{$current_host}{$current_request_uri}";
		$trace_start         = "\n===========================<<<< $timestamp >>>>===========================\n";
		$trace_heading       = "* Heading: $trace_heading \n* Current Page: $current_page_link \n";
		//$trace_end         = "\n===========================<<<< TRACE END >>>>===========================\n";

		$backtrace_start = "\n===========================<<<< BACKTRACE START >>>>===========================\n";
		$error_string    = print_r( ( new \Exception )->getTraceAsString(), true );
		$backtrace_end   = "\n===========================<<<< BACKTRACE END >>>>===========================\n";

		$trace_msg_start = "\n===========================<<<< TRACE MESSAGE START >>>>===========================\n";
		$trace_finish    = "\n===========================<<<< END >>>>===========================\n\n";

		$trace_message = print_r( $trace_message, true );
		$log_directory = UA_DEBUG_LOGS_DIR;
		if ( ! file_exists( $log_directory ) ) {
			mkdir( $log_directory, 0755 );
		}
		$file = $log_directory . 'uo-' . $file_name . '.log';
		if ( ! $backtrace ) {
			$complete_message = $trace_start . $trace_heading . $trace_msg_start . $trace_message . $trace_finish;
		} else {
			$complete_message = $trace_start . $trace_heading . $backtrace_start . $error_string . $backtrace_end . $trace_msg_start . $trace_message . $trace_finish;
		}

		error_log( $complete_message, 3, $file );
	}

	/**
	 * Get debug mode
	 *
	 * @return bool
	 * @since    1.0.0
	 *
	 */
	public static function automator_get_debug_mode() {
		return AUTOMATOR_DEBUG_MODE;
	}

	/**
	 * Get the date and time format for the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 *
	 */
	public static function automator_get_date_time_format() {
		return apply_filters( 'automator_date_time_format', 'F j, Y g:i a' );
	}

}

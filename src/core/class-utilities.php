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
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Creates a key if it doesnt exists yet and returns it.
	 *
	 * Otherwise, just returns the key. Most of the site has AUTH_KEY in place so most of the time this will not create additional options.
	 *
	 * @return string
	 */
	public static function get_key() {

		$opt_key = 'automator_logs_auth_key';

		if ( defined( 'AUTH_KEY' ) ) {
			return substr( AUTH_KEY, 0, 8 );
		}

		$key = automator_get_option( $opt_key, false );

		// Generate the key if its falsy.
		if ( empty( $key ) ) {
			$key = md5( uniqid( time() ) );
			// Make sure to autoload.
			automator_add_option( $opt_key, $key, 'yes' );
		}

		return substr( $key, 0, 8 );

	}

	/**
	 * Utilities constructor.
	 */
	public function __construct() {
		add_action(
			'activated_plugin',
			array(
				$this,
				'reset_integration_list_transients',
			),
			99999,
			2
		);
		add_action(
			'deactivated_plugin',
			array(
				$this,
				'reset_integration_list_transients',
			),
			99999,
			2
		);
	}

	/**
	 * @return void
	 */
	public function reset_integration_list_transients() {
		delete_transient( 'automator_pro_integrations_list_items' );
		delete_transient( 'automator_pro_integrations_list' );
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
	 */
	public static function automator_add_helper_instance( $integration, $class_instance ) {
		self::$helper_instances[ $integration ] = $class_instance;
	}

	/**
	 * Get all class instances
	 *
	 * @return array
	 * @since    1.0.0
	 */
	public static function automator_get_all_class_instances() {
		return self::$class_instances;
	}

	/**
	 * Get all class instances
	 *
	 * @return array
	 * @since    2.1.0
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
	 */
	public static function automator_get_date_format() {
		return apply_filters( 'automator_date_format', 'F j, Y' );
	}

	/**
	 * Get the time format for the plugin
	 *
	 * @return string
	 * @since    1.0.0
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
	 */
	public static function automator_get_integration_icon( $file_name, $plugin_path = AUTOMATOR_BASE_FILE ) {
		/**
		 * Integration icons are now moved in to integrations itself
		 *
		 * @since 3.0
		 */
		if ( ! empty( $file_name ) && is_dir( dirname( $file_name ) ) ) {
			$icon            = basename( $file_name ); // icon with extension.
			$integration_dir = basename( dirname( $file_name ) ); // integration folder path.
			$path            = self::cleanup_icon_path( $plugin_path, $icon, $file_name ); // path relative to plugin.
			$path            = apply_filters( 'automator_integration_icon_path', $path . $icon, $icon, $integration_dir, $plugin_path );
			$base_path       = apply_filters( 'automator_integration_icon_base_path', $plugin_path, $path, $icon, $integration_dir );
			if ( ! empty( $path ) && ! empty( $base_path ) ) {
				return plugins_url( $path, $base_path );
			}
		}

		// fallback
		$path      = apply_filters( 'automator_integration_icon_path_legacy', 'src/recipe-ui/dist/media/integrations/' . $file_name, $file_name, $plugin_path );
		$base_path = apply_filters( 'automator_integration_icon_base_path_legacy', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $plugin_path, $file_name );

		if ( ! empty( $path ) && ! empty( $base_path ) ) {
			return plugins_url( $path, $base_path );
		}

		return '';
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
		if ( is_file( $file_name ) ) {
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
	 */
	public static function automator_get_media( $file_name ) {
		return plugins_url( 'src/assets/backend/dist/img/' . $file_name, AUTOMATOR_BASE_FILE );
	}

	/**
	 * Returns the full url for the passed vendor file
	 *
	 * @since    1.0.0
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
	 */
	public static function legacy_automator_enqueue_global_assets() {
		wp_enqueue_style( 'uap-admin-global-fonts', 'https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap', array(), self::automator_get_version() );

		wp_enqueue_style( 'uap-admin-global', self::automator_get_asset( 'legacy/css/admin/global.css' ), array( 'uap-admin-global-fonts' ), self::automator_get_version() );

		wp_enqueue_script( 'uap-admin-global', self::automator_get_asset( 'legacy/js/admin/global.js' ), array( 'jquery' ), self::automator_get_version(), true );
	}

	/**
	 * Returns the full url for the passed CSS file
	 *
	 * @param $file_name
	 *
	 * @return $asset_url
	 * @since    1.0.0
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
	 */
	public static function automator_get_asset( $file_name ) {
		return plugins_url( 'src/assets/' . $file_name, AUTOMATOR_BASE_FILE );
	}

	/**
	 * Get the version for the plugin
	 *
	 * @return string
	 * @since    1.0.0
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
	 */
	public static function add_helper_instance( $integration, $class_instance ) {

		self::$helper_instances[ $integration ] = $class_instance;

	}

	/**
	 * Get all class instances
	 *
	 * @return array
	 * @since    1.0.0
	 */
	public static function get_all_class_instances() {
		return self::$class_instances;
	}

	/**
	 * Get all class instances
	 *
	 * @return array
	 * @since    2.1.0
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
	 */
	public static function get_pro_items_list() {
		require_once self::automator_get_include( 'pro-items-list.php' );

		return automator_pro_items_list();
	}

	/**
	 * @return array
	 */
	public static function get_pro_only_items() {

		$pro_only = get_transient( 'automator_pro_integrations_list' );

		if ( ! empty( $pro_only ) ) {
			return $pro_only;
		}

		// The endpoint url to S3
		$endpoint_url = AUTOMATOR_INTEGRATIONS_JSON_LIST; // Append time to prevent caching.

		$response = wp_remote_get( $endpoint_url );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$api_response   = json_decode( $response['body'], true );
		$pro_only       = array();
		$active_plugins = self::get_plugins_info();

		foreach ( $api_response as $integration ) {
			// If it's not pro, continue;
			if ( ! $integration['is_pro_integration'] ) {
				continue;
			}

			$integration_id   = $integration['integration_id'];
			$integration_name = $integration['integration_name'];

			if ( ! in_array( $integration_name, $active_plugins, true ) && ! $integration['is_app_integration'] ) {
				continue;
			}

			if ( array_key_exists( $integration_id, $pro_only ) ) {
				$integration_id = self::decouple_integration_id_name( $integration_id, $integration['integration_name'] );
			}

			$pro_only[ $integration_id ] = array(
				'name'         => $integration_name,
				'icon_svg'     => $integration['integration_icon'],
				'is_pro_only'  => 'yes',
				'settings_url' => '',
			);
		}

		set_transient( 'automator_pro_integrations_list', $pro_only, DAY_IN_SECONDS );

		return $pro_only;
	}

	/**
	 * @return array
	 */
	public static function get_pro_only_items_list() {
		$pro_only = get_transient( 'automator_pro_integrations_list_items' );

		if ( ! empty( $pro_only ) ) {
			return $pro_only;
		}

		// The endpoint url to S3
		$endpoint_url = AUTOMATOR_INTEGRATIONS_JSON_LIST_WITH_ITEMS; // Append time to prevent caching.

		$response = wp_remote_get( $endpoint_url );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$api_response = json_decode( $response['body'], true );
		$pro_only     = array();

		foreach ( $api_response as $integration ) {

			if ( ! $integration['is_pro_integration'] ) {
				continue;
			}

			$integration_id = $integration['integration_id'];

			if ( array_key_exists( $integration_id, $pro_only ) ) {
				$integration_id = self::decouple_integration_id_name( $integration_id, $integration['integration_name'] );
			}

			$pro_only[ $integration_id ] = array(
				'triggers' => $integration['integration_triggers'],
				'actions'  => $integration['integration_actions'],
			);
		}

		set_transient( 'automator_pro_integrations_list_items', $pro_only, DAY_IN_SECONDS );

		return $pro_only;
	}


	/**
	 * For example, WooCommerce & WooCommerce Subscriptions have
	 * the same Integration code. List them separately
	 *
	 * @param $integration_id
	 * @param $integration_name
	 *
	 * @return string
	 */
	public static function decouple_integration_id_name( $integration_id, $integration_name ) {

		$shortened_name = strtoupper( str_replace( ' ', '', $integration_name ) );

		return "{$integration_id}{$shortened_name}";
	}

	/**
	 * Return active plugins info
	 *
	 * @return array
	 */
	public static function get_plugins_info() {

		if ( ! \function_exists( 'wp_get_active_and_valid_plugins' ) ) {
			return array();
		}

		$plugins = wp_get_active_and_valid_plugins();
		if ( empty( $plugins ) ) {
			return array();
		}

		$names = array();

		foreach ( $plugins as $plugin ) {
			$data    = get_plugin_data( $plugin );
			$names[] = $data['Name'];
		}

		return $names;
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
	 */
	public static function log( $trace_message = '', $trace_heading = '', $force_log = false, $file_name = 'logs', $backtrace = false ) {

		// Only return log if debug mode is on OR if log is forced
		if ( ! self::automator_get_debug_mode() && false === $force_log ) {
			return false;
		}

		// if trace message is empty and file name is error-logs, bail
		if ( empty( $trace_message ) && 'error-logs' === $file_name ) {
			return false;
		}

		$timestamp           = current_time( 'Y-m-d H:i:s A' ); //phpcs ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$current_host        = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$current_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$current_page_link   = "https://{$current_host}{$current_request_uri}";
		$trace_start         = "\n\n===========================<<<< $timestamp >>>>=======================\n\n";
		$trace_heading       = "* Heading: $trace_heading \n* Current Page: $current_page_link \n";

		$backtrace_start = "\n\n===========================<<<< BACKTRACE START >>>>===========================\n\n";
		$error_string    = print_r( ( new \Exception() )->getTraceAsString(), true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$backtrace_end   = "\n\n===========================<<<< BACKTRACE END >>>>=============================\n\n";

		$trace_msg_start = "\n===========================<<<< TRACE MESSAGE START >>>>=========================\n\n";
		$trace_finish    = "\n\n===========================<<<< TRACE MESSAGE END >>>>==========================\n\n\n";

		$trace_message = print_r( $trace_message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$log_directory = UA_DEBUG_LOGS_DIR;

		if ( ! is_dir( $log_directory ) ) {
			// Recursively create the directory in case the 'uploads' folder doesn't exist yet.
			mkdir( $log_directory, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		}

		self::remove_old_logs();

		$filename = AUTOMATOR_SITE_KEY . '-' . $file_name;
		$file     = sprintf( '%s%s.%s', $log_directory, sanitize_file_name( $filename ), AUTOMATOR_LOGS_EXT );

		if ( ! $backtrace ) {
			$complete_message = $trace_start . $trace_heading . $trace_msg_start . $trace_message . $trace_finish;
		} else {
			$complete_message = $trace_start . $trace_heading . $backtrace_start . $error_string . $backtrace_end . $trace_msg_start . $trace_message . $trace_finish;
		}

		// Make sure the directory exists.
		if ( is_dir( $log_directory ) ) {
			error_log( $complete_message, 3, $file ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

	}

	private static function remove_old_logs() {
		$old_path = trailingslashit( WP_CONTENT_DIR ) . 'uploads' . DIRECTORY_SEPARATOR . 'automator-logs' . DIRECTORY_SEPARATOR;
		if ( ! is_dir( $old_path ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$wp_filesystem_direct = new \WP_Filesystem_Direct( false );
		$wp_filesystem_direct->rmdir( $old_path, true );
	}

	/**
	 * Get debug mode
	 *
	 * @return bool
	 * @since    1.0.0
	 */
	public static function automator_get_debug_mode() {
		return ! empty( automator_get_option( 'automator_settings_debug_enabled', false ) ) ? true : false;
	}

	/**
	 * Get the date and time format for the plugin
	 *
	 * @return string
	 * @since    1.0.0
	 */
	public static function automator_get_date_time_format() {
		return apply_filters( 'automator_date_time_format', 'F j, Y g:i a' );
	}

	/**
	 * Convert seconds to hours.
	 *
	 * @param int $seconds Seconds to convert.
	 *
	 * @return string Hours in HH:MM:SS format.
	 */
	public static function seconds_to_hours( $seconds ) {

		if ( ! is_numeric( $seconds ) || $seconds <= 0 ) {
			return '00:00:00';
		}
		$seconds = (int) $seconds;
		$hours   = floor( $seconds / 3600 );
		$hours   = str_pad( $hours, 2, '0', STR_PAD_LEFT );
		$minutes = floor( ( $seconds / 60 ) % 60 );
		$minutes = str_pad( $minutes, 2, '0', STR_PAD_LEFT );
		$seconds = $seconds % 60;
		$seconds = str_pad( $seconds, 2, '0', STR_PAD_LEFT );

		return "{$hours}:{$minutes}:{$seconds}";
	}

	/**
	 * Convert Array to CSV.
	 *
	 * @param array $array Array to convert.
	 * @param string $delimiter Delimiter to use.
	 * @param string $enclosure Enclosure to use.
	 * @param string $escape_char Escape character to use.
	 *
	 * @return string CSV string.
	 */
	public static function array_to_csv( $array = array(), $delimiter = ',', $enclosure = '"', $escape_char = '\\' ) {

		if ( empty( $array ) || ! is_array( $array ) ) {
			return '';
		}

		$temp_file = tempnam( sys_get_temp_dir(), 'csv' );
		if ( $temp_file === false ) {
			throw new \RuntimeException( 'Unable to create a temporary file' );
		}

		$f = fopen( $temp_file, 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( $f === false ) {
			throw new \RuntimeException( 'Unable to open temporary file for writing' );
		}

		foreach ( $array as $item ) {
			fputcsv( $f, $item, $delimiter, $enclosure, $escape_char );
		}

		rewind( $f );
		$csv = stream_get_contents( $f );

		fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		wp_delete_file( $temp_file );

		return $csv;
	}

	/**
	 * Accepts post meta array and flattens to to make it compatible with recipe_object.
	 *
	 * @param mixed[] $post_metas
	 *
	 * @return mixed[]
	 */
	public static function flatten_post_meta( $post_metas ) {

		if ( ! is_array( $post_metas ) ) {
			return array();
		}

		$flattened_array = array();

		foreach ( $post_metas as $key => $item ) {
			$flattened_array[ $key ] = end( $item );
		}

		return $flattened_array;

	}

	/**
	 * Returns the specific value of the array from the path using dot notation.
	 *
	 * @param mixed[] $array
	 * @param string $path
	 *
	 * @return mixed
	 */
	public static function get_array_value( $array, $path ) {

		if ( null === $path ) {
			return $array;
		}

		// Check if the path starts with '$.' followed by a number (e.g., $.0, $.1, $.2)
		if ( preg_match( '/^\$\.(\d+)$/', $path, $matches ) ) {
			// Extract the numeric index
			$index = (int) $matches[1];
			// Return the corresponding element or null if the index doesn't exist
			return isset( $array[ $index ] ) ? $array[ $index ] : null;
		}

		// Remove the '$.' part of the path for further processing
		$path = ltrim( $path, '$.' );

		// Return the whole array if the path is empty
		if ( empty( $path ) ) {
			return $array;
		}

		// Split the path by '.' to traverse the array
		$keys = explode( '.', $path );

		// Traverse the array based on the keys
		foreach ( $keys as $key ) {
			// Convert numeric keys from string to integer (for numeric array indices)
			if ( is_numeric( $key ) ) {
				$key = (int) $key;
			}

			// Check if the key exists and is part of the array
			if ( ! is_array( $array ) || ! array_key_exists( $key, $array ) ) {
				// Return null for non-existent keys
				return null;
			}

			// Move to the next level in the array
			$array = $array[ $key ];
		}

		// Return the final value directly (no need to wrap in an array for single elements)
		return $array;
	}


	/**
	 * Limits the number of elements the array contains.
	 *
	 * @param mixed[] $array
	 * @param int $limit
	 *
	 * @return mixed[]
	 */
	public static function limit_array_elements( $array, $limit ) {

		// Ensure the limit is a positive integer
		if ( ! is_int( $limit ) || $limit <= 0 ) {
			return $array;
		}

		// If the array has fewer elements than the limit, return it as is
		if ( count( $array ) <= $limit ) {
			return $array;
		}

		// Slice the array to the specified limit
		return array_slice( $array, 0, $limit, true ); // Use `true` to preserve the keys

	}

}

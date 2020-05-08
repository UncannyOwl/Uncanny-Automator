<?php

namespace Uncanny_Automator;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * This class autoloads all files within specified directories
 * and runs EDD plugin licensing and updater
 *
 * Class Boot
 * @package Uncanny_Automator
 */
class Boot {

	/**
	 * The instance of the class
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Boot
	 */
	private static $instance = null;

	/**
	 * The directories that are auto loaded and initialized
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array
	 */
	private static $auto_loaded_directories = null;

	/**
	 * class constructor
	 */
	private function __construct() {

		// We need to check if spl auto loading is available when activating plugin
		// Plugin will not activate if SPL extension is not enabled by throwing error
		if ( ! extension_loaded( 'SPL' ) ) {
			trigger_error( 'Please contact your hosting company to update to php version 5.3+ and enable spl extensions.', E_USER_ERROR );
		}

		spl_autoload_register( array( $this, 'require_class_files' ) );

		// Initialize all classes in given directories
		$this->auto_initialize_classes();

		// Load same script for free and pro
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		// Load script front-end
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_script' ] );

		/*Weekly delete logs from /wp-content/*/
		//add_action( 'admin_init', array( $this, 'schedule_clear_debug_logs' ) );
		//add_action( 'weekly_remove_debug_logs', array( $this, 'remove_weekly_log_action_data' ) );

		add_action( 'rest_api_init', [ $this, 'uo_register_api' ] );
		add_action( 'admin_init', [ $this, 'maybe_ask_review' ] );
	}

	/**
	 * Set a weekly schedule to remove debug logs
	 */
	/*public function schedule_clear_debug_logs() {
		if ( false === as_next_scheduled_action( 'weekly_remove_debug_logs' ) ) {
			as_schedule_recurring_action( strtotime( 'midnight tonight' ), ( 7 * DAY_IN_SECONDS ), 'weekly_remove_debug_logs' );
		}
	}*/

	/**
	 * A callback to run when the 'weekly_remove_debug_logs' scheduled action is run.
	 */
	/*public function remove_weekly_log_action_data() {
		if ( ! Utilities::get_debug_mode() ) {
			$files = glob( WP_CONTENT_DIR . '/uo-*.log' );
			if ( $files ) {
				foreach ( $files as $file ) {
					unlink( $file );
				}
			}
		}
	}*/

	/**
	 * Licensing page styles
	 *
	 * @param $hook
	 */
	public function scripts( $hook ) {

		if ( strpos( $hook, 'uncanny-automator-license-activation' ) ) {

			wp_enqueue_style( 'uap-admin-license', Utilities::get_css( 'admin/license.css' ), array(), Utilities::get_version() );

		}

	}
	
	/**
	 * Enqueue script
	 *
	 */
	public function enqueue_script() {
		global $wpdb;
		if ( is_user_logged_in() ) {
			// check if there is a recipe and closure with publish status
			$check_closure = $wpdb->get_col( "SELECT cp.ID as ID FROM {$wpdb->posts} cp LEFT JOIN {$wpdb->posts} rp ON rp.ID = cp.post_parent WHERE cp.post_type LIKE 'uo-closure' AND cp.post_status LIKE 'publish' AND rp.post_status LIKE 'publish' LIMIT 1" );
			if ( ! empty( $check_closure ) ) {
				$user_id   = wp_get_current_user()->ID;
				$api_setup = [
					'root'              => esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT . 'uoa_redirect/' ),
					'nonce'             => \wp_create_nonce( 'wp_rest' ),
					'user_id'           => $user_id,
					'client_secret_key' => md5( 'l6fsX3vAAiJbSXticLBd' . $user_id ),
				];
				wp_register_script( 'uoapp-client', Utilities::get_js( 'uo-sseclient.js' ), [], '2.1.0' );
				wp_localize_script( 'uoapp-client', 'uoAppRestApiSetup', $api_setup );
				wp_enqueue_script( 'uoapp-client' );
			}
		}
	}

	/**
	 * Creates singleton instance of Boot class and defines which directories are auto loaded
	 *
	 * @param array $auto_loaded_directories
	 *
	 * @return Boot
	 * @since 1.0.0
	 *
	 */
	public static function get_instance( $auto_loaded_directories = [ 'core/classes', 'core/extensions' ] ) {

		if ( null === self::$instance ) {

			// Define directories were the auto loader looks for files and initializes them
			self::$auto_loaded_directories = $auto_loaded_directories;

			// Lets boot up!
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * SPL Auto Loader functions
	 *
	 * @param string $class Any
	 *
	 * @since 1.0.0
	 *
	 */
	private function require_class_files( $class ) {

		// Remove Class's namespace eg: my_namespace/MyClassName to MyClassName
		$class = str_replace( __NAMESPACE__, '', $class );
		$class = str_replace( '\\', '', $class );

		// Replace _ with - eg. eg: My_Class_Name to My-Class-Name
		$class_to_filename = str_replace( '_', '-', $class );

		// Create file name that will be loaded from the classes directory eg: My-Class-Name to my-class-name.php
		$file_name = strtolower( $class_to_filename ) . '.php';


		// Check each directory
		foreach ( self::$auto_loaded_directories as $directory ) {

			$file_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $file_name;

			// Does the file exist
			if ( file_exists( $file_path ) ) {

				// File found, require it
				require_once( $file_path );

				// You can cannot have duplicate files names. Once the first file is found, the loop ends.
				return;
			}
		}

	}

	/**
	 * Looks through all defined directories and modifies file name to create new class instance.
	 *
	 * @since 1.0.0
	 *
	 */
	private function auto_initialize_classes() {

		// Check each directory
		foreach ( self::$auto_loaded_directories as $directory ) {

			// Get all files in directory
			$files = scandir( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $directory );

			// remove parent directory, sub directory, and silence is golden index.php
			$files = array_diff( $files, array( '..', '.', 'index.php' ) );

			// Loop through all files in directory to create class names from file name
			foreach ( $files as $file ) {

				// Load only php files
				$file_parts = pathinfo( $file );
				if ( key_exists( 'extension', $file_parts ) && 'php' !== $file_parts['extension'] ) {
					continue;
				}

				// Remove file extension my-class-name.php to my-class-name
				$file_name = str_replace( '.php', '', $file );

				// Split file name on - eg: my-class-name to array( 'my', 'class', 'name')
				$class_to_filename = explode( '-', $file_name );

				// Make the first letter of each word in array upper case - eg array( 'my', 'class', 'name') to array( 'My', 'Class', 'Name')
				$class_to_filename = array_map( function ( $word ) {
					return ucfirst( $word );
				}, $class_to_filename );

				// Implode array into class name - eg. array( 'My', 'Class', 'Name') to MyClassName
				$class_name = implode( '_', $class_to_filename );

				$class = __NAMESPACE__ . '\\' . $class_name;

				// We way want to include some class with the autoloader but not initialize them ex. interface class
				$skip_classes = apply_filters( 'Skip_class_initialization', array(), $directory, $files, $class, $class_name );
				if ( in_array( $class_name, $skip_classes ) ) {
					continue;
				}

				$path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $file;
				//$contents = file_get_contents( $path );
				//var_dump( $contents );

				// On plugin activation,
				// 1. collect all comments from every file loaded
				// 2. collect all add_shortcode, apply_filters, and do_actions


				$some_param    = array();
				$another_param = '';

				// ex
				/*
				 * The first line is the title
				 *
				 * The next line is the description and can be mulitple lines and even html
				 * entities. <br> The '@see the_hook' must be present to make the connection.
				 *
				 * @see the_hook
				 * @since version 1.0
				 * @access plugin | module | general  // not everyone needs to se all filters.... maybe we can categories them depending on if its a module and depend file, core plugin architecture file, and not making have to @access tag
				 * @param array $some_param Then the description at the end
				 * @param string $another_param Then the description at the end
				 */
				do_action( 'the_hook', array( $this, 'the_hook_function' ), $some_param, $another_param );

				//regex101
				// regex mulitline comments:  ^\s\/\*\*?[^!][.\s\t\S\n\r]*?\*\/    <<-- tested first
				// regex multiline comments: (?<!\/)\/\*((?:(?!\*\/).|\s)*)\*\/    <<-- found another https://regex101.com/r/nW6hU2/1
				// regex add_shortcode line functions: ^.*\badd_shortcode\b.*$
				// regex add_shortcode line functions: ^.*\bdo_action\b.*$
				// regex add_shortcode line functions: ^.*\bapply_filters\b.*$

				if ( class_exists( $class ) ) {
					Utilities::add_class_instance( $class, new $class );
				}
			}
		}

	}

	/**
	 * Register rest api calls for misc tasks.
	 *
	 * @since 2.1.0
	 */
	public function uo_register_api() {
		global $wpdb;
		$check_closure = $wpdb->get_col( "SELECT cp.ID as ID FROM {$wpdb->posts} cp LEFT JOIN {$wpdb->posts} rp ON rp.ID = cp.post_parent WHERE cp.post_type LIKE 'uo-closure' AND cp.post_status LIKE 'publish' AND rp.post_status LIKE 'publish' LIMIT 1" );
		if ( ! empty( $check_closure ) ) {
			register_rest_route( AUTOMATOR_REST_API_END_POINT, '/uoa_redirect/', [
				'methods'  => 'POST',
				'callback' => [ $this, 'send_feedback' ],
			] );
		}
		register_rest_route( AUTOMATOR_REST_API_END_POINT, '/review-banner-visibility/', [
			'methods'  => 'POST',
			'callback' => [ $this, 'save_review_settings' ],
		] );
	}

	/**
	 * Rest api callbacks for redirects.
	 *
	 * @since 2.1.0
	 */
	public function send_feedback( $request ) {
		// check if its a valid request.
		$data = $request->get_params();
		if ( isset( $data['user_id'] ) && isset( $data['client_secret'] ) && $data['client_secret'] == md5( 'l6fsX3vAAiJbSXticLBd' . $data['user_id'] ) ) {
			$user_id      = $data['user_id'];
			$redirect_url = get_option( 'UO_REDIRECTURL_' . $user_id, '' );
			// Send a simple message at random intervals.
			if ( ! empty( $redirect_url ) ) {
				delete_option( 'UO_REDIRECTURL_' . $user_id );

				return new \WP_REST_Response( [ 'redirect_url' => $redirect_url ], 201 );
			}
		}

		return new \WP_REST_Response( [ 'redirect_url' => '' ], 201 );
	}

	/**
	 * Admin notice for review this plugin.
	 *
	 * @since 2.1.4
	 */
	public function maybe_ask_review() {

		// check plugin install date
		$review_time = get_option( '_uncanny_automator_review_time', '' );

		if ( empty( $review_time ) ) {
			$review_time = current_time( 'timestamp' );
			update_option( '_uncanny_automator_review_time', $review_time );
		}

		$current_date = current_time( 'timestamp' );
		$days_after   = 10;
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ceil( ( $current_date - $review_time ) / 86400 ) > $days_after ) {

			$_is_reminder   = get_option( '_uncanny_automator_review_reminder', '' );
			$_reminder_date = get_option( '_uncanny_automator_review_reminder_date', current_time( 'timestamp' ) );

			if ( ! empty( $_is_reminder ) && 'hide-forever' === $_is_reminder ) {
				return;
			}

			if ( ! empty( $_is_reminder ) && 'maybe-later' === $_is_reminder ) {
				// check reminder date difference
				if ( ceil( ( $current_date - $_reminder_date ) / 86400 ) < $days_after ) {
					return;
				}
			}

			add_action( 'admin_notices', function () {

				// Get data about Automator's version
				$is_pro  = FALSE;
				$version = \Uncanny_Automator\InitializePlugin::PLUGIN_VERSION;
				if ( defined( 'AUTOMATOR_PRO_FILE' ) || class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) {
					$is_pro  = TRUE;
					$version = \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION;
				}

				// Send review URL
				$url_send_review = 'https://wordpress.org/support/plugin/uncanny-automator/reviews/#new-post';

				// Send feedback URL
				$url_send_feedback_version = $is_pro ? 'Uncanny%20Automator%20Pro%20' . $version : 'Uncanny%20Automator%20' . $version;
				$url_send_feedback_source  = $is_pro ? 'uncanny_automator_pro' : 'uncanny_automator';
				$url_send_feedback         = 'https://automatorplugin.com/feedback/?version=' . $url_send_feedback_version . '&utm_source=' . $url_send_feedback_source . '&utm_medium=review_banner';
				include Utilities::get_view( 'review-banner.php' );
			} );
		}
	}

	/**
	 * Rest API callback for saving user selection for review.
	 *
	 * @since 2.1.4
	 * @param object $request
	 *
	 * @return object
	 */
	public function save_review_settings( $request ) {
		// check if its a valid request.
		$data = $request->get_params();
		if ( isset( $data['action'] ) && ( 'maybe-later' === $data['action'] || 'hide-forever' === $data['action'] ) ) {
			update_option( '_uncanny_automator_review_reminder', $data['action'] );
			update_option( '_uncanny_automator_review_reminder_date', current_time( 'timestamp' ) );
			return new \WP_REST_Response( [ 'success' => true ], 200 );
		}

		return new \WP_REST_Response( [ 'success' => false ], 200 );
	}
}






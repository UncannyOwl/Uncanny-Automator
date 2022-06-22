<?php
/**
 * Automator_Load
 *
 * Boot loads Automator plugin.
 *
 * @class   Automator_Load
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
 */


namespace Uncanny_Automator;

/**
 * Class Automator_Load
 *
 * @package Uncanny_Automator
 */
class Automator_Load {

	/**
	 * The instance of the class
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      Object
	 */
	public static $instance = null;

	/**
	 * @var array
	 */
	public static $core_class_inits = array();

	/**
	 * @var array
	 */
	public static $integrations = array();

	/**
	 * @var array
	 */
	public static $active_integrations = array();

	/**
	 * class constructor
	 */
	public function __construct() {

		// Bailout if not php8 compatible.
		if ( ! $this->is_php8_compat() ) {
			return;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'favicon' ) ) {
			// bail out if it's favicon.ico
			return;
		}
		// Show upgrade notice from readme.txt.
		add_action(
			'in_plugin_update_message-' . plugin_basename( AUTOMATOR_BASE_FILE ),
			array( $this, 'in_plugin_update_message' ),
			10,
			2
		);

		// Load both admin & non-admin files.
		add_filter( 'automator_core_files', array( $this, 'global_classes' ) );

		// Load Admin only files.
		add_filter( 'automator_core_files', array( $this, 'admin_only_classes' ) );

		// Load Custom Post Types only files.
		add_filter( 'automator_core_files', array( $this, 'custom_post_types_classes' ) );

		// Load non-admin files.
		add_filter( 'automator_core_files', array( $this, 'front_only_classes' ) );

		// Add the pro links utm_r attributes.
		add_action( 'admin_footer', array( $this, 'global_utm_r_links' ) );

		add_action( 'activated_plugin', array( $this, 'automator_activated' ) );

		// Show 'Upgrade to Pro' on plugins page.
		add_filter(
			'plugin_action_links_' . plugin_basename( AUTOMATOR_BASE_FILE ),
			array(
				$this,
				'uo_automator_upgrade_to_pro_link',
			),
			99
		);

		$this->load_automator();

		// Show set-up wizard.
		$this->initiate_setup_wizard();

	}

	/**
	 * is_php8_compat
	 *
	 * Checks and displays an admin notices if php is version 8
	 * or above and both automator free and pro is version 3.2 or above.
	 *
	 * @return boolean True if version is 8 and both free or pro is less than 3.2. Otherwise, false.
	 */
	public function is_php8_compat() {

		// if Pro is not active, bail
		if ( ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			return true;
		}

		// Check if the php version is 8.0 and above.
		if ( ! version_compare( PHP_VERSION, '8.0.0', '>=' ) ) {
			return true;
		}

		$automator_pro_version_is_less_than_3_2 = version_compare( AUTOMATOR_PRO_PLUGIN_VERSION, '3.2', '<' );

		// If > php8.
		// If either of free and pro is < 3.2.
		if ( $automator_pro_version_is_less_than_3_2 ) {
			add_action( 'admin_notices', array( $this, 'check_automator32_php8_compat_message' ) );

			return false;
		}

		return true;
	}

	/**
	 * check_automator32_php8_compat_message
	 *
	 * Callback function from check_automator32_php8_compat. Shows an admin notice.
	 *
	 * @return void
	 */
	public function check_automator32_php8_compat_message() {
		$class   = 'notice notice-error';
		$version = '3.2';
		// An old version of Uncanny Automator is running
		$url = admin_url( 'plugins.php#uncanny-automator-pro-update' );

		/* translators: 2. Recipes. 3. PHP version */
		$message = sprintf( __( "%2\$s recipes have been disabled because your version of PHP (%3\$s) is not fully compatible with the version of %1\$s that's installed.", 'uncanny-automator' ), 'Uncanny Automator Pro', 'Uncanny Automator', PHP_VERSION );
		/* translators: 1. Trademarked term. 2. Version number */
		$message_update = sprintf( __( 'Please update %1$s to version %2$s or later.', 'uncanny-automator' ), 'Uncanny Automator Pro', $version );

		printf( '<div class="%1$s"><h3 style="font-weight: bold; color: red"><span class="dashicons dashicons-warning"></span>%2$s <a href="%3$s">' . esc_html( $message_update ) . '</a></h3></div>', esc_attr( $class ), esc_html( $message ), esc_url_raw( $url ) );
	}

	/**
	 * @param $plugin
	 */
	public function automator_activated( $plugin ) {

		if ( plugin_basename( AUTOMATOR_BASE_FILE ) === $plugin && true === apply_filters( 'automator_on_activate_redirect_to_dashboard', true ) ) {

			$checked = filter_input( INPUT_POST, 'checked', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

			// Bail if bulked activated and there are more than 1 plugin.
			if ( is_array( $checked ) && count( $checked ) >= 2 ) {
				return;
			}

			// Bail if not from `wp-admin/plugins.php` (e.g coming from an ajax, or unit test)
			if ( false !== wp_get_referer() && ! strpos( wp_get_referer(), 'wp-admin/plugins.php' ) ) {
				return;
			}

			// Bail if from Codeception WPTestCase.
			if ( class_exists( '\Codeception\TestCase\WPTestCase' ) ) {
				return;
			}

			wp_redirect( esc_url_raw( admin_url( 'admin.php?page=uncanny-automator-dashboard' ) ) ); //phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect

			exit();

		}
	}

	/**
	 * Callback function to `plugin_action_links_{$path}` to add our 'Upgrade to Pro' link.
	 *
	 * @param array $links The accepted argument.
	 *
	 * @return array The links.
	 */
	public function uo_automator_upgrade_to_pro_link( $links ) {

		// Check if Automator Pro is not active.
		if ( ! defined( 'AUTOMATOR_PRO_FILE' ) ) {

			$link = 'https://automatorplugin.com/pricing/?utm_source=uncanny_automator&utm_medium=plugins_page&utm_content=update_to_pro';

			$settings_link = sprintf( '<a href="%s" target="_blank" style="font-weight: bold;">%s</a>', $link, __( 'Upgrade to Pro', 'uncanny-learndash-toolkit' ) );

			array_unshift( $links, $settings_link );

		}

		return $links;

	}

	/**
	 *
	 */
	public function load_automator() {
		// If it's not required to load automator, bail
		if ( false === LOAD_AUTOMATOR ) {
			return;
		}

		// Load text domain
		add_action( 'plugins_loaded', array( $this, 'automator_load_textdomain' ) );

		do_action( 'automator_before_configure' );

		// Load Assets
		$this->initialize_assets();

		// Load Utilities
		$this->initialize_utilities();

		// Load Configuration
		$this->initialize_automator_db();

		// Load the core files
		$this->initialize_core_automator();

		do_action( 'automator_configuration_complete' );

		add_action( 'wpforms_loaded', array( $this, 'wpforms_integration' ) );
	}

	/**
	 *
	 */
	public function wpforms_integration() {
		if ( ! class_exists( 'WPForms' ) ) {
			return;
		}
		if ( version_compare( WPFORMS_VERSION, '1.7.0', '<' ) ) {
			return;
		}
		add_filter(
			'wpforms_load_providers',
			function ( $providers ) {
				$providers[] = 'uncanny-automator';

				return $providers;
			},
			99,
			1
		);

		add_action(
			'wpforms_load_uncanny-automator_provider',
			function () {
				require_once UA_ABSPATH . 'src/core/admin/class-wpforms-provider.php';
			},
			99
		);
	}

	/**
	 *
	 */
	public function initialize_assets() {
		// Load same script for free and pro
		add_action( 'admin_enqueue_scripts', array( $this, 'automator_license_style' ) );
		// Load script front-end
		add_action( 'wp_enqueue_scripts', array( $this, 'automator_closure_scripts' ) );
	}

	/**
	 * Initialize static singleton class that has shared functions and variables
	 *
	 * @since 1.0.0
	 */
	public function initialize_utilities() {

		require UA_ABSPATH . 'src/core/class-utilities.php';
		Utilities::get_instance();

	}

	/**
	 * Initialize static singleton class that configures all constants, utilities variables and handles
	 * activation/deactivation
	 *
	 * @since 1.0.0
	 */
	public function initialize_automator_db() {

		include_once dirname( AUTOMATOR_BASE_FILE ) . '/src/core/class-automator-db.php';

		$config_instance = Automator_DB::get_instance();

		register_activation_hook(
			AUTOMATOR_BASE_FILE,
			array(
				Automator_DB::class,
				'activation',
			)
		);

		$db_version = get_option( 'uap_database_version', null );

		if ( null === $db_version || (string) AUTOMATOR_DATABASE_VERSION !== (string) $db_version ) {
			Automator_DB::activation();
			$config_instance->mysql_8_auto_increment_fix();
		}

		if ( (string) AUTOMATOR_DATABASE_VIEWS_VERSION !== (string) get_option( 'uap_database_views_version', 0 ) ) {
			$config_instance->automator_generate_views();
		}
	}

	/**
	 *
	 */
	public function initialize_core_automator() {
		do_action( 'automator_before_init' );

		$classes = apply_filters( 'automator_core_files', array() );

		if ( empty( $classes ) ) {
			return;
		}

		$this->load_traits();

		foreach ( $classes as $class_name => $file ) {
			if ( ! file_exists( $file ) ) {
				continue;
			}
			require $file;
			$class                                 = __NAMESPACE__ . '\\' . $class_name;
			self::$core_class_inits[ $class_name ] = new $class();
		}

		do_action( 'automator_after_init' );
	}

	/**
	 * Creates singleton instance of class
	 *
	 * @return Automator_Load $instance The Automator_Load Class
	 * @since 1.0.0
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 *
	 */
	public static function maybe_load_automator() {

		$run_automator = true;

		$run_automator = apply_filters_deprecated( 'uap_run_automator_actions', array( $run_automator ), '3.0', 'automator_run_automator_actions' );

		return apply_filters( 'automator_run_automator_actions', $run_automator );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function automator_load_textdomain() {
		load_plugin_textdomain( 'uncanny-automator', false, basename( dirname( AUTOMATOR_BASE_FILE ) ) . '/languages/' );
	}

	/**
	 * @param $args
	 * @param $response
	 */
	public function in_plugin_update_message( $args, $response ) {
		$upgrade_notice = '';
		if ( isset( $response->upgrade_notice ) && ! empty( $response->upgrade_notice ) ) {
			$upgrade_notice .= '<div class="ua_plugin_upgrade_notice">';
			$upgrade_notice .= sprintf( '<strong>%s</strong>', __( 'Heads up!', 'uncanny-automator' ) );
			$upgrade_notice .= preg_replace( '~\[([^\]]*)\]\(([^\)]*)\)~', '<a href="${2}">${1}</a>', $response->upgrade_notice );
			$upgrade_notice .= '</div>';
		}

		echo apply_filters( 'uap_in_plugin_update_message', $upgrade_notice ? '</p>' . wp_kses_post( $upgrade_notice ) . '<p class="dummy">' : '', $args ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Licensing page styles
	 *
	 * @param $hook
	 */
	public function automator_license_style( $hook ) {
		if ( strpos( $hook, 'uncanny-automator-license-activation' ) ) {
			wp_enqueue_style( 'uap-admin-license', Utilities::automator_get_asset( 'legacy/css/admin/license.css' ), array(), Utilities::automator_get_version() );
		}
	}

	/**
	 * Enqueue script
	 */
	public function automator_closure_scripts() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		// check if there is a recipe and closure with publish status
		$check_closure = Automator()->db->closure->get_all();
		if ( empty( $check_closure ) ) {
			return;
		}
		$user_id   = wp_get_current_user()->ID;
		$api_setup = array(
			'root'              => esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT . '/uoa_redirect/' ),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'user_id'           => $user_id,
			'client_secret_key' => md5( 'l6fsX3vAAiJbSXticLBd' . $user_id ),
		);
		wp_register_script( 'uoapp-client', Utilities::automator_get_asset( 'legacy/js/uo-sseclient.js' ), array(), '2.1.0' ); //phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
		wp_localize_script( 'uoapp-client', 'uoAppRestApiSetup', $api_setup );
		wp_enqueue_script( 'uoapp-client' );
	}

	/**
	 * @return mixed|void
	 */
	public function include_core_files() {
		/**
		 * Abstracts.
		 */
		do_action( 'automator_before_abstract_init' );

		do_action( 'automator_after_abstract_init' );

	}

	/**
	 * @param array $classes
	 *
	 * @return array|mixed
	 */
	public function admin_only_classes( $classes = array() ) {
		/**
		 * Admin.
		 */
		if ( ! is_admin() ) {
			return $classes;
		}

		do_action( 'automator_before_admin_init' );

		$classes['Admin_Menu']        = UA_ABSPATH . 'src/core/admin/class-admin-menu.php';
		$classes['Copy_Recipe_Parts'] = UA_ABSPATH . 'src/core/admin/class-copy-recipe-parts.php';
		$classes['Prune_Logs']        = UA_ABSPATH . 'src/core/admin/class-prune-logs.php';
		$classes['Admin_Settings']    = UA_ABSPATH . 'src/core/admin/admin-settings/admin-settings.php';

		$classes['Add_User_Recipe_Type'] = UA_ABSPATH . 'src/core/classes/class-add-user-recipe-type.php';
		if ( ! defined( 'AUTOMATOR_PRO_FILE' ) ) {
			$classes['Add_Anon_Recipe_Type'] = UA_ABSPATH . 'src/core/anon/class-add-anon-recipe-type.php';
		}
		do_action( 'automator_after_admin_init' );

		/**
		 * Automator Custom Post Types.
		 */
		//$classes = $this->custom_post_types_classes( $classes );

		/**
		 * Activity Stream / Logs.
		 */
		$classes = $this->activity_stream_classes( $classes );

		/**
		 * Classes.
		 */
		do_action( 'automator_before_classes_init' );

		$classes['Populate_From_Query'] = UA_ABSPATH . 'src/core/classes/class-populate-from-query.php';

		do_action( 'automator_after_classes_init' );

		return $classes;
	}

	/**
	 * @param array $classes
	 *
	 * @return array|mixed
	 */
	public function custom_post_types_classes( $classes = array() ) {

		do_action( 'automator_before_automator_post_types_init' );

		$classes['Recipe_Post_Type']      = UA_ABSPATH . 'src/core/automator-post-types/uo-recipe/class-recipe-post-type.php';
		$classes['Recipe_Post_Metabox']   = UA_ABSPATH . 'src/core/automator-post-types/uo-recipe/class-recipe-post-metabox.php';
		$classes['Recipe_Post_Utilities'] = UA_ABSPATH . 'src/core/automator-post-types/uo-recipe/class-recipe-post-utilities.php';
		$classes['Recipe_Post_Rest_Api']  = UA_ABSPATH . 'src/core/automator-post-types/uo-recipe/class-recipe-post-rest-api.php';
		$classes['Triggers_Post_Type']    = UA_ABSPATH . 'src/core/automator-post-types/uo-trigger/class-triggers-post-type.php';
		$classes['Actions_Post_Type']     = UA_ABSPATH . 'src/core/automator-post-types/uo-action/class-actions-post-type.php';
		$classes['Closures_Post_Type']    = UA_ABSPATH . 'src/core/automator-post-types/uo-closure/class-closures-post-type.php';
		$classes['Automator_Taxonomies']  = UA_ABSPATH . 'src/core/automator-post-types/uo-taxonomies/class-automator-taxonomies.php';

		do_action( 'automator_after_automator_post_types_init' );

		return $classes;
	}

	/**
	 * @param array $classes
	 *
	 * @return array|mixed
	 */
	public function activity_stream_classes( $classes = array() ) {

		do_action( 'automator_before_activity_stream_init' );

		$classes['Activity_Log'] = UA_ABSPATH . 'src/core/admin/class-activity-log.php';

		do_action( 'automator_after_activity_stream_init' );

		return $classes;
	}

	/**
	 * @param array $classes
	 *
	 * @return array|mixed
	 */
	public function front_only_classes( $classes = array() ) {

		$classes['Actionify_Triggers'] = UA_ABSPATH . 'src/core/classes/class-actionify-triggers.php';

		return $classes;
	}

	/**
	 * @param array $classes
	 *
	 * @return array|mixed
	 */
	public function global_classes( $classes = array() ) {
		/**
		 * Class autoloader.
		 */
		do_action( 'automator_before_autoloader' );

		// Load Anon part if Pro is not active
		if ( ! defined( 'AUTOMATOR_PRO_FILE' ) ) {
			$classes['Automator_Handle_Anon'] = UA_ABSPATH . 'src/core/anon/automator-handle-anon.php';
		}

		// Webhooks
		$classes['Automator_Send_Webhook_Ajax_Handler'] = UA_ABSPATH . 'src/core/lib/webhooks/class-automator-send-webhook-ajax-handler.php';
		$classes['Automator_Review']                    = UA_ABSPATH . 'src/core/admin/class-automator-review.php';
		$classes['Automator_Autoloader']                = UA_ABSPATH . 'src/core/lib/autoload/class-ua-autoloader.php';
		$classes['Api_Server']                          = UA_ABSPATH . 'src/core/classes/class-api-server.php';
		$classes['Usage_Reports']                       = UA_ABSPATH . 'src/core/classes/class-usage-reports.php';
		$classes['Set_Up_Automator']                    = UA_ABSPATH . 'src/core/classes/class-set-up-automator.php';
		$classes['Automator_Notifications']             = UA_ABSPATH . 'src/core/admin/notifications/notifications.php';

		//$classes['Import_Recipe'] = UA_ABSPATH . 'src/core/classes/class-import-recipe.php';

		// Load migrations
		$this->load_migrations();

		do_action( 'automator_after_autoloader' );

		return $classes;
	}

	/**
	 * load_migrations
	 *
	 * @return void
	 */
	public function load_migrations() {
		require_once UA_ABSPATH . 'src/core/migrations/abstract-migration.php';
		require_once UA_ABSPATH . 'src/core/migrations/class-migrate-schedules.php';
	}

	/**
	 *
	 */
	public function load_traits() {
		do_action( 'automator_before_traits' );

		// Settings
		$classes['Trait_Settings_Premium_Integrations'] = UA_ABSPATH . 'src/core/lib/settings/trait-premium-integrations.php';

		// Integrations
		$classes['Integrations'] = UA_ABSPATH . 'src/core/lib/recipe-parts/trait-integrations.php';

		// Closures
		$classes['Trait_Closure_Setup'] = UA_ABSPATH . 'src/core/lib/recipe-parts/closures/trait-closure-setup.php';
		$classes['Closures']            = UA_ABSPATH . 'src/core/lib/recipe-parts/trait-closures.php';

		// Triggers
		$classes['Trait_Trigger_Setup']          = UA_ABSPATH . 'src/core/lib/recipe-parts/triggers/trait-trigger-setup.php';
		$classes['Trait_Trigger_Filters']        = UA_ABSPATH . 'src/core/lib/recipe-parts/triggers/trait-trigger-filters.php';
		$classes['Trait_Trigger_Recipe_Filters'] = UA_ABSPATH . 'src/core/lib/recipe-parts/triggers/trait-trigger-recipe-filters.php';
		$classes['Trait_Trigger_Conditions']     = UA_ABSPATH . 'src/core/lib/recipe-parts/triggers/trait-trigger-conditions.php';
		$classes['Trait_Trigger_Process']        = UA_ABSPATH . 'src/core/lib/recipe-parts/triggers/trait-trigger-process.php';
		$classes['Triggers']                     = UA_ABSPATH . 'src/core/lib/recipe-parts/triggers/trait-triggers.php';

		// Actions
		$classes['Trait_Action_Setup']         = UA_ABSPATH . 'src/core/lib/recipe-parts/actions/trait-action-setup.php';
		$classes['Trait_Action_Conditions']    = UA_ABSPATH . 'src/core/lib/recipe-parts/actions/trait-action-conditions.php';
		$classes['Trait_Action_Parser']        = UA_ABSPATH . 'src/core/lib/recipe-parts/actions/trait-action-parser.php';
		$classes['Trait_Action_Process']       = UA_ABSPATH . 'src/core/lib/recipe-parts/actions/trait-action-process.php';
		$classes['Trait_Action_Helpers_Email'] = UA_ABSPATH . 'src/core/lib/recipe-parts/actions/trait-action-helpers-email.php';
		$classes['Trait_Action_Helpers']       = UA_ABSPATH . 'src/core/lib/recipe-parts/actions/trait-action-helpers.php';
		$classes['Actions']                    = UA_ABSPATH . 'src/core/lib/recipe-parts/trait-actions.php';

		// Webhooks
		//$classes['Webhook_Ajax_Handler']        = UA_ABSPATH . 'src/core/lib/recipe-parts/webhooks/trait-webhook-ajax-handler.php';
		$classes['Webhook_Send_Rest_Handler']   = UA_ABSPATH . 'src/core/lib/recipe-parts/webhooks/trait-webhook-send-rest-handler.php';
		$classes['Webhook_Send_Sample_Handler'] = UA_ABSPATH . 'src/core/lib/recipe-parts/webhooks/trait-webhook-send-sample-handler.php';
		$classes['Webhook_Static_Content']      = UA_ABSPATH . 'src/core/lib/recipe-parts/webhooks/trait-webhook-static-content.php';
		$classes['Webhooks']                    = UA_ABSPATH . 'src/core/lib/recipe-parts/trait-webhooks.php';

		if ( empty( $classes ) ) {
			return;
		}
		// TODO: Generate Class names by filenames
		foreach ( $classes as $file ) {
			if ( ! file_exists( $file ) ) {
				continue;
			}
			require $file;
		}
		do_action( 'automator_after_traits' );
	}

	/**
	 * Adds `utm_r` parameters to all Automator Pro Links.
	 *
	 * @return void.
	 */
	public function global_utm_r_links() {

		$uncanny_automator_enabled_global_utm = apply_filters( 'uncanny_automator_enabled_global_utm', true );

		$uncannyautomator_source = get_option( 'uncannyautomator_source' );

		if ( false === $uncannyautomator_source || empty( $uncannyautomator_source ) ) {
			return;
		}

		if ( ! $uncanny_automator_enabled_global_utm ) {
			return;
		}
		?>
		<script>

			jQuery(document).ready(function ($) {

				"use strict";

				var automator_pro_links = 'a[href^="https://automatorplugin.com"]';

				var _update_url_parameter = function (uri, key, value) {

					// remove the hash part before operating on the uri
					var i = uri.indexOf('#');
					var hash = i === -1 ? '' : uri.substr(i);
					uri = i === -1 ? uri : uri.substr(0, i);

					var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
					var separator = uri.indexOf('?') !== -1 ? "&" : "?";
					if (uri.match(re)) {
						uri = uri.replace(re, '$1' + key + "=" + value + '$2');
					} else {
						uri = uri + separator + key + "=" + value;
					}
					return uri + hash;  // finally append the hash as well
				}

				var source = "<?php echo esc_js( $uncannyautomator_source ); ?>";

				// Add utmr to all automator upgrade links.
				$.each($(automator_pro_links), function () {
					var link_with_utmr = _update_url_parameter($(this).attr('href'), 'utm_r', '<?php echo esc_js( $uncannyautomator_source ); ?>');
					$(this).attr('href', link_with_utmr);
				});

				// Add utmr to all automator upgrade links which are not accessible on document ready.
				$(document).on('mouseover', automator_pro_links, function (e) {
					var link_with_utmr = _update_url_parameter($(this).attr('href'), 'utm_r', '<?php echo esc_js( $uncannyautomator_source ); ?>');
					$(this).attr('href', link_with_utmr);
				});

			});
		</script>
		<?php
	}

	/**
	 * Initiate the set-up wizard.
	 */
	public function initiate_setup_wizard() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			include_once UA_ABSPATH . 'src/core/admin/setup-wizard/setup-wizard.php';
			$setup_wizard = new Setup_Wizard();
		}

		if ( defined( 'DOING_AJAX' ) ) {
			// Add the ajax listener.
			include_once UA_ABSPATH . 'src/core/admin/setup-wizard/setup-wizard.php';
			add_action(
				'wp_ajax_uo_setup_wizard_set_tried_connecting',
				array(
					'\Uncanny_Automator\Setup_Wizard',
					'set_tried_connecting',
				)
			);
		}
	}
}

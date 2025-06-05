<?php

namespace Uncanny_Automator;

use WP_Error;
use Uncanny_Automator\Services\Addons\Data\License_Summary;

/**
 * Class Admin_Menu
 *
 * @package Uncanny_Automator
 */
class Admin_Menu {

	/**
	 * @var null
	 */
	public static $instance = null;

	/**
	 * @var array
	 */
	public static $tabs = array();
	/**
	 * Setting Page title
	 *
	 * @var
	 */
	public $settings_page_slug;

	/**
	 * Automator Connect
	 *
	 * @var
	 */
	public $automator_connect;

	/**
	 * Setting Base URL
	 *
	 * @var
	 */
	public static $automator_connect_url = AUTOMATOR_STORE_URL;

	/**
	 * Setting Connect URLs
	 *
	 * @var
	 */
	public static $automator_connect_page = AUTOMATOR_FREE_STORE_CONNECT_URL;
	/**
	 * @var
	 */
	public $backend_enqueue_in;
	/**
	 * The Rest-API route
	 *
	 * The v2 means we are using version 2 of the wp rest api
	 *
	 * @since    2.0
	 * @access   private
	 * @var      string
	 */
	private $root_path = 'uap/v2';
	/**
	 * @var
	 */
	public static $license;

	/**
	 * class constructor
	 */
	public function __construct() {
		// Global assets
		//$this->enqueue_global_assets();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_global_assets' ), 99, 1 );

		// Add inline JS data
		$this->dashboard_inline_js_data();
		$this->integrations_inline_js_data();

		add_filter(
			'admin_title',
			array(
				$this,
				'modify_report_titles',
			),
			40,
			2
		);

		// Run licence key update
		add_action(
			'admin_init',
			array(
				$this,
				'update_automator_connect',
			),
			1
		);

		// Auto opt-in users if they are connected.

		add_action( 'admin_init', array( $this, 'auto_optin_users' ), 20 );

		// Setup Theme Options Page Menu in Admin
		add_action( 'admin_init', array( $this, 'plugins_loaded' ), 1 );

		add_action(
			'admin_menu',
			array(
				$this,
				'register_options_menu_page',
			)
		);

		add_action(
			'admin_menu',
			array(
				$this,
				'register_submenu_app_integrations',
			)
		);

		add_action(
			'admin_menu',
			array(
				$this,
				'register_legacy_options_menu_page',
			),
			999
		);
		add_action(
			'admin_init',
			array(
				$this,
				'maybe_redirect_to_first_settings_tab',
			),
			1000
		);

		add_filter( 'admin_body_class', array( $this, 'add_legacy_activity_logs_css_class' ), 1, 1 );

		$this->register_dashboard_recent_articles_endpoint();
	}

	/**
	 * @return void
	 */
	public function register_dashboard_recent_articles_endpoint() {
		$recent_articles = new \Uncanny_Automator\Services\Dashboard\Recent_Articles();
		$recent_articles->register_hooks();
	}

	/**
	 * Adding legacy class name, since `null` is not an option in add_submenu_page()
	 *
	 * @param $classes
	 *
	 * @return mixed|string
	 */
	public function add_legacy_activity_logs_css_class( $classes ) {
		global $current_screen;
		if ( 'admin_page_uncanny-automator-recipe-activity-details' !== $current_screen->id ) {
			return $classes;
		}

		return "$classes uo-recipe_page_uncanny-automator-recipe-activity-details";
	}

	/**
	 * @return Admin_Menu|null
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new Admin_Menu();
		}

		return self::$instance;
	}

	/**
	 * Updates the `automator_reporting` to true if the user is connected.
	 *
	 * @return void.
	 */
	public function auto_optin_users() {

		$option_key = 'automator_reporting';

		$uap_automator_allow_tracking = automator_get_option( $option_key, false );

		$is_connected = Api_Server::get_license_type();

		if ( false === $uap_automator_allow_tracking && false !== $is_connected ) {
			// Opt-in the user automatically.
			automator_update_option( $option_key, true );
		}
	}

	/**
	 *
	 */
	public function plugins_loaded() {
		$tabs = array();

		$tabs       = apply_filters( 'uap_settings_tabs', $tabs );
		self::$tabs = apply_filters( 'automator_settings_tabs', $tabs );
		if ( self::$tabs ) {
			$tabs = json_decode( wp_json_encode( self::$tabs ), false );
			foreach ( $tabs as $tab => $tab_settings ) {
				if ( $tab_settings->fields ) {
					foreach ( $tab_settings->fields as $field_id => $field_settings ) {
						$args = isset( $field_settings->field_args ) ? $field_settings->field_args : array();
						register_setting( $tab_settings->settings_field, $field_id, $args ); // phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingMissing
					}
				}
			}
		}
	}

	/**
	 * is_a_log
	 *
	 * @param string $hook
	 *
	 * @return bool
	 */
	public function is_a_log( $hook ) {
		$log_pages = apply_filters(
			'automator_log_pages',
			array(
				'uncanny-automator-recipe-activity',
				'uncanny-automator-recipe-activity-details',
				'admin_page_uncanny-automator-recipe-activity-details',
				'uncanny-automator-debug-log',
				'uncanny-automator-recipe-log',
				'uncanny-automator-trigger-log',
				'uncanny-automator-action-log',
				'uncanny-automator-admin-logs',
			)
		);

		foreach ( $log_pages as $page ) {
			if ( strpos( $hook, $page ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create Plugin options menu
	 */
	public function register_options_menu_page() {

		if ( ! current_user_can( apply_filters( 'automator_admin_menu_capability', 'manage_options' ) ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			remove_menu_page( 'edit.php?post_type=uo-recipe' );
		}

		$parent_slug              = 'edit.php?post_type=uo-recipe';
		$parent_slug_fake         = 'options.php';
		$this->settings_page_slug = $parent_slug;
		$function                 = array(
			$this,
			'logs_options_menu_page_output',
		);

		// Create "Dashboard" submenu page
		add_submenu_page(
			$parent_slug,
			esc_attr__( 'Dashboard', 'uncanny-automator' ),
			esc_attr__( 'Dashboard', 'uncanny-automator' ),
			'manage_options',
			'uncanny-automator-dashboard',
			array(
				$this,
				'dashboard_menu_page_output',
			),
			0
		);

		// Create "All integrations" submenu page
		add_submenu_page(
			$parent_slug,
			esc_attr__( 'All integrations', 'uncanny-automator' ),
			esc_attr__( 'All integrations', 'uncanny-automator' ),
			'manage_options',
			'uncanny-automator-integrations',
			array(
				$this,
				'integrations_template',
			)
		);

		// Recipe details (modal).
		add_submenu_page(
			$parent_slug_fake,
			esc_attr__( 'Recipe activity details', 'uncanny-automator' ),
			esc_attr__( 'Recipe activity details', 'uncanny-automator' ),
			'manage_options',
			'uncanny-automator-recipe-activity-details',
			$function
		);
	}

	/**
	 * @return void
	 */
	public function register_submenu_app_integrations() {
		// Get the global $submenu array
		add_submenu_page(
			'edit.php?post_type=uo-recipe',
			/* translators: 1. Trademarked term */
			esc_html__( 'App integrations', 'uncanny-automator' ),
			esc_html__( 'App integrations', 'uncanny-automator' ),
			'manage_options',
			admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations' )
		);
	}

	/**
	 * Create legacy options menu
	 */
	public function register_legacy_options_menu_page() {

		if ( has_filter( 'uap_settings_tabs' ) ) {
			/* translators: 1. Trademarked term */
			$page_title               = sprintf( esc_attr__( '%1$s settings', 'uncanny-automator' ), 'Uncanny Automator' );
			$capability               = 'manage_options';
			$menu_title               = esc_attr__( 'Legacy settings', 'uncanny-automator' );
			$menu_slug                = 'uncanny-automator-settings';
			$this->settings_page_slug = $menu_slug;
			$function                 = array(
				$this,
				'options_menu_settings_page_output',
			);

			add_submenu_page( 'edit.php?post_type=uo-recipe', $page_title, $menu_title, $capability, $menu_slug, $function ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined
		}
	}


	/**
	 * Create Page view
	 */
	public function logs_options_menu_page_output() {

		$logs_class = __DIR__ . '/admin-logs/wp-list-table/class-logs-list-table.php';

		include_once $logs_class;

		include_once Utilities::automator_get_include( 'recipe-logs-view.php' );
	}

	/**
	 * Create Page view
	 */
	public function tools_menu_page_output() {
		include_once UA_ABSPATH . 'src/core/views/admin-tools-header.php';
		?>
		<div class="wrap uap">
			<section class="uap-logs">
				<div class="uap-log-table-container">
					<?php
					include UA_ABSPATH . 'src/core/views/html-admin-status.php';
					?>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Create Dashboard view
	 */
	public function dashboard_menu_page_output() {

		// Check connect and credits
		$dashboard = self::get_dashboard_details();

		?>

		<div class="wrap uap">
			<?php include UA_ABSPATH . 'src/core/views/admin-dashboard.php'; ?>
		</div>

		<?php
	}

	/**
	 * @return object
	 */
	public static function get_dashboard_details() {

		$is_connected = Api_Server::is_automator_connected( true );

		$redirect_url = admin_url( 'admin.php?page=uncanny-automator-dashboard' );
		$connect_url  = self::$automator_connect_url . self::$automator_connect_page . '?redirect_url=' . rawurlencode( $redirect_url );

		$is_elite_active = defined( 'UAEI_PLUGIN_VERSION' );
		$is_pro_active   = false;

		if ( isset( $is_connected['item_name'] ) ) {
			if ( defined( 'AUTOMATOR_PRO_ITEM_NAME' ) && AUTOMATOR_PRO_ITEM_NAME === $is_connected['item_name'] ) {
				$is_pro_active = true;
			}
		}

		$user = wp_get_current_user();

		$paid_usage_count = isset( $is_connected['paid_usage_count'] ) ? $is_connected['paid_usage_count'] : 0;
		$usage_limit      = isset( $is_connected['usage_limit'] ) ? $is_connected['usage_limit'] : 250;
		$first_name       = isset( $is_connected['customer_name'] ) ? $is_connected['customer_name'] : esc_html__( 'Guest', 'uncanny-automator' );
		$avatar           = isset( $is_connected['user_avatar'] ) ? $is_connected['user_avatar'] : esc_url( get_avatar_url( $user->ID ) );

		$connected_sites = isset( $is_connected['license_id'] ) && isset( $is_connected['payment_id'] )
			? self::$automator_connect_url . 'checkout/purchase-history/?license_id=' . $is_connected['license_id'] . '&action=manage_licenses&payment_id=' . $is_connected['payment_id']
			: '#';

		$free_credits = $is_connected ? ( $usage_limit - $paid_usage_count ) : 250;

		$kb_articles = array(
			array(
				'title' => esc_html__( 'Getting started', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'What is Uncanny Automator?', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/what-is-uncanny-automator/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
					array(
						'title' => esc_html__( 'Installing Uncanny Automator', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/installing-uncanny-automator/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
					array(
						'title' => esc_html__( 'Creating a Recipe', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/creating-a-recipe/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
					array(
						'title' => esc_html__( 'Recipes for Everyone', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/anonymous-recipes/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
					array(
						'title' => esc_html__( 'Managing Triggers', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/managing-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
					array(
						'title' => esc_html__( 'Managing Actions', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/managing-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
					array(
						'title' => esc_html__( 'Managing Tokens', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/managing-tokens/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
					array(
						'title' => esc_html__( 'Scheduled Actions', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/scheduled-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
					array(
						'title' => esc_html__( 'Action filters / conditions', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/action-filters-conditions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
					array(
						'title' => esc_html__( 'What are App Credits?', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
					array(
						'title' => esc_html__( 'License Keys', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/where-can-i-find-my-license-key/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
					array(
						'title' => esc_html__( 'Working with Redirects', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/working-with-redirects/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_getting_started',
					),
				),
			),
			array(
				'title' => esc_html__( 'Key resources', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'Uncanny Automator Changelog', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/uncanny-automator-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources',
					),
					array(
						'title' => esc_html__( 'Uncanny Automator Pro Changelog', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/uncanny-automator-pro-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources',
					),
					array(
						'title' => esc_html__( 'Having trouble? Read this', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/important-notes-troubleshooting/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources',
					),
					array(
						'title' => esc_html__( 'Using Automator Logs', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/using-automator-logs/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources',
					),
					array(
						'title' => esc_html__( 'Developer Resources', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/developer-resources/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources',
					),
					array(
						'title' => esc_html__( 'Data Privacy and GDPR', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/data-privacy-and-gdpr/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources',
					),
					array(
						'title' => esc_html__( 'Usage Tracking', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/usage-tracking/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources',
					),
					array(
						'title' => esc_html__( 'Connecting your site', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/connecting-your-site-with-a-free-uncanny-automator-account/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources',
					),
					array(
						'title' => esc_html__( 'PHP version', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/php-version/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_key_resources',
					)
				),
			),
			array(
				'title' => esc_html__( 'Webhooks', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'Incoming Webhook Triggers', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/webhook-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Webhook Actions', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/send-data-to-a-webhook/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Sending a JSON Array', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/sending-a-json-array-with-automators-outgoing-webhook-action/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Sending a JSON Object', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/sending-a-json-object-with-outgoing-webhooks/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
				),
			),
			array(
				'title' => esc_html__( 'Special triggers', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'Magic Buttons & Magic Links', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/magic-button/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'Schedule', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/schedule/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'Google Sheets™ Webhook Addon', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/google-sheets-webhook-addon/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'Run Now', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/run-now/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'Advanced Custom Fields', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/advanced-custom-fields/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'ActiveCampaign', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/activecampaign-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'IFTTT', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/ifttt-to-wordpress/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'OptinMonster Triggers', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/optinmonster-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'Mailchimp', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/mailchimp-wordpress-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'WhatsApp', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/whatsapp/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'Help Scout', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/helpscout-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'Telegram', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/telegram/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
					array(
						'title' => esc_html__( 'WooCommerce', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/woocommerce-triggers/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_triggers',
					),
				),
			),
			array(
				'title' => esc_html__( 'Special actions', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'Run a WordPress hook', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/run-a-wordpress-hook/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Call a custom function/method', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/call-a-custom-function-method/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'The Formatter Action', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/the-formatter-action/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Google Sheets', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/google-sheets/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Google Calendar', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/google-calendar/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'OpenAI', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/open-ai/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'X / Twitter', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/twitter/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Mailchimp', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/mailchimp/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'ActiveCampaign', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/activecampaign/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Facebook Pages', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/facebook/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'OptinMonster', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/optinmonster/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Zapier Actions', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/working-with-zapier-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'WhatsApp', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/whatsapp/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Integrately', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/integrately/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Popup Maker', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/working-with-popup-maker-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Slack', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/slack/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Zoom', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/zoom/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Integromat', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/working-with-integromat-actions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'HubSpot', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/hubspot/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'GoToTraining', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/gototraining/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'GoToWebinar', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/gotowebinar/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Twilio', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/twilio/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Instagram Business', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/instagram/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Send a certificate', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/generate-an-email-a-certificate-to-the-user/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Uncanny Continuing Education Credits', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/uncanny-continuing-education-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'IFTTT', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/ifttt/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Create WooCommerce orders', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/create-woocommerce-orders/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'LinkedIn Pages', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/linkedin-pages/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Airtable', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/airtable/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Make', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/make/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'MailerLite', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/mailerlite/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Drip', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/drip/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Microsoft Teams (Beta)', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/microsoft-teams/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Telegram', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/telegram/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Zoho Campaigns', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/zoho-campaigns/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
					array(
						'title' => esc_html__( 'Facebook Groups (Deprecated)', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/facebook-groups/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_actions',
					),
				),
			),
			array(
				'title' => esc_html__( 'Special tokens', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'User meta tokens', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/user-meta-tokens/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_tokens',
					),
					array(
						'title' => esc_html__( 'Post meta tokens', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/post-meta-tokens/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_tokens',
					),
					array(
						'title' => esc_html__( 'Calculations (math equations)', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/calculations-math-equations/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_special_tokens',
					),
				),
			),
			array(
				'title' => esc_html__( 'Custom User Fields Addon', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'Installing the Custom User Fields Addon', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/installing-the-custom-user-fields-addon/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Managing Custom User Fields', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/managing-custom-user-fields/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Updating Custom User Field Data', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/updating-custom-user-field-data/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Displaying Custom User Fields', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/displaying-custom-user-fields/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Uncanny Automator Custom User Fields Addon Changelog', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/uncanny-automator-custom-user-fields-addon-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
				),
			),
			array(
				'title' => esc_html__( 'Restrict Content Addon', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'Installing the Restrict Content Addon', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/installing-the-restrict-content-addon/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Managing Access Levels', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/managing-access-levels/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Restrict Content Shortcodes', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/restrict-content-shortcodes/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Restrict Content for Blocks', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/restrict-content-for-blocks/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Restrict WordPress Pages & Posts', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/restrict-wordpress-pages-posts/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Restrict Content Integration with Automator', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/restrict-content-integration-with-automator/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Uncanny Automator Restrict Content Addon Changelog', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/uncanny-automator-restrict-content-addon-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
				),
			),
			array(
				'title' => esc_html__( 'User Lists Addon', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'Installing the User Lists Addon', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/installing-the-user-lists-addon/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Managing user lists', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/managing-user-lists/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Managing user list subscriptions', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/managing-user-list-subscriptions/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'User Lists Integration with Automator', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/user-lists-integration-with-automator/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Sending bulk emails with user lists', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/sending-bulk-emails-with-user-lists/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'The Unsubscribed list and managing subscriptions', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/the-unsubscribed-list-subscription-management/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Uncanny Automator User Lists Addon Changelog', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/uncanny-automator-user-lists-addon-changelog/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
				),
			),
			array(
				'title' => esc_html__( 'Advanced Topics', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'User Loops', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/user-loops/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Post Loops', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/post-loops/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Custom Scheduling', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/how-to-use-custom-scheduling-in-wordpress-automations/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
				),
			),
			array(
				'title' => esc_html__( 'Registering users', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'Registration form with Contact Form 7', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/create-a-registration-form-with-contact-form-7/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users',
					),
					array(
						'title' => esc_html__( 'Registration form with Caldera Forms', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/create-a-registration-form-with-caldera-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users',
					),
					array(
						'title' => esc_html__( 'Registration form with Ninja Forms', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/create-a-registration-form-with-ninja-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users',
					),
					array(
						'title' => esc_html__( 'Registration form with Gravity Forms', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/create-a-registration-form-with-gravity-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users',
					),
					array(
						'title' => esc_html__( 'Registration form with Formidable Forms', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/create-a-registration-form-with-formidable-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users',
					),
					array(
						'title' => esc_html__( 'Registration form with WPForms', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/create-a-registration-form-with-wpforms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_registering_users',
					),
				),
			),
			array(
				'title' => esc_html__( 'Integrations FAQ', 'uncanny-automator' ),
				'articles' => array(
					array(
						'title' => esc_html__( 'Contact Form 7', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/contact-form-7/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'Gravity Forms', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/gravity-forms/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
					array(
						'title' => esc_html__( 'WooCommerce', 'uncanny-automator' ),
						'url' => 'https://automatorplugin.com/knowledge-base/woocommerce/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=kb_integration_faq',
					),
				),
			)
		);

		$faq_items = array(
			array(
				'question' => esc_html__( 'What are app credits?', 'uncanny-automator' ),
				'answer'   => esc_html__( "Some app integrations connect to other services using an API. Automator's app credit system allows free plugin users to try this out. Passing a record to one of these integrations uses one app credit.", 'uncanny-automator' ),
			),
			array(
				'question' => esc_html__( 'Do I need app credits?', 'uncanny-automator' ),
				'answer'   => esc_html__( 'App credits are only needed for app integrations that pass through an API. Everything else is unrestricted (and Pro users get unlimited app credits).', 'uncanny-automator' ),
			),
			array(
				'question' => esc_html__( 'Can I get more app credits?', 'uncanny-automator' ),
				'answer'   => esc_html__( 'If you use more than 250 app credits, you must either purchase the Pro version or disable your actions that use credits.', 'uncanny-automator' ),
			),
		);

		return (object) array(
			// The number of credits used by pro.
			'paid_usage_count'   => absint( $paid_usage_count ),
			// Check if the user is using Automator Pro
			'is_pro'             => $is_pro_active,
			'is_elite'           => $is_elite_active,
			// Is Pro connected
			'is_pro_installed'   => defined( 'AUTOMATOR_PRO_FILE' ) ? true : false,
			'pro_activate_link'  => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=general&general=license' ),
			// Check if this site is connected to an automatorplugin.com account
			'has_site_connected' => $is_connected ? true : false,
			// Get data about the CONNECTED user (automatorplugin.com)
			// If no user is connected, "connected_user" should be NULL
			'connected_user'     => (object) array(
				// First name.
				// If first name is not available, then Display name
				'first_name' => $first_name,
				// Gravatar
				'avatar'     => $avatar,
				'url'        => (object) array(
					// automatorplugin.com link to edit profile
					'edit_profile'       => self::$automator_connect_url . 'my-account/',
					// automatorplugin.com link to manage connected sites under this account
					'connected_sites'    => $connected_sites,
					// URL to disconnect current site from the account
					'disconnect_account' => add_query_arg(
						array(
							'action' => 'discount_automator_connect',
							'state'  => wp_create_nonce( 'automator_setup_wizard_redirect_nonce' ),
						)
					),
				),
			),
			'kb_articles'        => $kb_articles,
			'faq_items'          => $faq_items,
			'connect_url'        => $connect_url,
			'miscellaneous'      => (object) array(
				'free_credits'              => $free_credits,
				'site_url_without_protocol' => preg_replace( '(^https?://)', '', get_site_url() ),
			),
			'upgrade_url'        => 'https://automatorplugin.com/pricing/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=pricing',
		);
	}

	/**
	 * Create Page view
	 */
	public function database_tools_menu_page_output() {
		include_once UA_ABSPATH . 'src/core/views/admin-tools-header.php';
		?>
		<div class="wrap uap">
			<section class="uap-logs">
				<div class="uap-log-table-container">
					<?php
					include UA_ABSPATH . 'src/core/views/html-database-tools.php';
					?>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * @param $admin_title
	 * @param $title
	 *
	 * @return string
	 */
	public function modify_report_titles( $admin_title, $title ) {

		if ( automator_filter_has_var( 'tab' ) ) {
			switch ( sanitize_text_field( automator_filter_input( 'tab' ) ) ) {
				case 'recipe-log':
					$admin_title = sprintf( '%s &mdash; %s', esc_attr__( 'Recipe log', 'uncanny-automator' ), $admin_title );
					break;
				case 'trigger-log':
					$admin_title = sprintf( '%s &mdash; %s', esc_attr__( 'Trigger log', 'uncanny-automator' ), $admin_title );
					break;
				case 'action-log':
					$admin_title = sprintf( '%s &mdash; %s', esc_attr__( 'Action log', 'uncanny-automator' ), $admin_title );
					break;
			}
		}

		return apply_filters( 'automator_report_titles', $admin_title, $title );
	}

	/**
	 * is_pro_older_than_37
	 *
	 * Returns false if Automator Pro is enabled and older than 3.8
	 *
	 * @return void
	 */
	public function is_pro_older_than_38() {

		if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			return version_compare( AUTOMATOR_PRO_PLUGIN_VERSION, '3.8', '<' );
		}

		return false;
	}

	/**
	 * @return void
	 */
	public function maybe_redirect_to_first_settings_tab() {

		if ( $this->is_pro_older_than_38() ) {
			return;
		}

		if ( ! automator_filter_has_var( 'post_type' ) ) {
			return;
		}

		if ( 'uo-recipe' !== automator_filter_input( 'post_type' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( 'page' ) ) {
			return;
		}

		if ( 'uncanny-automator-settings' !== automator_filter_input( 'page' ) ) {
			return;
		}

		if ( automator_filter_has_var( 'tab' ) ) {
			return;
		}

		if ( empty( self::$tabs ) ) {
			return;
		}

		$tab_ids = array_keys( self::$tabs );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'uo-recipe',
					'page'      => 'uncanny-automator-settings',
					'tab'       => array_shift( $tab_ids ),
				),
				admin_url( 'edit.php' )
			)
		);
	}

	/**
	 *
	 */
	public function options_menu_settings_page_output() {

		if ( $this->is_pro_older_than_38() ) {

			$active = automator_filter_has_var( 'tab' ) ? sanitize_text_field( automator_filter_input( 'tab' ) ) : 'settings';

			if ( 'settings' === $active ) {
				// Check connect and credits
				$is_connected = Api_Server::is_automator_connected();

				$website            = preg_replace( '(^https?://)', '', get_home_url() );
				$redirect_url       = site_url( 'wp-admin/edit.php?post_type=uo-recipe&page=uncanny-automator-settings' );
				$connect_url        = self::$automator_connect_url . self::$automator_connect_page . '?redirect_url=' . rawurlencode( $redirect_url );
				$disconnect_account = add_query_arg(
					array(
						'action' => 'discount_automator_connect',
						'state'  => 'automator_setup_wizard_redirect_nonce',
					)
				);

				$license_data = false;
				if ( $is_connected ) {
					$license_data = automator_get_option( 'uap_automator_free_license_data' );
				}

				$is_pro_active = false;

				//if ( isset( $is_connected['item_name'] ) ) {
				if ( defined( 'AUTOMATOR_PRO_ITEM_NAME' ) ) {
					$is_pro_active = true;
				}
				//}

				$uap_automator_allow_tracking = automator_get_option( 'automator_reporting', false );

				if ( $is_pro_active ) {
					$license_data = $this->check_pro_license( true );

					$license = automator_get_option( 'uap_automator_pro_license_key' );
					$status  = automator_get_option( 'uap_automator_pro_license_status' ); // $license_data->license will be either "valid", "invalid", "expired", "disabled"

					// Check license status
					$license_is_active = ( 'valid' === $status ) ? true : false;

					// CSS Classes
					$license_css_classes = array();

					if ( $license_is_active ) {
						$license_css_classes[] = 'uo-license--active';
					}

					// Set links. Add UTM parameters at the end of each URL
					$where_to_get_my_license = 'https://automatorplugin.com/knowledge-base/where-can-i-find-my-license-key/?utm_source=uncanny_automator_pro&utm_medium=license_page&utm_content=where_to_get_my_license';
					$buy_new_license         = 'https://automatorplugin.com/pricing/?utm_source=uncanny_automator_pro&utm_medium=license_page&utm_content=buy_new_license';
					$knowledge_base          = 'https://automatorplugin.com/knowledge-base/?utm_source=uncanny_automator_pro&utm_medium=license_page&utm_content=knowledge_base';

				}
			}
		}

		$this->settings_tabs();
		include Utilities::automator_get_include( 'automator-settings.php' );
	}

	/**
	 * @param string $current
	 */
	public function settings_tabs( $current = 'settings' ) {

		$tabs = json_decode( wp_json_encode( self::$tabs ), false );
		if ( automator_filter_has_var( 'tab' ) ) {
			$current = esc_html( automator_filter_input( 'tab' ) );
		}

		if ( $tabs ) {
			$html = '<h2 class="nav-tab-wrapper">';
			foreach ( $tabs as $tab => $tab_settings ) {
				$class = ( (string) $tab === (string) $current ) ? 'nav-tab-active' : '';
				$url   = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings';
				$html .= '<a class="nav-tab ' . $class . '" href="' . $url . '&tab=' . $tab . '">' . $tab_settings->name . '</a>'; // phpcs:ignore Generic.Formatting.MultipleStatementAlignment.NotSameWarning
			}
			$html .= '</h2>';
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Updates or remove the license key of the connected user depending on the `action` query parameter..
	 *
	 * @return void
	 */
	public function update_automator_connect() {

		if ( 'update_free_key' === automator_filter_input( 'action' ) && ! empty( automator_filter_input( 'uap_automator_free_license_key' ) ) ) {

			$this->activate_license();

			return;
		}

		if ( 'discount_automator_connect' === automator_filter_input( 'action' ) ) {

			$this->deactivate_license();
		}
	}

	/**
	 * Validates the given nonce.
	 *
	 * @param string $nonce The nonce to check.
	 *
	 * @return void.
	 */
	private function validate_credentials( $nonce = '' ) {

		// Validate request.
		if ( ! current_user_can( 'manage_options' ) ) {

			wp_die( 'Error: Insufficient privilege - The current logged in user does not have administrative access to execute this action.' );

		}

		// Validate nonce.
		if ( ! wp_verify_nonce( $nonce, 'automator_setup_wizard_redirect_nonce' ) ) {

			wp_die( 'Error: Invalid nonce.' );

		}
	}

	/**
	 * API call to check if License key is valid
	 *
	 * The updater class does this for you. This function can be used to do
	 * something custom.
	 *
	 * @return null|object|bool
	 * @since    1.0.0
	 * @throws \Exception
	 */
	public function check_pro_license( $force_check = false ) {
		$last_checked = automator_get_option( 'uap_automator_pro_license_last_checked' );
		if ( ! empty( $last_checked ) && false === $force_check ) {
			$datediff = time() - $last_checked;
			if ( $datediff < DAY_IN_SECONDS ) {
				return null;
			}
		}
		if ( true === $force_check ) {
			automator_delete_option( 'uap_automator_pro_license_last_checked' );
		}
		$license = trim( automator_get_option( 'uap_automator_pro_license_key' ) );
		if ( empty( $license ) ) {
			return new \stdClass();
		}

		$license_data = Api_Server::is_automator_connected( $force_check );

		if ( ! $license_data ) {
			return new \stdClass();
		}

		$license_data = (object) $license_data;

		// this license is still valid
		if ( 'valid' === $license_data->license ) {
			automator_update_option( 'uap_automator_pro_license_status', $license_data->license );
			if ( 'lifetime' !== $license_data->expires ) {
				automator_update_option( 'uap_automator_pro_license_expiry', $license_data->expires );
			} else {
				automator_update_option(
					'uap_automator_pro_license_expiry',
					wp_date( 'Y-m-d H:i:s', mktime( 12, 59, 59, 12, 31, 2099 ) )
				);
			}

			if ( 'lifetime' !== $license_data->expires ) {
				$expire_notification = new \DateTime( $license_data->expires, wp_timezone() );
				automator_update_option( 'uap_automator_pro_license_expiry_notice', $expire_notification );
				if ( wp_get_scheduled_event( 'uapro_notify_admin_of_license_expiry' ) ) {
					wp_unschedule_hook( 'uapro_notify_admin_of_license_expiry' );
				}
				// 1 hour after the license is schedule to expire.
				wp_schedule_single_event( $expire_notification->getTimestamp() + 3600, 'uapro_notify_admin_of_license_expiry' );

			}
		} else {
			automator_update_option( 'uap_automator_pro_license_status', 'invalid' );
			automator_update_option( 'uap_automator_pro_license_expiry', '' );
			// this license is no longer valid
		}
		automator_update_option( 'uap_automator_pro_license_last_checked', time() );

		return $license_data;
	}


	/**
	 * Enqueues global assets in the Automator pages
	 */
	public function enqueue_global_assets( $hook ) {
		global $current_screen;
		// List of pages where we have to add the assets
		$this->backend_enqueue_in = apply_filters(
			'automator_enqueue_global_assets',
			array(
				'post.php', // Has filter, check callback
				'edit-tags.php',
				'options.php',
				'uncanny-automator-dashboard',
				'uncanny-automator-integrations',
				'uncanny-automator-config',
				'uncanny-automator-tools',
				'uncanny-automator-action-log',
				'uncanny-automator-trigger-log',
				'uncanny-automator-recipe-log',
				'uncanny-automator-recipe-activity-details',
				'uncanny-automator-admin-logs',
				'uncanny-automator-admin-tools',
				'uncanny-automator-pro-upgrade',
				'uncanny-automator-addons',
				'uncanny-automator-setup-wizard',
				'edit.php',
			)
		);

		// Enqueue admin scripts
		//add_action(
		//  'admin_enqueue_scripts',
		//function ( $hook ) {

		$hooks_assets_loaded = array(
			'post.php',
			'edit.php',
			'edit-tags.php', // Added in 4.2 for review banner
		);

		// Add exception for the "post.php" hook
		if ( in_array( $hook, $hooks_assets_loaded, true ) ) {
			if (
				'uo-recipe' !== $this->get_current_screen_post_type() &&
				'admin_page_uncanny-automator-recipe-activity-details' !== $current_screen->id
			) {
				return;
			}
		}

		// Check if the current page is one of the target pages
		if ( ! in_array(
			str_replace(
				array(
					'uo-recipe_page_',
					'admin_page_',
				),
				'',
				$hook
			),
			$this->backend_enqueue_in,
			true
		) ) {
			return;
		}

		// Load Automator font
		wp_enqueue_style(
			'uap-admin-font',
			'https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&display=swap',
			array(),
			Utilities::automator_get_version()
		);

		Utilities::enqueue_asset(
			'uap-admin',
			'main',
			array(
				'localize' => array(
					'UncannyAutomatorBackend' => $this->get_js_backend_inline_data( $hook ),
					'UncannyAutomator' => array(),
				)
			)
		);
	}

	/**
	 * Method get_current_screen_post_type
	 *
	 * This method will return the post type from `get_post_type()`.
	 * Defaults to http query var `post_type` if get_post_type() is empty.
	 *
	 * @return string The current post type loaded inside wp-admin.
	 */
	public function get_current_screen_post_type() {

		$post_type = (string) get_post_type();

		if ( ! empty( $post_type ) ) {
			return $post_type;
		}

		if ( ! empty( automator_filter_input( 'post_type' ) ) ) {
			return automator_filter_input( 'post_type' );
		}
	}

	/**
	 * Returns the JS object with dynamic data required in some backend pages
	 *
	 * @param  {String} $hook The ID of the current page
	 *
	 * @return array        The inline data
	 */
	public function get_js_backend_inline_data( $hook ) {
		// Set default data
		$automator_backend_js = array(
			'ajax'       => array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => \wp_create_nonce( 'uncanny_automator' ),
			),
			'rest'       => array(
				'url'   => esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ), // Automator URL endpoint
				'base'  => esc_url_raw( rest_url() ), // Actual URL of the /wp-json/
				'nonce' => \wp_create_nonce( 'wp_rest' ),
			),
			'debugging'  => array(
				'enabled' => (bool) AUTOMATOR_DEBUG_MODE,
			),
			'components' => array(
				'icon'     => array(
					'integrations' => $this->get_integrations_for_components(),
				),
			),
			'_site'      => array(
				'automator' => array(
					'license_details'    => ( new License_Summary() )->get_license_summary(),
					'is_pro_active'      => defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ),
					'links' => array(
						'marketing_referer'  => automator_get_option( 'uncannyautomator_source', '' ),
						'external' => array(
							'url_upgrade_to_pro' => add_query_arg(
								// UTM
								array(
									'utm_source'  => 'uncanny_automator',
									'utm_medium'  => 'upgrade_to_pro',
									'utm_content' => 'upgrade_to_pro_button',
								),
								'https://automatorplugin.com/pricing/'
							),
						),
						'internal' => array(
							'all_recipes'        => admin_url( 'edit.php?post_type=uo-recipe' ),
							'tools'              => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-tools' ),
							'manage_license'     => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=general&general=license' ),
						)
					)
				),
			),
		);

		// Filter data
		$automator_backend_js = apply_filters(
			'automator_assets_backend_js_data',
			$automator_backend_js,
			$hook
		);

		return $automator_backend_js;
	}

	/**
	 * Adds required JS data for the Dashboard page. Before doing so, checks if
	 * the current page is indeed the Dashboard page.
	 * This uses the filter "automator_assets_backend_js_data". If the page is
	 * not the targeted page, it just returns the data unmodified.
	 */
	private function dashboard_inline_js_data() {
		// Filter inline data
		add_filter(
			'automator_assets_backend_js_data',
			function ( $data, $hook ) {
				// Check if the current page is the "Dashboard" page
				if ( 'uo-recipe_page_uncanny-automator-dashboard' === (string) $hook ) {
					// Get data about the connected site
					$this->automator_connect = Api_Server::is_automator_connected();

					// Check if the user has Automator Pro
					$is_pro_active = false;
					if ( isset( $this->automator_connect['item_name'] ) ) {
						if ( defined( 'AUTOMATOR_PRO_ITEM_NAME' ) && AUTOMATOR_PRO_ITEM_NAME === $this->automator_connect['item_name'] ) {
							$is_pro_active = true;
						}
					}

					// Add it to the main JS variable
					$data['isPro'] = $is_pro_active;

					// Check if the site is connected
					$data['hasSiteConnected'] = $this->automator_connect ? true : false;
				}

				return $data;
			},
			10,
			2
		);
	}

	/**
	 * Adds required JS data for the Integrations page. Before doing so, checks
	 * if the current page is indeed the Integrations page. This uses the
	 * filter "automator_assets_backend_js_data". If the page is not the
	 * targeted page, it just returns the data unmodified.
	 */
	private function integrations_inline_js_data() {
		// Filter inline data
		add_filter(
			'automator_assets_backend_js_data',
			function ( $data, $hook ) {
				// Check if the current page is the "Integrations" page
				if ( 'uo-recipe_page_uncanny-automator-integrations' === (string) $hook ) {
					// Check if integrations are already loaded in transient.
					$integrations = get_transient( 'automator_all_integration_items' );

					if ( false === $integrations ) {
						$integrations = $this->get_integrations();
					}

					// Check if integrations' collections are already loaded in transient.
					$collections = get_transient( 'automator_integration_collection_items' );

					if ( false === $collections ) {
						$collections = $this->get_collections();
					}

					// Add integrations
					$data['integrations'] = $integrations;
					$data['collections']  = $collections;
				}

				return $data;
			},
			10,
			2
		);
	}

	/**
	 * Returns the integration ID defined in the URL
	 *
	 * @return {String} The integration ID
	 */
	public function integrations_get_id_from_url() {
		return automator_filter_has_var( 'integration' ) ? sanitize_text_field( automator_filter_input( 'integration' ) ) : '';
	}

	/**
	 * Defines what's the template that must be loaded for the integrations
	 * page, depending on the value of the GET parameter "integration"
	 *
	 * @return null|void
	 */
	public function integrations_template() {
		// Get the current integration
		$integration_id = $this->integrations_get_id_from_url();

		// Check if it's the archive page by checking if an integration ID
		// is defined in the URL
		$is_archive = empty( $integration_id );

		/*
		if ( $is_archive ) {
			$this->integrations_template_load_archive();
		} else {
			$this->integrations_template_load_single();
		}
		*/

		$this->integrations_template_load_archive();
	}

	/**
	 * Loads the archive view of the integrations page
	 *
	 * @return null|void
	 */
	public function integrations_template_load_archive() {

		// Go to all recipes URL
		$all_recipes_url = add_query_arg(
			array(
				'post_type' => 'uo-recipe',
			),
			admin_url( 'edit.php' )
		);

		// Check if the user has Automator Pro installed
		$user_has_automator_pro = defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' );

		// Check if integrations are already loaded in transient.
		$integrations = get_transient( 'automator_all_integration_items' );

		$is_refresh = automator_filter_input( 'refresh' );

		if ( false === $integrations || isset( $is_refresh ) ) {
			$integrations = $this->get_integrations();
		}

		// Check if integrations' collections are already loaded in transient.
		$collections = get_transient( 'automator_integration_collection_items' );

		if ( false === $collections ) {
			$collections = $this->get_collections();
		}

		// Load archive view
		include Utilities::automator_get_view( 'admin-integrations/archive.php' );
	}

	/**
	 * @return array
	 */
	public function get_collections() {

		// The endpoint url. Change this to live site later.
		$endpoint_url = AUTOMATOR_INTEGRATIONS_JSON_LIST; // Append time to prevent caching.

		// Get integrations from Automator plugin.
		$response = wp_remote_get( esc_url_raw( $endpoint_url ) );

		$collections = array();

		if ( is_wp_error( $response ) ) {
			return $collections;
		}

		$api_response = json_decode( $response['body'], true );

		foreach ( $api_response as $integration ) {

			$collection = $integration['collection'];

			if ( empty( $collection ) ) {
				continue;
			}

			$collection = array_shift( $collection );

			$collection_slug = $collection['slug'];

			if ( isset( $collections[ $collection_slug ] ) && is_object( $collections[ $collection_slug ] ) ) {
				if ( ! isset( $collections[ $collection_slug ]->integrations ) || ! is_array( $collections[ $collection_slug ]->integrations ) ) {
					$collections[ $collection_slug ]->integrations = array();
				}
				$collections[ $collection_slug ]->integrations[] = $integration['post_id'];
				continue;
			}

			$collections[ $collection_slug ] = (object) array(
				'id'           => $collection['slug'],
				'name'         => $collection['name'],
				'description'  => $collection['description'],
				'integrations' => array( $integration['post_id'] ),
			);
		}

		// Add "Installed integrations"
		$collections['installed-integrations'] = (object) array(
			'id'           => 'installed-integrations',
			'name'         => esc_html__( 'Installed integrations', 'uncanny-automator' ),
			'description'  => esc_html__( 'Ready-to-use integrations', 'uncanny-automator' ),
			'integrations' => $this->get_installed_integrations_ids(),
		);

		// Save in transients. Refreshes every day.
		set_transient( 'automator_integration_collection_items', $collections, DAY_IN_SECONDS );

		return $collections;
	}

	/**
	 * Returns the list of integrations.
	 *
	 * @return array $integrations The list of integrations.
	 */
	public function get_integrations() {

		// The endpoint url to S3
		$endpoint_url = AUTOMATOR_INTEGRATIONS_JSON_LIST; // Append time to prevent caching.

		$response = wp_remote_get( $endpoint_url );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$integrations = array();

		$api_response = json_decode( $response['body'], true );

		foreach ( $api_response as $integration ) {

			// Requires on "All integrations" page to sort by categories
			$post_id = isset( $integration['post_id'] ) ? $integration['post_id'] : 0;

			$integration_id   = $integration['integration_id'];
			$integration_name = $integration['integration_name'];
			$permalink        = add_query_arg(
				array(
					'post_type'   => 'uo-recipe',
					'page'        => 'uncanny-automator-integrations',
					'integration' => $integration_id,
				),
				admin_url( 'edit.php' )
			);

			// Assume that the integration name is
			// different but the code is same
			if ( isset( $integrations[ $integration_id ] ) ) {
				$integration_id = Utilities::decouple_integration_id_name( $integration_id, $integration_name );
			}

			$integrations[ $post_id ] = (object) array(
				'id'                 => $post_id,
				'integration_id'     => $integration['integration_id'],
				'name'               => $integration_name,
				'permalink'          => $permalink,
				'external_permalink' => $integration['integration_link'],
				'is_pro'             => $integration['is_pro_integration'],
				'is_plus'            => $integration['is_plus_integration'],
				'is_elite'           => isset( $integration['is_elite_integration'] ) ? $integration['is_elite_integration'] : false,
				'is_built_in'        => $integration['is_app_integration'],
				'is_addon'           => $integration['is_automator_addon'],
				'is_installed'       => $this->is_installed( $integration_id ),
				'short_description'  => $integration['short_description'],
				'icon_url'           => $integration['integration_icon'],
			);

		}

		set_transient( 'automator_all_integration_items', $integrations, DAY_IN_SECONDS );

		return $integrations;
	}

	/**
	 * Returns the IDs of the installed integrations
	 *
	 * @return array The IDs
	 */
	public function get_installed_integrations_ids() {
		// Check if integrations are already loaded in transient.
		$integrations = get_transient( 'automator_all_integration_items' );

		if ( false === $integrations ) {
			$integrations = $this->get_integrations();
		}

		// Filter them to get only the installed ones
		$installed_integrations = array_filter(
			$integrations,
			function ( $integration ) {
				return $integration->is_installed;
			}
		);

		// Create collection data
		return array_keys( $installed_integrations );
	}

	/**
	 * Returns the "All integrations" collection
	 *
	 * @return object The collection
	 */
	public function get_all_integrations_collection() {
		// Check if integrations are already loaded in transient.
		$integrations = get_transient( 'automator_all_integration_items' );

		if ( false === $integrations ) {
			$integrations = $this->get_integrations();
		}

		// Create collection data
		return (object) array(
			'id'                  => 'all-integrations',
			'name'                => esc_html__( 'All integrations', 'uncanny-automator' ),
			'description'         => esc_html__( 'Put your WordPress site on autopilot', 'uncanny-automator' ),
			'integrations'        => array_keys( $integrations ),
			'add_no_results_item' => true,
		);
	}

	/**
	 * Check if the plugin integration is installed or not.
	 *
	 * @param string $integration_id The ID of the integration.
	 *
	 * @return boolean True if installed. Otherwise, false.
	 */
	public function is_installed( $integration_id = '' ) {

		if ( empty( $integration_id ) ) {
			return false;
		}

		$existing_integrations = array_keys( Automator()->get_integrations() );

		return in_array( $integration_id, $existing_integrations, true );
	}

	/**
	 * Loads the single view of the integrations page
	 *
	 * @return null|void
	 */
	public function integrations_template_load_single() {
		// Get the current integration
		$integration_id = $this->integrations_get_id_from_url();

		// Load single view
		include Utilities::automator_get_view( 'admin-integrations/single.php' );
	}

	/**
	 * @return array
	 */
	public function get_integrations_for_components() {
		// List all integrations, active and not active
		$integrations = Automator()->get_all_integrations();
		if ( empty( $integrations ) ) {
			return array();
		}
		$data = array();
		foreach ( $integrations as $integration_id => $integration ) {
			if ( array_key_exists( $integration_id, $data ) ) {
				continue;
			}
			$data[ $integration_id ] = array(
				'id'   => $integration_id,
				'icon' => isset( $integration['icon_svg'] ) ? $integration['icon_svg'] : '',
				'name' => isset( $integration['name'] ) ? $integration['name'] : '',
			);
		}

		// fallback for legacy methods,
		// where a trait method is not used
		// to define integration. Mostly API integrations
		$integrations = Automator()->get_integrations();
		if ( empty( $integrations ) ) {
			return $data;
		}
		foreach ( $integrations as $integration_id => $integration ) {
			if ( array_key_exists( $integration_id, $data ) ) {
				continue;
			}
			$data[ $integration_id ] = array(
				'id'   => $integration_id,
				'icon' => isset( $integration['icon_svg'] ) ? $integration['icon_svg'] : '',
				'name' => isset( $integration['name'] ) ? $integration['name'] : '',
			);
		}

		return $data;
	}

	/**
	 * @param string $endpoint
	 * @param string $license_key
	 * @param int $item_id
	 * @param string $store_url
	 *
	 * @return false|mixed|void|null
	 */
	public static function licensing_call( $endpoint = 'check-license', $license_key = '', $item_id = AUTOMATOR_FREE_ITEM_ID, $store_url = AUTOMATOR_LICENSING_URL, $should_redirect = true ) {
		$valid_endpoints = array(
			'check-license',
			'activate-license',
			'deactivate-license',
			'get_version',
		);

		if ( ! in_array( $endpoint, $valid_endpoints, true ) ) {
			wp_die( 'Invalid endpoint selected.' );
		}

		if ( empty( $license_key ) ) {
			wp_die( 'License Key not provided.' );
		}

		$data = array(
			'license' => $license_key,
			'item_id' => $item_id,
			'url'     => home_url(),
		);

		// Convert array to JSON and then encode it with Base64
		$encoded_data = base64_encode( wp_json_encode( $data ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$item_name    = AUTOMATOR_FREE_ITEM_NAME;
		$item_version = AUTOMATOR_PLUGIN_VERSION;
		if ( defined( 'AUTOMATOR_PRO_ITEM_ID' ) && (int) AUTOMATOR_PRO_ITEM_ID === (int) $item_id ) {
			$item_name    = AUTOMATOR_PRO_ITEM_NAME;
			$item_version = AUTOMATOR_PRO_PLUGIN_VERSION;
		}

		// Call the custom API.
		$url = $store_url . $endpoint . '?plugin=' . rawurlencode( $item_name ) . '&version=' . $item_version;

		$response = wp_remote_post(
			$url,
			array(
				'timeout'   => apply_filters( 'automator_licensing_timeout', 20 ),
				'body'      => '',
				'headers'   => array(
					'X-UO-Licensing'   => $encoded_data,
					'X-UO-Destination' => 'ap',
				),
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			$error = wp_remote_retrieve_body( $response );

			if ( is_wp_error( $response ) ) {
				$error = $response->get_error_message();
			}

			$query_params = array(
				'sl_activation' => 'false',
				'error_message' => urlencode( $error ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
			);

			if ( $should_redirect ) {

				$redirect = add_query_arg( $query_params, self::get_license_page_url() );

				wp_safe_redirect( $redirect );

				exit();
			}

			return new WP_Error( 400, 'Invalid license', $query_params );

		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * @return void
	 */
	public function activate_license() {
		$this->validate_credentials( automator_filter_input( 'state' ) );

		automator_update_option( 'uap_automator_free_license_key', automator_filter_input( 'uap_automator_free_license_key' ) );

		$license = trim( automator_filter_input( 'uap_automator_free_license_key' ) );

		$license_data = self::licensing_call( 'activate-license', $license );

		if ( ! $license_data ) {
			automator_delete_option( 'uap_automator_free_license_key' );
		}

		// The $license_data->license_check will be either "valid", "invalid", "expired", "disabled", "inactive", or "site_inactive".
		automator_update_option( 'uap_automator_free_license_status', $license_data->license );
		// Update the license data as well.
		automator_update_option( 'uap_automator_free_license_data', (array) $license_data );

		if ( ! empty( automator_filter_input( 'ua_connecting_integration_id' ) ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'action' => 'edit',
					),
					remove_query_arg(
						array(
							'uap_automator_free_license_key',
							'state',
						)
					)
				)
			);
			die;
		}

		// Redirect to step 2.
		wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-setup-wizard&step=2' ) );

		die;
	}

	/**
	 * @return void
	 */
	public function deactivate_license() {

		$this->validate_credentials( automator_filter_input( 'state' ) );

		$license = automator_get_option( 'uap_automator_free_license_key' );

		if ( $license ) {

			if ( self::licensing_call( 'deactivate-license', $license ) ) {

				automator_delete_option( 'uap_automator_free_license_status' );
				automator_delete_option( 'uap_automator_free_license_key' );
				automator_delete_option( 'uap_automator_free_license_data' );
				delete_transient( 'automator_api_credit_data' );
				delete_transient( 'automator_api_credits' );
				delete_transient( 'automator_api_license' );
			}

			wp_safe_redirect( remove_query_arg( array( 'action' ) ) );
			die;
		}
	}

	/**
	 * @return string
	 */
	public static function get_license_page_url() {
		return add_query_arg(
			array(
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-config',
				'tab'       => 'general',
				'general'   => 'license',
			),
			admin_url( 'edit.php' )
		);
	}
}

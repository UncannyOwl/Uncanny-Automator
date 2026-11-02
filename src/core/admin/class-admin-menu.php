<?php

namespace Uncanny_Automator;

use WP_Error;

/**
 * Class Admin_Menu
 *
 * @package Uncanny_Automator
 */
class Admin_Menu {

	/**
	 * The Connect Apps entry's slug: the App integrations tab of Settings, relative to wp-admin.
	 *
	 * @var string
	 */
	const CONNECT_APPS_SLUG = 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations';

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

		// Runs on admin_head, not admin_menu: WordPress reads a page's title from the submenu list before admin_head and renders the menu after it, so removing an entry earlier leaves its page with a null title (blank browser tab, strip_tags() deprecation notice).
		add_action( 'admin_head', array( $this, 'hide_submenu_pages' ) );

		add_filter( 'submenu_file', array( $this, 'highlight_dashboard_on_status_page' ) );

		// After Admin_Ai_Menu (20), so an existing site's entry can sit right below Connect your AI; the 8.0 order is Admin_Menu_Order's.
		add_action( 'admin_menu', array( $this, 'register_submenu_connect_apps' ), 21 );
		add_action( 'admin_menu', array( $this, 'place_connect_apps_below_ai' ), 1001 );
		add_filter( 'submenu_file', array( $this, 'highlight_connect_apps' ) );

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

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook name, kept for compatibility.
		$tabs       = apply_filters( 'uap_settings_tabs', $tabs );
		self::$tabs = apply_filters( 'automator_settings_tabs', $tabs );
		if ( self::$tabs ) {
			$tabs = json_decode( wp_json_encode( self::$tabs ), false );
			foreach ( $tabs as $tab => $tab_settings ) {
				if ( $tab_settings->fields ) {
					foreach ( $tab_settings->fields as $field_id => $field_settings ) {
						$args = isset( $field_settings->field_args ) ? $field_settings->field_args : array();
						// Third-party settings_field values must start with 'uncanny_automator_' to be recognized
						// by the demand-driven loader when options.php processes the form submission. Otherwise,
						// WordPress will reject it with "options page is not in the allowed options list".
						// See: Recipe_Manifest::is_automator_options_php_submit().
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

		if ( ! current_user_can( automator_get_capability() ) ) {
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
			automator_get_capability(),
			'uncanny-automator-dashboard',
			array(
				$this,
				'dashboard_menu_page_output',
			),
			0
		);

		// Recipe details (modal).
		add_submenu_page(
			$parent_slug_fake,
			esc_attr__( 'Recipe activity details', 'uncanny-automator' ),
			esc_attr__( 'Recipe activity details', 'uncanny-automator' ),
			automator_get_capability(),
			'uncanny-automator-recipe-activity-details',
			$function
		);
	}

	/**
	 * Adds the Connect Apps entry, which opens the App integrations tab of Settings.
	 *
	 * @return void
	 */
	public function register_submenu_connect_apps() {
		add_submenu_page(
			'edit.php?post_type=uo-recipe',
			esc_attr__( 'Connect Apps', 'uncanny-automator' ),
			esc_html__( 'Connect Apps', 'uncanny-automator' ),
			automator_get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- automator_get_capability() returns the capability the Automator menu requires.
			self::CONNECT_APPS_SLUG
		);
	}

	/**
	 * Moves Connect Apps directly below Connect your AI on a site that upgraded to 8.0.
	 *
	 * A site that installed 8.0 gets its order from Admin_Menu_Order (below AI activity). Without a
	 * Connect your AI entry, the entry follows Logs, the entry Connect your AI itself follows.
	 *
	 * @return void
	 */
	public function place_connect_apps_below_ai() {

		$parent = 'edit.php?post_type=uo-recipe';

		if ( Admin_Ai_Menu::site_installed_at_8_or_later() || empty( $GLOBALS['submenu'][ $parent ] ) ) {
			return;
		}

		$items = array_values( $GLOBALS['submenu'][ $parent ] );
		$slugs = array_column( $items, 2 );
		$index = array_search( self::CONNECT_APPS_SLUG, $slugs, true );

		if ( false === $index ) {
			return;
		}

		$entry = $items[ $index ];
		unset( $items[ $index ] );
		$items  = array_values( $items );
		$hosted = \Uncanny_Automator\App\Application\Mcp\Hosted_App::base_url();
		$anchor = false;

		foreach ( $items as $position => $item ) {
			if ( in_array( $item[2], array( Admin_Ai_Menu::SETTINGS_SLUG, Admin_Ai_Menu::WIZARD_SLUG ), true ) || 0 === strpos( $item[2], $hosted ) ) {
				$anchor = $position;
				break;
			}
		}

		if ( false === $anchor ) {
			$anchor = array_search( Admin_Ai_Menu::ANCHOR_EXISTING_INSTALL, array_column( $items, 2 ), true );
		}

		array_splice( $items, false === $anchor ? count( $items ) : $anchor + 1, 0, array( $entry ) );

		$GLOBALS['submenu'][ $parent ] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress offers no function to place a submenu entry, as Admin_Ai_Menu::reposition() and Admin_Menu_Order::arrange() do.
	}

	/**
	 * Highlights Connect Apps, not Settings, while the App integrations tab is open.
	 *
	 * @param string|null $submenu_file The submenu file WordPress resolved for the current page.
	 *
	 * @return string|null
	 */
	public function highlight_connect_apps( $submenu_file ) {

		global $plugin_page;

		if ( 'uncanny-automator-config' !== $plugin_page || 'premium-integrations' !== (string) automator_filter_input( 'tab' ) ) {
			return $submenu_file;
		}

		return self::CONNECT_APPS_SLUG;
	}

	/**
	 * Removes the Recipe templates and Status entries from the Automator menu.
	 *
	 * The Dashboard links to Status. Recipe templates left the menu for 8.0
	 * (Ken, November 2, 2026) and may return in a later release. Both pages
	 * stay registered, so their URLs, screen IDs and load hooks keep working.
	 *
	 * @return void
	 */
	public function hide_submenu_pages() {
		remove_submenu_page( 'edit.php?post_type=uo-recipe', 'uncanny-automator-template-library' );
		remove_submenu_page( 'edit.php?post_type=uo-recipe', 'uncanny-automator-admin-tools' );
	}

	/**
	 * Highlights the Dashboard entry while the user is on the Status page, which the Dashboard links to.
	 *
	 * @param string|null $submenu_file The submenu file WordPress resolved for the current page.
	 *
	 * @return string|null
	 */
	public function highlight_dashboard_on_status_page( $submenu_file ) {

		global $plugin_page;

		if ( 'uncanny-automator-admin-tools' !== $plugin_page ) {
			return $submenu_file;
		}

		return 'uncanny-automator-dashboard';
	}

	/**
	 * Create legacy options menu
	 */
	public function register_legacy_options_menu_page() {

		if ( has_filter( 'uap_settings_tabs' ) ) {
			/* translators: 1. Trademarked term */
			$page_title               = sprintf( esc_attr__( '%1$s settings', 'uncanny-automator' ), 'Uncanny Automator' );
			$capability               = automator_get_capability();
			$menu_title               = esc_attr__( 'Legacy settings', 'uncanny-automator' );
			$menu_slug                = 'uncanny-automator-settings';
			$this->settings_page_slug = $menu_slug;
			$function                 = array(
				$this,
				'options_menu_settings_page_output',
			);

			add_submenu_page( 'edit.php?post_type=uo-recipe', $page_title, $menu_title, $capability, $menu_slug, $function );
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
		?>

		<div class="wrap uap">
			<div id="uap-dashboard">

				<!-- Some plugins need an h1 to add their notices -->
				<h1 style="margin: 0; padding: 0;"></h1>

				<?php do_action( 'automator_dashboard_header_after' ); ?>

				<uap-d-site-auth
					site-api-url="<?php echo esc_attr( rest_url() . AUTOMATOR_REST_API_END_POINT ); ?>"
					site-api-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
				>
					<?php if ( Admin_Ai_Settings::current_user_can_manage_ai() ) { ?>
						<uap-f-page-dashboard can-manage-ai></uap-f-page-dashboard>
					<?php } else { ?>
						<uap-f-page-dashboard></uap-f-page-dashboard>
					<?php } ?>
				</uap-d-site-auth>

			</div>
		</div>

		<?php
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

		if ( AUTOMATOR_POST_TYPE_RECIPE !== automator_filter_input( 'post_type' ) ) {
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
					'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
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
					$where_to_get_my_license = 'https://automatorplugin.com/knowledge-base/where-can-i-find-my-license-key/?utm_source=uncanny-automator-pro&utm_medium=in-plugin&utm_content=license-page-where-to-get-my-license';
					$buy_new_license         = 'https://automatorplugin.com/pricing/?utm_source=uncanny-automator-pro&utm_medium=in-plugin&utm_content=license-page&utm_campaign=buy-new-license';
					$knowledge_base          = 'https://automatorplugin.com/knowledge-base/?utm_source=uncanny-automator-pro&utm_medium=in-plugin&utm_content=license-page-knowledge-base';

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
		if ( ! current_user_can( automator_get_admin_capability() ) ) {

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

			// Lifetime/perpetual licenses have no expiry. The API strips the null
			// `expires` from the response (sparse-shape parity, platform PR #94),
			// so the property is absent on the wire — only the EDD store path
			// emits the 'lifetime' sentinel this branch was written for.
			// Normalise a missing/null value to 'lifetime' so the checks below
			// don't warn on an undefined property, blank the stored expiry, or
			// pass null to DateTime (which resolves to NOW and schedules a bogus
			// "license expiring" notification for lifetime customers). Mirrors
			// Pro's Licensing::check_license() fix.
			$expires = $license_data->expires ?? 'lifetime';

			if ( 'lifetime' !== $expires ) {
				automator_update_option( 'uap_automator_pro_license_expiry', $expires );
			} else {
				automator_update_option(
					'uap_automator_pro_license_expiry',
					wp_date( 'Y-m-d H:i:s', mktime( 12, 59, 59, 12, 31, 2099 ) )
				);
			}

			if ( 'lifetime' !== $expires ) {
				$expire_notification = new \DateTime( $expires, wp_timezone() );
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
			// License is no longer valid — drop the cached license payload so the
			// Uncanny Agent gate stops reading a stale "valid" status.
			$this->flush_license_caches();
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
				AUTOMATOR_POST_TYPE_RECIPE !== $this->get_current_screen_post_type() &&
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
					'UncannyAutomator' => $this->get_js_backend_inline_data( $hook ),
				),
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
			'utm' => array(
				'source'   => defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ? 'plugin-pro' : 'plugin-lite',
				'referrer' => automator_get_option( 'uncannyautomator_source', null ),
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
	 * Clear evidence cached for the current license/account identity.
	 *
	 * The Transient bounded context owns the exact key set. In particular, it
	 * clears the failed-license marker as well as the license and legacy credit
	 * snapshots, while preserving unrelated Automator transients.
	 *
	 * @return void
	 */
	private function flush_license_caches() {
		Automator()->cache->delete_license_transients();
	}

	/**
	 * Store one completed Free-license activation and invalidate its old evidence.
	 *
	 * Automator_Options intentionally emits no update event for a same-value write.
	 * Reconnecting the same key is nevertheless an explicit support recovery action,
	 * so it must clear the previous license and allocation snapshots after the remote
	 * activation succeeds. Keeping this after the response preserves known-good
	 * evidence when the remote activation itself fails.
	 *
	 * @param object $license_data Successful activation response.
	 *
	 * @return void
	 */
	private function store_free_license_activation( $license_data ) {
		automator_update_option( 'uap_automator_free_license_status', $license_data->license );
		automator_update_option( 'uap_automator_free_license_data', (array) $license_data );

		$this->flush_license_caches();
	}

	/**
	 * @return void
	 */
	public function activate_license() {
		$this->validate_credentials( automator_filter_input( 'state' ) );

		automator_update_option( 'uap_automator_free_license_key', automator_filter_input( 'uap_automator_free_license_key' ) );

		$license = trim( automator_filter_input( 'uap_automator_free_license_key' ) );

		$license_data = self::licensing_call( 'activate-license', $license );

		wp_safe_redirect(
			$this->finish_free_license_activation(
				$license_data,
				automator_filter_input( 'page' ),
				! empty( automator_filter_input( 'ua_connecting_integration_id' ) ),
				automator_filter_input( \Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Account_Link::RETURN_PARAM )
			)
		);

		die;
	}

	/**
	 * Store the store's activation reply and say where the owner goes next:
	 * back to the recipe when an integration was connecting, to the Dashboard
	 * when linking started there, to the wizard screen it started from, else
	 * to Setup wizard step 2. A reply with
	 * no license data stores nothing and goes to the license page, which
	 * explains the failure.
	 *
	 * @param object|null $license_data           The store's activation reply.
	 * @param string      $page                   The admin page the store returned to.
	 * @param bool        $connecting_integration Whether an integration was connecting.
	 * @param string      $account_link           Optional saved AI approval return.
	 *
	 * @return string The URL to redirect to, without the key and state.
	 */
	private function finish_free_license_activation( $license_data, $page, $connecting_integration, $account_link = '' ) {
		if ( ! is_object( $license_data ) ) {
			automator_delete_option( 'uap_automator_free_license_key' );

			return add_query_arg(
				array(
					'sl_activation' => 'false',
					'error_message' => 'license_activation_failed',
				),
				self::get_license_page_url()
			);
		}

		$this->store_free_license_activation( $license_data );

		if ( 'valid' === ( $license_data->license ?? '' ) && '' !== $account_link ) {
			$resume_url = ( new \Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Account_Link() )->resume_url( $account_link );
			if ( null !== $resume_url ) {
				return $resume_url;
			}
		}

		if ( $connecting_integration ) {
			return add_query_arg(
				array(
					'action' => 'edit',
				),
				remove_query_arg(
					array(
						'uap_automator_free_license_key',
						'state',
					)
				)
			);
		}

		if ( 'uncanny-automator-dashboard' === $page ) {
			return admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-dashboard' );
		}

		// The wizard's return address names its screen (Choose your AI, or Skipped).
		// It is rebuilt from the request path, not home_url( REQUEST_URI ), which
		// doubles the folder on a site installed in a subfolder.
		if ( 'uncanny-automator-setup-wizard' === $page ) {
			return remove_query_arg( array( 'action', 'uap_automator_free_license_key', 'state', 'method' ) );
		}

		return admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-setup-wizard&step=2' );
	}

	/**
	 * @return void
	 */
	public function deactivate_license() {

		$this->validate_credentials( automator_filter_input( 'state' ) );
		// This explicit Free-account action now ends access for connected AI apps
		// on this site. A filtered recipe-management capability is not sufficient.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only a site administrator can disconnect this account.', 'uncanny-automator' ) );
		}

		$license = automator_get_option( 'uap_automator_free_license_key' );

		if ( $license ) {

			$this->disconnect_free_license( $license );

			wp_safe_redirect( remove_query_arg( array( 'action' ) ) );
			die;
		}
	}

	/**
	 * End this site's AI access, unlink the Free account at the store, and,
	 * once the store agrees, forget the account and stop usage reports.
	 *
	 * Connecting opts the site in to usage reports (auto_optin_users()), so
	 * disconnecting opts it out. Nothing records whether the owner had opted in
	 * before connecting, so every disconnect turns reports off; the owner can
	 * turn them on again in Settings > General > Improve Automator.
	 *
	 * @since 8.0.0
	 *
	 * @param string $license The Free license key.
	 *
	 * @return void
	 */
	private function disconnect_free_license( $license ) {

		// End access locally first. If the store is unavailable, the Free account
		// remains linked for retry, but already-ended AI sessions stay ended.
		// Pro deactivation and automatic license checks do not enter this path.
		if ( ! ( new \Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Site_Credential_Revoker() )->revoke() ) {
			wp_die( esc_html__( 'AI connections could not all be disconnected. Please try disconnecting your account again.', 'uncanny-automator' ) );
		}

		if ( ! self::licensing_call( 'deactivate-license', $license ) ) {
			return;
		}

		automator_delete_option( 'uap_automator_free_license_status' );
		automator_delete_option( 'uap_automator_free_license_key' );
		automator_delete_option( 'uap_automator_free_license_data' );

		// Off is stored as no option, as the switch and the banner store it.
		automator_delete_option( 'automator_reporting' );

		// Option deletion has no lifecycle hook. After the remote disconnect
		// succeeds, invalidate once all local identity removals were attempted.
		$this->flush_license_caches();
	}

	/**
	 * @return string
	 */
	public static function get_license_page_url() {
		return add_query_arg(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => 'uncanny-automator-config',
				'tab'       => 'general',
				'general'   => 'license',
			),
			admin_url( 'edit.php' )
		);
	}
}

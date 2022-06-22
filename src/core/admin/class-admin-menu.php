<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Menu
 *
 * @package Uncanny_Automator
 */
class Admin_Menu {

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
	public static $automator_connect_url = AUTOMATOR_FREE_STORE_URL;

	/**
	 * Setting Connect URLs
	 *
	 * @var
	 */
	public static $automator_connect_page = AUTOMATOR_FREE_STORE_CONNECT_URL;

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
	 * class constructor
	 */
	public function __construct() {
		// Global assets
		$this->enqueue_global_assets();

		// Add inline JS data
		$this->dashboard_inline_js_data();
		$this->integrations_inline_js_data();

		add_action( 'admin_enqueue_scripts', array( $this, 'reporting_assets' ) );
		add_filter( 'admin_title', array( $this, 'modify_report_titles' ), 40, 2 );

		// Run licence key update
		add_action( 'admin_init', array( $this, 'update_automator_connect' ), 1 );

		// Auto opt-in users if they are connected.

		add_action( 'admin_init', array( $this, 'auto_optin_users' ), 20 );

		// Setup Theme Options Page Menu in Admin
		add_action( 'admin_init', array( $this, 'plugins_loaded' ), 1 );
		add_action( 'admin_menu', array( $this, 'register_options_menu_page' ) );

		add_action( 'admin_menu', array( $this, 'register_legacy_options_menu_page' ), 999 );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_first_settings_tab' ), 1000 );

	}

	/**
	 * Updates the `automator_reporting` to true if the user is connected.
	 *
	 * @return void.
	 */
	function auto_optin_users() {

		$option_key = 'automator_reporting';

		$uap_automator_allow_tracking = get_option( $option_key, false );

		$is_connected = self::is_automator_connected();

		if ( false === $uap_automator_allow_tracking && false !== $is_connected ) {
			// Opt-in the user automatically.
			update_option( $option_key, true );
		}

		return;
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
						if ( empty( $args ) ) {
							register_setting( $tab_settings->settings_field, $field_id );
						} else {
							register_setting( $tab_settings->settings_field, $field_id, $args );
						}
					}
				}
			}
		}
	}

	/**
	 * @param $hook
	 */
	public function reporting_assets( $hook ) {
		$is_a_log = ( strpos( $hook, 'uncanny-automator-recipe-activity' ) !== false )
					|| ( strpos( $hook, 'uncanny-automator-recipe-activity-details' ) !== false )
					|| ( strpos( $hook, 'uncanny-automator-debug-log' ) !== false )
					|| ( strpos( $hook, 'uncanny-automator-recipe-log' ) !== false )
					|| ( strpos( $hook, 'uncanny-automator-trigger-log' ) !== false )
					|| ( strpos( $hook, 'uncanny-automator-action-log' ) !== false );

		// Load tools.css.
		$load_in_pages = array(
			'uo-recipe_page_uncanny-automator-database-tools',
			'uo-recipe_page_uncanny-automator-tools',
			'uo-recipe_page_uncanny-automator-debug-log',
		);

		if ( in_array( $hook, $load_in_pages, true ) ) {
			wp_enqueue_style( 'uap-admin-tools', Utilities::automator_get_asset( 'legacy/css/admin/tools.css' ), array(), Utilities::automator_get_version() );
		}

		if ( $is_a_log ) {
			Utilities::legacy_automator_enqueue_global_assets();
			// Automator assets
			wp_enqueue_script( 'jquery-ui-tabs' );
			wp_enqueue_style( 'uap-logs-free', Utilities::automator_get_asset( 'legacy/css/admin/logs.css' ), array(), Utilities::automator_get_version() );

		}

		if ( 'uo-recipe_page_uncanny-automator-settings' === (string) $hook ) {
			Utilities::legacy_automator_enqueue_global_assets();
			// Automator assets.
			wp_enqueue_style( 'uap-admin-settings', Utilities::automator_get_asset( 'legacy/css/admin/performance.css' ), array(), Utilities::automator_get_version() );
			if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
				wp_enqueue_style( 'uapro-admin-license', \Uncanny_Automator_Pro\Utilities::get_css( 'admin/license.css' ), array(), AUTOMATOR_PRO_PLUGIN_VERSION );
			}
		}
	}

	/**
	 * Create Plugin options menu
	 */
	public function register_options_menu_page() {
		$parent_slug              = 'edit.php?post_type=uo-recipe';
		$this->settings_page_slug = $parent_slug;
		$function                 = array( $this, 'logs_options_menu_page_output' );

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

		// Create "Integrations" submenu page
		add_submenu_page(
			$parent_slug,
			esc_attr__( 'Integrations', 'uncanny-automator' ),
			esc_attr__( 'Integrations', 'uncanny-automator' ),
			'manage_options',
			'uncanny-automator-integrations',
			array(
				$this,
				'integrations_template',
			)
		);

		add_submenu_page( null, esc_attr__( 'Recipe activity details', 'uncanny-automator' ), esc_attr__( 'Recipe activity details', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-recipe-activity-details', $function );
		add_submenu_page( $parent_slug, esc_attr__( 'Recipe log', 'uncanny-automator' ), esc_attr__( 'Recipe log', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-recipe-log', $function );
		add_submenu_page( $parent_slug, esc_attr__( 'Trigger log', 'uncanny-automator' ), esc_attr__( 'Trigger log', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-trigger-log', $function );
		add_submenu_page( $parent_slug, esc_attr__( 'Action log', 'uncanny-automator' ), esc_attr__( 'Action log', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-action-log', $function );
		add_submenu_page(
			null,
			esc_attr__( 'Debug logs', 'uncanny-automator' ),
			esc_attr__( 'Debug logs', 'uncanny-automator' ),
			'manage_options',
			'uncanny-automator-debug-log',
			array(
				$this,
				'debug_logs_options_menu_page_output',
			)
		);

		$function = array( $this, 'tools_menu_page_output' );
		add_submenu_page( $parent_slug, esc_attr__( 'Tools', 'uncanny-automator' ), esc_attr__( 'Tools', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-tools', $function );
		add_submenu_page(
			null,
			esc_attr__( 'Database tools', 'uncanny-automator' ),
			esc_attr__( 'Database tools', 'uncanny-automator' ),
			'manage_options',
			'uncanny-automator-database-tools',
			array(
				$this,
				'database_tools_menu_page_output',
			)
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
			$function                 = array( $this, 'options_menu_settings_page_output' );

			add_submenu_page( 'edit.php?post_type=uo-recipe', $page_title, $menu_title, $capability, $menu_slug, $function );
		}

	}


	/**
	 * Create Page view
	 */
	public function logs_options_menu_page_output() {
		$logs_class = __DIR__ . '/class-logs-list-table.php';
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
		$is_connected = $this->automator_connect;

		$website      = preg_replace( '(^https?://)', '', get_home_url() );
		$redirect_url = admin_url( 'admin.php?page=uncanny-automator-dashboard' );
		$connect_url  = self::$automator_connect_url . self::$automator_connect_page . '?redirect_url=' . urlencode( $redirect_url );

		$license_data = false;
		if ( $is_connected ) {
			$license_data = get_option( 'uap_automator_free_license_data' );
		}

		$is_pro_active = false;

		if ( isset( $is_connected['item_name'] ) ) {
			if ( defined( 'AUTOMATOR_PRO_ITEM_NAME' ) && $is_connected['item_name'] === AUTOMATOR_PRO_ITEM_NAME ) {
				$is_pro_active = true;
			}
		}

		$user             = wp_get_current_user();
		$paid_usage_count = isset( $is_connected['paid_usage_count'] ) ? $is_connected['paid_usage_count'] : 0;
		$usage_limit      = isset( $is_connected['usage_limit'] ) ? $is_connected['usage_limit'] : 1000;
		$dashboard        = (object) array(
			// Check if the user is using Automator Pro
			'is_pro'             => $is_pro_active,
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
				'first_name' => $is_connected ? $is_connected['customer_name'] : 'Guest',
				// Gravatar
				'avatar'     => $is_connected ? $is_connected['user_avatar'] : esc_url( get_avatar_url( $user->ID ) ),
				'url'        => (object) array(
					// automatorplugin.com link to edit profile
					'edit_profile'       => self::$automator_connect_url . 'my-account/',
					// automatorplugin.com link to manage connected sites under this account
					'connected_sites'    => $is_connected ? self::$automator_connect_url . 'checkout/purchase-history/?license_id=' . $is_connected['license_id'] . '&action=manage_licenses&payment_id=' . $is_connected['payment_id'] : '#',
					// URL to disconnect current site from the account
					'disconnect_account' => add_query_arg( array( 'action' => 'discount_automator_connect' ) ),
				),
			),
			'connect_url'        => $connect_url,
			'miscellaneous'      => (object) array(
				'free_credits'              => $is_connected ? ( $usage_limit - $paid_usage_count ) : 1000,
				'site_url_without_protocol' => preg_replace( '(^https?://)', '', get_site_url() ),
			),
		);

		?>

		<div class="wrap uap">
			<?php include UA_ABSPATH . 'src/core/views/admin-dashboard.php'; ?>
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
	 * Create Page view
	 */
	public function debug_logs_options_menu_page_output() {
		include UA_ABSPATH . 'src/core/views/admin-debug-log.php';
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
				$is_connected = self::is_automator_connected();

				$website            = preg_replace( '(^https?://)', '', get_home_url() );
				$redirect_url       = site_url( 'wp-admin/edit.php?post_type=uo-recipe&page=uncanny-automator-settings' );
				$connect_url        = self::$automator_connect_url . self::$automator_connect_page . '?redirect_url=' . urlencode( $redirect_url );
				$disconnect_account = add_query_arg( array( 'action' => 'discount_automator_connect' ) );

				$license_data = false;
				if ( $is_connected ) {
					$license_data = get_option( 'uap_automator_free_license_data' );
				}

				$is_pro_active = false;

				//if ( isset( $is_connected['item_name'] ) ) {
				if ( defined( 'AUTOMATOR_PRO_ITEM_NAME' ) ) {
					$is_pro_active = true;
				}
				//}

				$uap_automator_allow_tracking = get_option( 'automator_reporting', false );

				if ( $is_pro_active ) {
					$license_data = $this->check_pro_license( true );

					$license = get_option( 'uap_automator_pro_license_key' );
					$status  = get_option( 'uap_automator_pro_license_status' ); // $license_data->license will be either "valid", "invalid", "expired", "disabled"

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
				$html  .= '<a class="nav-tab ' . $class . '" href="' . $url . '&tab=' . $tab . '">' . $tab_settings->name . '</a>'; //phpcs:ignore Generic.Formatting.MultipleStatementAlignment.NotSameWarning
			}
			$html .= '</h2>';
			echo $html; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Checks automator connect and get credits
	 *
	 * @return false|array
	 */
	public static function is_automator_connected() {
		$existing_data = get_transient( 'automator_api_credit_data' );
		if ( ! empty( $existing_data ) ) {
			return is_object( $existing_data ) ? (array) $existing_data : $existing_data;
		}
		$licence_key = '';

		if ( defined( 'AUTOMATOR_PRO_FILE' ) && 'valid' === get_option( 'uap_automator_pro_license_status' ) ) {
			$licence_key = get_option( 'uap_automator_pro_license_key' );
		} elseif ( 'valid' === get_option( 'uap_automator_free_license_status' ) ) {
			$licence_key = get_option( 'uap_automator_free_license_key' );
		}

		if ( empty( $licence_key ) ) {
			return false;
		}

		$plugin_version = AUTOMATOR_PLUGIN_VERSION;
		if ( defined( 'AUTOMATOR_PRO_FILE' ) ) {
			$plugin_version = defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ? AUTOMATOR_PRO_PLUGIN_VERSION : \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION;
		}
		// data to send in our API request
		$api_params = array(
			'action'  => 'get_credits',
			'api_ver' => '2.0',
			'plugins' => $plugin_version,
		);

		// Call the custom API.
		$response = wp_remote_post(
			AUTOMATOR_API_URL . 'v2/credits',
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => $api_params,
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$credit_data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! empty( $credit_data->statusCode ) && 200 === $credit_data->statusCode ) { //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			set_transient( 'automator_api_credit_data', (array) $credit_data->data, HOUR_IN_SECONDS );

			return (array) $credit_data->data;
		}

		return false;
	}


	/**
	 *
	 */
	public function update_automator_connect() {
		if ( automator_filter_has_var( 'action' ) && 'update_free_key' === automator_filter_input( 'action' ) && automator_filter_has_var( 'uap_automator_free_license_key' ) && ! empty( automator_filter_input( 'uap_automator_free_license_key' ) ) ) {
			update_option( 'uap_automator_free_license_key', automator_filter_input( 'uap_automator_free_license_key' ) );
			$license = trim( automator_filter_input( 'uap_automator_free_license_key' ) );
			// data to send in our API request
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license,
				'item_name'  => urlencode( AUTOMATOR_FREE_ITEM_NAME ), // the name of our product in uo
				'url'        => home_url(),
			);

			// Call the custom API.
			$response = wp_remote_post(
				AUTOMATOR_FREE_STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params,
				)
			);

			// make sure the response came back okay
			if ( is_wp_error( $response ) ) {
				delete_option( 'uap_automator_free_license_key' );
				$license = false;
			} else {
				// decode the license data
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );
				if ( $license_data ) {
					// $license_data->license_check will be either "valid", "invalid", "expired", "disabled", "inactive", or "site_inactive"
					update_option( 'uap_automator_free_license_status', $license_data->license );
					// License data
					update_option( 'uap_automator_free_license_data', (array) $license_data );
				}
				wp_safe_redirect( remove_query_arg( array( 'action', 'uap_automator_free_license_key' ) ) );
				die;
			}
		} elseif ( automator_filter_has_var( 'action' ) && 'discount_automator_connect' === automator_filter_input( 'action' ) ) {

			$license = get_option( 'uap_automator_free_license_key' );
			if ( $license ) {
				// data to send in our API request
				$api_params = array(
					'edd_action' => 'deactivate_license',
					'license'    => $license,
					'item_name'  => urlencode( AUTOMATOR_FREE_ITEM_NAME ), // the name of our product in uo
					'url'        => home_url(),
				);

				// Call the custom API.
				$response = wp_remote_post(
					AUTOMATOR_FREE_STORE_URL,
					array(
						'timeout'   => 15,
						'sslverify' => false,
						'body'      => $api_params,
					)
				);
			}
			delete_option( 'uap_automator_free_license_status' );
			delete_option( 'uap_automator_free_license_key' );
			delete_option( 'uap_automator_free_license_data' );
			delete_transient( 'automator_api_credit_data' );
			delete_transient( 'automator_api_credits' );
			delete_transient( 'automator_api_license' );

			wp_safe_redirect( remove_query_arg( array( 'action' ) ) );
			die;
		}
	}

	/**
	 * API call to check if License key is valid
	 *
	 * The updater class does this for you. This function can be used to do something custom.
	 *
	 * @return null|object|bool
	 * @since    1.0.0
	 * @throws \Exception
	 */
	public function check_pro_license( $force_check = false ) {
		$last_checked = get_option( 'uap_automator_pro_license_last_checked' );
		if ( ! empty( $last_checked ) && false === $force_check ) {
			$datediff = time() - $last_checked;
			if ( $datediff < DAY_IN_SECONDS ) {
				return null;
			}
		}
		if ( true === $force_check ) {
			delete_option( 'uap_automator_pro_license_last_checked' );
		}
		$license = trim( get_option( 'uap_automator_pro_license_key' ) );
		if ( empty( $license ) ) {
			return new \stdClass();
		}
		$api_params = array(
			'edd_action' => 'check_license',
			'license'    => $license,
			'item_name'  => urlencode( AUTOMATOR_PRO_ITEM_NAME ),
			'url'        => home_url(),
		);

		// Call the custom API.
		$response = wp_remote_post(
			AUTOMATOR_PRO_STORE_URL,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => $api_params,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// this license is still valid
		if ( $license_data->license === 'valid' ) {
			update_option( 'uap_automator_pro_license_status', $license_data->license );
			if ( 'lifetime' !== $license_data->expires ) {
				update_option( 'uap_automator_pro_license_expiry', $license_data->expires );
			} else {
				update_option( 'uap_automator_pro_license_expiry', date( 'Y-m-d H:i:s', mktime( 12, 59, 59, 12, 31, 2099 ) ) );
			}

			if ( 'lifetime' !== $license_data->expires ) {
				$expire_notification = new \DateTime( $license_data->expires, wp_timezone() );
				update_option( 'uap_automator_pro_license_expiry_notice', $expire_notification );
				if ( wp_get_scheduled_event( 'uapro_notify_admin_of_license_expiry' ) ) {
					wp_unschedule_hook( 'uapro_notify_admin_of_license_expiry' );
				}
				// 1 hour after the license is schedule to expire.
				wp_schedule_single_event( $expire_notification->getTimestamp() + 3600, 'uapro_notify_admin_of_license_expiry' );

			}
		} else {
			update_option( 'uap_automator_pro_license_status', 'invalid' );
			update_option( 'uap_automator_pro_license_expiry', '' );
			// this license is no longer valid
		}
		update_option( 'uap_automator_pro_license_last_checked', time() );

		return $license_data;
	}


	/**
	 * Enqueues global assets in the Automator pages
	 */
	private function enqueue_global_assets() {
		// List of page where we have to add the assets
		$this->backend_enqueue_in = array(
			'post.php', // Has filter, check callback
			'uncanny-automator-dashboard',
			'uncanny-automator-integrations',
			'uncanny-automator-config',
			'uncanny-automator-tools',
			'uncanny-automator-action-log',
			//          'uncanny-automator-trigger-log',
			//          'uncanny-automator-recipe-log',
				'edit.php',
		);

		// Enqueue admin scripts
		add_action(
			'admin_enqueue_scripts',
			function ( $hook ) {
				// Add exception for the "post.php" hook
				if ( 'post.php' === $hook || 'edit.php' === $hook ) {
					if ( 'uo-recipe' !== (string) get_post_type() ) {
						return;
					}
				}

				// Check if the current page is one of the target pages
				if ( in_array( str_replace( 'uo-recipe_page_', '', $hook ), $this->backend_enqueue_in, true ) ) {
					// Enqueue main CSS
					wp_enqueue_style(
						'uap-admin',
						Utilities::automator_get_asset( 'backend/dist/bundle.min.css' ),
						array(),
						Utilities::automator_get_version()
					);

					// Register main JS
					wp_register_script(
						'uap-admin',
						Utilities::automator_get_asset( 'backend/dist/bundle.min.js' ),
						array(),
						Utilities::automator_get_version(),
						true
					);

					// Get data for the main script
					wp_localize_script(
						'uap-admin',
						'UncannyAutomatorBackend',
						$this->get_js_backend_inline_data( $hook )
					);

					// Enqueue main JS
					wp_enqueue_script( 'uap-admin' );
				}
			}
		);
	}

	/**
	 * Returns the JS object with dynamic data required in some backend pages
	 *
	 * @param  {String} $hook The ID of the current page
	 *
	 * @return array        The inline data
	 */
	private function get_js_backend_inline_data( $hook ) {
		// Set default data
		$automator_backend_js = array(
			'ajax'      => array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => \wp_create_nonce( 'uncanny_automator' ),
			),
			'rest'      => array(
				'url'   => esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ),
				'nonce' => \wp_create_nonce( 'wp_rest' ),
			),
			'i18n'      => array(
				'error'    => array(
					'request' => array(
						'badRequest'   => array(
							'title' => __( 'Bad request', 'uncanny-automator' ),
						),

						'accessDenied' => array(
							'title' => __( 'Access denied', 'uncanny-automator' ),
						),

						'notFound'     => array(
							'title' => __( 'Not found', 'uncanny-automator' ),
						),

						'timeout'      => array(
							'title' => __( 'Request timeout', 'uncanny-automator' ),
						),

						'serverError'  => array(
							'title' => __( 'Internal error', 'uncanny-automator' ),
						),

						'parserError'  => array(
							'title' => __( 'Parser error', 'uncanny-automator' ),
						),

						'generic'      => array(
							'title' => __( 'Unknown error', 'uncanny-automator' ),
						),
					),
				),
				'proLabel' => array(
					'pro' => __( 'Pro', 'uncanny-automator' ),
				),
				'notSaved' => __( 'Changes you made may not be saved.', 'uncanny-automator' ),
			),
			'debugging' => array(
				'enabled' => (bool) AUTOMATOR_DEBUG_MODE,
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
	 * This uses the filter "automator_assets_backend_js_data". If the page is not
	 * the targeted page, it just returns the data unmodified.
	 */
	private function dashboard_inline_js_data() {
		// Filter inline data
		add_filter(
			'automator_assets_backend_js_data',
			function ( $data, $hook ) {
				// Check if the current page is the "Dashboard" page
				if ( 'uo-recipe_page_uncanny-automator-dashboard' === (string) $hook ) {
					// Get data about the connected site
					$this->automator_connect = self::is_automator_connected();

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

					// Add strings
					$data['i18n']['credits'] = array(
						'recipesUsingCredits' => array(
							'noRecipes' => __( 'No recipes using credits on this site', 'uncanny-automator' ),
							'table'     => array(
								'recipe'             => __( 'Recipe', 'uncanny-automator' ),
								'completionsAllowed' => __( 'Completions allowed', 'uncanny-automator' ),
								'completedRuns'      => __( 'Completed runs', 'uncanny-automator' ),
								/* translators: 1. Number */
								'perUser'            => __( 'Per user: %1$s', 'uncanny-automator' ),
								/* translators: 1. Number */
								'total'              => __( 'Total: %1$s', 'uncanny-automator' ),
								/* translators: Unlimited times */
								'unlimited'          => _x( 'Unlimited', 'Times', 'uncanny-automator' ),
							),
						),
					);
				}

				return $data;
			},
			10,
			2
		);
	}

	/**
	 * Adds required JS data for the Integrations page. Before doing so, checks if
	 * the current page is indeed the Integrations page.
	 * This uses the filter "automator_assets_backend_js_data". If the page is not
	 * the targeted page, it just returns the data unmodified.
	 */
	private function integrations_inline_js_data() {
		// Filter inline data
		add_filter(
			'automator_assets_backend_js_data',
			function ( $data, $hook ) {
				// Check if the current page is the "Integrations" page
				if ( 'uo-recipe_page_uncanny-automator-integrations' === (string) $hook ) {
					// Check if integrations are already loaded in transient.
					$integrations = get_transient( 'uo-automator-integration-items' );

					if ( false === $integrations ) {
						$integrations = $this->get_integrations();
					}

					// Check if integrations' collections are already loaded in transient.
					$collections = get_transient( 'uo-automator-integration-collection-items' );

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
	 * Defines what's the template that must be loaded for the integrations page,
	 * depending on the value of the GET parameter "integration"
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
		$integrations = get_transient( 'uo-automator-integration-items' );

		$is_refresh = automator_filter_input( 'refresh' );

		if ( false === $integrations || isset( $is_refresh ) ) {
			$integrations = $this->get_integrations();
		}

		// Check if integrations' collections are already loaded in transient.
		$collections = get_transient( 'uo-automator-integration-collection-items' );

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
		$endpoint_url = 'https://automatorplugin.com/wp-json/automator-integrations-collections/v1/list/all?time=' . time(); // Append time to prevent caching.

		// Get integrations from Automator plugin.
		$response = wp_remote_get( esc_url_raw( $endpoint_url ) );

		$collections = array();

		if ( ! is_wp_error( $response ) ) {

			$api_response = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $api_response['result'] ) && ! empty( $api_response['result'] ) ) {

				foreach ( $api_response['result'] as $collection ) {

					$collections[ $collection['slug'] ] = (object) array(
						'id'           => $collection['slug'],
						'name'         => $collection['name'],
						'description'  => $collection['description'],
						'integrations' => $collection['integrations'],
					);
				}

				// Add "Installed integrations"
				$collections['installed-integrations'] = (object) array(
					'id'           => 'installed-integrations',
					'name'         => esc_html__( 'Installed integrations', 'uncanny-automator' ),
					'description'  => esc_html__( 'Ready-to-use integrations', 'uncanny-automator' ),
					'integrations' => $this->get_installed_integrations_ids(),
				);

				// Save in transients. Refreshes every hour.
				set_transient( 'uo-automator-integration-collection-items', $collections, HOUR_IN_SECONDS );
			}
		}

		return $collections;

	}

	/**
	 * Returns the list of integrations.
	 *
	 * @return array $integrations The list of integrations.
	 */
	public function get_integrations() {

		// The endpoint url. Change this to live site later.
		$endpoint_url = 'https://automatorplugin.com/wp-json/automator-integrations/v1/list/all?time=' . time(); // Append time to prevent caching.

		// Get integrations from Automator plugin.
		$response = wp_remote_get( esc_url_raw( $endpoint_url ) );

		$integrations = array();

		if ( ! is_wp_error( $response ) ) {

			$api_response = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $api_response['result'] ) && ! empty( $api_response['result'] ) ) {

				foreach ( $api_response['result']['integrations'] as $integration ) {

					// Construct the permalink.
					$permalink = add_query_arg(
						array(
							'post_type'   => 'uo-recipe',
							'page'        => 'uncanny-automator-integrations',
							'integration' => $integration['post_id'],
						),
						admin_url( 'edit.php' )
					);

					$integration_id = $integration['integration_id'];

					$integrations[ $integration['post_id'] ] = (object) array(
						'id'                 => $integration['post_id'],
						'integration_id'     => $integration['integration_id'],
						'name'               => $integration['name'],
						'permalink'          => $permalink,
						'external_permalink' => $integration['external_permalink'],
						'is_pro'             => $integration['is_pro'],
						'is_built_in'        => $integration['is_built_in'],
						'is_installed'       => $this->is_installed( $integration_id ),
						'short_description'  => $integration['short_description'],
						'icon_url'           => $integration['icon_url'],
					);

				}

				// Save in transients. Refreshes every hour.
				set_transient( 'uo-automator-integration-items', $integrations, HOUR_IN_SECONDS );
			}
		}

		return $integrations;

	}

	/**
	 * Returns the IDs of the installed integrations
	 *
	 * @return array The IDs
	 */
	public function get_installed_integrations_ids() {
		// Check if integrations are already loaded in transient.
		$integrations = get_transient( 'uo-automator-integration-items' );

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
		$integrations = get_transient( 'uo-automator-integration-items' );

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
}

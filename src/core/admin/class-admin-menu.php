<?php

namespace Uncanny_Automator;

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
	public static $automator_connect_url = AUTOMATOR_FREE_STORE_URL;

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
	 * class constructor
	 */
	public function __construct() {
		// Global assets
		//$this->enqueue_global_assets();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_global_assets' ), 99, 1 );

		// Add inline JS data
		$this->dashboard_inline_js_data();
		$this->integrations_inline_js_data();

		add_action(
			'admin_enqueue_scripts',
			array(
				$this,
				'reporting_assets',
			)
		);
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
			update_option( $option_key, true );
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
	 * @param $hook
	 */
	public function reporting_assets( $hook ) {

		// Load tools.css.
		$load_in_pages = array(
			'uo-recipe_page_uncanny-automator-database-tools',
			'uo-recipe_page_uncanny-automator-tools',
			'uo-recipe_page_uncanny-automator-debug-log',
		);

		if ( in_array( $hook, $load_in_pages, true ) ) {
			wp_enqueue_style( 'uap-admin-tools', Utilities::automator_get_asset( 'legacy/css/admin/tools.css' ), array(), Utilities::automator_get_version() );
		}

		if ( $this->is_a_log( $hook ) ) {
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

		if ( ! current_user_can( apply_filters( 'automator_admin_menu_capability', 'manage_options' ) ) ) {
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
			__( 'App integrations', 'uncanny-automator' ),
			__( 'App integrations', 'uncanny-automator' ),
			'manage_options',
			'uncanny-automator-app-integrations',
			function () {
				if ( ! automator_filter_has_var( 'page' ) ) {
					return;
				}
				if ( 'uncanny-automator-app-integrations' === automator_filter_input( 'page' ) ) {
					if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
						echo '<script>window.location.replace(\'' . admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations' ) . '\')</script>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						exit;
					}
					wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations' ) );
					exit;
				}
			}
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

		//$website      = preg_replace( '(^https?://)', '', get_home_url() );
		$redirect_url = admin_url( 'admin.php?page=uncanny-automator-dashboard' );
		$connect_url  = self::$automator_connect_url . self::$automator_connect_page . '?redirect_url=' . rawurlencode( $redirect_url );

		//      $license_data = false;
		//      if ( $is_connected ) {
		//          $license_data = automator_get_option( 'uap_automator_free_license_data' );
		//      }

		$is_pro_active = false;

		if ( isset( $is_connected['item_name'] ) ) {
			if ( defined( 'AUTOMATOR_PRO_ITEM_NAME' ) && $is_connected['item_name'] === AUTOMATOR_PRO_ITEM_NAME ) {
				$is_pro_active = true;
			}
		}

		$user             = wp_get_current_user();
		$paid_usage_count = isset( $is_connected['paid_usage_count'] ) ? $is_connected['paid_usage_count'] : 0;
		$usage_limit      = isset( $is_connected['usage_limit'] ) ? $is_connected['usage_limit'] : 250;

		$first_name      = isset( $is_connected['customer_name'] ) ? $is_connected['customer_name'] : __( 'Guest', 'uncanny-automator' );
		$avatar          = isset( $is_connected['user_avatar'] ) ? $is_connected['user_avatar'] : esc_url( get_avatar_url( $user->ID ) );
		$connected_sites = isset( $is_connected['license_id'] ) && isset( $is_connected['payment_id'] ) ? self::$automator_connect_url . 'checkout/purchase-history/?license_id=' . $is_connected['license_id'] . '&action=manage_licenses&payment_id=' . $is_connected['payment_id'] : '#';
		$free_credits    = $is_connected ? ( $usage_limit - $paid_usage_count ) : 250;

		return (object) array(
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
			'connect_url'        => $connect_url,
			'miscellaneous'      => (object) array(
				'free_credits'              => $free_credits,
				'site_url_without_protocol' => preg_replace( '(^https?://)', '', get_site_url() ),
			),
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
				$html  .= '<a class="nav-tab ' . $class . '" href="' . $url . '&tab=' . $tab . '">' . $tab_settings->name . '</a>'; //phpcs:ignore Generic.Formatting.MultipleStatementAlignment.NotSameWarning
			}
			$html .= '</h2>';
			echo $html; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Updates or remove the license key of the connected user depending on the `action` query parameter..
	 *
	 * @return void
	 */
	public function update_automator_connect() {

		if ( 'update_free_key' === automator_filter_input( 'action' ) && ! empty( automator_filter_input( 'uap_automator_free_license_key' ) ) ) {

			$this->validate_credentials( automator_filter_input( 'state' ) );

			update_option( 'uap_automator_free_license_key', automator_filter_input( 'uap_automator_free_license_key' ) );

			$license = trim( automator_filter_input( 'uap_automator_free_license_key' ) );

			// The body parameters of the request.
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license,
				'item_name'  => rawurlencode( AUTOMATOR_FREE_ITEM_NAME ),
				'url'        => home_url(),
			);

			// Sends a request to our API license API.
			$response = wp_remote_post(
				AUTOMATOR_FREE_STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params,
				)
			);

			// Handle HTTP Response from license endpoint.
			if ( is_wp_error( $response ) ) {

				delete_option( 'uap_automator_free_license_key' );

				$license = false;

			} else {

				// Decode the license data.
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );

				if ( $license_data ) {
					// The $license_data->license_check will be either "valid", "invalid", "expired", "disabled", "inactive", or "site_inactive".
					update_option( 'uap_automator_free_license_status', $license_data->license );
					// Update the license data as well.
					update_option( 'uap_automator_free_license_data', (array) $license_data );
				}

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
		} elseif ( 'discount_automator_connect' === automator_filter_input( 'action' ) ) { //@TODO: `discount` should be `disconnect`.

			$this->validate_credentials( automator_filter_input( 'state' ) );

			$license = automator_get_option( 'uap_automator_free_license_key' );

			if ( $license ) {

				// The body parameters of the request.
				$api_params = array(
					'edd_action' => 'deactivate_license',
					'license'    => $license,
					'item_name'  => rawurlencode( AUTOMATOR_FREE_ITEM_NAME ),
					'url'        => home_url(),
				);

				// Sends a request to our API license API.
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
			delete_option( 'uap_automator_pro_license_last_checked' );
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
				'uncanny-automator-setup-wizard',
				'edit.php',
			)
		);

		// Enqueue admin scripts
		//add_action(
		//	'admin_enqueue_scripts',
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
			'https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap',
			array(),
			Utilities::automator_get_version()
		);

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
			'ajax'        => array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => \wp_create_nonce( 'uncanny_automator' ),
			),
			'rest'        => array(
				'url'   => esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ), // Automator URL endpoint
				'base'  => esc_url_raw( rest_url() ), // Actual URL of the /wp-json/
				'nonce' => \wp_create_nonce( 'wp_rest' ),
			),
			'i18n'        => array(
				'error'           => array(
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
				'proLabel'        => array(
					'pro' => __( 'Pro', 'uncanny-automator' ),
				),
				'notSaved'        => __( 'Changes you made may not be saved.', 'uncanny-automator' ),

				'utilities'       => array(
					'confirm'      => array(
						'heading'            => __( 'Are you sure?', 'uncanny-automator' ),
						// UncannyAutomatorBackend.i18n.utilities.confirm.heading
						'confirmButtonLabel' => __( 'Confirm', 'uncanny-automator' ),
						// UncannyAutomatorBackend.i18n.utilities.confirm.confirmButtonLabel
						'cancelButtonLabel'  => __( 'Cancel', 'uncanny-automator' ),
						// UncannyAutomatorBackend.i18n.utilities.confirm.cancelButtonLabel
					),
					// UncannyAutomatorBackend.i18n.utilities.relativeTime
					'relativeTime' => array(
						/* translators: 1. A relative time in the future, like "in 5 seconds" or "in 1 year" */
						'future' => __( 'in %s', 'uncanny-automator' ),
						/* translators: 1. A relative time in the past, like "5 seconds ago" or "1 year ago" */
						'past'   => __( '%s ago', 'uncanny-automator' ),
						's'      => __( 'a second', 'uncanny-automator' ),
						/* translators: 1. Number of seconds */
						'ss'     => __( '%d seconds', 'uncanny-automator' ),
						'm'      => __( 'a minute', 'uncanny-automator' ),
						/* translators: 1. Number of minutes */
						'mm'     => __( '%d minutes', 'uncanny-automator' ),
						'h'      => __( 'an hour', 'uncanny-automator' ),
						/* translators: 1. Number of hours */
						'hh'     => __( '%d hours', 'uncanny-automator' ),
						'd'      => __( 'a day', 'uncanny-automator' ),
						/* translators: 1. Number of days */
						'dd'     => __( '%d days', 'uncanny-automator' ),
						'M'      => __( 'a month', 'uncanny-automator' ),
						/* translators: 1. Number of months */
						'MM'     => __( '%d months', 'uncanny-automator' ),
						'y'      => __( 'a year', 'uncanny-automator' ),
						/* translators: 1. Number of years */
						'yy'     => __( '%d years', 'uncanny-automator' ),
						'now'    => __( 'now', 'uncanny-automator' ),
					),
				),

				'copyToClipboard' => esc_html__( 'Copy to clipboard', 'uncanny-automator' ),
				// UncannyAutomatorBackend.i18n.copyToClipboard
			),
			'debugging'   => array(
				'enabled' => (bool) AUTOMATOR_DEBUG_MODE,
			),
			'components'  => array(
				'icon'     => array(
					'integrations' => $this->get_integrations_for_components(),
				),
				'userCard' => array(
					'i18n' => array(
						'userNotFound' => __( 'User not found', 'uncanny-automator' ),
						'userID'       => __( 'User ID #%1$s', 'uncanny-automator' ),
						'unknownError' => __( 'Error: The user data could not be retrieved because of an unknown problem', 'uncanny-automator' ),
						'idRequired'   => __( "Error: User ID can't be empty", 'uncanny-automator' ),
					),
				),
			),

			'logs'        => array(
				'components' => array(
					'log'                => array(
						'i18n' => array(
							'somethingWentWrong'   => __( 'Something went wrong', 'uncanny-automator' ),
							'unknownError'         => __( 'Unknown error', 'uncanny-automator' ),
							'tryAgain'             => _x( 'Try again', 'Button label', 'uncanny-automator' ),
							/* translators: 1. Name of the attribute */
							'attributeMissing'     => __( 'Error: The required attribute "%1$s" is missing', 'uncanny-automator' ),
							'triggeredBy'          => __( 'User', 'uncanny-automator' ),
							'userRunNumber'        => __( 'User run number', 'uncanny-automator' ),
							'recipeStatus'         => __( 'Status', 'uncanny-automator' ),
							'recipeStartDate'      => __( 'Start date', 'uncanny-automator' ),
							'recipeEndDate'        => __( 'End date', 'uncanny-automator' ),
							'triggersSectionTitle' => __( 'Triggers', 'uncanny-automator' ),
							'actionsSectionTitle'  => __( 'Actions', 'uncanny-automator' ),
							'refreshingLog'        => __( 'Reloading this log', 'uncanny-automator' ),
							'userIDNumber'         => __( 'User ID #%1$s', 'uncanny-automator' ),
							'anyTrigger'           => _x( 'Any', 'Trigger', 'uncanny-automator' ),
							'allTriggers'          => _x( 'All', 'Trigger', 'uncanny-automator' ),
							'actions'              => array(
								'closeLogDetails'    => __( 'Close log details', 'uncanny-automator' ),
								'reload'             => __( 'Reload this log entry', 'uncanny-automator' ),
								'editRecipe'         => __( 'Edit recipe', 'uncanny-automator' ),
								'deleteLogEntry'     => __( 'Delete this log entry', 'uncanny-automator' ),
								'downloadLogEntry'   => __( 'Download this log entry', 'uncanny-automator' ),

								'irreversibleAction' => __( 'This action is irreversible', 'uncanny-automator' ),
								'sureDeleteRun'      => __( 'Are you sure you want to delete this entry?', 'uncanny-automator' ),
								'confirm'            => __( 'Confirm', 'uncanny-automator' ),

								/* translators: 1. The recipe name */
								'downloadFilename'   => __( 'Log %1$s', 'uncanny-automator' ),

								'downloading'        => __( 'Downloading', 'uncanny-automator' ),
							),
						),
					),
					'logDialogButton'    => array(
						'i18n' => array(
							'details'     => __( 'Details', 'uncanny-automator' ),
							'viewDetails' => __( 'View details', 'uncanny-automator' ),
						),
					),
					'logItemItem'        => array(
						'i18n' => array(
							/* translators: 1 and 2 are dates */
							'dateRange'     => __( '%1$s to %2$s', 'uncanny-automator' ),
							/* translators: 1. Is a number */
							'runs'          => __( '%1$s runs', 'uncanny-automator' ),

							'openInSidebar' => __( 'Open in sidebar', 'uncanny-automator' ),
							'closeSidebar'  => __( 'Close sidebar', 'uncanny-automator' ),
						),
					),
					'logItemTrigger'     => array(
						'i18n' => array(
							'sidebarTitle' => __( 'Trigger', 'uncanny-automator' ),
							'summary'      => array(
								'date'      => __( 'Date', 'uncanny-automator' ),
								'startDate' => __( 'Start date', 'uncanny-automator' ),
								'endDate'   => __( 'End date', 'uncanny-automator' ),
								'status'    => __( 'Status', 'uncanny-automator' ),
								'runs'      => __( 'Runs', 'uncanny-automator' ),
							),
							'timesNumber'  => __( '%1$s times', 'uncanny-automator' ),
							/* translators: 1. Number */
							'runNumber'    => __( 'Run %1$s', 'uncanny-automator' ),
							'missingItem'  => __( 'Note: The information about this trigger is unavailable because it was removed from the recipe.', 'uncanny-automator' ),
						),
					),
					'logItemAction'      => array(
						'i18n' => array(
							'sidebarTitle'           => __( 'Action', 'uncanny-automator' ),
							'summary'                => array(
								'date'      => __( 'Date', 'uncanny-automator' ),
								'startDate' => __( 'Start date', 'uncanny-automator' ),
								'endDate'   => __( 'End date', 'uncanny-automator' ),
								'status'    => __( 'Status', 'uncanny-automator' ),
								'runs'      => __( 'Runs', 'uncanny-automator' ),
								'message'   => __( 'Notes', 'uncanny-automator' ),
								'events'    => __( 'Events', 'uncanny-automator' ),
							),
							/* translators: 1. Is a number */
							'tries'                  => __( '%1$s tries', 'uncanny-automator' ),
							/* translators: 1. Number */
							'tryNumber'              => __( 'Try %1$s', 'uncanny-automator' ),
							'resend'                 => __( 'Resend', 'uncanny-automator' ),
							'unknownError'           => __( 'Unknown error', 'uncanny-automator' ),
							'cantResendInImportMode' => __( 'Resending is not possible in import mode', 'uncanny-automator' ),
							'missingItem'            => __( 'Note: The information about this action is unavailable because it was removed from the recipe.', 'uncanny-automator' ),
						),
					),
					'logItemLoop'        => array(
						'i18n' => array(
							'actionInsideTitle' => __( 'Action inside loop', 'uncanny-automator' ),
							'missingItem'       => __( 'Note: The information about this action is unavailable because it was removed from the recipe.', 'uncanny-automator' ),
							'summary'           => array(
								'status'    => __( 'Status', 'uncanny-automator' ),
								'date'      => __( 'Date', 'uncanny-automator' ),
								'startDate' => __( 'Start date', 'uncanny-automator' ),
								'endDate'   => __( 'End date', 'uncanny-automator' ),
								'message'   => __( 'Notes', 'uncanny-automator' ),
							),
						),
					),
					'logItemFilterBlock' => array(
						'i18n' => array(
							'runIf'         => _x( 'Run if', 'Conditions logic', 'uncanny-automator' ),
							/* translators: Any [condition] */
							'any'           => _x( 'Any', 'Conditions logic', 'uncanny-automator' ),
							/* translators: All [conditions] */
							'all'           => _x( 'All', 'Conditions logic', 'uncanny-automator' ),
							'anyFull'       => _x( 'of the following conditions is met', 'Conditions logic', 'uncanny-automator' ),
							'allFull'       => _x( 'of the following conditions are met', 'Conditions logic', 'uncanny-automator' ),
							'openInSidebar' => __( 'Open in sidebar', 'uncanny-automator' ),
							'closeSidebar'  => __( 'Close sidebar', 'uncanny-automator' ),
							'sidebarTitle'  => __( 'Condition', 'uncanny-automator' ),
							'properties'    => __( 'Properties', 'uncanny-automator' ),
							'summary'       => array(
								'status' => __( 'Status', 'uncanny-automator' ),
								'notes'  => __( 'Notes', 'uncanny-automator' ),
							),
						),
					),
					'logSidebar'         => array(
						'i18n' => array(
							'details'      => __( 'Details', 'uncanny-automator' ),
							'closeSidebar' => __( 'Close sidebar', 'uncanny-automator' ),
							'closeDialog'  => __( 'Close log details', 'uncanny-automator' ),
						),
					),
					'logStatus'          => array(
						'i18n' => array(
							'invalidStatus'               => __( 'Error: "%1$s" is not a valid status ID.', 'uncanny-automator' ),
							'completedStatus'             => __( 'Completed', 'uncanny-automator' ),
							'completedDoNothingStatus'    => __( 'Completed, do nothing', 'uncanny-automator' ),
							'completedDidNothingStatus'   => __( 'Completed, did nothing', 'uncanny-automator' ),
							'cancelledStatus'             => __( 'Cancelled', 'uncanny-automator' ),
							'pausedStatus'                => __( 'Paused', 'uncanny-automator' ),
							'completedWithNoticeStatus'   => __( 'Completed with notice', 'uncanny-automator' ),
							'notCompletedStatus'          => __( 'Not completed', 'uncanny-automator' ),
							'skipped'                     => __( 'Skipped', 'uncanny-automator' ),
							'queuedStatus'                => __( 'Queued', 'uncanny-automator' ),
							'completedWithErrorsStatus'   => __( 'Completed with errors', 'uncanny-automator' ),
							'inProgressStatus'            => __( 'In progress', 'uncanny-automator' ),
							'scheduledStatus'             => __( 'Scheduled', 'uncanny-automator' ),
							'delayedStatus'               => __( 'Delayed', 'uncanny-automator' ),
							'completedAwaitingStatus'     => __( 'Completed awaiting', 'uncanny-automator' ),
							'conditionMetStatus'          => __( 'Met', 'uncanny-automator' ),
							'conditionNotMetStatus'       => __( 'Not met', 'uncanny-automator' ),
							'conditionNotEvaluatedStatus' => __( 'Not evaluated', 'uncanny-automator' ),
						),
					),
					'logSidebarProperty' => array(
						'i18n' => array(
							'viewPreview'  => __( 'View preview', 'uncanny-automator' ),
							/* translators: 1. Label of field */
							'previewLabel' => __( '"%1$s" preview', 'uncanny-automator' ),
							'empty'        => __( '(empty)', 'uncanny-automator' ),
							'plainText'    => __( 'Plain text', 'uncanny-automator' ),
							/* translators: 1. Number of lines */
							'expandLines'  => __( 'Expand %1$s lines', 'uncanny-automator' ),
							'collapse'     => __( 'Collapse', 'uncanny-automator' ),
						),
					),
					// UncannyAutomatorBackend.logs.components.logLoop.i18n
					'logLoop'            => array(
						'i18n' => array(
							/* translators: Noun */
							'loop'                      => _x( 'Loop', 'Block name, noun', 'uncanny-automator' ),
							'users'                     => __( 'Users', 'uncanny-automator' ),
							'posts'                     => __( 'Posts', 'uncanny-automator' ),
							/* translators: 1. Number of items processed. 2. Total number of items */
							'progressStatus'            => __( '%1$s/%2$s processed', 'uncanny-automator' ),
							/* translators: 1. Number of items processed */
							'progressStatusCompleted'   => __( '%1$s processed', 'uncanny-automator' ),
							'inProgress'                => __( 'In progress', 'uncanny-automator' ),
							'matchTheFollowingCriteria' => __( 'that match the following criteria', 'uncanny-automator' ),
							'timeElapsed'               => __( 'Elapsed:', 'uncanny-automator' ),
							'nextBatch'                 => __( 'Next batch:', 'uncanny-automator' ),
							'started'                   => __( 'Started:', 'uncanny-automator' ),
							/* translators: 1 and 2 are dates */
							'dateRange'                 => __( '%1$s to %2$s', 'uncanny-automator' ),
							'openInSidebar'             => __( 'Open in sidebar', 'uncanny-automator' ),
							'closeSidebar'              => __( 'Close sidebar', 'uncanny-automator' ),
							'sidebarTitle'              => __( 'Loop filter', 'uncanny-automator' ),
							'properties'                => __( 'Properties', 'uncanny-automator' ),
							'cancel'                    => array(
								'buttonLabel'            => __( 'Cancel', 'uncanny-automator' ),
								'cancelConfirm'          => __( "You won't be able to resume this loop later.", 'uncanny-automator' ),
								'cantCancelInImportMode' => __( 'Cancelling a loop is not possible in import mode', 'uncanny-automator' ),
							),
						),
					),
				),
			),

			'setupWizard' => array(
				'i18n' => array(
					'skipThis'            => __( 'Skip this', 'uncanny-automator' ),
					'areYouSure'          => __( 'Are you sure?', 'uncanny-automator' ),
					'freeAccountFeatures' => __( 'Your free account gives you access to Slack, Google Sheets, Facebook, exclusive discounts, updates and much more.', 'uncanny-automator' ),
					'skipForNow'          => __( 'Skip for now', 'uncanny-automator' ),
					'signUpNow'           => __( 'Sign up now!', 'uncanny-automator' ),
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

					// Add strings
					$data['i18n']['credits'] = array(
						'recipesUsingCredits' => array(
							'noRecipes' => __( 'No recipes using app credits on this site', 'uncanny-automator' ),
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

			if ( isset( $collections[ $collection_slug ] ) ) {
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
				'is_built_in'        => $integration['is_app_integration'],
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
}

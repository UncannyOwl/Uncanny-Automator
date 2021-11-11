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
		// Setup Theme Options Page Menu in Admin
		add_action( 'admin_init', array( $this, 'plugins_loaded' ), 1 );
		add_action( 'admin_menu', array( $this, 'register_options_menu_page' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'reporting_assets' ) );
		add_filter( 'admin_title', array( $this, 'modify_report_titles' ), 40, 2 );

		// Run licence key update
		add_action( 'admin_init', array( $this, 'update_automator_connect' ), 1 );
	}

	/**
	 *
	 */
	public function plugins_loaded() {
		$tabs = array(
			'settings' => array(
				'name'        => esc_attr__( 'Settings', 'uncanny_automator' ),
				'title'       => esc_attr__( 'Auto-prune activity logs', 'uncanny-automator' ),
				'description' => esc_attr__( 'Enter a number of days below to have trigger and action log entries older than the specified number of days automatically deleted from your site daily. Trigger and action log entries will only be deleted for recipes with "Completed" status.', 'uncanny-automator' ),
				'is_pro'      => true,
				'fields'      => array( /* see implementation in pro*/ ),
			),
		);

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
			wp_enqueue_style( 'uap-admin-tools', Utilities::automator_get_css( 'admin/tools.css' ), array(), Utilities::automator_get_version() );
		}

		if ( $is_a_log ) {
			Utilities::automator_enqueue_global_assets();
			// Automator assets
			wp_enqueue_script( 'jquery-ui-tabs' );
			wp_enqueue_style( 'uap-logs-free', Utilities::automator_get_css( 'admin/logs.css' ), array(), Utilities::automator_get_version() );

		}

		if ( 'uo-recipe_page_uncanny-automator-settings' === (string) $hook ) {
			Utilities::automator_enqueue_global_assets();
			// Automator assets.
			wp_enqueue_style( 'uap-admin-settings', Utilities::automator_get_css( 'admin/performance.css' ), array(), Utilities::automator_get_version() );
			if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
				wp_enqueue_style( 'uapro-admin-license', \Uncanny_Automator_Pro\Utilities::get_css( 'admin/license.css' ), array(), AUTOMATOR_PRO_PLUGIN_VERSION );
			}
		}

		if ( 'uo-recipe_page_uncanny-automator-dashboard' === (string) $hook ) {
			Utilities::automator_enqueue_global_assets();

			// Get data about the connected site
			$this->automator_connect = self::is_automator_connected();

			add_filter(
				'automator_assets_backend_js_data',
				function ( $data ) {

					// Check if the user has Automator Pro
					$is_pro_active = false;
					if ( isset( $this->automator_connect['item_name'] ) ) {
						if ( defined( 'AUTOMATOR_PRO_ITEM_NAME' ) && $this->automator_connect['item_name'] === AUTOMATOR_PRO_ITEM_NAME ) {
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

					return $data;

				},
				10,
				1
			);
		}

		if ( 'uo-recipe_page_uncanny-automator-integrations' === (string) $hook ) {
			// Load global assets
			Utilities::automator_enqueue_global_assets();
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
		/* add_submenu_page(
			$parent_slug,
			esc_attr__( 'Integrations', 'uncanny-automator' ),
			esc_attr__( 'Integrations', 'uncanny-automator' ),
			'manage_options',
			'uncanny-automator-integration',
			array(
				$this,
				'page_integrations',
			)
		); */

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

		/* translators: 1. Trademarked term */
		$page_title               = sprintf( esc_attr__( '%1$s settings', 'uncanny-automator' ), 'Uncanny Automator' );
		$capability               = 'manage_options';
		$menu_title               = esc_attr__( 'Settings', 'uncanny-automator' );
		$menu_slug                = 'uncanny-automator-settings';
		$this->settings_page_slug = $menu_slug;
		$function                 = array( $this, 'options_menu_settings_page_output' );

		add_submenu_page( 'edit.php?post_type=uo-recipe', $page_title, $menu_title, $capability, $menu_slug, $function );

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

		$user      = wp_get_current_user();
		$dashboard = (object) array(
			// Check if the user is using Automator Pro
			'is_pro'             => $is_pro_active,
			// Is Pro connected
			'is_pro_installed'   => defined( 'AUTOMATOR_PRO_FILE' ) ? true : false,
			'pro_activate_link'  => site_url( 'wp-admin/edit.php?post_type=uo-recipe&page=uncanny-automator-license-activation' ),
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
				'free_credits'              => $is_connected ? ( $is_connected['usage_limit'] - $is_connected['paid_usage_count'] ) : 1000,
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
	 * Create the "Integrations" view
	 */
	public function page_integrations() {
		// Include the template
		include UA_ABSPATH . 'src/core/views/admin-integrations.php';
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
	 *
	 */
	public function options_menu_settings_page_output() {
		// Loading license and data tracking info
		$active = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
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

		if ( 200 === $credit_data->statusCode ) { //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			set_transient( 'automator_api_credit_data', (array) $credit_data->data, time() + ( ( 60 * 60 ) * 1 ) );

			return (array) $credit_data->data;
		}

		return false;
	}


	/**
	 *
	 */
	public function update_automator_connect() {
		if ( isset( $_GET['action'] ) && 'update_free_key' === $_GET['action'] && isset( $_GET['uap_automator_free_license_key'] ) && ! empty( $_GET['uap_automator_free_license_key'] ) ) {
			update_option( 'uap_automator_free_license_key', $_GET['uap_automator_free_license_key'] );
			$license = trim( $_GET['uap_automator_free_license_key'] );
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
					// $license_data->license will be either "valid" or "invalid"
					update_option( 'uap_automator_free_license_status', $license_data->license_check );

					// $license_data->license_check will be either "valid", "invalid", "expired", "disabled", "inactive", or "site_inactive"
					update_option( 'uap_automator_free_license_status', $license_data->license );
					// License data
					update_option( 'uap_automator_free_license_data', (array) $license_data );
				}
				wp_safe_redirect( remove_query_arg( array( 'action', 'uap_automator_free_license_key' ) ) );
				die;
			}
		} elseif ( isset( $_GET['action'] ) && 'discount_automator_connect' === $_GET['action'] ) {

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
		if ( $license_data->license == 'valid' ) {
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
}

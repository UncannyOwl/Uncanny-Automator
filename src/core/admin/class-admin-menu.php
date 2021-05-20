<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Menu
 * @package Uncanny_Automator
 */
class Admin_Menu {

	/**
	 * @var array
	 */
	public static $tabs = array();
	/**
	 * Setting Page title
	 * @var
	 */
	public $settings_page_slug;

	/**
	 * class constructor
	 */
	public function __construct() {
		// Setup Theme Options Page Menu in Admin
		add_action( 'admin_init', array( $this, 'plugins_loaded' ), 1 );
		add_action( 'admin_menu', array( $this, 'register_options_menu_page' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'reporting_assets' ) );
		add_filter( 'admin_title', array( $this, 'modify_report_titles' ), 40, 2 );
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
		}
	}

	/**
	 * Create Plugin options menu
	 */
	public function register_options_menu_page() {
		$parent_slug              = 'edit.php?post_type=uo-recipe';
		$this->settings_page_slug = $parent_slug;
		$function                 = array( $this, 'logs_options_menu_page_output' );
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
}

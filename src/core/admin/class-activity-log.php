<?php

namespace Uncanny_Automator;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Activity_Log
 *
 * @package Uncanny_Automator
 */
class Activity_Log {

	/**
	 * Activity Page title
	 * @var $settings_page_slug
	 */
	public $settings_page_slug;

	/*
	 * Activity Log Data
	 */
	/**
	 * @var array
	 */
	public $log_data = array();

	/**
	 *  Class constructor
	 */
	public function __construct() {

		add_action( 'admin_enqueue_scripts', array( $this, 'add_log_scripts' ) );
		add_action( 'wp_ajax_recipe-triggers', array( $this, 'load_recipe_triggers' ), 50 );
		add_action( 'wp_ajax_nopriv_recipe-triggers', array( $this, 'load_recipe_triggers' ), 50 );
		add_action( 'wp_ajax_recipe-actions', array( $this, 'load_recipe_actions' ), 50 );
		add_action( 'wp_ajax_nopriv_recipe-actions', array( $this, 'load_recipe_actions' ), 50 );
		add_action( 'admin_init', array( $this, 'load_minimal_admin' ) );
		add_action( 'admin_init', array( $this, 'close_window_on_load' ) );
		// Remove all admin notices in recipe details log modal.
		add_action( 'in_admin_header', array( $this, 'recipe_logs_notices_remove' ), 99 );
	}

	/**
	 * Remove admin notices in recipe logs details page.
	 *
	 * @return boolean True after remove_all_actions. Otherwise, false.
	 */
	public function recipe_logs_notices_remove() {

		$current_screen = get_current_screen();

		if ( ! isset( $current_screen->id ) ) {
			return false;
		}

		if ( 'uo-recipe_page_uncanny-automator-recipe-activity-details' === $current_screen->id ) {

			// Remove sitewide notices.
			remove_all_actions( 'network_admin_notices' );
			// Remove all notices for site admins.
			remove_all_actions( 'user_admin_notices' );
			// Remove all user notices.
			remove_all_actions( 'admin_notices' );

			return true;
		}

		return false;
	}

	public function close_window_on_load(){
		// Check if we should close the window
		if ( automator_filter_has_var( 'ua_close_window' ) ){
			?>

			<script>

			try {
			    // Close this window
			    window.close();
			} catch ( e ){
				console.log( e );
			}

			</script>

			<?php
		}
	}

	public function load_minimal_admin() {
		if ( automator_filter_has_var( 'hide_settings_tabs' ) ){
			ob_start();
			?>
			<style>
				.nav-tab-wrapper {
					display: none !important;
				}
			</style>
			<?php
			echo ob_get_clean();
		}

		if ( ! automator_filter_has_var( 'minimal' ) ) {
			return;
		}
		ob_start();
		?>
		<style>
			html.wp-toolbar {
				padding-top: 0 !important;
			}

			.wrap.uap .uap-nav-tab-wrapper,
			.uap-logs .tablenav.top,
			#wpadminbar,
			#wpfooter,
			#uap-review-banner,
			#lity-container,
			.notice,
			.uap .uap-review-banner,
			div.uap-log-table-container div.error,
			#adminmenumain {
				display: none !important;
			}

			#wpcontent, #wpfooter {
				margin-left: 0 !important;
			}

			.lity-container {
				height: 80% !important;
			}

			.lity-content,
			.lity-iframe-container {
				height: 100% !important;
			}
		</style>
		<?php
		echo ob_get_clean();
	}

	/**
	 *
	 */
	public function add_log_scripts() {
		if ( ! automator_filter_has_var( 'post_type' ) && 'uo-recipe' !== automator_filter_input( 'post_type' ) ) {
			return;
		}
		if ( ! automator_filter_has_var( 'page' ) && 'uncanny-automator-recipe-activity' !== automator_filter_input( 'page' ) ) {
			return;
		}
		//Added lity option for the iframe ligthbox
		wp_enqueue_style( 'uap-lity', Utilities::automator_get_vendor_asset( 'lity/css/lity.min.css' ), array(), '2.4.1' );
		wp_enqueue_script( 'uap-lity', Utilities::automator_get_vendor_asset( 'lity/js/lity.min.js' ), array( 'jquery' ), '2.4.1', true );

		// Recipe details css.
		wp_enqueue_style( 'uap-recipe-details', Utilities::automator_get_css( 'admin/recipe-details.css' ), array(), Utilities::automator_get_version() );

	}

	/**
	 * Ajax load triggers for recipe
	 *
	 */
	public function load_recipe_triggers() {
		global $wpdb;
		check_ajax_referer( 'load-recipes-ref', 'ajax_nonce' );

		$recipe_id     = absint( $_REQUEST['recipe_id'] );
		$return_data   = array();
		$return_data[] = array(
			'id'   => '',
			'text' => 'All triggers',
		);

		if ( ! $recipe_id ) {
			wp_send_json( $return_data );
		}

		$triggers = $wpdb->get_results(
			"select distinct(r.automator_trigger_id) as id,p.post_title as trigger_title from {$wpdb->prefix}uap_trigger_log r join {$wpdb->posts} p on p.ID = r.automator_trigger_id WHERE r.automator_recipe_id = '{$recipe_id}'  order by trigger_title asc",
			ARRAY_A
		);

		if ( $triggers ) {
			foreach ( $triggers as $trigger ) {
				$return_data[] = array(
					'id'   => $trigger['id'],
					'text' => $trigger['trigger_title'],
				);
			}
		}

		wp_send_json( $return_data );
	}

	/**
	 * Ajax load triggers for recipe
	 *
	 */
	public function load_recipe_actions() {
		global $wpdb;
		check_ajax_referer( 'load-recipes-ref', 'ajax_nonce' );

		$recipe_id     = absint( $_REQUEST['recipe_id'] );
		$return_data   = array();
		$return_data[] = array(
			'id'   => '',
			'text' => 'All actions',
		);

		if ( ! $recipe_id ) {
			wp_send_json( $return_data );
		}
		$actions = $wpdb->get_results(
			"select distinct(r.automator_action_id) as id,p.post_title as action_title from {$wpdb->prefix}uap_action_log r join {$wpdb->posts} p on p.ID = r.automator_action_id WHERE r.automator_recipe_id = '{$recipe_id}' order by action_title asc",
			ARRAY_A
		);

		if ( $actions ) {
			foreach ( $actions as $action ) {
				$return_data[] = array(
					'id'   => $action['id'],
					'text' => $action['action_title'],
				);
			}
		}

		wp_send_json( $return_data );
	}
}

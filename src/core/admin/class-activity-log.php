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
	 *
	 * @var $settings_page_slug
	 */
	public $settings_page_slug;

	/**
	 * Activity Log Data
	 *
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
		add_action( 'admin_head', array( $this, 'load_minimal_admin' ) );
		add_action( 'admin_init', array( $this, 'close_window_on_load' ) );
		// Remove all admin notices in recipe details log modal.
		add_action( 'in_admin_header', array( $this, 'recipe_logs_notices_remove' ), 99 );

		// Clear recipe run / activity logs
		add_action( 'admin_init', array( $this, 'remove_specific_run' ), 999 );
		add_action( 'admin_init', array( $this, 'remove_specific_recipe_runs' ), 999 );
		add_action( 'admin_notices', array( $this, 'recipe_run_cleared' ) );
		add_filter( 'post_row_actions', array( $this, 'add_delete_recipe_run_row' ), 10, 2 );
	}

	/**
	 * Remove a specific run from DB
	 *
	 * @return void
	 */
	public function remove_specific_run() {
		if ( ! automator_filter_has_var( 'delete_specific_activity' ) ) {
			return;
		}
		if ( ! automator_filter_has_var( 'wpnonce' ) ) {
			return;
		}
		if ( ! automator_filter_has_var( 'recipe_id' ) ) {
			return;
		}
		if ( ! automator_filter_has_var( 'run_number' ) ) {
			return;
		}
		if ( ! automator_filter_has_var( 'recipe_log_id' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( automator_filter_input( 'wpnonce' ), AUTOMATOR_FREE_ITEM_NAME ) ) {
			return;
		}
		$recipe_id     = (int) automator_filter_input( 'recipe_id' );
		$recipe_log_id = (int) automator_filter_input( 'recipe_log_id' );
		$page          = (string) automator_filter_input( 'page' );

		// Delete closure logs
		automator_purge_closure_logs( $recipe_id, $recipe_log_id );

		// Delete action logs
		automator_purge_action_logs( $recipe_id, $recipe_log_id );

		// Delete trigger logs
		automator_purge_trigger_logs( $recipe_id, $recipe_log_id );

		// Delete recipe logs
		automator_purge_recipe_logs( $recipe_id, $recipe_log_id );
		$get_referer = wp_get_referer();
		if ( preg_match( "/$page/", $get_referer ) ) {
			wp_safe_redirect( sprintf( '%s&recipe_activity_run_success=1', $get_referer ) );
			exit;
		}
		wp_safe_redirect( sprintf( '%s?post_type=%s&page=%s&recipe_activity_run_success=1', admin_url( 'edit.php' ), 'uo-recipe', $page ) );
		exit;
	}

	/**
	 * Remove all logs of a specific recipe
	 *
	 * @return void
	 */
	public function remove_specific_recipe_runs() {
		if ( ! automator_filter_has_var( 'clear_recipe_activity' ) ) {
			return;
		}
		if ( ! automator_filter_has_var( 'wpnonce' ) ) {
			return;
		}
		if ( ! automator_filter_has_var( 'recipe_id' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( automator_filter_input( 'wpnonce' ), AUTOMATOR_FREE_ITEM_NAME ) ) {
			return;
		}
		$recipe_id = (int) automator_filter_input( 'recipe_id' );
		if ( empty( $recipe_id ) ) {
			return;
		}
		// clear logs
		clear_recipe_logs( $recipe_id );
		wp_safe_redirect( sprintf( '%s?post_type=%s&recipe_activity_clear_success=1', admin_url( 'edit.php' ), 'uo-recipe' ) );
		exit;
	}

	/**
	 * Show success messages
	 *
	 * @return void
	 */
	public function recipe_run_cleared() {
		if ( ! automator_filter_has_var( 'recipe_activity_clear_success' ) && ! automator_filter_has_var( 'recipe_activity_run_success' ) ) {
			return;
		}
		$message = '';
		if ( automator_filter_has_var( 'recipe_activity_clear_success' ) ) {
			$message = esc_attr__( 'Recipe run data successfully deleted.', 'uncanny-automator' );
		}
		if ( automator_filter_has_var( 'recipe_activity_run_success' ) ) {
			$message = esc_attr__( 'Recipe run successfully deleted.', 'uncanny-automator' );
		}

		if ( empty( $message ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_attr( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Add "Clear activity logs" row under Recipe title on all recipes page
	 *
	 * @param $actions
	 * @param $post
	 *
	 * @return mixed
	 */
	public function add_delete_recipe_run_row( $actions, $post ) {
		if ( 'uo-recipe' !== $post->post_type ) {
			return $actions;
		}
		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post    = current_user_can( $post_type_object->cap->edit_post, $post->ID );
		if ( ! $can_edit_post ) {
			return $actions;
		}
		$delete_url                         = sprintf( '%s?post_type=%s&recipe_id=%d&clear_recipe_activity=1&wpnonce=%s', admin_url( 'edit.php' ), 'uo-recipe', $post->ID, wp_create_nonce( AUTOMATOR_FREE_ITEM_NAME ) );
		$actions['clear_recipe_runs trash'] = sprintf( '<a href="%s" class="submitdelete" onclick="javascript: return confirm(\'%s\')">%s</a>', $delete_url, esc_attr__( 'Are you sure you want to delete all run data associated with this recipe? This will reset recipe runs to zero for all users. This action is irreversible.', 'uncanny-automator' ), esc_attr__( 'Clear activity logs', 'uncanny-automator' ) );

		return $actions;
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

	/**
	 * Close the window on load.
	 *
	 * @return void
	 */
	public function close_window_on_load() {
		// Check if we should close the window
		if ( automator_filter_has_var( 'ua_close_window' ) ) {
			?>

			<script>

				try {
					// Close this window
					window.close();
				} catch (e) {
					console.log(e);
				}

			</script>

			<?php
		}
	}

	/**
	 * Adds inline style to admin head.
	 *
	 * @return void
	 */
	public function load_minimal_admin() {

		// Early bail if query string param `automator_minimal` and `automator_hide_settings_tabs` is not set.
		if ( ! automator_filter_has_var( 'automator_minimal' ) || ! automator_filter_has_var( 'automator_hide_settings_tabs' ) ) {

			// Bail if page is not recipe log details.
			if ( 'uncanny-automator-recipe-activity-details' !== automator_filter_input( 'page' ) ) {
				return;
			}
		}

		?>
		<style>
			.nav-tab-wrapper {
				display : none !important ;
			}
			html.wp-toolbar {
				padding-top : 0 !important ;
			}
			.wrap.uap .uap-nav-tab-wrapper, .uap-logs .tablenav.top, #wpadminbar, #wpfooter, #uap-review-banner, #lity-container, .notice, .uap .uap-review-banner, div.uap-log-table-container div.error, #adminmenumain {
				display : none !important ;
			}
			#wpcontent, #wpfooter {
				margin-left : 0 !important ;
			}
			.lity-container {
				height : 80% !important ;
			}
			.lity-content, .lity-iframe-container {
				height : 100% !important ;
			}
			#wpbody {
				padding-top : 0 !important ;
			}
		</style>
		<?php

	}

	/**
	 * Add log scripts.
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
		wp_enqueue_style( 'uap-recipe-details', Utilities::automator_get_asset( 'legacy/css/admin/recipe-details.css' ), array(), Utilities::automator_get_version() );

	}

	/**
	 * Ajax load triggers for recipe
	 */
	public function load_recipe_triggers() {
		global $wpdb;
		check_ajax_referer( 'load-recipes-ref', 'ajax_nonce' );

		$recipe_id     = absint( automator_filter_input( 'recipe_id', INPUT_POST ) );
		$return_data   = array();
		$return_data[] = array(
			'id'   => '',
			'text' => 'All triggers',
		);

		if ( ! $recipe_id ) {
			wp_send_json( $return_data );
		}

		$triggers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT (r.automator_trigger_id) AS id, p.post_title as trigger_title
FROM {$wpdb->prefix}uap_trigger_log r
    JOIN $wpdb->posts p on p.ID = r.automator_trigger_id
WHERE r.automator_recipe_id = %d
ORDER BY trigger_title ASC",
				$recipe_id
			),
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
	 */
	public function load_recipe_actions() {
		global $wpdb;
		check_ajax_referer( 'load-recipes-ref', 'ajax_nonce' );

		$recipe_id     = absint( automator_filter_input( 'recipe_id', INPUT_POST ) );
		$return_data   = array();
		$return_data[] = array(
			'id'   => '',
			'text' => 'All actions',
		);

		if ( ! $recipe_id ) {
			wp_send_json( $return_data );
		}
		$actions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
    DISTINCT (r.automator_action_id) AS id, p.post_title AS action_title
FROM {$wpdb->prefix}uap_action_log r
    JOIN $wpdb->posts p ON p.ID = r.automator_action_id
WHERE r.automator_recipe_id = %d
ORDER BY action_title",
				$recipe_id
			),
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

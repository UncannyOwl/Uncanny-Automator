<?php

namespace Uncanny_Automator;

/**
 *
 */
class Prune_Logs {

	/**
	 * Class constructor
	 */
	public function __construct() {
		// Add setting
		add_action( 'automator_settings_general_logs_content', array( $this, 'add_purge_settings' ), 10 );

		// Add setting to automatically prone logs (just for promotion)
		add_action( 'automator_settings_general_logs_content', array( $this, 'add_pro_auto_prune_settings' ), 15 );

		add_action( 'admin_init', array( $this, 'maybe_purge_logs' ) );

		// Register settings
		$this->register_settings();
	}

	/**
	 * Register the settings
	 */
	private function register_settings() {
		add_action( 'admin_init', function(){
			register_setting( 'uncanny_automator_manual_prune', 'automator_manual_purge_days' );
		} );
	}

	/**
	 * Add field to the settings page
	 */
	public function add_purge_settings() {
		// Get the date of the last time this action was performed
		$last_manual_prune_date = get_option( 'automator_last_manual_prune_date', '' );

		// Check if it was ever executed
		$user_pruned_before = ! empty( $last_manual_prune_date );

		// Number of days (value of the field)
		$number_of_days = get_option( 'automator_manual_purge_days', '' );

		// Check if the logs were JUST pruned
		$user_just_pruned_logs = automator_filter_has_var( 'pruned' );

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/general/logs/prune-logs.php' );
	}

	/**
	 * Add a tease of the auto prune tool available in Pro
	 */
	public function add_pro_auto_prune_settings() {
		// Check if the user has Automator Free
		// Don't add it if Pro is active
		if ( ! is_automator_pro_active() ) {
			// Get the link to upgrade to Pro
			$upgrade_to_pro_url = add_query_arg(
				// UTM
				array(
					'utm_source' => 'uncanny_automator',
					'utm_medium' => 'settings',
					'utm_content' => 'auto_prune_tease'
				),

				'https://automatorplugin.com/pricing/'
			);


			// Load the view
			include Utilities::automator_get_view( 'admin-settings/tab/general/logs/auto-prune-logs-tease.php' );
		}
	}

	public function maybe_purge_logs() {

		if ( ! automator_filter_has_var( '_wpnonce', INPUT_POST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce', INPUT_POST ), 'uncanny_automator' ) ) {
			return;
		}

		$prune_days_limit = automator_filter_input( 'automator_manual_purge_days', INPUT_POST );

		if ( empty( $prune_days_limit ) ) {
			return;
		}
		if ( intval( $prune_days_limit ) < 1 ) {
			return;
		}

		global $wpdb;

		$previous_time = gmdate( 'Y-m-d', strtotime( '-' . $prune_days_limit . ' days' ) );
		$recipes       = $wpdb->get_results( $wpdb->prepare( "SELECT `ID`, `automator_recipe_id` FROM {$wpdb->prefix}uap_recipe_log WHERE `date_time` < %s AND ( `completed` = %d OR `completed` = %d  OR `completed` = %d )", $previous_time, 1, 2, 9 ) );

		if ( empty( $recipes ) ) {
			update_option( 'automator_last_manual_prune_date', time() );

			wp_safe_redirect(
				add_query_arg(
					array(
						'pruned' => 1
					),
					$this->get_logs_settings_url()
				)
			);

			exit;
		}

		foreach ( $recipes as $recipe ) {
			$recipe_id               = absint( $recipe->automator_recipe_id );
			$automator_recipe_log_id = absint( $recipe->ID );

			// Prune recipe logs.
			automator_purge_recipe_logs( $recipe_id, $automator_recipe_log_id );

			// Prune trigger logs.
			automator_purge_trigger_logs( $recipe_id, $automator_recipe_log_id );

			// Prune action logs.
			automator_purge_action_logs( $recipe_id, $automator_recipe_log_id );

			// Prune closure logs.
			automator_purge_closure_logs( $recipe_id, $automator_recipe_log_id );
		}
		update_option( 'automator_last_manual_prune_date', time() );

		wp_safe_redirect(
			add_query_arg(
				array(
					'pruned' => 1
				),
				$this->get_logs_settings_url()
			)
		);

		exit;
	}

	/**
	 * Get the URL with the field to prune the logs
	 * 
	 * @return string The URL
	 */
	public function get_logs_settings_url() {
		return add_query_arg(
			array(
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-config',
				'tab'       => 'general',
				'general'   => 'logs'
			),
			admin_url( 'edit.php' )
		);
	}
}

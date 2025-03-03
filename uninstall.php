<?php

// If uninstall is not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if the 'automator_delete_data_on_uninstall' setting is enabled
if ( false === get_option( 'automator_delete_data_on_uninstall', false ) ) {
	// Check if the 'automator_delete_data_on_uninstall' setting is enabled in custom table
	global $wpdb;
	$setting = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}uap_options WHERE option_name = 'automator_delete_data_on_uninstall'" );

	if ( false === $setting ) {
		// The setting is not enabled, so bail out
		return;
	}

	// The setting is not enabled, so bail out
	return;
}

/**
 * Function to log errors
 *
 * @param $message
 *
 * @return void
 */
function log_uninstall_error( $message ) {
	// Error logging logic here (e.g., error_log, writing to a custom log file, etc.)
	error_log( 'Uncanny Automator Uninstall Error: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

try {
	global $wpdb;

	// Database tables to be removed
	$tables_to_remove = array(
		$wpdb->prefix . 'uap_recipe_log',
		$wpdb->prefix . 'uap_recipe_log_meta',
		$wpdb->prefix . 'uap_trigger_log',
		$wpdb->prefix . 'uap_trigger_log_meta',
		$wpdb->prefix . 'uap_action_log',
		$wpdb->prefix . 'uap_action_log_meta',
		$wpdb->prefix . 'uap_closure_log',
		$wpdb->prefix . 'uap_closure_log_meta',
		$wpdb->prefix . 'uap_tokens_log',
		$wpdb->prefix . 'uap_api_log_response',
		$wpdb->prefix . 'uap_api_log',
		$wpdb->prefix . 'uap_recipe_count',
		$wpdb->prefix . 'uap_options',
		// Pro tables
		$wpdb->prefix . 'uap_loop_entries',
		$wpdb->prefix . 'uap_loop_entries_items',
		$wpdb->prefix . 'uap_queue',
		$wpdb->prefix . 'uap_scheduled_actions',
	);

	// Remove the tables
	foreach ( $tables_to_remove as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Database views to be removed
	$views_to_remove = array(
		$wpdb->prefix . 'uap_recipe_logs_view',
		$wpdb->prefix . 'uap_trigger_logs_view',
		$wpdb->prefix . 'uap_action_logs_view',
		$wpdb->prefix . 'uap_api_logs_view',
	);

	// Remove the views
	foreach ( $views_to_remove as $view ) {
		$wpdb->query( "DROP VIEW IF EXISTS $view" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Custom post types to be removed
	$post_types_to_remove = array(
		'uo-action',
		'uo-closure',
		'uo-recipe',
		'uo-trigger',
		// Pro post-types
		'uo-loop',
		'uo-loop-filter',
	);

	// Remove posts and post meta of custom post types
	foreach ( $post_types_to_remove as $p_type ) {
		$_posts = get_posts(
			array(
				'post_type'      => $p_type,
				'posts_per_page' => 9999999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			)
		);

		foreach ( $_posts as $_post ) {
			wp_delete_post( $_post->ID, true );
		}
	}

	// Delete transients containing 'automator'
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_%automator%'" );
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_%uap%'" );

	// Delete options containing 'automator' or 'uap' etc
	// Patterns to match in option names
	$like_patterns = array(
		'automator_%',
		'uncanny_automator_%',
		'uap_%',
		'_uoa_%',
		'_uncanny_automator%',
		'_uncanny_credits%',
		'UO_REDIRECTURL_%',
		'_uncannyowl_zoom_%',
		'uoa_setup_wiz_has_connected',
		'zoho_campaigns_%',
		'USERROLEADDED_migrated',
		'_uncannyowl_slack_settings',
		'affwp_insert_referral_migrated',
		'ua_facebook%',
		'_uncannyowl_gtt%',
	);

	// Building the query dynamically
	$like_conditions = array_map(
		function ( $pattern ) use ( $wpdb ) {
			return $wpdb->prepare( 'option_name LIKE %s', $pattern );
		},
		$like_patterns
	);

	// Combine the conditions with OR
	$conditions_query = implode( ' OR ', $like_conditions );

	// Final SQL query
	$sql_query = "DELETE FROM $wpdb->options WHERE $conditions_query";

	// Execute the query
	$wpdb->query( $sql_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$cron_jobs_to_unhook = array(
		'automator_weekly_healthcheck',
		'automator_daily_healthcheck',
		'automator_report',
		'automator_pro_loops_run_queue', // Automator Pro hook
	);

	foreach ( $cron_jobs_to_unhook as $cron_job ) {
		wp_clear_scheduled_hook( $cron_job );
	}

	// Remove custom translations for 'uncanny-automator'
	$translations_dir               = WP_CONTENT_DIR . '/languages/plugins';
	$uncanny_automator_translations = glob( $translations_dir . '/uncanny-automator-*' );

	foreach ( $uncanny_automator_translations as $file ) {
		// Check if file exists and is writable
		if ( file_exists( $file ) && wp_is_writable( $file ) ) {
			wp_delete_file( $file ); // Delete the file
		}
	}

	// Check if Action Scheduler is installed
	if ( class_exists( 'ActionScheduler_DBStore' ) ) {
		// Unhook all scheduled actions by the "automator" or "Uncanny Automator" group
		ActionScheduler_DBStore::instance()->cancel_actions_by_group( 'automator' );
		ActionScheduler_DBStore::instance()->cancel_actions_by_group( 'Uncanny Automator' );
	}

	// Remove the 'uapro_auto_purge_logs' scheduled action
	wp_clear_scheduled_hook( 'uapro_auto_purge_logs' );
	wp_clear_scheduled_hook( 'automator_async_run_with_hash' );

} catch ( Error $e ) {
	log_uninstall_error( $e->getMessage() );
} catch ( Exception $e ) {
	log_uninstall_error( $e->getMessage() );
}

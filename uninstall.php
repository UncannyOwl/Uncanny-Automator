<?php

// If uninstall is not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if the 'automator_delete_data_on_uninstall' setting is enabled
// Check both wp_options and uap_options tables in a single query
global $wpdb;
$setting = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COALESCE(
			(SELECT option_value FROM {$wpdb->options} WHERE option_name = %s),
			(SELECT option_value FROM {$wpdb->prefix}uap_options WHERE option_name = %s)
		) as setting_value",
		'automator_delete_data_on_uninstall',
		'automator_delete_data_on_uninstall'
	)
);

// Only proceed if setting is explicitly enabled ('1' or 'yes')
// This prevents uninstall when setting is '0', '', or doesn't exist (NULL)
if ( '1' !== $setting && 'yes' !== $setting ) {
	return;
}

/**
 * Function to log errors during uninstall
 *
 * @param string $message The error message to log.
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
		$result = $wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( false === $result && ! empty( $wpdb->last_error ) ) {
			log_uninstall_error( "Failed to drop table {$table}: " . $wpdb->last_error );
		}
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
		$result = $wpdb->query( "DROP VIEW IF EXISTS $view" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( false === $result && ! empty( $wpdb->last_error ) ) {
			log_uninstall_error( "Failed to drop view {$view}: " . $wpdb->last_error );
		}
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
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);

		foreach ( $_posts as $_post ) {
			wp_delete_post( $_post->ID, true );
		}
	}

	// Delete custom taxonomy terms
	$taxonomies_to_remove = array( 'recipe_category', 'recipe_tag' );
	foreach ( $taxonomies_to_remove as $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				wp_delete_term( $term->term_id, $taxonomy );
			}
		}
	}

	// Delete transients containing 'automator'
	$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", '%_transient_%automator%' ) );
	if ( false === $result && ! empty( $wpdb->last_error ) ) {
		log_uninstall_error( 'Failed to delete automator transients: ' . $wpdb->last_error );
	}

	$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", '%_transient_%uap%' ) );
	if ( false === $result && ! empty( $wpdb->last_error ) ) {
		log_uninstall_error( 'Failed to delete UAP transients: ' . $wpdb->last_error );
	}

	// Delete webhook sample options (new naming convention)
	$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", '%webhook_sample_uap%' ) );
	if ( false === $result && ! empty( $wpdb->last_error ) ) {
		log_uninstall_error( 'Failed to delete webhook sample options: ' . $wpdb->last_error );
	}

	$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", '%webhook_expiry_uap%' ) );
	if ( false === $result && ! empty( $wpdb->last_error ) ) {
		log_uninstall_error( 'Failed to delete webhook expiry options: ' . $wpdb->last_error );
	}

	$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", '%webhook_data_type_uap%' ) );
	if ( false === $result && ! empty( $wpdb->last_error ) ) {
		log_uninstall_error( 'Failed to delete webhook data type options: ' . $wpdb->last_error );
	}

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

	// Execute each deletion separately for clarity and safety
	foreach ( $like_patterns as $pattern ) {
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", $pattern ) );
		if ( false === $result && ! empty( $wpdb->last_error ) ) {
			log_uninstall_error( "Failed to delete options matching pattern '{$pattern}': " . $wpdb->last_error );
		}
	}

	$cron_jobs_to_unhook = array(
		'automator_weekly_healthcheck',
		'automator_daily_healthcheck',
		'automator_report',
		'automator_pro_loops_run_queue', // Automator Pro hook
	);

	foreach ( $cron_jobs_to_unhook as $cron_job ) {
		wp_clear_scheduled_hook( $cron_job );
	}

	// Delete user meta created by Automator and integrations
	$user_meta_patterns = array(
		'_uncannyowl_gtt_training_%',
		'_uncannyowl_gtw_webinar_%',
		'_twilio_sms_',
		'display_pop_up_%',
		'automator_tooltips_visibility',
	);

	foreach ( $user_meta_patterns as $pattern ) {
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE %s", $pattern ) );
		if ( false === $result && ! empty( $wpdb->last_error ) ) {
			log_uninstall_error( "Failed to delete user meta matching pattern '{$pattern}': " . $wpdb->last_error );
		}
	}

	// Remove custom translations for 'uncanny-automator'
	$translations_dir               = WP_CONTENT_DIR . '/languages/plugins';
	$uncanny_automator_translations = glob( $translations_dir . '/uncanny-automator-*' );

	if ( false !== $uncanny_automator_translations && is_array( $uncanny_automator_translations ) ) {
		foreach ( $uncanny_automator_translations as $file ) {
			// Check if file exists and is writable
			if ( file_exists( $file ) && wp_is_writable( $file ) ) {
				wp_delete_file( $file ); // Delete the file
			}
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

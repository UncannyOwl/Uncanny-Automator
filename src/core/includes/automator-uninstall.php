<?php
/**
 * Site-scoped helpers used when WordPress removes the plugin.
 *
 * @package Uncanny_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Log an uninstall failure without preventing cleanup of other sites.
 *
 * @since 8.0.0
 * @param string $message Error description.
 * @return void
 */
function automator_uninstall_log_error( $message ) {
	error_log( 'Uncanny Automator Uninstall Error: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

/**
 * The current site's Automator tables that "Delete all data" drops.
 *
 * Every table Free's schema creates (Automator_DB::get_schema()), the closure log tables 8.0.0 retired, which a
 * site deleted before it upgraded still holds, and Pro's tables, which stay behind when Free is deleted first.
 *
 * @since 8.0.0
 * @return string[] Table names with the current site's prefix.
 */
function automator_uninstall_tables() {
	global $wpdb;

	$tables = array(
		'uap_recipe_log',
		'uap_recipe_log_meta',
		'uap_trigger_log',
		'uap_trigger_log_meta',
		'uap_action_log',
		'uap_action_log_meta',
		'uap_closure_log',
		'uap_closure_log_meta',
		'uap_tokens_log',
		'uap_api_log_response',
		'uap_api_log',
		'uap_recipe_count',
		'uap_options',
		'uap_recipe_throttle_log',
		'uap_error_log',
		'uap_run_snapshots',
		'uap_async_jobs',
		'uap_connections',
		'uap_platform_inbox',
		// Pro tables.
		'uap_loop_entries',
		'uap_loop_entries_items',
		'uap_loop_entries_items_data',
		'uap_queue',
		'uap_scheduled_actions',
		'uap_rss_items',
	);

	$prefixed = array();
	foreach ( $tables as $table ) {
		$prefixed[] = $wpdb->prefix . $table;
	}

	return $prefixed;
}

/**
 * The cron hooks Automator schedules on a site, which "Delete all data" unschedules whatever their arguments.
 *
 * @since 8.0.0
 * @return string[]
 */
function automator_uninstall_cron_hooks() {
	return array(
		'automator_weekly_healthcheck',
		'automator_daily_healthcheck',
		'automator_report',
		'automator_pro_loops_run_queue', // Automator Pro hook.
		'automator_mcp_token_cleanup',
		'automator_mcp_pending_registration_cleanup',
		'uapro_auto_purge_logs',
		'automator_async_run_with_hash',
		'automator_purge_run_snapshots',
		'automator_calculate_tables_size',
		'automator_upload_dir_sanitize',
		'automator_migrate_error_log_batch',
		'uap_options_migration',
		'automator_options_migration_cron',
		'automator_background_job_poll',
		'automator_background_site_retry',
		'automator_background_site_sweep',
		'automator_gemini_key_scrub_batch',
		'automator_recover_stuck_recipes',
		'automator_platform_site_heartbeat',
		'automator_platform_daily_maintenance',
		'automator_platform_complete_registration',
		'automator_platform_connection_sync',
		'automator_platform_connection_adoption',
		'automator_platform_inbox_drain',
		'automator_facebook_lead_ads_prune_lead_claims',
		'automator_facebook_lead_ads_page_adoption',
	);
}

/**
 * Read the deletion choice for the current site without loading the plugin.
 *
 * @since 8.0.0
 * @return bool
 */
function automator_uninstall_site_opted_in() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads wp_options directly: during uninstall the plugin's options layer is not loaded.
	$setting = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			'automator_delete_data_on_uninstall'
		)
	);
	if ( '' !== $wpdb->last_error ) {
		automator_uninstall_log_error( 'Could not read a site deletion setting.' );
		return false;
	}

	if ( null === $setting ) {
		$table = $wpdb->prefix . 'uap_options';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall check for the custom options table; must not be cached.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( '' !== $wpdb->last_error ) {
			automator_uninstall_log_error( 'Could not verify a site deletion setting.' );
			return false;
		}
		if ( $table === $found ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup in bounded batches; must not be cached.
			$setting = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT option_value FROM `' . esc_sql( $table ) . '` WHERE option_name = %s',
					'automator_delete_data_on_uninstall'
				)
			);
			if ( '' !== $wpdb->last_error ) {
				automator_uninstall_log_error( 'Could not read a site deletion setting.' );
				return false;
			}
		}
	}

	return '1' === $setting || 'yes' === $setting;
}

/**
 * Visit each opted-in site without holding the entire network in memory.
 *
 * @since 8.0.0
 * @param callable $cleanup Cleanup for the current site.
 * @return void
 */
function automator_uninstall_opted_in_sites( $cleanup ) {
	global $wpdb;

	if ( ! is_multisite() ) {
		if ( automator_uninstall_site_opted_in() ) {
			call_user_func( $cleanup );
		}
		return;
	}

	$after = 0;
	do {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lists the network sites during uninstall; must not be cached.
		$site_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT blog_id FROM {$wpdb->blogs} WHERE blog_id > %d ORDER BY blog_id ASC LIMIT %d",
				$after,
				100
			)
		);
		if ( '' !== $wpdb->last_error || ! is_array( $site_ids ) ) {
			automator_uninstall_log_error( 'Could not list WordPress sites for cleanup.' );
			return;
		}
		foreach ( $site_ids as $site_id ) {
			$after = (int) $site_id;
			switch_to_blog( (int) $site_id );
			try {
				if ( automator_uninstall_site_opted_in() ) {
					call_user_func( $cleanup );
				}
			} catch ( Throwable $error ) {
				automator_uninstall_log_error( 'Could not clean site ' . (int) $site_id . '.' );
			} finally {
				restore_current_blog();
			}
		}
		$batch_count = count( $site_ids );
	} while ( 100 === $batch_count );
}

/**
 * Remove only the current site's MCP user metadata, in bounded batches.
 *
 * Network-global legacy records have no safe site owner and are retained on
 * multisite. WordPress's metadata API invalidates persistent user-meta caches.
 *
 * @since 8.0.0
 * @return void
 */
function automator_uninstall_mcp_user_meta() {
	global $wpdb;

	$suffix       = is_multisite() ? '_site_' . max( 1, (int) get_current_blog_id() ) : '';
	$token_key    = 'automator_mcp_tokens_encrypted' . $suffix;
	$internal_key = 'automator_mcp_internal_token' . $suffix;
	$usage_prefix = 'automator_mcp_token_last_used' . $suffix . '_';
	$after        = 0;

	do {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup in bounded batches; must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT umeta_id, meta_key FROM {$wpdb->usermeta}
				WHERE umeta_id > %d AND (meta_key = %s OR meta_key = %s OR meta_key LIKE %s)
				ORDER BY umeta_id ASC LIMIT %d",
				$after,
				$token_key,
				$internal_key,
				$wpdb->esc_like( $usage_prefix ) . '%',
				200
			)
		);
		if ( '' !== $wpdb->last_error || ! is_array( $rows ) ) {
			automator_uninstall_log_error( 'Could not read MCP user metadata.' );
			return;
		}

		foreach ( $rows as $row ) {
			$after = (int) $row->umeta_id;
			$key   = (string) $row->meta_key;
			$usage = 0 === strpos( $key, $usage_prefix ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', substr( $key, strlen( $usage_prefix ) ) );
			if ( $token_key !== $key && $internal_key !== $key && ! $usage ) {
				continue;
			}
			if ( ! delete_metadata_by_mid( 'user', $after ) ) {
				automator_uninstall_log_error( 'Could not remove an MCP user metadata record.' );
			}
		}
		$batch_count = count( $rows );
	} while ( 200 === $batch_count );
}

/**
 * Remove the current site's Automator user options, for every user.
 *
 * User options are stored under this site's blog-prefixed meta key, so other
 * sites of a network keep theirs. A feature that stores a per-site user
 * option adds its key here. The keys are written out because uninstall runs
 * without the plugin's autoloader; each is pinned to its constant by
 * Uninstall_User_Meta_Test.
 *
 * @since 8.0.0
 * @return void
 */
function automator_uninstall_user_meta() {
	global $wpdb;

	$keys = array(
		// Recipe_View_Preference_Service::VIEW_USER_OPTION.
		'automator_recipe_view',
		// Recipe_View_Preference_Service::DIAGRAM_INTRO_USER_OPTION.
		'automator_recipe_diagram_intro_dismissed',
		// Recipe_View_Preference_Service::DEFAULT_VIEW_USER_OPTION.
		'automator_recipe_default_view',
		// Notice_Dismissals::USER_OPTION. Its site-wide map is in uap_options.
		'automator_notice_dismissals',
	);

	foreach ( $keys as $key ) {
		delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . $key, '', true );
	}
}

/**
 * Remove files recorded in the current site's database-backed transfers.
 *
 * Cache-only references and files with no remaining reference cannot be
 * assigned to a site safely on multisite and are retained.
 *
 * @since 8.0.0
 * @param string $directory Current site's temporary transfer directory.
 * @return void
 */
function automator_uninstall_mcp_recorded_transfers( $directory ) {
	global $wpdb;

	$prefix = '_transient_automator_mcp_upload_';
	$after  = 0;
	do {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup in bounded batches; must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_value FROM {$wpdb->options} WHERE option_id > %d AND option_name LIKE %s ORDER BY option_id ASC LIMIT %d",
				$after,
				$wpdb->esc_like( $prefix ) . '%',
				200
			)
		);
		if ( '' !== $wpdb->last_error || ! is_array( $rows ) ) {
			automator_uninstall_log_error( 'Could not read MCP temporary upload references.' );
			return;
		}
		foreach ( $rows as $row ) {
			$after = (int) $row->option_id;
			// The reference is a plain array; no class is ever restored (global-functions.php, and with it
			// automator_safe_unserialize(), is not loaded during uninstall).
			$value = is_serialized( $row->option_value )
				? @unserialize( trim( $row->option_value ), array( 'allowed_classes' => false ) ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged -- arrays only, allowed_classes false.
				: $row->option_value;
			$name  = is_array( $value ) ? ( $value['file'] ?? '' ) : '';
			if ( ! is_string( $name ) || 1 !== preg_match( '/^[A-Za-z0-9]{64}$/D', $name ) ) {
				continue;
			}
			$path = $directory . '/' . $name;
			if ( is_file( $path ) && ! is_link( $path ) ) {
				wp_delete_file( $path );
			}
		}
		$batch_count = count( $rows );
	} while ( 200 === $batch_count );
}

/**
 * Remove owned temporary transfers without deleting another site's files.
 *
 * @since 8.0.0
 * @return void
 */
function automator_uninstall_mcp_transfers() {
	$uploads = wp_upload_dir( null, false );
	if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
		return;
	}
	$directory = untrailingslashit( $uploads['basedir'] ) . '/automator-mcp-transfers';
	if ( ! is_dir( $directory ) || is_link( $directory ) ) {
		return;
	}
	if ( is_multisite() ) {
		automator_uninstall_mcp_recorded_transfers( $directory );
		return;
	}

	$handle = opendir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_opendir -- A local, plugin-owned temporary directory.
	if ( false === $handle ) {
		automator_uninstall_log_error( 'Could not open the MCP temporary upload directory.' );
		return;
	}

	$unrecognized = false;
	while ( true ) {
		$name = readdir( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readdir
		if ( false === $name ) {
			break;
		}
		if ( '.' === $name || '..' === $name || '.htaccess' === $name || 'index.php' === $name ) {
			continue;
		}
		$path = $directory . '/' . $name;
		if ( 1 !== preg_match( '/^[A-Za-z0-9]{64}$/D', $name ) || ! is_file( $path ) || is_link( $path ) ) {
			$unrecognized = true;
			continue;
		}
		wp_delete_file( $path );
		if ( file_exists( $path ) ) {
			$unrecognized = true;
			automator_uninstall_log_error( 'Could not remove an MCP temporary upload.' );
		}
	}
	closedir( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_closedir

	if ( $unrecognized ) {
		return;
	}
	$guards = array(
		'.htaccess' => "# Pending Automator MCP file transfers.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
		'index.php' => "<?php\n// Silence is golden.\n",
	);
	foreach ( $guards as $name => $contents ) {
		$path = $directory . '/' . $name;
		if ( is_file( $path ) && ! is_link( $path ) && filesize( $path ) === strlen( $contents ) && file_get_contents( $path ) === $contents ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			wp_delete_file( $path );
		}
	}
	@rmdir( $directory ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Remove only an empty directory.
}

/**
 * Remove current-site options in bounded batches while clearing WP caches.
 *
 * @since 8.0.0
 * @param string $pattern Existing SQL LIKE pattern for Automator options.
 * @return void
 */
function automator_uninstall_options_like( $pattern ) {
	global $wpdb;

	$after = 0;
	do {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup in bounded batches; must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name FROM {$wpdb->options} WHERE option_id > %d AND option_name LIKE %s ORDER BY option_id ASC LIMIT %d",
				$after,
				$pattern,
				200
			)
		);
		if ( '' !== $wpdb->last_error || ! is_array( $rows ) ) {
			automator_uninstall_log_error( 'Could not read Automator options.' );
			return;
		}

		foreach ( $rows as $row ) {
			$after = (int) $row->option_id;
			if ( ! delete_option( $row->option_name ) ) {
				automator_uninstall_log_error( 'Could not remove an Automator option.' );
			}
		}
		$batch_count = count( $rows );
	} while ( 200 === $batch_count );
}

/**
 * Remove the plugin's catalogued option names without wildcard matching.
 *
 * @since 8.0.0
 * @param string[] $keys Known option names.
 * @return void
 */
function automator_uninstall_known_options( $keys ) {
	global $wpdb;

	foreach ( array_chunk( $keys, 100 ) as $chunk ) {
		$after = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup in bounded batches; must not be cached.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_id, option_name FROM {$wpdb->options} WHERE option_id > %d AND option_name IN (" . implode( ', ', array_fill( 0, count( $chunk ), '%s' ) ) . ') ORDER BY option_id ASC LIMIT %d',
					array_merge( array( $after ), $chunk, array( 200 ) )
				)
			);
			if ( '' !== $wpdb->last_error || ! is_array( $rows ) ) {
				automator_uninstall_log_error( 'Could not read Automator options.' );
				return;
			}
			foreach ( $rows as $row ) {
				$after = (int) $row->option_id;
				if ( in_array( $row->option_name, $chunk, true ) && ! delete_option( $row->option_name ) ) {
					automator_uninstall_log_error( 'Could not remove an Automator option.' );
				}
			}
			$batch_count = count( $rows );
		} while ( 200 === $batch_count );
	}
}

/**
 * Remove the current site's Automator options and no other plugin's.
 *
 * Automator's options start with `automator_` or `uap_automator_` (its license keys and app settings such as Zoom,
 * Twilio, GoToTraining and GoToWebinar), and a single site's Pro loop batches and their status with `uap_loops_`.
 * The prefixes are escaped, so `_` matches only itself and AutomatorWP's `automatorwp_` options stay. Automator's
 * other `uap_` options go by name, because Ultimate Affiliate Pro keeps its settings under `uap_` too.
 *
 * @since 8.0.0
 * @return void
 */
function automator_uninstall_options() {
	global $wpdb;

	foreach ( array( 'automator_', 'uap_automator_', 'uap_loops_' ) as $prefix ) {
		automator_uninstall_options_like( $wpdb->esc_like( $prefix ) . '%' );
	}

	$names = require dirname( __DIR__ ) . '/classes/automator-options/array-option-keys.php';
	// ActiveCampaign kept these in wp_options before 6.0; the options migration list never carried them.
	$names[] = 'uap_active_campaign_credentials_hash';
	$names[] = 'uap_active_campaign_settings_timestamp';
	automator_uninstall_known_options( $names );
}

/**
 * Remove the current site's Automator transients and no other plugin's.
 *
 * WordPress keeps a transient in `_transient_{name}` and its expiry in `_transient_timeout_{name}`; a single site keeps
 * its site transients in `_site_transient_{name}` and `_site_transient_timeout_{name}`. Each prefix below is a name
 * Free or Pro gives its transients, and each is escaped, so `_` matches only itself: AutomatorWP's `automatorwp_` and
 * Ultimate Affiliate Pro's `uap_` transients stay.
 *
 * @since 8.0.0
 * @return void
 */
function automator_uninstall_transients() {
	global $wpdb;

	$prefixes = array(
		'automator_',
		'uap_automator_',
		'_automator_hubspot_token_info', // HubSpot's token before its credentials migration.
		'uap_complete_json', // The integration catalog feed, one copy per language.
		'uap_idem_', // A recipe run's idempotency guard.
		'uap_webhook_evt_', // Automator Platform webhook events already handled.
		'uap_post_update_dedup_', // The WordPress "post updated" triggers, Free and Pro.
		'uap_mp_added_', // MemberPress "added to a membership".
		'uap_loops_', // Pro's loop process lock, a site transient.
		'ua_ac_', // ActiveCampaign's tags, lists and contact fields.
		'asana_custom_fields_refresh_',
		'asana_task_added_',
		'github_repo_branches_',
		'github_repo_issues_',
		'github_repo_labels_',
		'github_repo_pull_requests_',
		'github_repo_tags_',
		'uo_recipe_schedule_', // Pro's Schedule notices.
		'uo_wpcafe_deleted_reservation_', // Pro's WPCafe reservation snapshots.
	);

	foreach ( array( '_transient_', '_transient_timeout_', '_site_transient_', '_site_transient_timeout_' ) as $form ) {
		foreach ( $prefixes as $prefix ) {
			automator_uninstall_options_like( $wpdb->esc_like( $form . $prefix ) . '%' );
		}
		// Pro's loop transaction, loop_process_{loop}_{recipe}_{run log}_{run number}_transaction_transient: its prefix
		// alone is too common to claim.
		automator_uninstall_options_like( $wpdb->esc_like( $form . 'loop_process_' ) . '%' . $wpdb->esc_like( '_transaction_transient' ) );
	}
}

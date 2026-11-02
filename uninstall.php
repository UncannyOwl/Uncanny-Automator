<?php

// If uninstall is not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/src/core/includes/automator-uninstall.php';
require_once __DIR__ . '/src/core/services/action-scheduler.php';

/**
 * Delete an opted-in site's Automator data.
 *
 * @since 8.0.0
 * @param bool $legacy_patterns Whether this is the site where deletion started.
 * @return void
 */
function automator_uninstall_site( $legacy_patterns ) {
	try {
		global $wpdb;

		automator_uninstall_mcp_transfers();
		automator_uninstall_mcp_user_meta();
		automator_uninstall_user_meta();

		// Database tables to be removed
		$tables_to_remove = automator_uninstall_tables();

		// Remove the tables
		foreach ( $tables_to_remove as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall removes the plugin's own tables.
			$result = $wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
			if ( false === $result && ! empty( $wpdb->last_error ) ) {
				automator_uninstall_log_error( "Failed to drop table {$table}: " . $wpdb->last_error );
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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall removes the plugin's own views.
			$result = $wpdb->query( 'DROP VIEW IF EXISTS `' . esc_sql( $view ) . '`' );
			if ( false === $result && ! empty( $wpdb->last_error ) ) {
				automator_uninstall_log_error( "Failed to drop view {$view}: " . $wpdb->last_error );
			}
		}

		// Custom post types to be removed
		$post_types_to_remove = array(
			'uo-action',
			'uo-closure',
			'uo-recipe',
			'uo-trigger',
			'uo-block',
			'uo-block-modifier',
			// Pro post-types
			'uo-loop',
			'uo-loop-filter',
		);

		// Remove posts and post meta of custom post types
		foreach ( $post_types_to_remove as $p_type ) {
			$after = 0;
			do {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall removes the plugin's own data in batches; nothing to cache.
				$post_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
						$p_type,
						$after,
						200
					)
				);
				if ( '' !== $wpdb->last_error || ! is_array( $post_ids ) ) {
					automator_uninstall_log_error( 'Could not read Automator posts.' );
					break;
				}
				foreach ( $post_ids as $post_id ) {
					$after = (int) $post_id;
					if ( ! wp_delete_post( $after, true ) ) {
						automator_uninstall_log_error( 'Could not remove an Automator post.' );
					}
				}
				$batch_count = count( $post_ids );
			} while ( 200 === $batch_count );
		}

		// Delete custom taxonomy terms
		$taxonomies_to_remove = array( 'recipe_category', 'recipe_tag' );
		foreach ( $taxonomies_to_remove as $taxonomy_to_remove ) {
			// The plugin is inactive during uninstall. An existing registration
			// can therefore belong to another plugin and must not be removed.
			if ( taxonomy_exists( $taxonomy_to_remove ) ) {
				automator_uninstall_log_error( 'A recipe taxonomy is in use and was retained.' );
				continue;
			}
			$registered = register_taxonomy(
				$taxonomy_to_remove,
				$post_types_to_remove,
				array(
					'public'  => false,
					'rewrite' => false,
				)
			);
			if ( is_wp_error( $registered ) ) {
				automator_uninstall_log_error( 'Could not register a recipe taxonomy for removal.' );
				continue;
			}
			$after = 0;
			do {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall removes the plugin's own data in batches; nothing to cache.
				$terms = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s AND term_taxonomy_id > %d ORDER BY term_taxonomy_id ASC LIMIT %d",
						$taxonomy_to_remove,
						$after,
						200
					)
				);
				if ( '' !== $wpdb->last_error || ! is_array( $terms ) ) {
					automator_uninstall_log_error( 'Could not read Automator terms.' );
					break;
				}
				foreach ( $terms as $term ) {
					$after   = (int) $term->term_taxonomy_id;
					$deleted = wp_delete_term( (int) $term->term_id, $taxonomy_to_remove );
					if ( ! $deleted || is_wp_error( $deleted ) ) {
						automator_uninstall_log_error( 'Could not remove an Automator taxonomy term.' );
					}
				}
				$batch_count = count( $terms );
			} while ( 200 === $batch_count );
			unregister_taxonomy( $taxonomy_to_remove );
		}

		// Automator's options and transients go on every site, and another plugin's stay.
		automator_uninstall_options();
		automator_uninstall_transients();

		if ( $legacy_patterns ) {
			// Preserve existing cleanup behavior on the original site.
			$like_patterns = array(
				'%webhook_sample_uap%',
				'%webhook_expiry_uap%',
				'%webhook_data_type_uap%',
				'uncanny_automator_%',
				$wpdb->esc_like( '_uoa_' ) . '%',
				'_uncanny_automator%',
				'_uncanny_credits%',
				'UO_REDIRECTURL_%',
				'_uncannyowl_zoom_%',
				'uoa_setup_wiz_has_connected',
				$wpdb->esc_like( 'zoho_campaigns_' ) . '%',
				'USERROLEADDED_migrated',
				'_uncannyowl_slack_settings',
				'affwp_insert_referral_migrated',
				$wpdb->esc_like( 'ua_facebook' ) . '%',
				'_uncannyowl_gtt%',
			);
			foreach ( $like_patterns as $pattern ) {
				automator_uninstall_options_like( $pattern );
			}
		}

		// Every event of each hook goes, whatever its arguments: a background job's retry is scheduled with its job id.
		foreach ( automator_uninstall_cron_hooks() as $cron_job ) {
			wp_unschedule_hook( $cron_job );
		}

		// Legacy integration metadata is network-global and has no site owner.
		// Preserve it on multisite rather than affecting another site's users.
		$user_meta_patterns = array(
			'_uncannyowl_gtt_training_%',
			'_uncannyowl_gtw_webinar_%',
			'_twilio_sms_',
			'display_pop_up_%',
			'automator_tooltips_visibility',
		);

		if ( ! is_multisite() ) {
			foreach ( $user_meta_patterns as $pattern ) {
				do {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall removes the plugin's own data in batches; nothing to cache.
					$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE %s LIMIT %d", $pattern, 200 ) );
					if ( false === $result && ! empty( $wpdb->last_error ) ) {
						automator_uninstall_log_error( "Failed to delete user meta matching pattern '{$pattern}': " . $wpdb->last_error );
					}
				} while ( 200 === $result );
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

		// Cancel every scheduled action in the "automator" and "Uncanny Automator" groups (only possible when another
		// active plugin has loaded Action Scheduler; the service does nothing otherwise).
		\Uncanny_Automator\Services\Action_Scheduler::cancel_actions_by_group( 'automator' );
		\Uncanny_Automator\Services\Action_Scheduler::cancel_actions_by_group( 'Uncanny Automator' );

		// Remove the 'uapro_auto_purge_logs' scheduled action
		wp_clear_scheduled_hook( 'uapro_auto_purge_logs' );
		wp_clear_scheduled_hook( 'automator_async_run_with_hash' );

	} catch ( Error $e ) {
		automator_uninstall_log_error( $e->getMessage() );
	} catch ( Exception $e ) {
		automator_uninstall_log_error( $e->getMessage() );
	}
}

$automator_uninstall_origin = (int) get_current_blog_id();
automator_uninstall_opted_in_sites(
	static function () use ( $automator_uninstall_origin ) {
		automator_uninstall_site( $automator_uninstall_origin === (int) get_current_blog_id() ); // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- Both operands are values.
	}
);

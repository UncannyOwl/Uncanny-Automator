<?php
/**
 * EDD to EDDR Migration Script
 *
 * This script migrates existing EDD Recurring triggers, actions, and conditions
 * from the EDD integration to the new EDDR integration by updating postmeta.
 *
 * @package Uncanny_Automator\Integrations\Edd_Recurring_Integration
 */

namespace Uncanny_Automator\Integrations\Edd_Recurring_Integration;

use Uncanny_Automator\Migrations\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Class EDD_To_EDDR_Migration
 */
class EDD_To_EDDR_Migration extends Migration {

	/**
	 * EDD Recurring specific codes that need to be migrated
	 */
	const EDD_RECURRING_CODES = array(
		// Free triggers
		'EDDR_SUBSCRIBES',

		// Free actions
		'EDDR_CANCEL_SUBSCRIPTION',

		// Pro triggers
		'EDDR_SUBSCRIPTION_EXPIRES',
		'EDDR_SUBSCRIPTION_CANCELS',
		'EDD_USER_SUBSCRIBE_PRODWITHPRICEOPTION',
		'EDD_USER_REFUND_PRODWITHPRICEOPTION',
		'EDD_USER_CANCELS_SUBSCRIPTION_PRODWITHPRICEOPTION',

		// Pro actions
		'EDDR_SET_SUBSCRIPTION_EXPIRY',
		'EDDR_CANCEL_BY_ID',

		// Pro conditions
		'EDD_NOT_ACTIVE_SUBSCRIPTION',
		'EDDR_ACTIVE_SUBSCRIPTION',
	);

	/**
	 * Initialize the migration
	 */
	public function __construct() {
		// Use a version-specific migration name to ensure uniqueness
		parent::__construct( '610_edd_to_eddr_migration' );
	}

	/**
	 * Check if conditions are met to run the migration
	 *
	 * @return bool
	 */
	public function conditions_met() {
		// Only run in admin context
		return is_admin();
	}

	/**
	 * Run the migration process
	 */
	public function migrate() {
		global $wpdb;

		$updated_count = 0;
		$error_count   = 0;

		// Get all posts that have EDD integration and match our codes
		$placeholders = implode( ',', array_fill( 0, count( self::EDD_RECURRING_CODES ), '%s' ) );
		$query        = $wpdb->prepare( // phpcs:ignore
			"
			SELECT DISTINCT p.ID, p.post_type
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_integration ON p.ID = pm_integration.post_id
			INNER JOIN {$wpdb->postmeta} pm_code ON p.ID = pm_code.post_id
			WHERE pm_integration.meta_key = 'integration'
			AND pm_integration.meta_value = %s
			AND pm_code.meta_key = 'code'
			AND pm_code.meta_value IN ({$placeholders})
			", // phpcs:ignore
			array_merge( array( 'EDD' ), self::EDD_RECURRING_CODES )
		);

		$posts = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $posts ) ) {
			// No posts to migrate, mark as completed
			automator_log( 'No posts found to migrate', $this->name, true );
			$this->complete();
			return;
		}

		automator_log( sprintf( 'Starting migration for %d posts', count( $posts ) ), $this->name, true );

		foreach ( $posts as $post ) {
			// Update the integration meta from EDD to EDDR
			$updated = update_post_meta( $post->ID, 'integration', 'EDD_RECURRING' );

			if ( $updated ) {
				$updated_count++;
			} else {
				$error_count++;
			}
		}

		// Log migration results
		if ( $updated_count > 0 ) {
			automator_log( sprintf( 'Successfully updated %d posts', $updated_count ), $this->name, true );
		}
		if ( $error_count > 0 ) {
			automator_log( sprintf( 'Failed to update %d posts', $error_count ), $this->name, true );
		}

		// Mark migration as completed
		$this->complete();
	}

	/**
	 * Reset migration status (for testing purposes)
	 */
	public function reset_migration() {
		$migrations = automator_get_option( self::OPTION_NAME, array() );
		unset( $migrations[ $this->name ] );
		automator_update_option( self::OPTION_NAME, $migrations );
	}

	/**
	 * Get migration status info
	 *
	 * @return array
	 */
	public function get_migration_status() {
		return array(
			'completed' => $this->migration_completed_before(),
			'codes_to_migrate' => self::EDD_RECURRING_CODES,
			'migration_name' => $this->name,
		);
	}
}

<?php

namespace Uncanny_Automator\Integrations\Learndash;

use Uncanny_Automator\Actionify_Triggers\Trigger_Query;
use Uncanny_Automator\Migrations\Migration;

/**
 * Class Learndash_Quiz_Action_Migration
 *
 * Rewrites legacy `add_action` postmeta values on LD_PASSQUIZ and LD_FAILQUIZ
 * trigger posts to the current serialized array form.
 *
 * Historical values observed on customer imports:
 *   - 'learndash_quiz_completed' (very old, pre-rewrite)
 *   - 'learndash_quiz_submitted' (first rewrite, pre-essay-support)
 *
 * Current value (both runtime hooks, covers the admin-graded-essay path):
 *   array( 'learndash_quiz_submitted', 'learndash_essay_quiz_data_updated' )
 *
 * Runs once per site via the Migration abstract's shutdown hook. Replaces the
 * per-trigger `admin_init` migration previously registered from the LD_PASSQUIZ
 * and LD_FAILQUIZ constructors, which only fired when (a) the trigger class
 * was instantiated under demand-driven loading, and (b) the request hit the
 * admin. Neither is guaranteed for front-end-only installs.
 */
class Learndash_Quiz_Action_Migration extends Migration {

	/**
	 * Trigger codes that share this legacy-hook migration.
	 */
	const TRIGGER_CODES = array( 'LD_PASSQUIZ', 'LD_FAILQUIZ' );

	/**
	 * Legacy `add_action` values that should be rewritten.
	 */
	const LEGACY_VALUES = array(
		'learndash_quiz_completed',
		'learndash_quiz_submitted',
	);

	/**
	 * Perform the migration.
	 *
	 * @return void
	 */
	public function migrate() {

		global $wpdb;

		$new_value         = maybe_serialize(
			array(
				'learndash_quiz_submitted',
				'learndash_essay_quiz_data_updated',
			)
		);
		$code_placeholders = implode( ',', array_fill( 0, count( self::TRIGGER_CODES ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $code_placeholders is built from array_fill('%s').
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value IN ({$code_placeholders})",
				'code',
				...self::TRIGGER_CODES
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		if ( empty( $post_ids ) ) {
			$this->complete();
			return;
		}

		$id_placeholders    = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$value_placeholders = implode( ',', array_fill( 0, count( self::LEGACY_VALUES ), '%s' ) );
		$prepare_args       = array_merge(
			array( $new_value ),
			array_map( 'intval', $post_ids ),
			self::LEGACY_VALUES
		);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholders built from array_fill.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %s
				WHERE meta_key = 'add_action'
					AND post_id IN ({$id_placeholders})
					AND meta_value IN ({$value_placeholders})",
				...$prepare_args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		// Bust the trigger engine's active-triggers cache so new add_action values
		// are registered as WP hooks on the next request instead of waiting for the
		// transient TTL to lapse.
		if ( class_exists( '\\Uncanny_Automator\\Actionify_Triggers\\Trigger_Query' ) ) {
			delete_transient( Trigger_Query::CACHE_KEY );
		}

		$this->complete();
	}
}

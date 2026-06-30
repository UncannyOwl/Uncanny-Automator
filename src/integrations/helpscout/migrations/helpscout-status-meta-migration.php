<?php

namespace Uncanny_Automator\Integrations\Helpscout;

use Uncanny_Automator\Migrations\Migration;

/**
 * Class Helpscout_Status_Meta_Migration.
 *
 * The conversation-create action originally used option_code 'STATUS', which
 * collides with the reserved action item-meta key 'status' (postmeta meta_key
 * is case-insensitive in MySQL). The field value was written over the reserved
 * 'status' row and never persisted as its own key, so the required field looked
 * empty and the action stayed stuck in draft. The option_code is now
 * CONVERSATION_STATUS.
 *
 * For existing recipes this migration recovers the chosen status from the
 * surviving STATUS_readable label, writes CONVERSATION_STATUS(+_readable),
 * removes the stale field meta, and repairs any reserved 'status' row the
 * collision clobbered with a conversation value (active/closed/pending) instead
 * of a valid action status (draft/publish).
 *
 * @package Uncanny_Automator
 */
class Helpscout_Status_Meta_Migration extends Migration {

	/**
	 * The action code whose STATUS field collided with the reserved key.
	 *
	 * @var string
	 */
	const ACTION_CODE = 'HELPSCOUT_CONVERSATION_CREATE';

	/**
	 * Valid conversation status option values.
	 *
	 * @var array
	 */
	const VALID_STATUSES = array( 'active', 'closed', 'pending' );

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function migrate() {

		global $wpdb;

		// Action posts for the affected action code.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'code' AND meta_value = %s", // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::ACTION_CODE
			)
		);

		foreach ( $post_ids as $post_id ) {
			$this->migrate_action( (int) $post_id );
		}

		$this->complete();
	}

	/**
	 * Migrate a single action post.
	 *
	 * @param int $post_id The action post ID.
	 *
	 * @return void
	 */
	private function migrate_action( $post_id ) {

		global $wpdb;

		// Already migrated.
		if ( '' !== (string) get_post_meta( $post_id, 'CONVERSATION_STATUS', true ) ) {
			return;
		}

		// A literal 'STATUS' field row is unreadable via get_post_meta() (the
		// case-insensitive match returns the reserved 'status' row instead), so
		// read it with a binary comparison; fall back to the unambiguous label.
		$status_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = BINARY %s LIMIT 1", // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$post_id,
				'STATUS'
			)
		);

		$readable = (string) get_post_meta( $post_id, 'STATUS_readable', true );
		$value    = $this->resolve_status_value( $status_value, $readable );

		if ( '' !== $value ) {
			update_post_meta( $post_id, 'CONVERSATION_STATUS', $value );

			if ( '' !== $readable ) {
				update_post_meta( $post_id, 'CONVERSATION_STATUS_readable', $readable );
			}
		}

		// Remove the stale field meta. The binary delete is essential — a plain
		// delete_post_meta( 'STATUS' ) would case-insensitively wipe the reserved
		// 'status' row too.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = BINARY %s", // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$post_id,
				'STATUS'
			)
		);
		delete_post_meta( $post_id, 'STATUS_readable' );

		$this->maybe_repair_reserved_status( $post_id );
	}

	/**
	 * Resolve the conversation status value from the binary STATUS row or the
	 * readable label.
	 *
	 * @param string|null $status_value Raw value from a literal STATUS row, if any.
	 * @param string      $readable     The STATUS_readable label.
	 *
	 * @return string The status value, or empty string when nothing is recoverable.
	 */
	private function resolve_status_value( $status_value, $readable ) {

		foreach ( array( (string) $status_value, $readable ) as $candidate ) {
			$candidate = strtolower( trim( $candidate ) );
			if ( in_array( $candidate, self::VALID_STATUSES, true ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Restore the reserved 'status' item-meta when the collision overwrote it
	 * with a conversation value instead of a valid action status.
	 *
	 * @param int $post_id The action post ID.
	 *
	 * @return void
	 */
	private function maybe_repair_reserved_status( $post_id ) {

		$reserved = (string) get_post_meta( $post_id, 'status', true );

		if ( in_array( $reserved, array( 'draft', 'publish' ), true ) ) {
			return;
		}

		update_post_meta( $post_id, 'status', 'publish' === get_post_status( $post_id ) ? 'publish' : 'draft' );
	}
}

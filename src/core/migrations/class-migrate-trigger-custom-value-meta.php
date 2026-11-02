<?php

namespace Uncanny_Automator\Migrations;

use Uncanny_Automator\Trigger_Index;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migrate_Trigger_Custom_Value_Meta
 *
 * Renames trigger custom values stored as `{CODE}_CUSTOM` to `{CODE}_custom`, the
 * key the runtime match, the builder and the token parser read. The app-layer
 * trigger store wrote the uppercase suffix until it was fixed.
 *
 * MySQL compares meta keys without case, so `update_post_meta()` would update the
 * uppercase row in place and keep its key. The suffix is matched by exact case
 * and rows are written by meta ID instead.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator
 */
class Migrate_Trigger_Custom_Value_Meta extends Migration {

	/**
	 * Suffix the trigger store wrote.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const UPPERCASE_SUFFIX = '_CUSTOM';

	/**
	 * Suffix every reader looks up.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const LOWERCASE_SUFFIX = '_custom';

	/**
	 * Register the migration under its one-time key.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( '80_trigger_custom_value_meta' );
	}

	/**
	 * Rename the uppercase rows, then drop every cache that holds the old keys.
	 *
	 * A row is renamed only when its base field is stored on the same trigger,
	 * in any case, and the new key keeps the base's stored spelling. When the
	 * trigger already holds that key, the uppercase row is deleted instead.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function migrate() {

		global $wpdb;

		// A plain LIKE ignores case and would match the correct lowercase rows too.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration.
		$candidates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.meta_id, m.post_id, m.meta_key, p.post_parent
				   FROM $wpdb->posts p
				   INNER JOIN $wpdb->postmeta m ON m.post_id = p.ID
				  WHERE p.post_type = %s
				    AND m.meta_key LIKE BINARY %s
				  ORDER BY m.meta_id ASC",
				AUTOMATOR_POST_TYPE_TRIGGER,
				'%' . $wpdb->esc_like( self::UPPERCASE_SUFFIX )
			)
		);

		$stored_keys = $this->stored_keys( array_unique( wp_list_pluck( $candidates, 'post_id' ) ) );
		$changed     = array();

		foreach ( $candidates as $row ) {

			$trigger_id = (int) $row->post_id;
			$base_key   = $this->stored_spelling( $stored_keys[ $trigger_id ], substr( $row->meta_key, 0, - strlen( self::UPPERCASE_SUFFIX ) ) );

			if ( null === $base_key ) {
				continue;
			}

			$lowercase_key = $base_key . self::LOWERCASE_SUFFIX;

			if ( isset( $stored_keys[ $trigger_id ][ $lowercase_key ] ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- One-off migration.
				$wpdb->delete( $wpdb->postmeta, array( 'meta_id' => (int) $row->meta_id ), array( '%d' ) );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- One-off migration.
				$wpdb->update( $wpdb->postmeta, array( 'meta_key' => $lowercase_key ), array( 'meta_id' => (int) $row->meta_id ), array( '%s' ), array( '%d' ) );
				$stored_keys[ $trigger_id ][ $lowercase_key ] = true;
			}

			$changed[ $trigger_id ] = (int) $row->post_parent;
		}

		foreach ( array_keys( $changed ) as $trigger_id ) {
			wp_cache_delete( $trigger_id, 'post_meta' );
		}

		foreach ( array_unique( $changed ) as $recipe_id ) {
			automator_invalidate_recipe_schema( $recipe_id );
			Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );
		}

		// The upgrade rebuilds the trigger index before this runs, so it still holds the old keys.
		if ( ! empty( $changed ) ) {
			Trigger_Index::get_instance()->invalidate();
		}

		$this->complete();
	}

	/**
	 * Every meta key stored on the given triggers, read from the table by exact key.
	 *
	 * @since 8.0.0
	 *
	 * @param int[] $trigger_ids Trigger post IDs.
	 *
	 * @return array<int, array<string, true>> Trigger ID => meta key => true.
	 */
	private function stored_keys( array $trigger_ids ) {

		global $wpdb;

		$keys = array();

		if ( empty( $trigger_ids ) ) {
			return $keys;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_key FROM $wpdb->postmeta WHERE post_id IN (" . implode( ',', array_fill( 0, count( $trigger_ids ), '%d' ) ) . ')',
				$trigger_ids
			)
		);

		foreach ( $rows as $row ) {
			$keys[ (int) $row->post_id ][ $row->meta_key ] = true;
		}

		return $keys;
	}

	/**
	 * The spelling a key is stored under on a trigger, compared without case.
	 *
	 * A preserved-case field such as `product_rating` keeps its declared code, so
	 * its base is lowercase while its custom value was written `PRODUCT_RATING_CUSTOM`.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string, true> $keys Meta keys stored on the trigger.
	 * @param string              $key  Key to look up.
	 *
	 * @return string|null The stored spelling, an exact match first, or null when the trigger holds no such key.
	 */
	private function stored_spelling( array $keys, $key ) {

		if ( isset( $keys[ $key ] ) ) {
			return $key;
		}

		foreach ( array_keys( $keys ) as $stored_key ) {
			if ( 0 === strcasecmp( (string) $stored_key, $key ) ) {
				return (string) $stored_key;
			}
		}

		return null;
	}
}

new Migrate_Trigger_Custom_Value_Meta();

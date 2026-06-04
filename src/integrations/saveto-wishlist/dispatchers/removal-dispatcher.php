<?php

namespace Uncanny_Automator\Integrations\Saveto_Wishlist\Dispatchers;

/**
 * Class Removal_Dispatcher
 *
 * Normalizes the several ways SaveTo removes a wishlist item into one clean
 * internal action for the USER_REMOVES_PRODUCT trigger.
 *
 * Removal happens through three different code paths firing two different hooks
 * with incompatible payloads:
 *
 *   - Legacy AJAX `remove_product_from_wishlist` →
 *     `stwlite_after_product_removed_from_wishlist( $product_id, $collection_id, $variation_ids )`
 *     (single product, has variation ids).
 *   - Front-end REST `remove_wishlist_item` → `delete_collection_items( …, reverse=false )` →
 *     `stwlite_after_delete_collection_items( $collection_id, $product_ids, false, $deleted )`
 *     where `$product_ids` IS the removed set.
 *   - Admin bulk `save_collection` → `delete_collection_items( …, reverse=true )` →
 *     the same hook but `$product_ids` is the KEPT set, so the removed set has to
 *     be diffed against a pre-delete snapshot.
 *
 * The trigger only listened on the legacy hook, so REST / admin removals never
 * fired. This dispatcher emits one action per removed product on every path:
 *   `automator_saveto_wishlist_product_removed( int $product_id, int $collection_id, int[] $variation_ids )`
 *
 * @package Uncanny_Automator\Integrations\Saveto_Wishlist\Dispatchers
 */
class Removal_Dispatcher {

	/**
	 * Internal action the removal trigger listens on.
	 */
	const HOOK = 'automator_saveto_wishlist_product_removed';

	/**
	 * Pre-delete snapshot of a collection's product IDs, keyed by collection ID.
	 * Only populated for reverse (prune) deletes, where the hook reports the kept
	 * set rather than the removed set.
	 *
	 * @var array<int,int[]>
	 */
	private static $pre_delete = array();

	/**
	 * Idempotent boot.
	 *
	 * @return void
	 */
	public static function boot() {

		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		add_action( 'stwlite_after_product_removed_from_wishlist', array( __CLASS__, 'on_single_removed' ), 10, 3 );
		add_action( 'stwlite_before_delete_collection_items', array( __CLASS__, 'on_before_bulk_delete' ), 10, 3 );
		add_action( 'stwlite_after_delete_collection_items', array( __CLASS__, 'on_after_bulk_delete' ), 10, 4 );
	}

	/**
	 * Legacy single-product removal.
	 *
	 * @param int   $product_id
	 * @param int   $collection_id
	 * @param mixed $variation_ids
	 *
	 * @return void
	 */
	public static function on_single_removed( $product_id, $collection_id, $variation_ids ) {

		$product_id    = absint( $product_id );
		$collection_id = absint( $collection_id );

		if ( $product_id <= 0 || $collection_id <= 0 ) {
			return;
		}

		do_action( self::HOOK, $product_id, $collection_id, is_array( $variation_ids ) ? $variation_ids : array() );
	}

	/**
	 * Snapshot the collection's current products before a reverse (prune) delete,
	 * so the after-hook can work out which products were actually removed.
	 *
	 * @param int   $collection_id
	 * @param mixed $product_ids
	 * @param bool  $reverse_condition
	 *
	 * @return void
	 */
	public static function on_before_bulk_delete( $collection_id, $product_ids, $reverse_condition ) {

		if ( empty( $reverse_condition ) ) {
			return; // Non-reverse deletes carry the removed set directly.
		}

		$collection_id = absint( $collection_id );
		if ( $collection_id <= 0 ) {
			return;
		}

		self::$pre_delete[ $collection_id ] = self::current_product_ids( $collection_id );
	}

	/**
	 * Fan out one internal action per removed product after a bulk delete.
	 *
	 * @param int   $collection_id
	 * @param mixed $product_ids       Removed set when reverse is false; kept set when true.
	 * @param bool  $reverse_condition
	 * @param int   $deleted           Rows deleted.
	 *
	 * @return void
	 */
	public static function on_after_bulk_delete( $collection_id, $product_ids, $reverse_condition, $deleted ) {

		$collection_id = absint( $collection_id );
		if ( $collection_id <= 0 || (int) $deleted <= 0 ) {
			return;
		}

		$listed = array_values( array_filter( array_map( 'absint', (array) $product_ids ) ) );

		if ( empty( $reverse_condition ) ) {
			// $product_ids is the removed set.
			$removed = $listed;
		} else {
			// $product_ids is the kept set; removed = snapshot - kept.
			$before  = isset( self::$pre_delete[ $collection_id ] ) ? self::$pre_delete[ $collection_id ] : array();
			$removed = array_values( array_diff( $before, $listed ) );
			unset( self::$pre_delete[ $collection_id ] );
		}

		foreach ( $removed as $product_id ) {
			$product_id = absint( $product_id );
			if ( $product_id > 0 ) {
				do_action( self::HOOK, $product_id, $collection_id, array() );
			}
		}
	}

	/**
	 * Current product IDs in a collection.
	 *
	 * @param int $collection_id
	 *
	 * @return int[]
	 */
	private static function current_product_ids( $collection_id ) {

		global $wpdb;

		if ( ! class_exists( '\SaveToWishlist\Helpers\Helper_Database' ) ) {
			return array();
		}

		$items_table = \SaveToWishlist\Helpers\Helper_Database::get_collection_items_table_name();

		// Table name comes from the SaveTo helper; value is prepared.
		$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT product_id FROM {$items_table} WHERE collection_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$collection_id
			)
		);

		return is_array( $rows ) ? array_values( array_unique( array_map( 'absint', $rows ) ) ) : array();
	}
}

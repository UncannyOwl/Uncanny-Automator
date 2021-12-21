<?php


namespace Uncanny_Automator;

/**
 * Class Automator_DB_Handler_Closures
 *
 * @package Uncanny_Automator
 */
class Automator_DB_Handler_Closures {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @return Automator_DB_Handler_Closures
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @return array
	 */
	public function get_all() {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT cp.ID FROM $wpdb->posts cp
	                    LEFT JOIN $wpdb->posts rp ON rp.ID = cp.post_parent
						WHERE cp.post_type LIKE %s
						  AND cp.post_status LIKE %s
						  AND rp.post_status LIKE %s LIMIT 1",
				'uo-closure',
				'publish',
				'publish'
			)
		);
	}

	/**
	 * @param $closure_id
	 */
	public function delete( $closure_id ) {
		global $wpdb;

		// delete from uap_closure_log
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->closure,
			array( 'automator_closure_id' => $closure_id )
		);

		// delete from uap_closure_log_meta
		$wpdb->delete(
			$wpdb->prefix . Automator()->db->tables->closure_meta,
			array( 'automator_closure_id' => $closure_id )
		);
	}

	/**
	 * @param $recipe_id
	 * @param $automator_recipe_log_id
	 */
	public function delete_logs( $recipe_id, $automator_recipe_log_id ) {
		global $wpdb;
		$closure_tbl      = $wpdb->prefix . Automator()->db->tables->closure;
		$closure_meta_tbl = $wpdb->prefix . Automator()->db->tables->closure_meta;
		$closure          = $wpdb->get_col( $wpdb->prepare( "SELECT `ID` FROM $closure_tbl WHERE automator_recipe_id=%d AND automator_recipe_log_id=%d", $recipe_id, $automator_recipe_log_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $closure ) {
			foreach ( $closure as $automator_closure_log_id ) {
				// delete from uap_closure_log_meta
				$wpdb->delete(
					$closure_meta_tbl,
					array( 'automator_closure_log_id' => $automator_closure_log_id )
				);
			}
		}

		// delete from uap_closure_log
		$wpdb->delete(
			$closure_tbl,
			array(
				'automator_recipe_id'     => $recipe_id,
				'automator_recipe_log_id' => $automator_recipe_log_id,
			)
		);
	}

	/**
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function delete_by_recipe_id( $recipe_id ) {
		global $wpdb;
		$closure_tbl      = $wpdb->prefix . Automator()->db->tables->closure;
		$closure_meta_tbl = $wpdb->prefix . Automator()->db->tables->closure_meta;
		$closure          = $wpdb->get_col( $wpdb->prepare( "SELECT `ID` FROM $closure_tbl WHERE automator_recipe_id=%d", $recipe_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $closure ) {
			foreach ( $closure as $automator_closure_log_id ) {
				// delete from uap_closure_log_meta
				$wpdb->delete(
					$closure_meta_tbl,
					array( 'automator_closure_log_id' => $automator_closure_log_id )
				);
			}
		}

		// delete from uap_closure_log
		$wpdb->delete(
			$closure_tbl,
			array(
				'automator_recipe_id' => $recipe_id,
			)
		);
	}
}

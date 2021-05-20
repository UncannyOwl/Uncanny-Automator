<?php


namespace Uncanny_Automator;

/**
 * Class Automator_DB_Handler_Closures
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
	 * @param int $closure_id
	 */
	public function delete( int $closure_id ) {
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
}

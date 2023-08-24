<?php
namespace Uncanny_Automator\Services\Structure\Actions\Item\Loop;

/**
 * Handles loops filters database transactions.
 *
 * @package Uncanny_Automator\Services\Structure\Actions\Item\Loop
 * @since 5.0
 */
class Filters_Db {

	protected $db;

	public function __construct() {
		global $wpdb;
		$this->db = $wpdb;
	}

	public function get_loop_filters( $loop_id ) {

		$args = array(
			'post_parent' => $loop_id,
			'post_type'   => 'uo-loop-filter',
			'post_status' => 'publish',
		);

		$results = (array) get_posts( $args );

		if ( empty( $results ) ) {
			return array();
		}

		return array_column( $results, 'ID' );

	}
}

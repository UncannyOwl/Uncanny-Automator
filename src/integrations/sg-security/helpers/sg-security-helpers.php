<?php

namespace Uncanny_Automator\Integrations\Sg_Security;

/**
 * Class Sg_Security_Helpers
 *
 * @package Uncanny_Automator
 */
class Sg_Security_Helpers {

	/**
	 * Get the visitors table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sgs_log_visitors';
	}

	/**
	 * Get a visitor row by IP address.
	 *
	 * @param string $ip The IP address.
	 *
	 * @return object|null
	 */
	public function get_visitor_by_ip( $ip ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $this->get_table_name() . '` WHERE `ip` = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$ip
			)
		);
	}

	/**
	 * Get a visitor row by user ID.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return object|null
	 */
	public function get_visitor_by_user_id( $user_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $this->get_table_name() . '` WHERE `user_id` = %d LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user_id
			)
		);
	}

	/**
	 * Insert a new visitor row.
	 *
	 * @param string $ip      The IP address.
	 * @param int    $user_id The user ID.
	 * @param int    $block   Block status (1 = blocked, 0 = unblocked).
	 *
	 * @return int|false The insert ID or false on failure.
	 */
	public function insert_visitor( $ip, $user_id = 0, $block = 0 ) {
		global $wpdb;

		$data = array(
			'ip'         => $ip,
			'user_id'    => absint( $user_id ),
			'block'      => absint( $block ),
			'blocked_on' => time(),
		);

		$result = $wpdb->insert( $this->get_table_name(), $data );

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update the block status of a visitor row.
	 *
	 * @param int $id    The row ID.
	 * @param int $block Block status (1 = blocked, 0 = unblocked).
	 *
	 * @return int|false The number of rows updated or false on error.
	 */
	public function update_visitor_block( $id, $block ) {
		global $wpdb;

		$data = array( 'block' => absint( $block ) );

		if ( 1 === absint( $block ) ) {
			$data['blocked_on'] = time();
		}

		return $wpdb->update(
			$this->get_table_name(),
			$data,
			array( 'id' => absint( $id ) )
		);
	}
}

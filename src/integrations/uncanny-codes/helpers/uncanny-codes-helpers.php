<?php

namespace Uncanny_Automator\Integrations\Uncanny_Codes;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Uc_Helpers
 *
 * @package Uncanny_Automator
 */
class Uc_Helpers extends Abstract_Helpers {

	/**
	 * Uc_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * Remote-data handler: load batches with "Any batch" sentinel (triggers).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_batches( $request ): array {
		return $this->remote_data_success( $this->build_batch_options( true ) );
	}

	/**
	 * Remote-data handler: load batches without "Any" sentinel (actions).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_batches_strict( $request ): array {
		return $this->remote_data_success( $this->build_batch_options( false ) );
	}

	/**
	 * Remote-data handler: load distinct prefixes for dropdown.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_prefixes( $request ): array {
		return $this->remote_data_success( $this->build_distinct_options( 'prefix' ) );
	}

	/**
	 * Remote-data handler: load distinct suffixes for dropdown.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_suffixes( $request ): array {
		return $this->remote_data_success( $this->build_distinct_options( 'suffix' ) );
	}

	/**
	 * Build batch options array.
	 *
	 * @param bool $include_any Whether to prepend "Any batch" sentinel.
	 *
	 * @return array
	 */
	private function build_batch_options( $include_any = true ) {

		global $wpdb;

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any batch', 'Uncanny Codes', 'uncanny-automator' ),
			);
		}

		$all_batches = $wpdb->get_results( 'SELECT DISTINCT id, name FROM ' . $wpdb->prefix . 'uncanny_codes_groups', ARRAY_A );

		if ( ! empty( $all_batches ) ) {
			foreach ( $all_batches as $batch ) {
				if ( ! empty( $batch['name'] ) ) {
					$options[] = array(
						'value' => $batch['id'],
						'text'  => $batch['name'],
					);
				}
			}
		}

		return $options;
	}

	/**
	 * Build distinct option list for a single column on uncanny_codes_groups.
	 *
	 * @param string $column The whitelisted column name (`prefix` or `suffix`).
	 *
	 * @return array
	 */
	private function build_distinct_options( $column ) {

		global $wpdb;

		// Whitelist columns to keep the query safe.
		if ( ! in_array( $column, array( 'prefix', 'suffix' ), true ) ) {
			return array();
		}

		$options = array();
		$rows    = $wpdb->get_results( 'SELECT DISTINCT ' . $column . ' FROM ' . $wpdb->prefix . 'uncanny_codes_groups', ARRAY_A );

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! empty( $row[ $column ] ) ) {
					$options[] = array(
						'value' => $row[ $column ],
						'text'  => $row[ $column ],
					);
				}
			}
		}

		return $options;
	}

	/**
	 * Get batch info by batch ID.
	 *
	 * @param int $batch_id The batch ID.
	 *
	 * @return array Associative array with 'batch_data' and 'codes_data'.
	 */
	public function uc_get_batch_info( $batch_id ) {

		global $wpdb;

		$tbl_groups = $wpdb->prefix . 'uncanny_codes_groups';
		$tbl_codes  = $wpdb->prefix . 'uncanny_codes_codes';

		$batch_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_groups} WHERE `ID` = %d GROUP BY ID", $batch_id ), OBJECT ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$codes_data = $wpdb->get_row( $wpdb->prepare( "SELECT GROUP_CONCAT(`code`) as codes FROM {$tbl_codes} WHERE `code_group` = %d", $batch_id ), OBJECT ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'batch_data' => $batch_data,
			'codes_data' => $codes_data,
		);
	}

	/**
	 * Get code string by coupon ID.
	 *
	 * @param int $coupon_id The coupon ID.
	 *
	 * @return string The code string.
	 */
	public function uc_get_code_redeemed( $coupon_id ) {

		global $wpdb;

		$tbl_codes = $wpdb->prefix . 'uncanny_codes_codes';

		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT `code` FROM {$tbl_codes} WHERE `ID` = %d", $coupon_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get code_group ID by code string.
	 *
	 * @param string $code The code string.
	 *
	 * @return int The code group ID.
	 */
	public function uc_get_code_group_by_code( $code ) {

		global $wpdb;

		$tbl_codes  = $wpdb->prefix . 'uncanny_codes_codes';
		$code_group = $wpdb->get_var( $wpdb->prepare( "SELECT `code_group` FROM {$tbl_codes} WHERE `code` = %s", $code ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $code_group;
	}

	/**
	 * Get code ID by code string.
	 *
	 * @param string $code The code string.
	 *
	 * @return int The code ID.
	 */
	public function uc_get_id_by_code( $code ) {

		global $wpdb;

		$tbl_codes = $wpdb->prefix . 'uncanny_codes_codes';
		$id        = $wpdb->get_var( $wpdb->prepare( "SELECT `ID` FROM {$tbl_codes} WHERE `code` = %s", $code ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $id;
	}

	/**
	 * Get full code group row by group ID.
	 *
	 * @param int $group_id The code group ID.
	 *
	 * @return object|array The group row object or empty array.
	 */
	public function uc_get_code_group_row( $group_id ) {

		global $wpdb;

		$tbl_groups = $wpdb->prefix . 'uncanny_codes_groups';
		$group      = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $tbl_groups . ' WHERE ID = %d', $group_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $group ) ) {
			return array();
		}

		return $group;
	}
}

<?php

namespace Uncanny_Automator\Integrations\SureMembers;

use SureMembers\Inc\Access;
use SureMembers\Inc\Access_Groups;

class SureMembers_Helpers {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

	}

	/**
	 * get_access_groups_options
	 *
	 * @return void
	 */
	public function get_access_groups_options( $allow_any = true ) {

		$options = array();

		if ( $allow_any ) {
			$options[] = array(
				'text'  => _x( 'Any group', 'SureMembers', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$access_groups = Access_Groups::get_active();

		foreach ( $access_groups as $group_id => $group_name ) {
			$options[] = array(
				'value' => $group_id,
				'text'  => $group_name,
			);
		}

		return $options;
	}

	/**
	 * get_group_name_by_id
	 *
	 * @param  int $group_id
	 * @return string
	 */
	public function get_group_name_by_id( $group_id ) {

		$all_groups = Access_Groups::get_active();

		if ( ! isset( $all_groups[ $group_id ] ) ) {
			return _x( 'Access group not found', 'SureMembers', 'uncanny-automator' );
		}

		return $all_groups[ $group_id ];
	}

	/**
	 * grant_access
	 *
	 * @param  mixed $user_id
	 * @param  mixed $group_id
	 * @return void
	 */
	public function grant_access( $user_id, $group_id ) {

		if ( empty( $user_id ) ) {
			throw new \Exception( esc_html__( 'Missing user ID', 'uncanny-automator' ) );
		}

		if ( ! Access_Groups::is_active_access_group( $group_id ) ) {
			throw new \Exception( esc_html__( "Group wasn't found", 'uncanny-automator' ) );
		}

		if ( Access_Groups::check_plan_active( absint( $user_id ), absint( $group_id ) ) ) {
			throw new \Exception( esc_html__( 'The user was already in the specified group', 'uncanny-automator' ) );
		}

		Access::grant( $user_id, $group_id );
	}

	/**
	 * revoke_access
	 *
	 * @param  mixed $user_id
	 * @param  mixed $group_id
	 * @return void
	 */
	public function revoke_access( $user_id, $group_id ) {

		if ( empty( $user_id ) ) {
			throw new \Exception( esc_html__( 'Missing user ID', 'uncanny-automator' ) );
		}

		if ( ! Access_Groups::is_active_access_group( $group_id ) ) {
			throw new \Exception( esc_html__( "Group wasn't found", 'uncanny-automator' ) );
		}

		if ( ! Access_Groups::check_plan_active( absint( $user_id ), absint( $group_id ) ) ) {
			throw new \Exception( esc_html__( 'The user was not in the specified group', 'uncanny-automator' ) );
		}

		Access::revoke( $user_id, $group_id );
	}
}

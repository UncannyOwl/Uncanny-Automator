<?php

namespace Uncanny_Automator\Integrations\Userswp;

use WP_Error;
use WP_User;

/**
 * Class Userswp_User_Record
 *
 * The WP user record fields UsersWP's own account save writes alongside its
 * table: first and last name, display name, website, `bio` as `description`
 * and `uwp_language` as the `locale` user meta.
 *
 * @package Uncanny_Automator\Integrations\Userswp
 */
class Userswp_User_Record {

	/**
	 * @var Userswp_Helpers
	 */
	private $helpers;

	/**
	 * @param Userswp_Helpers $helpers
	 */
	public function __construct( $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * Mirror written keys to the WP user record in one `wp_update_user()` call,
	 * as UsersWP's own account save does, so WordPress's `profile_update` fires
	 * once. The record is read back and compared with each value as WordPress
	 * stores it, so a value something changes or blocks after WordPress's own
	 * filters is reported. Keys that are not mirrored are ignored.
	 *
	 * @param int   $user_id
	 * @param array $values UsersWP key => the value written.
	 *
	 * @return string[] The keys the record does not hold; empty on success.
	 */
	public function mirror( $user_id, $values ) {

		$user     = get_userdata( absint( $user_id ) );
		$expected = array();

		foreach ( (array) $values as $key => $value ) {
			if ( isset( Userswp_Helpers::CORE_MIRRORED_KEYS[ $key ] ) ) {
				$expected[ $key ] = $this->helpers->scalarise( $value );
			}
		}

		if ( empty( $expected ) ) {
			return array();
		}

		if ( ! $user instanceof WP_User ) {
			return array_keys( $expected );
		}

		$update = array();

		foreach ( $expected as $key => $value ) {
			if ( (string) $user->{Userswp_Helpers::CORE_MIRRORED_KEYS[ $key ]} !== $value ) {
				$update[ Userswp_Helpers::CORE_MIRRORED_KEYS[ $key ] ] = $value;
			}
		}

		if ( empty( $update ) ) {
			return array();
		}

		// wp_insert_user() unslashes what it is given.
		$result = wp_update_user( array_merge( array( 'ID' => $user->ID ), wp_slash( $update ) ) );

		if ( $result instanceof WP_Error ) {
			return array_keys( $expected );
		}

		$user   = get_userdata( $user->ID );
		$failed = array();

		foreach ( $expected as $key => $value ) {

			$field = Userswp_Helpers::CORE_MIRRORED_KEYS[ $key ];

			if ( ! $user instanceof WP_User || (string) $user->{$field} !== $this->stored_form( $field, $value ) ) {
				$failed[] = $key;
			}
		}

		return $failed;
	}

	/**
	 * A value as WordPress stores it in a user record field: `wp_insert_user()`
	 * passes the slashed value through the field's `pre_user_*` filter, and the
	 * users table write or `update_user_meta()` unslashes the result. A field
	 * WordPress does not filter, `locale`, is stored as given.
	 *
	 * @since 8.1.0
	 *
	 * @param string $field A WP user record field, one of CORE_MIRRORED_KEYS' values.
	 * @param string $value The value written.
	 *
	 * @return string
	 */
	private function stored_form( $field, $value ) {

		$filters = array(
			'first_name'   => 'pre_user_first_name',
			'last_name'    => 'pre_user_last_name',
			'display_name' => 'pre_user_display_name',
			'user_url'     => 'pre_user_url',
			'description'  => 'pre_user_description',
		);

		if ( ! isset( $filters[ $field ] ) ) {
			return $value;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WordPress's own hook, one of the pre_user_* names above.
		return (string) wp_unslash( apply_filters( $filters[ $field ], wp_slash( $value ) ) );
	}

	/**
	 * The mirrored fields of the WP user record, for a restore.
	 *
	 * @param int $user_id
	 *
	 * @return array
	 */
	public function values( $user_id ) {

		$user   = get_userdata( absint( $user_id ) );
		$values = array();

		if ( ! $user instanceof WP_User ) {
			return $values;
		}

		foreach ( Userswp_Helpers::CORE_MIRRORED_KEYS as $field ) {
			$values[ $field ] = (string) $user->{$field};
		}

		return $values;
	}

	/**
	 * Put the mirrored fields of the WP user record back.
	 *
	 * @param int   $user_id
	 * @param array $values From values().
	 *
	 * @return bool
	 */
	public function restore( $user_id, $values ) {

		if ( empty( $values ) ) {
			return true;
		}

		$result = wp_update_user( array_merge( array( 'ID' => absint( $user_id ) ), wp_slash( $values ) ) );

		return ! $result instanceof WP_Error;
	}
}

<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * User_Option_Bridge over get_user_option() / update_user_option().
 *
 * @since 8.0.0
 */
final class WP_User_Option_Bridge implements User_Option_Bridge {

	/**
	 * @param int    $user_id The user.
	 * @param string $key     The option.
	 *
	 * @return mixed
	 */
	public function get( int $user_id, string $key ) {

		return get_user_option( $key, $user_id );
	}

	/**
	 * @param int    $user_id The user.
	 * @param string $key     The option.
	 * @param mixed  $value   The value.
	 *
	 * @return void
	 */
	public function update( int $user_id, string $key, $value ): void {

		update_user_option( $user_id, $key, $value );
	}
}

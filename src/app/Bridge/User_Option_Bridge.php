<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * A user's own setting on this site: WordPress user options, blog-prefixed
 * on multisite so each site keeps its own (src/app/CLAUDE.md Rule 4).
 *
 * @since 8.0.0
 */
interface User_Option_Bridge {

	/**
	 * @param int    $user_id The user.
	 * @param string $key     The option.
	 *
	 * @return mixed The stored value; false when there is none.
	 */
	public function get( int $user_id, string $key );

	/**
	 * @param int    $user_id The user.
	 * @param string $key     The option.
	 * @param mixed  $value   The value.
	 *
	 * @return void
	 */
	public function update( int $user_id, string $key, $value ): void;
}

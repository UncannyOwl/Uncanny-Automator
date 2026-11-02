<?php
/**
 * Whether this site is connected to the hosted Automator MCP app.
 *
 * AI apps connect through mcp.automatorplugin.com. The site counts as
 * connected while an admin's sign-in there stands: the hosted app keeps a
 * read credential on this site, and Hosted_Sign_In_Liveness, the rule the
 * Dashboard uses, says its sign-in is live. The read is local and changes
 * nothing, because the AI menu entry asks on every admin page.
 *
 * @package Uncanny_Automator
 * @since   8.0
 */

namespace Uncanny_Automator;

use Throwable;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge\Hosted_Read_Credential_Store;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge\Hosted_Sign_In_Liveness;

/**
 * Class Ai_Connection_State
 *
 * @package Uncanny_Automator
 */
class Ai_Connection_State {

	/**
	 * Filter that overrides the connected state.
	 *
	 * @var string
	 */
	const FILTER_CONNECTED = 'automator_ai_connected';

	/**
	 * Per-request cache of the filtered state.
	 *
	 * @var bool|null
	 */
	private static $cache = null;

	/**
	 * Whether an AI app is connected, with the storage errors swallowed and
	 * the result filtered and cached for the request.
	 *
	 * @return bool
	 */
	public static function is_connected() {

		if ( null !== self::$cache ) {
			return self::$cache;
		}

		try {
			$connected = self::read();
		} catch ( Throwable $error ) {
			unset( $error );
			$connected = false;
		}

		/**
		 * Whether an AI app is treated as connected to this site.
		 *
		 * @param bool $connected An admin's sign-in to the hosted app stands.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The hook name is a class constant that starts with the automator_ prefix.
		self::$cache = (bool) apply_filters( self::FILTER_CONNECTED, $connected );

		return self::$cache;
	}

	/**
	 * Forgets the cached state so the next call reads storage again.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$cache = null;
	}

	/**
	 * Whether any stored hosted read credential has a live sign-in. Throws on
	 * storage errors.
	 *
	 * Unlike Hosted_Read_Credential_Reaper::live_credentials(), this does not
	 * revoke or delete a dead credential; that cleanup stays with the reads
	 * that use the credentials.
	 *
	 * @return bool
	 */
	public static function read() {

		$liveness = new Hosted_Sign_In_Liveness();

		foreach ( ( new Hosted_Read_Credential_Store() )->readable() as $credential ) {
			if ( $liveness->is_live( (int) $credential['user_id'], (string) $credential['family_id'] ) ) {
				return true;
			}
		}

		return false;
	}
}

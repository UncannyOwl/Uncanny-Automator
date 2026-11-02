<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Whether this site offers more than one account per app: the choice of the
 * account an app's recipes use, when it has accounts and none is chosen. Off
 * unless the filter returns exactly true. Not in 8.0; it ships with multiple
 * connections (Saad, 29 Oct).
 *
 * Off, an app with accounts and none chosen reads as not connected, as it
 * did before the choice existed: its settings page offers Connect, a step
 * that needs the account is told to connect one, and connecting makes the
 * new account the default. The lookup still reports the state as it is
 * ({@see Default_Connection_Lookup::STATE_UNCHOSEN}); every screen, message
 * and handler that offers the choice asks this class first.
 *
 * @since 8.0.0
 */
final class Multiple_Connections_Feature {

	/**
	 * Return true to turn multiple connections on.
	 */
	public const FILTER = 'automator_multiple_app_connections_enabled';

	/**
	 * @return bool
	 */
	public function is_enabled(): bool {

		return true === Dispatcher::filter( self::FILTER, false );
	}
}

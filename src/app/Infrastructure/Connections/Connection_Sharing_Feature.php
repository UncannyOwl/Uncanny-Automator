<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Whether this site offers sharing app connections between the sites on its
 * license: the sharing switch, and a copy's "use the live site's app
 * connections here". Off unless the filter returns exactly true.
 *
 * Off, no site can turn sharing on, so the Automator App Platform offers no
 * account to any other site; a copy can only keep its copied connections
 * switched off. Every screen that shows either control, and every handler
 * behind one, asks this class first.
 *
 * @since 8.0.0
 */
final class Connection_Sharing_Feature {

	/**
	 * Return true to turn sharing on.
	 */
	public const FILTER = 'automator_app_connection_sharing_enabled';

	/**
	 * @return bool
	 */
	public function is_enabled(): bool {

		return true === Dispatcher::filter( self::FILTER, false );
	}
}

<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Auth_Log;

/**
 * Secret-free OAuth lifecycle events.
 */
final class OAuth_Telemetry {

	/**
	 * Record a lifecycle transition without token, code, redirect, or consent data.
	 */
	public static function record( string $event_type, string $outcome, string $client_id = '', int $user_id = 0 ): void {
		Mcp_Auth_Log::record(
			$event_type,
			$outcome,
			array(
				'subject' => $client_id,
				'user_id' => $user_id,
			)
		);

		Dispatcher::action(
			'automator_mcp_oauth_event',
			array(
				'event_type'  => sanitize_key( $event_type ),
				'outcome'     => sanitize_key( $outcome ),
				'client_hash' => '' === $client_id ? '' : hash( 'sha256', $client_id ),
				'user_id'     => max( 0, $user_id ),
				'timestamp'   => time(),
			)
		);
	}
}

<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Error_Exception;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Not_Found_Exception;

/**
 * Sends a request made with the site's connection — once more when connection
 * sync repaired the row in the meantime.
 *
 * When the platform answers that the stored row and the platform disagree
 * (contract 4.3: CONNECTION_NOT_FOUND, CONNECTION_SIGNATURE_INVALID,
 * CONNECTION_INACTIVE), the client has already named the row to connection
 * sync — `automator_platform_connection_out_of_sync`, fired inside
 * Client::send() — before the answer reaches here. Sync may have repaired the
 * row on the spot: a fresh signature from the platform's list, or another
 * connection made the default. Then the step is not lost — the request goes
 * out once more with the envelope the site holds now, under the same
 * Idempotency-Key: the refused request was never admitted, so the key is still
 * free (contract 4.7). When the row is as it was, or there is no connection to
 * send with any more, the first answer stands and tells the customer what
 * happened. The second answer stands whatever it is: there is no third send.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App_Integrations\Automator_Platform\Caller}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Connection_Repair_Retry {

	/**
	 * @param array<string, mixed>   $params   The request parameters; the body carries the envelope that was sent.
	 * @param callable(array): array $send     Sends the parameters and returns the response array.
	 * @param callable(): array      $envelope Reads the connection envelope the site holds now. Read fresh on every
	 *                                         call: the stored row can change under a request.
	 *
	 * @return array<string, mixed> The response array.
	 * @throws \Exception The platform's answer, or a transport failure.
	 */
	public function send( array $params, callable $send, callable $envelope ): array {

		try {
			return $send( $params );
		} catch ( Platform_Error_Exception $e ) {
			$repaired = $this->repaired_envelope( $e, (array) $params['body'], $envelope );
			if ( null === $repaired ) {
				throw $e;
			}

			$params['body'] = array_merge( $params['body'], $repaired );

			return $send( $params );
		}
	}

	/**
	 * The envelope to send once more: the one the site holds now, when the
	 * answer names the stored row and connection sync has changed it since.
	 * Null when the answer is about something else, the row is as it was, or
	 * there is no connection to send with any more (the first answer then
	 * stands, and its message looks the connection up itself).
	 *
	 * @param Platform_Error_Exception $e        The answer.
	 * @param array<string, mixed>     $sent     The body that was sent.
	 * @param callable(): array        $envelope Reads the envelope the site holds now.
	 *
	 * @return array<string, mixed>|null
	 */
	private function repaired_envelope( Platform_Error_Exception $e, array $sent, callable $envelope ): ?array {

		if ( ! $e->failure()->is_connection_out_of_sync() ) {
			return null;
		}

		try {
			$held = $envelope();
		} catch ( Connection_Not_Found_Exception $gone ) {
			return null;
		}

		return array_intersect_key( $sent, $held ) === $held ? null : $held;
	}
}

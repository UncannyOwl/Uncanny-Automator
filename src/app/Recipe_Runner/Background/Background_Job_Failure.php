<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Background;

use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Response;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Rejection_Observer;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_License_Refusal_Observer;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Error_Handler;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Registry;

use function Uncanny_Automator\App\Infrastructure\automator_connection_error_handler;
use function Uncanny_Automator\App\Infrastructure\automator_connection_registry;

/**
 * A platform job that failed, read as the same answer is read when the
 * platform gives it at once. First what the answer does to this site:
 *
 *  - to the connection the job's write was sent with
 *    ({@see Connection_Error_Handler}): a revoked account is marked, missing
 *    scopes are recorded, an out-of-sync connection goes to connection sync;
 *  - to this site's registration ({@see Site_Credentials_Rejection_Observer}):
 *    a rejection of its credentials asks for the re-verification the
 *    customer's sentence promises;
 *  - to this site's license ({@see Site_License_Refusal_Observer}): the
 *    license gate's refusal is remembered, so App connections tells the
 *    license first.
 *
 * Then the customer is told by the one message owner
 * ({@see Platform_Failure_Message::for_integration()}), whose sentence for an
 * out-of-sync connection reads what connection sync left.
 *
 * Applying the answer runs other code (connection sync listens on the
 * out-of-sync hook). Whatever it throws is logged and never stops the
 * customer being told, so the job is always finished, never left claimed.
 * A job that recorded no connection (a write sent without one) skips the
 * connection; a lost job is not a platform answer and keeps its own error.
 *
 * @since 8.0.0
 */
final class Background_Job_Failure {

	/**
	 * @var Connection_Error_Handler|null
	 */
	private $handler;

	/**
	 * @var Site_Credentials_Rejection_Observer
	 */
	private $credentials;

	/**
	 * @var Site_License_Refusal_Observer
	 */
	private $license;

	/**
	 * @var Connection_Registry|null
	 */
	private $registry;

	/**
	 * @var Integration_Registry_Bridge
	 */
	private $integrations;

	/**
	 * @param Connection_Error_Handler|null            $handler      Applies an answer to a stored connection.
	 * @param Site_Credentials_Rejection_Observer|null $credentials  Hears a rejection of this site's credentials.
	 * @param Connection_Registry|null                 $registry     Reads the app's default connection after it.
	 * @param Integration_Registry_Bridge|null         $integrations Names the app.
	 * @param Site_License_Refusal_Observer|null       $license      Hears a refusal of this site's license.
	 */
	public function __construct(
		?Connection_Error_Handler $handler = null,
		?Site_Credentials_Rejection_Observer $credentials = null,
		?Connection_Registry $registry = null,
		?Integration_Registry_Bridge $integrations = null,
		?Site_License_Refusal_Observer $license = null
	) {
		$this->handler      = $handler ?? automator_connection_error_handler();
		$this->credentials  = $credentials ?? new Site_Credentials_Rejection_Observer();
		$this->registry     = $registry ?? automator_connection_registry();
		$this->integrations = $integrations ?? new Automator_Integration_Registry_Bridge();
		$this->license      = $license ?? new Site_License_Refusal_Observer();
	}

	/**
	 * What to tell the customer about a failed job, after its answer was applied
	 * to the connection it was sent with, to this site's registration and to
	 * its license.
	 *
	 * @param array<string, mixed> $envelope      The job's failed result: statusCode and error.
	 * @param string               $connection_id The connection the job's write was sent with; '' when none was recorded.
	 * @param string               $integration   The action's integration code.
	 *
	 * @return Platform_Failure_Message
	 */
	public function message( array $envelope, string $connection_id, string $integration ): Platform_Failure_Message {

		$error    = is_array( $envelope['error'] ?? null ) ? $envelope['error'] : array();
		$response = new Response( (int) ( $envelope['statusCode'] ?? $error['code'] ?? 0 ), array(), $error );

		$this->apply( $response, $connection_id );

		return Platform_Failure_Message::for_integration( Platform_Failure::of( $response ), $integration, $this->integrations->get_integration_name_by_code( $integration ), $this->registry );
	}

	/**
	 * What the answer does to this site, as a synchronous answer's observers
	 * apply it.
	 *
	 * @param Response $response      The job's answer.
	 * @param string   $connection_id The connection it was sent with; '' when none.
	 *
	 * @return void
	 */
	private function apply( Response $response, string $connection_id ): void {

		if ( '' !== $connection_id && null !== $this->handler ) {
			$this->guarded(
				$response,
				function () use ( $response, $connection_id ): void {
					$this->handler->handle( $response, $connection_id );
				}
			);
		}

		$this->guarded(
			$response,
			function () use ( $response ): void {
				$this->credentials->hear_site_signed_answer( $response );
			}
		);

		$this->guarded(
			$response,
			function () use ( $response ): void {
				$this->license->hear_answer( $response );
			}
		);
	}

	/**
	 * Run one effect of the answer; what it throws is logged, so the next
	 * effect and the customer's sentence still follow.
	 *
	 * @param Response $response The job's answer, for the log.
	 * @param callable $effect   The effect.
	 *
	 * @return void
	 */
	private function guarded( Response $response, callable $effect ): void {

		try {
			$effect();
		} catch ( \Throwable $e ) {
			automator_log( 'Applying a failed background job\'s answer (' . $response->status_code() . ' ' . $response->error_type() . ') threw: ' . $e->getMessage() . ' The job is still finished as failed.', 'Background_Job_Failure', false );
		}
	}
}

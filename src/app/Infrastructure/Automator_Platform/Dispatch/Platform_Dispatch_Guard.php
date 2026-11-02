<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;

use function Uncanny_Automator\App\Infrastructure\automator_platform_site_registration;

/**
 * The two cases where a request to the platform is refused here, before it is
 * sent: while the site's registration cannot be trusted to sign it, and when a
 * local site asks to be sent webhooks. Both fail the step with the one sentence
 * that says why — no connection lookup, no request, no credit.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App_Integrations\Automator_Platform\Caller}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Platform_Dispatch_Guard {

	/**
	 * The endpoint a site subscribes to the platform's webhooks with.
	 */
	private const WEBHOOK_SUBSCRIPTION_ENDPOINT = 'register-site-for-webhooks';

	/**
	 * @var callable(): ?Registration_Status
	 */
	private $status;

	/**
	 * @var Local_Site_Status
	 */
	private $local;

	/**
	 * @param (callable(): ?Registration_Status)|null $status Where registration stands; the site's registration when none is given.
	 * @param Local_Site_Status|null                  $local  Whether the platform registered this site as local.
	 */
	public function __construct( ?callable $status = null, ?Local_Site_Status $local = null ) {

		$this->status = $status ?? static function (): ?Registration_Status {
			$registration = automator_platform_site_registration();

			return null === $registration ? null : $registration->status();
		};
		$this->local  = $local ?? new Local_Site_Status();
	}

	/**
	 * Handoff R6 — "paused" means paused. While the site-address question is
	 * open, or the admin's answer — a copy, a move — is being registered, the
	 * secret on file may not be this site's to sign with (a copy holds its
	 * source's), so a /v3 request is refused here: it fails with the one
	 * sentence that says why, before any connection lookup and without reaching
	 * the platform — no connection error, no "reconnect", no credit. The
	 * platform's deliveries to this site are not held; the receiver is
	 * untouched. The one owner of "dispatch is held" is {@see Registration_Status::holds_dispatch()},
	 * the answer the registration notice reads too.
	 *
	 * The refused step is not queued: it fails, as the handoff specifies, and
	 * nothing runs it again. The recipe's next run goes out once the answer has
	 * landed.
	 *
	 * @return void
	 * @throws Action_Error_Exception While dispatch is held.
	 */
	public function hold_while_the_site_address_is_answered(): void {

		$status = ( $this->status )();
		if ( null === $status || ! $status->holds_dispatch() ) {
			return;
		}

		throw Platform_Failure_Message::for_registration( $status )->to_exception(); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Platform_Failure_Message escapes its sentences as it builds them.
	}

	/**
	 * L247 — nothing is ever delivered to a LOCAL site ({@see Local_Site_Status}),
	 * so it never asks the platform to subscribe it: `register-site-for-webhooks`
	 * is answered here, before any request, as the platform would answer it
	 * (`409 SITE_IS_LOCAL`) — final, with the one sentence that explains it. Every
	 * integration's subscription goes through the shared caller, so none can send it.
	 *
	 * @param string $endpoint The request's endpoint path.
	 *
	 * @return void
	 * @throws Action_Error_Exception When a local site would ask for webhooks.
	 */
	public function refuse_webhooks_on_local_site( string $endpoint ): void {

		if ( self::WEBHOOK_SUBSCRIPTION_ENDPOINT !== basename( rtrim( $endpoint, '/' ) ) ) {
			return;
		}

		if ( ! $this->local->is_local() ) {
			return;
		}

		throw Platform_Failure_Message::local_site()->to_exception( 409 );
	}
}

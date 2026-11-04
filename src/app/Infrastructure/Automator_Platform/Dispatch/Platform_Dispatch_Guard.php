<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Infrastructure\License\License_Provider_Interface;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;
use function Uncanny_Automator\App\Infrastructure\automator_platform_site_registration;

/**
 * The three cases where a request to the platform is refused here, before it
 * is sent: while the site's registration cannot be trusted to sign it, when a
 * local site asks to be sent webhooks, and when the site has no license key to
 * send. Each fails the step with the one sentence that says why — no
 * connection lookup, no request, no credit.
 *
 * @internal Collaborator of the platform's send paths; not part of the plugin's API.
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
	 * The license whose key the request would carry.
	 *
	 * @var License_Provider_Interface
	 */
	private $license;

	/**
	 * Whether Pro is active, which picks the sentence a refusal for the license
	 * gives; null to ask the site at refusal time.
	 *
	 * @var bool|null
	 */
	private $pro_active;

	/**
	 * @param (callable(): ?Registration_Status)|null $status     Where registration stands; the site's registration when none is given.
	 * @param Local_Site_Status|null                  $local      Whether the platform registered this site as local.
	 * @param License_Provider_Interface|null         $license    The license whose key the request would carry; the site's license when none is given.
	 * @param bool|null                               $pro_active Whether Pro is active; the site's answer when none is given.
	 */
	public function __construct( ?callable $status = null, ?Local_Site_Status $local = null, ?License_Provider_Interface $license = null, ?bool $pro_active = null ) {

		$this->status     = $status ?? static function (): ?Registration_Status {
			$registration = automator_platform_site_registration();

			return null === $registration ? null : $registration->status();
		};
		$this->local      = $local ?? new Local_Site_Status();
		$this->license    = $license ?? automator_license_manager() ?? new License_Manager();
		$this->pro_active = $pro_active;
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

	/**
	 * A request to the platform carries the site's license key in its
	 * `license-key` header, and the key is there only while the site's license
	 * is active ({@see License_Manager::get_key()}). With no key the request
	 * can only be refused: production's edge blocks it with a 403 of its own,
	 * and the platform behind it answers 401 Missing license headers. So
	 * nothing is sent; the request fails here with the sentence that says which
	 * account to link or which license to activate, and with the edge's 403, a
	 * final answer no retry changes until the license does.
	 *
	 * A key whose license the platform no longer accepts is still sent: that
	 * verdict is the platform's, told in its own words.
	 *
	 * @since 8.0.1
	 *
	 * @return void
	 * @throws Refused_Before_Sending_Exception When the site has no license key to send.
	 */
	public function refuse_without_a_license_key(): void {

		if ( '' !== $this->license->get_key() ) {
			return;
		}

		$message = Platform_Failure_Message::license_needed_to_connect( $this->pro_active );

		throw new Refused_Before_Sending_Exception( $message->text(), $message->error_code(), 403 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Platform_Failure_Message escapes its sentences as it builds them.
	}
}

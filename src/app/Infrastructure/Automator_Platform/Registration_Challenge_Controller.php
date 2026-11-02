<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * C1 dedicated pre-registration endpoint (site identity spec, ClickUp 868jw791t).
 *
 * During a first registration, or a recovery of a site whose secret the platform
 * no longer accepts (wire contract W2), the platform issues a single-use
 * challenge to this route. It answers by signing the challenge with the LOCAL
 * attempt secret — but ONLY for this install's own in-flight attempt. An
 * attacker who registered a victim's site-name gave the platform their OWN
 * attempt id, which is not present on the victim's install, so this endpoint
 * returns 404 and the attacker's registration can never be proven.
 *
 * The route is public: there is no signing secret the platform accepts yet, so
 * there is nothing to authenticate with. Safety comes from the attempt id being
 * an unguessable local secret and from domain separation — the signature
 * produced here is a CHALLENGE proof and can never be replayed as the
 * COMPLETION proof that redeems the secret (see {@see Registration_Challenge}).
 *
 * It answers on whichever host the request arrives on, and must keep doing so.
 * A move without the old secret (move contract §10) is also challenged at the
 * OLD address: when that address now points at this install, the challenge
 * arrives under the old host, and this install's own attempt answering it is
 * how the platform learns the old address serves this site. Another install
 * still live there answers `unknown_attempt` — the platform's SITE_STILL_LIVE.
 *
 * @since 8.0.0
 */
final class Registration_Challenge_Controller {

	const REST_NAMESPACE = 'automator/platform/v1';
	const ROUTE          = '/register-challenge';

	/**
	 * @var Registration_Challenge
	 */
	private $challenge;

	/**
	 * @param Registration_Challenge|null $challenge Attempt store + signer.
	 */
	public function __construct( ?Registration_Challenge $challenge = null ) {

		$this->challenge = $challenge ?? new Registration_Challenge();
	}

	/**
	 * Register the public POST route on `rest_api_init`.
	 *
	 * @return void
	 */
	public function register_routes(): void {

		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sign' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Sign the platform's challenge with this install's attempt secret.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function sign( WP_REST_Request $request ): WP_REST_Response {

		$attempt_id = (string) $request->get_param( 'attempt_id' );
		$challenge  = (string) $request->get_param( 'challenge' );

		$pending = $this->challenge->pending_attempt();

		// Sign ONLY for this install's own pending attempt. A challenge for any
		// other attempt id (an attacker's registration of this host) cannot be
		// answered here.
		if ( null === $pending || $pending['attempt_id'] !== $attempt_id || '' === $challenge ) {
			return new WP_REST_Response( array( 'error' => 'unknown_attempt' ), 404 );
		}

		return new WP_REST_Response(
			array(
				'signature' => $this->challenge->sign_challenge( $pending['attempt_secret'], $attempt_id, $challenge ),
			),
			200
		);
	}
}

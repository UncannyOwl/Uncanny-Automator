<?php

namespace Uncanny_Automator\Settings;

use Exception;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch\Platform_Dispatch_Guard;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Response;
use Uncanny_Automator\App\Infrastructure\Connections\Connection;
use Uncanny_Automator\App\Presentation\Admin\Default_Connection_Chooser;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;

/**
 * Trait OAuth_App_Integration_V3 — MVP plan A.8.
 *
 * V3-aware OAuth flow for integrations whose api_endpoint targets the
 * Laravel `/app/v1/*` surface (Drive + Dropbox in MVP). Replaces the
 * three legacy-Slim methods on `OAuth_App_Integration`:
 *
 *  - `handle_oauth_init()` — server-side `GET /app/v1/oauth/{slug}/start`
 *    with license headers + return_url + state nonce; reads the platform's
 *    302 `Location:` and hands it to the browser. Keeps `license_key` out
 *    of the URL bar (would otherwise have to ride as query string for
 *    browser-direct navigation, since `V3LicenseAuth` middleware reads
 *    headers only).
 *
 *  - `process_oauth_authentication()` — parses the new callback redirect
 *    shape per Contract B / `OAuthController::callback`:
 *    `?connection_id=X&connection_signature=Y&identity_id=I&state=Z&ts=N&_sig=H`.
 *    Verifies the state nonce (CSRF defense), then INSERTs a
 *    `wp_uap_connections` row via `Connection_Registry` carrying both
 *    `connection_id` + `identity_id` directly off the redirect query —
 *    no follow-up roundtrip needed (P3.1-bis ships `identity_id` inline
 *    on the redirect).
 *
 *  - `validate_vault_signature()` — neutered. The legacy `vault_signature`
 *    concept doesn't exist on /v3; transport security comes from the
 *    static `connection_signature` per Contract G + the redirect's `_sig`.
 *
 * Drive + Dropbox settings opt in via `use OAuth_App_Integration_V3`
 * INSTEAD OF `use OAuth_App_Integration`.
 *
 * **Deferred from MVP — `_sig` HMAC verification.** The platform
 * canonical (`ReturnUrlSigner::payload()`) is
 * `v1:{connection_id}:{site_id}:{license_id}:{plugin_state}:{identity_id}:{ts}`.
 * Two of those bindings are values the plugin does NOT hold today:
 * `license_id`, and the platform's authoritative `site_id` — the latter
 * deliberately left unset by the site-registration flow (registerSite()
 * never returns one; `automator_platform_site_id` stays empty), and the
 * plugin's own site UUID is a local clone/drift marker the platform
 * ignores, not this `site_id`. So enabling `_sig` is NOT a plugin
 * one-liner: it needs a coordinated platform change to return BOTH
 * `license_id` and `site_id` in the `/sites/register` response, after
 * which the plugin can reconstruct the canonical and verify.
 *
 * Until then, trust comes from the state-token (per-user transient
 * prevents CSRF/replay across sessions) + downstream V3ConnectionAuth
 * (every dispatch verifies `connection_id` and `connection_signature`
 * against the platform's store, so a tampered redirect is refused on its
 * first dispatch with `CONNECTION_NOT_FOUND` or
 * `CONNECTION_SIGNATURE_INVALID`, which leaves the row as it is and asks
 * connection sync to settle it against the platform's list — see
 * `Connection_Error_Handler`). The callback itself is also capability-gated
 * before any row is written.
 *
 * @package Uncanny_Automator\Settings
 *
 * @since 8.0.0
 */
trait OAuth_App_Integration_V3 {

	/**
	 * State-nonce TTL (seconds). Matches the platform's 10-minute OAuth
	 * state window — anything longer would make the platform's
	 * `OAuthStateStore` reject a valid callback.
	 */
	protected $oauth_v3_state_ttl_seconds = 600;

	/**
	 * Build the platform OAuth start URL by issuing a server-side GET to
	 * `/app/v1/oauth/{slug}/start` with license headers, capturing the
	 * 302 `Location:`, and handing it to the browser as `redirect_url`.
	 *
	 * Server-side because `V3LicenseAuth` middleware reads license-key /
	 * site-name / item-name from request headers; a browser-direct
	 * `window.location.href` cannot attach those headers. Two-hop pattern:
	 * plugin → platform `/start` (server) → browser → 3rd party OAuth
	 * provider. License_key never appears in the URL bar.
	 *
	 * @since 8.0.0 A.8.
	 *
	 * @param array $data    Posted data from the REST request (unused).
	 * @param mixed $manager Action_Manager (unused — kept for trait-shape parity).
	 *
	 * @return array { success: bool, redirect_url: string }
	 *
	 * @throws Exception When the site is not registered, has no active
	 *                   license, the platform call fails, or the platform
	 *                   returns a non-302 (told from its error type).
	 */
	protected function handle_oauth_init( $data, $manager ) {

		unset( $data, $manager );

		// The start is signed with this site's registration secret. No secret,
		// no connection — see require_v3_site_registration().
		$this->require_v3_site_registration();

		// A registered site keeps its secret when its license lapses or is
		// deactivated; the platform would only refuse the start for the license.
		// The rule the app calls are refused by, so no app is connected that
		// could never run: the site needs a license key to send.
		try {
			( new Platform_Dispatch_Guard() )->refuse_without_a_license_key();
		} catch ( Action_Error_Exception $refused ) {
			throw new Exception( wp_kses( $refused->getMessage(), array() ) );
		}

		$slug         = $this->get_v3_oauth_slug();
		$callback_url = $this->build_v3_callback_url();
		$state        = $this->mint_v3_state_nonce( $slug );

		$start_url = sprintf(
			'%s%s/oauth/%s/start',
			rtrim( AUTOMATOR_API_URL, '/' ),
			AUTOMATOR_PLATFORM_API_PATH,
			rawurlencode( $slug )
		);

		// Build the start URL with explicit RFC 3986 encoding. add_query_arg
		// leaves nested `?` + `&` inside the return_url value unescaped,
		// which causes the platform's query parser to truncate
		// `return_url` at the first inner `&` — the Dropbox settings URL
		// loses its `page=`/`tab=`/`integration=` params and the post-OAuth
		// redirect lands on the bare recipes list instead of the
		// integration's connected-state panel.
		$full_url = $start_url . '?' . http_build_query(
			array(
				'return_url' => $callback_url,
				'state'      => $state,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);

		$response = wp_remote_get(
			$full_url,
			array(
				'headers'     => array(
					'license-key' => Api_Server::get_license_key(),
					'site-name'   => Api_Server::get_site_name(),
					'item-name'   => Api_Server::get_item_name(),
				),
				'redirection' => 0,
				'timeout'     => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: error message */
					esc_html_x( 'Could not reach the Automator App Platform: %s', 'OAuth init', 'uncanny-automator' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$status   = (int) wp_remote_retrieve_response_code( $response );
		$location = (string) wp_remote_retrieve_header( $response, 'location' );

		if ( 302 !== $status || '' === $location ) {
			// A refusal is told by the one owner of platform-failure sentences,
			// from the answer's type, as it is everywhere else.
			$failure = Platform_Failure::from_response( Response::from_wp_response( $response, 0.0 ) );
			if ( null !== $failure ) {
				throw new Exception( wp_kses( Platform_Failure_Message::for_failure( $failure, (string) $this->get_name() )->text(), array() ) );
			}

			throw new Exception(
				sprintf(
					/* translators: %d: HTTP status code */
					esc_html_x( 'Unexpected response from the Automator App Platform (status %d).', 'OAuth init', 'uncanny-automator' ),
					absint( $status )
				)
			);
		}

		return array(
			'success'      => true,
			'redirect_url' => $location,
		);
	}

	/**
	 * Process the platform OAuth callback redirect.
	 *
	 * Reads `connection_id`, `connection_signature`, `identity_id`, `state`
	 * from query; verifies the state-nonce against the per-user transient
	 * minted at `handle_oauth_init` time; INSERTs a `wp_uap_connections`
	 * row via `Connection_Registry` with `platform_version = 'v3'`. All
	 * fields land directly off the callback redirect — no follow-up
	 * roundtrip per platform P3.1-bis.
	 *
	 * @since 8.0.0 A.8.
	 *
	 * @param mixed $manager Action_Manager (unused — kept for trait-shape parity).
	 *
	 * @return array { success: bool, redirect_url: string }
	 *
	 * @throws Exception On state-nonce mismatch or registry write failure.
	 */
	public function process_oauth_authentication( $manager ) {

		unset( $manager );

		// Provider error params win over the happy-path decode (same UX
		// rationale as the legacy trait — "user cancelled OAuth consent"
		// shouldn't dump the user on a WP 500 page).
		$error_params = array( 'error', 'connect', 'auth_error' );
		foreach ( $error_params as $param ) {
			if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$error_code    = sanitize_text_field( wp_unslash( $_GET[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$error_detail  = isset( $_GET['error_message'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					? sanitize_text_field( wp_unslash( $_GET['error_message'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					: '';
				$error_message = ( '' !== $error_detail && $error_detail !== $error_code )
					? sprintf( '%s: %s', $error_code, $error_detail )
					: $error_code;
				$this->register_oauth_error_alert( $error_message );

				return array(
					'success'      => false,
					'redirect_url' => $this->get_settings_page_url(),
				);
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$connection_id        = isset( $_GET['connection_id'] )
			? sanitize_text_field( wp_unslash( $_GET['connection_id'] ) )
			: '';
		$connection_signature = isset( $_GET['connection_signature'] )
			? sanitize_text_field( wp_unslash( $_GET['connection_signature'] ) )
			: '';
		$identity_id          = isset( $_GET['identity_id'] )
			? sanitize_text_field( wp_unslash( $_GET['identity_id'] ) )
			: '';
		$state                = isset( $_GET['state'] )
			? sanitize_text_field( wp_unslash( $_GET['state'] ) )
			: '';
		// phpcs:enable

		if ( '' === $connection_id || '' === $connection_signature || '' === $state ) {
			$message = esc_html_x( 'Incomplete connection response from the Automator App Platform.', 'OAuth callback', 'uncanny-automator' );
			$this->register_oauth_error_alert( $message );
			throw new Exception( esc_html( $message ) );
		}

		$slug = $this->get_v3_oauth_slug();
		if ( ! $this->verify_v3_state_nonce( $slug, $state ) ) {
			$message = esc_html_x( 'OAuth state could not be verified. Please try connecting again.', 'OAuth callback', 'uncanny-automator' );
			$this->register_oauth_error_alert( $message );
			throw new Exception( esc_html( $message ) );
		}

		$registry = \Uncanny_Automator\App\Infrastructure\automator_connection_registry();
		$row_data = array(
			'connection_id'        => $connection_id,
			'connection_signature' => $connection_signature,
			'integration'          => $slug,
			'platform_version'     => Connection::PLATFORM_VERSION_V3,
			'is_default'           => true,
		);
		if ( '' !== $identity_id ) {
			$row_data['identity_id'] = $identity_id;

			// A row for this identity can still be on file: one the reconnect
			// leg does not disconnect (it only takes the ACTIVE default — a
			// row marked requires_reauth / revoked / expired stays), or a
			// plain Connect while one exists. store() is insert-only and
			// returns 0 on the (integration, identity_id) UNIQUE collision,
			// which would silently keep the STALE connection_id/signature
			// while the UI reports success. Replace the stale row explicitly;
			// the platform reactivates the same connection for the same
			// identity.
			$existing = $registry->get_by_identity( $slug, $identity_id );
			if ( null !== $existing ) {
				$registry->delete( $existing->id() );
			}
		}
		$registry->store( $row_data );

		// Display CII (name/email/avatar_url) lands via
		// `get_account_info_from_vault_cached()` on first settings render —
		// cached for 7 days per G.8. `external_user_id` is integration-
		// specific correlation; the plugin reads it from the cached
		// account-info envelope when needed.

		$this->register_oauth_success_alert();

		return array(
			'success'      => true,
			'redirect_url' => $this->get_settings_page_url(),
		);
	}

	/**
	 * Override — vault_signature is a Slim-era concept; on /v3 the
	 * connection_signature itself is the trust token (verified by the
	 * platform's V3ConnectionAuth on every dispatch). No-op here so the
	 * default OAuth_App_Integration's required-field check doesn't fire.
	 *
	 * @since 8.0.0 A.8.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return void
	 */
	protected function validate_vault_signature( array $credentials ) {

		unset( $credentials );
		// Intentionally empty. See class docblock.
	}

	// ── helpers ──

	/**
	 * Drive site registration to completion before an OAuth start, and refuse
	 * to send one this site cannot sign.
	 *
	 * The connect button is a REST action, and the `init@20` entry point
	 * (`Platform_Site_Registration::register_on_request()`) deliberately does
	 * nothing on a REST request — a visitor must never pay for a blocking
	 * platform round trip. So on a fresh site the sequence is: an admin page
	 * load registers and gets a 202, the platform proves the challenge, the
	 * admin clicks Connect, and nothing in that REST request advances the
	 * handshake. The signing secret is still missing,
	 * `Platform_Request_Signature_Injector` leaves the start unsigned, and the
	 * platform — which enforces the site signature — answers 401. Clicking
	 * again repeats it forever.
	 *
	 * This is a deliberate admin action, like the license-activation hook and
	 * the site-address drift notice, so it registers directly — `confirm()`,
	 * which also polls a 202's completion for about 30 seconds (handoff R2) —
	 * rather than waiting for the next page load, and reports a state still
	 * pending as retryable instead of sending an unsigned request.
	 *
	 * A copy that has not registered yet still holds the secret it inherited
	 * from its source site. That secret is not this site's to sign with, so a
	 * pending copy is treated the same as no secret at all.
	 *
	 * Likewise while the platform is confirming this site (a 202 left a
	 * challenge in flight): that is a first registration, or a recovery of a
	 * site whose secret the platform no longer accepts and is about to
	 * replace — so a secret on file is no reason to start, and the pending
	 * state is reported as retryable.
	 *
	 * Only a settled registration starts a connection, and anything else is
	 * told from where the registration stands (handoff R1,
	 * `Platform_Site_Registration::status()`): the license is blamed only when
	 * no license is active.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 *
	 * @throws Exception When this site's registration is not settled.
	 */
	private function require_v3_site_registration(): void {

		$registration = \Uncanny_Automator\App\Infrastructure\automator_platform_site_registration();
		if ( null === $registration ) {
			throw new Exception(
				wp_kses( \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message::for_registration( \Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status::of( \Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status::NOT_REGISTERED ) )->text(), array() )
			);
		}

		// An admin action (handoff R2): a 202 is completed within this request
		// on the attempt's ladder, not left to the next admin page load.
		$registration->confirm();

		// Handoff R1 — where the registration stands, from its one owner, told
		// as it is: confirming, a copy or a move in progress, refused with the
		// platform's reason, unreachable, retrying — the license only when no
		// license is active. Never "reconnect": the app is not the problem.
		$status = $registration->status();
		if ( $status->is_registered() ) {
			return;
		}

		throw new Exception(
			wp_kses( \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message::for_registration( $status )->text(), array() )
		);
	}


	/**
	 * Render, in the disconnected panel, the choice of the account recipes
	 * use, when the app has accounts and none is chosen (Saad, 29 Oct).
	 *
	 * @since 8.0.0
	 *
	 * @return bool Whether the choice was rendered, in place of the connect prompt.
	 */
	protected function output_default_connection_chooser(): bool {

		return ( new Default_Connection_Chooser() )->render( $this->get_v3_oauth_slug(), (string) $this->get_name() );
	}

	/**
	 * Whether the app has accounts and none is chosen: Connect gives way to
	 * the choice.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	protected function is_default_connection_unchosen(): bool {

		return ( new Default_Connection_Chooser() )->is_needed( $this->get_v3_oauth_slug() );
	}

	/**
	 * Resolve the integration's lower-case slug as the platform expects
	 * it (`/v3/dropbox` → `dropbox`). Single source of truth so the URL
	 * builder + registry write + transient key all agree.
	 *
	 * @return string
	 */
	private function get_v3_oauth_slug(): string {

		$endpoint = (string) $this->helpers->get_api_endpoint();
		// Strip any leading `vN/` prefix.
		$slug = preg_replace( '#^v\d+/#', '', $endpoint );

		return strtolower( (string) $slug );
	}

	/**
	 * Build the WP-Admin OAuth callback URL the platform should redirect
	 * back to after the 3rd-party OAuth dance completes.
	 *
	 * @return string
	 */
	private function build_v3_callback_url(): string {

		return $this->get_settings_page_url(
			array(
				'automator_oauth_callback' => '1',
			)
		);
	}

	/**
	 * Mint a fresh state nonce + persist to a per-user transient so the
	 * callback can verify it. Per-user binding closes the cross-account
	 * replay vector (a leaked state token is only valid for the user
	 * who initiated the connect).
	 */
	private function mint_v3_state_nonce( string $slug ): string {

		$state = bin2hex( random_bytes( 16 ) );
		set_transient(
			$this->v3_state_transient_key( $slug ),
			$state,
			$this->oauth_v3_state_ttl_seconds
		);

		return $state;
	}

	/**
	 * Constant-time compare the callback's state against the per-user
	 * transient. Single-use — clears the transient on success so a leaked
	 * URL can't be replayed.
	 */
	private function verify_v3_state_nonce( string $slug, string $supplied ): bool {

		$key      = $this->v3_state_transient_key( $slug );
		$expected = (string) get_transient( $key );

		if ( '' === $expected ) {
			return false;
		}

		$ok = hash_equals( $expected, $supplied );
		if ( $ok ) {
			delete_transient( $key );
		}

		return $ok;
	}
	/**
	 * V3 state transient key.
	 *
	 * @param string $slug The slug.
	 * @return string
	 */
	private function v3_state_transient_key( string $slug ): string {

		return sprintf( 'automator_oauth_v3_state_%s_%d', $slug, get_current_user_id() );
	}
}

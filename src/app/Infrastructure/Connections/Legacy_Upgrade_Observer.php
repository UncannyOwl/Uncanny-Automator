<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Request;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Response;

/**
 * Observer for the platform's Contract H §"v3 promotion envelope"
 * — MVP plan G.7. Read-side handler for P16 Path B (scope-unchanged
 * /v2 → /v3 promotion).
 *
 * Trigger: a /v3/{integration} request was made with a `legacy_*`
 * connection_id in the body (i.e. an integration whose `api_endpoint`
 * was just flipped from `v2/...` to `v3/...` in a plugin release —
 * the registry still holds a `platform_version='v2'` row from the G.3
 * one-shot migration). When `IntegrationConfig.v3_reauth_required = false`
 * AND the legacy credentials' scopes are sufficient for /v3, the
 * platform performs an in-place upgrade and returns the new
 * SiteConnection material in the response body envelope:
 *
 *   data.upgraded_connection_id        — fresh `cnx_<ULID>`
 *   data.upgraded_connection_signature — fresh `<env_ver>.<hex_hmac>`
 *   data.upgraded_identity_id          — fresh `<UUID>`
 *   data.platform_version              — `'v3'`
 *
 * On detection the observer:
 *
 * 1. Finds the local `wp_uap_connections` row by the request body's
 *    outgoing `connection_id` (the legacy_* one we just sent).
 * 2. UPDATEs in place via `Connection_Registry::upgrade_legacy_to_v3()`
 *    — preserves the row's `id`, `is_default`, `metadata`, etc. so
 *    any future multi-connection-pinned recipes don't dangle.
 * 3. Wipes the integration's pre-promotion credentials option
 *    (`automator_{slug}_credentials`) — credentials now live
 *    exclusively in the platform vault (same wipe behavior as the
 *    Contract H lift signal in C.1).
 *
 * STATUS — spec-only on the platform side in MVP per the plan's
 * "v3 promotion envelope — SPEC-ONLY IN MVP" carve-out. Drive +
 * Dropbox are greenfield /v3 integrations; there are no /v2 → /v3
 * promotions in this rollout. This observer ships symmetric to the
 * lift-signal observer (C.1) so the read-side is structurally
 * complete and ready when the first integration actually flips.
 *
 * Wired as a passive observer on `automator_platform_response`,
 * peer to `Connection_Error_Handler` and `Credential_Lift_Observer`.
 *
 * @since 8.0.0
 */
final class Legacy_Upgrade_Observer {

	/**
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * @param Connection_Registry|null $registry Local uap_connections store; defaults to a fresh instance.
	 */
	public function __construct( ?Connection_Registry $registry = null ) {

		$this->registry = $registry ?? new Connection_Registry();
	}

	/**
	 * Attach the observer to `automator_platform_response`. Called once
	 * from `Application_Bootstrap`. Idempotent — WP's `add_action` with
	 * the same callback + priority is silently deduplicated.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action( 'automator_platform_response', array( $this, 'observe' ), 10, 2 );
	}

	/**
	 * Observer entry point. Defensive type-checks before delegating
	 * to `handle()` — third-party code may emit
	 * `automator_platform_response` with arbitrary args.
	 *
	 * @param mixed $response Expected Response instance.
	 * @param mixed $request  Expected Request instance.
	 *
	 * @return void
	 */
	public function observe( $response = null, $request = null ): void {

		if ( ! $response instanceof Response || ! $request instanceof Request ) {
			return;
		}

		$this->handle( $response, $request );
	}

	/**
	 * Apply the upgrade when the response carries the promotion
	 * envelope. No-ops on responses without the envelope, or when the
	 * outgoing request didn't carry a `legacy_*` connection_id.
	 *
	 * Public so tests can drive the handler directly without the hook.
	 *
	 * @return void
	 */
	public function handle( Response $response, Request $request ): void {

		$data = $response->data();

		// Envelope detection — all three fields required.
		$new_connection_id     = isset( $data['upgraded_connection_id'] ) ? (string) $data['upgraded_connection_id'] : '';
		$new_signature         = isset( $data['upgraded_connection_signature'] ) ? (string) $data['upgraded_connection_signature'] : '';
		$new_identity_id       = isset( $data['upgraded_identity_id'] ) ? (string) $data['upgraded_identity_id'] : '';
		$envelope_platform_ver = isset( $data['platform_version'] ) ? (string) $data['platform_version'] : '';

		if ( '' === $new_connection_id || '' === $new_signature || '' === $new_identity_id ) {
			return;
		}

		if ( Connection::PLATFORM_VERSION_V3 !== $envelope_platform_ver ) {
			return;
		}

		// Locate the legacy_* row by the connection_id that was sent in
		// the request body. Per MVP G.3 the row's connection_id matches
		// `^legacy_{integration}_[a-f0-9]+$`.
		$outgoing_connection_id = $this->extract_outgoing_connection_id( $request );
		if ( '' === $outgoing_connection_id ) {
			return;
		}

		$row = $this->registry->get_by_connection_id( $outgoing_connection_id );
		if ( null === $row ) {
			return;
		}

		// Idempotency guard: if the row is already v3 the platform
		// likely re-emitted the envelope on a stale-cached request
		// (or the plugin replayed). No-op.
		if ( ! $row->is_legacy() ) {
			return;
		}

		$this->registry->upgrade_legacy_to_v3(
			$row->id(),
			$new_connection_id,
			$new_signature,
			$new_identity_id
		);

		$this->wipe_credentials_option( $row->integration() );
	}

	/**
	 * Pull the outgoing `connection_id` from the request — first from
	 * the body (modern v3+ shape per Contract G), then falling back to
	 * the Request VO's explicit `connection_id` accessor for any
	 * historic call path that set it there.
	 *
	 * @return string The legacy_* connection_id sent on this request,
	 *                or '' when not resolvable.
	 */
	private function extract_outgoing_connection_id( Request $request ): string {

		$body = $request->body();
		if ( isset( $body['connection_id'] ) && '' !== (string) $body['connection_id'] ) {
			return (string) $body['connection_id'];
		}

		$explicit = $request->connection_id();
		return null !== $explicit ? (string) $explicit : '';
	}

	/**
	 * Wipe the integration's pre-promotion credentials option. Same
	 * pattern as `Credential_Lift_Observer::wipe_credentials_option()`
	 * — convention-derived option name with a filter override hook for
	 * integrations whose settings_id diverges from their integration
	 * code.
	 *
	 * @return void
	 */
	private function wipe_credentials_option( string $integration ): void {

		$default_option_name = 'automator_' . str_replace( '-', '_', $integration ) . '_credentials';

		$option_name = (string) apply_filters(
			'automator_lifted_credentials_option_name',
			$default_option_name,
			$integration
		);

		if ( '' === $option_name ) {
			return;
		}

		automator_delete_option( $option_name );
	}
}

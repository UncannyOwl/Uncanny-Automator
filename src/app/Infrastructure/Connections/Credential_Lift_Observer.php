<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Request;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Response;

/**
 * Observer for the platform's Contract H credential-lift signal (MVP plan C.1).
 *
 * Branch A lift is the platform-side path that takes a /v2/{integration}
 * dispatch carrying body credentials, mints an Identity + SiteConnection
 * in the v2 vault, and signals the result back to the plugin so the
 * plugin can transition to signature-mode auth on subsequent calls
 * (Phase C.2/C.3).
 *
 * Lift signal headers (per Contract H, emitted by platform tasks
 * P3.8 + P2.4):
 *
 *   X-Automator-Credentials-Lifted: 1
 *   X-Automator-Connection-Id:    <SiteConnection UUID>
 *   X-Automator-Identity-Id:      <Identity UUID>
 *   X-Automator-External-User-Label: <human-readable, optional>
 *
 * On every /v2/{integration} response, this observer:
 *
 * 1. Checks `X-Automator-Credentials-Lifted: 1`. If absent → no-op.
 * 2. Extracts the integration slug from the request endpoint
 *    (`v2/{integration}` → `{integration}`).
 * 3. Reads connection_id + identity_id from response headers.
 * 4. Upserts a `wp_uap_connections` row keyed on
 *    `UNIQUE(integration, identity_id)` — first row for the integration
 *    sets `is_default=1`; subsequent (already-lifted, signal repeating)
 *    is a `touch_last_used` no-op.
 * 5. Wipes the integration's credentials option
 *    (`automator_{integration}_credentials`) so the next dispatch falls
 *    into signature mode (Contract G) rather than re-sending raw
 *    credentials. Filterable via
 *    `automator_lifted_credentials_option_name` for integrations whose
 *    settings_id diverges from their integration code.
 *
 * The wipe happens only once the row is stored. Connection sync never
 * touches a /v2 row, so wiping after a failed insert would leave the
 * connection with neither a row nor credentials; a failed insert is logged
 * and the credentials stay, so the connection still dispatches and the next
 * lift signal (the platform repeats it) stores the row and wipes them.
 *
 * The full lift signal is idempotent on both sides: platform repeats it
 * on every post-lift response (P2.4 idempotent emission rule); plugin's
 * upsert is a no-op when the (integration, identity_id) row already
 * exists; option-wipe is idempotent because `automator_delete_option()`
 * doesn't error on missing options.
 *
 * Wired as a passive observer on `automator_platform_response`
 * (Application_Bootstrap → wire_credential_lift_observer). Same pattern
 * as `Connection_Error_Handler` — both react to platform responses by
 * mutating the registry.
 *
 * @since 8.0.0
 */
final class Credential_Lift_Observer {

	private const HEADER_LIFTED              = 'X-Automator-Credentials-Lifted';
	private const HEADER_CONNECTION_ID       = 'X-Automator-Connection-Id';
	private const HEADER_IDENTITY_ID         = 'X-Automator-Identity-Id';
	private const HEADER_EXTERNAL_USER_LABEL = 'X-Automator-External-User-Label';

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
	 * Observer entry point. Defensive type-checks before delegating to
	 * `handle()` — third-party code may emit
	 * `automator_platform_response` with arbitrary args; we only act on
	 * Response + Request pairs from `Client::send()`.
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
	 * Apply the lift signal when present. No-ops on responses without the
	 * `X-Automator-Credentials-Lifted: 1` header, on non-/v2 endpoints,
	 * or when required correlation headers are missing.
	 *
	 * Public so tests can drive the handler directly without going
	 * through the observer hook.
	 *
	 * @return void
	 */
	public function handle( Response $response, Request $request ): void {

		if ( '1' !== $response->header( self::HEADER_LIFTED ) ) {
			return;
		}

		// Branch A lift only fires on /v2/{integration}; skip anything else
		// defensively even though the platform shouldn't emit the signal
		// elsewhere.
		$integration = $this->extract_integration_slug( $request->endpoint() );
		if ( '' === $integration ) {
			return;
		}

		$connection_id = $response->header( self::HEADER_CONNECTION_ID );
		$identity_id   = $response->header( self::HEADER_IDENTITY_ID );

		if ( '' === $connection_id || '' === $identity_id ) {
			return;
		}

		// Idempotent path: row for this (integration, identity_id) pair
		// already exists — the signal is repeating on a post-lift call.
		// Touch last-used and bail; the wipe already happened on first
		// receipt, source blob is gone.
		$existing = $this->registry->get_by_identity( $integration, $identity_id );
		if ( null !== $existing ) {
			$this->registry->touch_last_used( $existing->id() );
			return;
		}

		// First receipt: insert, then wipe — only once a row holds the
		// connection.
		$is_first_for_integration = array() === $this->registry->list_for_integration( $integration, false );

		$external_user_label = $response->header( self::HEADER_EXTERNAL_USER_LABEL );

		$id = $this->registry->store(
			array(
				'connection_id'    => $connection_id,
				'identity_id'      => $identity_id,
				'integration'      => $integration,
				'label'            => '' !== $external_user_label ? $external_user_label : null,
				'platform_version' => Connection::PLATFORM_VERSION_V2,
				'metadata'         => array(),
				'status'           => Connection::STATUS_ACTIVE,
				'is_default'       => $is_first_for_integration,
			)
		);

		if ( 0 === $id ) {
			automator_log( 'The ' . $integration . ' credential lift could not store connection ' . $connection_id . '; the credentials are kept until the next lift signal.', 'Credential_Lift_Observer', true );
			return;
		}

		$this->wipe_credentials_option( $integration );
	}

	/**
	 * Pull the integration slug from a `v2/{integration}` endpoint
	 * string. Returns empty when the endpoint isn't /v2 (e.g. /v3, /app/v1).
	 *
	 * @return string Lowercased integration slug, or '' on miss.
	 */
	private function extract_integration_slug( string $endpoint ): string {

		if ( ! preg_match( '#^v2/([^/?]+)#', $endpoint, $m ) ) {
			return '';
		}

		return strtolower( $m[1] );
	}

	/**
	 * Wipe the integration's pre-lift credentials option. Convention:
	 * `automator_{integration_slug_underscored}_credentials`. Per Contract
	 * H this is the standard storage location for body-credentials
	 * integrations.
	 *
	 * Filter `automator_lifted_credentials_option_name` lets per-
	 * integration code override when settings_id diverges from
	 * integration code (e.g. Slack: integration='SLACK',
	 * settings_id='slack-api', option = `automator_slack_api_credentials`).
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

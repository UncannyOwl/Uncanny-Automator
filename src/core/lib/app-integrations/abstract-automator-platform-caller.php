<?php

namespace Uncanny_Automator\App_Integrations\Automator_Platform;

use Exception;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch\Connection_Repair_Retry;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch\Platform_Dispatch_Guard;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch\Refused_Before_Sending_Exception;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch\Platform_Request_Builder;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch\Platform_Request_Options;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Error_Exception;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;

/**
 * Abstract class for app integration Automator Platform caller.
 * - Common methods to make API requests
 * - Common method to inject credentials as needed
 * - Common method to handle errors and register additional error messages with help links
 * - On /v3 and later, the platform's failures are told to the customer by
 *   Platform_Failure_Message, from the answer's error.type; an integration
 *   keeps only what is specific to its vendor
 *
 * @package Uncanny_Automator
 */
abstract class Caller {

	/**
	 * The extended App_Helpers instance for the integration.
	 *
	 * @var App_Helpers
	 */
	protected $helpers;

	/**
	 * Webhooks instance for this integration.
	 *
	 * @var App_Webhooks|null
	 */
	protected $webhooks = null;

	/**
	 * The API endpoint.
	 *
	 * @var string
	 */
	protected $api_endpoint;

	/**
	 * Common error patterns and their user-friendly messages.
	 *
	 * @var array
	 */
	protected $error_messages = array();

	/**
	 * The new API client instance (null = use legacy Api_Server path).
	 *
	 * @var \Uncanny_Automator\App\Infrastructure\Automator_Platform\Client|null
	 */
	protected $api_client = null;

	/**
	 * The credential request key.
	 * - property name for applying credentials to the request.
	 * - Added dynamically to allow extending classes to override it.
	 *
	 * @var string
	 */
	protected $credential_request_key = 'credentials';

	/**
	 * Default request timeout in seconds.
	 * - When set, all api_request calls will include this timeout.
	 * - Individual requests may still override via $args['include_timeout'].
	 *
	 * @var int|null
	 */
	protected $request_timeout = null;

	/**
	 * The App_Action currently driving this api caller, bound by
	 * App_Action::do_action() right before process_action() runs. Used by
	 * api_request() to give a v3 helper's call the running action's log
	 * context — action authors don't thread it through call sites.
	 *
	 * Sequential recipe execution within a single PHP request means
	 * concurrent owners aren't a concern. App_Action::do_action() puts the
	 * previous owner back when its run ends.
	 *
	 * @var \Uncanny_Automator\Recipe\App_Action|null
	 */
	protected $owner_action = null;

	/**
	 * The connection the last request of the current api_request() call was
	 * sent with — after a repaired resend, the repaired one — so an accepted
	 * background job names the connection the platform runs it with. '' when
	 * the request carried none.
	 *
	 * @var string
	 */
	private $sent_connection_id = '';

	/**
	 * What refuses this caller's requests before they are sent; built on first
	 * use ({@see self::dispatch_guard()}).
	 *
	 * @var Platform_Dispatch_Guard|null
	 */
	private $dispatch_guard = null;

	/**
	 * Bind the App_Action driving the next api_request() call.
	 *
	 * Called by App_Action::do_action() — action authors don't invoke this.
	 *
	 * @param \Uncanny_Automator\Recipe\App_Action|null $action
	 *
	 * @return \Uncanny_Automator\Recipe\App_Action|null The owner this replaced,
	 *         for the caller to restore once its run ends.
	 */
	public function set_owner_action( $action ) {

		$previous           = $this->owner_action;
		$this->owner_action = $action;

		return $previous;
	}

	/**
	 * __construct
	 *
	 * @param  mixed $helpers
	 *
	 * @return void
	 */
	public function __construct( $helpers ) {

		// Set helpers.
		$this->helpers = $helpers;

		// Register common error messages.
		$this->register_error_messages(
			array(
				'invalid credentials' => array(
					// translators: %s: Settings page URL
					'message'   => esc_html_x( 'Your connection has expired or is invalid. [reconnect your account](%s)', 'API error message', 'uncanny-automator' ),
					'help_link' => $this->helpers->get_settings_page_url(),
				),
			)
		);

		// Set the API endpoint ( will error out if not set )
		if ( method_exists( $this->helpers, 'get_api_endpoint' ) ) {
			$this->set_api_endpoint( $this->helpers->get_api_endpoint() );
		}

		// Give calling class a method to set properties.
		$this->set_properties();

		// Wire the credential-refresh seam used when an action is resent from the logs.
		$this->register_resend_credential_refresh();
	}

	/**
	 * Register the credential-refresh filter for log resends.
	 *
	 * A resend replays the request body that was stored in uap_api_log at
	 * original-run time — including the credential that was live back then. On
	 * replay, Api_Server::api_call() runs filter_params(), which fires
	 * automator_{integration}_api_call for any endpoint of the form
	 * "{version}/{integration}". We hook that filter so the stale credential is
	 * swapped for the current one before the request is re-fired.
	 *
	 * No-op unless the endpoint resolves to a 2-segment "{version}/{slug}" pair
	 * (the exact shape Api_Server::add_endpoint_parts() derives the integration
	 * slug from); otherwise the filter would never fire and registering it is
	 * pointless.
	 *
	 * @return void
	 */
	protected function register_resend_credential_refresh() {

		$parts = explode( '/', (string) $this->api_endpoint );

		if ( 2 !== count( $parts ) || '' === $parts[1] ) {
			return;
		}

		add_filter( 'automator_' . $parts[1] . '_api_call', array( $this, 'refresh_credentials_on_resend' ) );
	}

	/**
	 * Filter callback: replace the stored (possibly stale) credential in a
	 * resent request body with a freshly-resolved current one.
	 *
	 * Acts only on a resend replay ($params['resend']), only on this caller's
	 * own endpoint, and only when the original request actually carried a
	 * credential under our key — so a normal live call (credentials already
	 * injected upstream) and an intentional credential-less request are both
	 * left untouched. A resolution failure leaves the stored value in place so
	 * the replay surfaces the auth error rather than silently dropping it.
	 *
	 * @param array $params The api_call params being replayed.
	 *
	 * @return array
	 */
	public function refresh_credentials_on_resend( $params ) {

		if ( ! is_array( $params ) || empty( $params['resend'] ) ) {
			return $params;
		}

		if ( ! isset( $params['endpoint'], $params['body'] ) || ! is_array( $params['body'] ) ) {
			return $params;
		}

		if ( $params['endpoint'] !== $this->api_endpoint ) {
			return $params;
		}

		try {
			$params['body'] = $this->replace_resend_credentials( $params['body'] );
		} catch ( Exception $e ) {
			automator_log( $e->getMessage(), 'Api_Caller resend credential refresh failed', false );
		}

		return $params;
	}

	/**
	 * Overwrite the stored credentials in a resent request body with
	 * freshly-resolved current ones, and return the body.
	 *
	 * Default behaviour refreshes the value under credential_request_key when the
	 * original request carried it (so a credential-less request is left alone),
	 * re-resolving with the args resend_credential_args() derives from the stored
	 * body. Integrations that bake credentials under custom keys — e.g. a bare
	 * token plus a whole credentials object — override this to refresh those keys.
	 *
	 * @param array $body The stored request body being replayed.
	 *
	 * @return array
	 * @throws Exception If the current credentials cannot be resolved.
	 */
	protected function replace_resend_credentials( $body ) {

		$key = $this->get_credential_request_key();

		if ( array_key_exists( $key, $body ) ) {
			$body[ $key ] = $this->get_api_request_credentials( $this->resend_credential_args( $body ) );
		}

		return $body;
	}

	/**
	 * Request args to re-resolve credentials with on a resend.
	 *
	 * The live path can scope a credential per request: api_request() hands its
	 * $args to prepare_request_credentials(), so a caller may pick a different
	 * credential per call. Those args are not part of the logged body, so a replay
	 * cannot see them — a caller that scopes its credentials recovers the scope
	 * from the stored body here. The default is unscoped, which is correct for
	 * every caller that resolves one credential per connection.
	 *
	 * @param array $body The stored request body being replayed.
	 *
	 * @return array
	 */
	protected function resend_credential_args( $body ) {
		return array();
	}

	/**
	 * Set class properties.
	 * Override this method in child classes to set the API endpoint and other properties.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function set_properties() {
		// $this->register_error_messages( array() );
	}

	/**
	 * Get the API endpoint.
	 *
	 * @return string
	 */
	public function get_api_endpoint() {
		return $this->api_endpoint;
	}

	/**
	 * Set the API endpoint.
	 *
	 * @return string
	 */
	protected function set_api_endpoint( $endpoint ) {
		$this->api_endpoint = $endpoint;
	}

	/**
	 * Get the credential request key.
	 *
	 * @return string
	 */
	protected function get_credential_request_key() {
		return $this->credential_request_key;
	}

	/**
	 * Set the credential request key.
	 *
	 * @param string $key The key.
	 *
	 * @return void
	 */
	protected function set_credential_request_key( $key ) {
		$this->credential_request_key = $key;
	}

	/**
	 * Get the default request timeout.
	 *
	 * @return int|null
	 */
	protected function get_request_timeout() {
		return $this->request_timeout;
	}

	/**
	 * Set the default request timeout in seconds.
	 *
	 * @param int|null $timeout The timeout in seconds, or null to disable.
	 *
	 * @return void
	 */
	protected function set_request_timeout( $timeout ) {
		$this->request_timeout = $timeout;
	}

	/**
	 * Set webhooks dependency.
	 *
	 * @param stdClass $dependencies The dependencies object.
	 *
	 * @return void
	 */
	public function set_dependencies( $dependencies ) {
		$this->webhooks = $dependencies->webhooks ?? null;
	}

	/**
	 * Set the new API client for this caller.
	 * When set, api_request() will use Api_Client::send() instead of Api_Server::api_call().
	 *
	 * @param \Uncanny_Automator\App\Infrastructure\Automator_Platform\Client $api_client The API client.
	 * @return void
	 */
	public function set_api_client( $api_client ): void {
		$this->api_client = $api_client;
	}

	/**
	 * API request - Common method to make API requests to API server.
	 *
	 * @param mixed $body        The body of the request or the single action string
	 * @param array $action_data The action data ( required for retriggering webhooks from logs )
	 * @param array $args        Method arguments
	 *  @property exclude_credentials - boolean default false
	 *  @property exclude_error_check - boolean default false
	 *  @property return_vendor_errors - boolean default false: a vendor error nested under `data` comes back as a
	 *            response instead of an exception; envelope and transport errors still throw
	 *  @property include_timeout - integer default null
	 *  @property async - boolean default true; false keeps an owner action's write synchronous
	 *  @property any additional parameters for handling responses.
	 *
	 * @return array
	 */
	public function api_request( $body, $action_data = null, $args = array() ) {

		// The caller's own $action_data marks the action's write; lookups omit it, and the owner fallback below must not blur that.
		$explicit_action_data = is_array( $action_data ) && array() !== $action_data;

		$action_data = $this->action_data_for_call( $action_data );
		$options     = Platform_Request_Options::from_args( $args, $this->request_timeout );
		$args        = $options->args();
		$is_modern   = $this->is_modern_endpoint_version( (string) $this->api_endpoint );

		if ( $is_modern ) {
			$this->dispatch_guard()->hold_while_the_site_address_is_answered();
		}

		$options = $options->with_background_key( $this->background_key_for_call( $explicit_action_data, $options ) );

		$this->sent_connection_id = '';

		try {
			$params   = $this->request_params( $body, $action_data, $options, $is_modern );
			$response = $is_modern && $options->includes_credentials()
				? $this->send_with_connection( $params )
				: $this->make_api_call( $params );

			$accepted = $this->accepted_in_background( $response, $options );
			if ( null !== $accepted ) {
				return $accepted;
			}
		} catch ( Exception $e ) {
			// If errors are not specifically skipped, check for them.
			if ( $options->checks_errors() ) {
				$this->check_for_failure( $e, $is_modern, $args );
			}
			// Re-throw the original exception.
			throw $e;
		}

		// If errors are not specifically skipped, check for them.
		if ( $options->checks_errors() ) {
			$this->check_for_errors( $response, $args );
		}

		return $response;
	}

	/**
	 * The key a call goes out as a background write with, or null when it is not one.
	 *
	 * Only an owner action that can answer with a key, only for the action's own
	 * write (a lookup passes no action data), and only when the call allows it
	 * (`async` is not literally false).
	 *
	 * @param bool                     $explicit_action_data Whether the caller passed action data itself.
	 * @param Platform_Request_Options $options              What the call asked for.
	 *
	 * @return string|null
	 */
	private function background_key_for_call( bool $explicit_action_data, Platform_Request_Options $options ): ?string {

		if ( ! $explicit_action_data || ! $options->allows_background() ) {
			return null;
		}

		if ( ! is_object( $this->owner_action ) || ! method_exists( $this->owner_action, 'background_dispatch_key' ) ) {
			return null;
		}

		return $this->owner_action->background_dispatch_key();
	}

	/**
	 * The platform's answer to a background write, when it accepted a job for it:
	 * noted on the owner action and handed back as it is, with the key and the
	 * connection it was sent with — before the integration's error check, which
	 * would refuse a 202.
	 *
	 * @param array                    $response The platform's answer.
	 * @param Platform_Request_Options $options  What the call asked for.
	 *
	 * @return array|null The answer, or null when the call is not an accepted background write.
	 */
	private function accepted_in_background( array $response, Platform_Request_Options $options ): ?array {

		$key = $options->background_key();

		if ( null === $key || 202 !== (int) ( $response['statusCode'] ?? 0 ) || '' === (string) ( $response['job_id'] ?? '' ) ) {
			return null;
		}

		$response['idempotency_key'] = $key;
		$response['connection_id']   = $this->sent_connection_id;
		$this->owner_action->note_background_accepted( (string) $response['job_id'] );

		return $response;
	}

	/**
	 * The action data a call is logged with: the caller's own, else the bound
	 * owner action's.
	 *
	 * Fallback: v3 path-per-action helpers don't thread `$action_data` through
	 * every method — the pattern is `$this->api_request( array( 'foo' => 'bar' ) )`.
	 * Pull it off the bound owner App_Action when the caller didn't supply it;
	 * that is what gives the call its uap_api_log row (resend, credit marker).
	 * owner_action_data() scopes this to v3 endpoints, so a /v2 integration logs
	 * exactly the calls it passes `$action_data` for, as it always has.
	 *
	 * @param array|null $action_data What the caller passed.
	 *
	 * @return array|null
	 */
	private function action_data_for_call( $action_data ) {

		if ( null !== $action_data && array() !== $action_data ) {
			return $action_data;
		}

		return $this->owner_action_data() ?? $action_data;
	}

	/**
	 * The parameters of one platform call: its shape by endpoint generation
	 * ({@see Platform_Request_Builder}), then the credentials, unless the call
	 * excludes them.
	 *
	 * @param mixed                    $body        The body of the request or the single action string.
	 * @param array|null               $action_data The action data the call is logged with.
	 * @param Platform_Request_Options $options     What the call asked for.
	 * @param bool                     $is_modern   Whether the endpoint is /v3 or later.
	 *
	 * @return array
	 * @throws Exception When the credentials cannot be resolved.
	 */
	private function request_params( $body, $action_data, Platform_Request_Options $options, bool $is_modern ): array {

		if ( is_string( $body ) ) {
			$body = array( 'action' => $body );
		}

		$params = ( new Platform_Request_Builder() )->build( (string) $this->api_endpoint, $is_modern, is_array( $body ) ? $body : array(), $action_data, $options );

		if ( $options->includes_credentials() ) {
			$params['body'] = $this->with_credentials( $params['body'], $is_modern, $options->args() );
		}

		return $params;
	}

	/**
	 * The body with the credentials its endpoint generation takes.
	 *
	 * On /v3 and later the site's default connection rides in the body as flat
	 * fields ({@see dispatch_envelope()}); on the legacy Slim platform the
	 * credentials are nested under a key (default 'credentials') — typically a
	 * JSON-encoded blob the per-integration `prepare_request_credentials()`
	 * produces.
	 *
	 * @param array $body      The request body.
	 * @param bool  $is_modern Whether the endpoint is /v3 or later.
	 * @param array $args      The api_request() arguments.
	 *
	 * @return array
	 * @throws Exception When the credentials cannot be resolved.
	 */
	private function with_credentials( array $body, bool $is_modern, array $args ): array {

		if ( $is_modern ) {
			return array_merge( $body, $this->dispatch_envelope() );
		}

		$body[ $this->get_credential_request_key() ] = $this->get_api_request_credentials( $args );

		return $body;
	}

	/**
	 * Make the API call — uses new Api_Client if available, falls back to legacy Api_Server.
	 *
	 * @param array $params The request parameters.
	 * @return array The response array.
	 * @throws \Exception On API errors.
	 */
	protected function make_api_call( array $params ): array {

		$this->dispatch_guard()->refuse_webhooks_on_local_site( (string) ( $params['endpoint'] ?? '' ) );

		$client = $this->platform_client();

		if ( null !== $client ) {
			$request  = \Uncanny_Automator\App\Infrastructure\Automator_Platform\Request::from_legacy_params( $params );
			$response = $client->send( $request );

			/**
			 * Fires when an API call uses the new Api_Client path.
			 *
			 * @since 7.1
			 *
			 * @param array  $params   The legacy params array.
			 * @param object $response The Api_Response object.
			 */
			do_action( 'automator_api_call_via_client', $params, $response );

			return $response->to_legacy_array();
		}

		// Legacy path — will be removed when all integrations use the new client.
		return Api_Server::api_call( $params );
	}

	/**
	 * What refuses this caller's requests before they are sent: one guard for
	 * the caller's calls. A request with no license key is refused later, where
	 * it is sent.
	 *
	 * @return Platform_Dispatch_Guard
	 */
	private function dispatch_guard(): Platform_Dispatch_Guard {

		if ( null === $this->dispatch_guard ) {
			$this->dispatch_guard = new Platform_Dispatch_Guard();
		}

		return $this->dispatch_guard;
	}

	/**
	 * The dispatch envelope a /v3 request carries: the site's default
	 * connection for this integration, as `connection_id` and
	 * `connection_signature` — read from the helpers' standard envelope, not
	 * through the per-integration `prepare_request_credentials()` (Slim-era
	 * `vault_signature` packaging). `external_user_id` is left out: it is
	 * integration-specific, and the caller decides if and where it goes.
	 *
	 * Read fresh on every call: the stored row can change under a request
	 * (connection sync repairs it from the platform's list).
	 *
	 * @return array{connection_id: string, connection_signature: string}
	 * @throws Exception When the helpers cannot give an envelope.
	 * @throws \Uncanny_Automator\App\Infrastructure\Connections\Connection_Not_Found_Exception When there is no connection to send with.
	 */
	private function dispatch_envelope(): array {

		if ( ! method_exists( $this->helpers, 'get_credentials' ) ) {
			throw new Exception( 'Helpers class does not have a get_credentials method' );
		}

		$envelope = $this->helpers->get_credentials();
		if ( ! is_array( $envelope ) ) {
			throw new Exception( 'V3 envelope must be an array (per Contract H)' );
		}

		return array_intersect_key( $envelope, array_flip( array( 'connection_id', 'connection_signature' ) ) );
	}

	/**
	 * Send a request made with the site's connection — once more when
	 * connection sync repaired the row in the meantime
	 * ({@see Connection_Repair_Retry}, which holds the rule: exactly one more
	 * send, with the envelope the site holds now).
	 *
	 * @param array $params The request parameters; the body carries the envelope that was sent.
	 *
	 * @return array The response array.
	 * @throws Exception The platform's answer, or a transport failure.
	 */
	private function send_with_connection( array $params ): array {

		return ( new Connection_Repair_Retry() )->send(
			$params,
			function ( array $request ): array {
				$this->sent_connection_id = (string) ( $request['body']['connection_id'] ?? '' );
				return $this->make_api_call( $request );
			},
			function (): array {
				return $this->dispatch_envelope();
			}
		);
	}

	/**
	 * The client a request is sent with: the injected one, else — for a /v3
	 * (or later) endpoint — the shared platform client.
	 *
	 * A /v3 request never takes the legacy Api_Server path, whatever the
	 * order the integration was wired in (App_Integration evaluates the
	 * connection, which can call the API, before it injects the client). Only
	 * the client fires automator_platform_response, which applies an answer
	 * to the stored connection, and throws the answer itself, which tells the
	 * customer the right thing; the legacy path does neither. A /v2 endpoint
	 * with no injected client keeps the legacy path.
	 *
	 * @return \Uncanny_Automator\App\Infrastructure\Automator_Platform\Client|null
	 */
	private function platform_client() {

		if ( null !== $this->api_client ) {
			return $this->api_client;
		}

		return $this->is_modern_endpoint_version( (string) $this->api_endpoint )
			? \Uncanny_Automator\App\Infrastructure\automator_platform_client()
			: null;
	}

	/**
	 * Get the API request credentials.
	 *
	 * @param array $args The arguments.
	 *
	 * @return mixed - Array or string of credentials
	 * @throws Exception If credentials are invalid
	 * @throws Action_Error_Exception When the app has no stored credentials: the reconnect message, with the settings link.
	 */
	public function get_api_request_credentials( $args ) {

		// Add check if get_credentials method exists.
		if ( ! method_exists( $this->helpers, 'get_credentials' ) ) {
			throw new Exception( 'Helpers class does not have a get_credentials method' );
		}

		// Get the credentials.
		$credentials = $this->helpers->get_credentials();

		// No stored credentials: the app is not connected, so the owner gets the credential message the constructor registers, with its settings link.
		if ( empty( $credentials ) ) {
			throw Action_Error_Exception::app_not_connected( esc_html( $this->format_error_message( $this->error_messages['invalid credentials'] ) ) );
		}

		// Prepare / sanitize / format the credentials.
		return $this->prepare_request_credentials( $credentials, $args );
	}

	/**
	 * Detect whether `$endpoint` targets the modern Laravel platform
	 * (path-per-action, flat-body credentials) vs the legacy Slim
	 * platform (single endpoint, action-field-routed body, nested
	 * credentials blob).
	 *
	 * Rule: any `vN/...` prefix where `N >= 3` is modern. Future v4, v5
	 * (etc.) inherit the modern shape automatically — no per-version
	 * touch on this dispatcher.
	 *
	 * Examples:
	 *  - `v3/dropbox`             → modern
	 *  - `v3/google-drive`        → modern
	 *  - `v4/anything`            → modern
	 *  - `v2/slack`               → legacy (Slim)
	 *  - `slack` (no prefix)      → legacy
	 *
	 * @param string $endpoint The integration's `api_endpoint` config value.
	 *
	 * @return bool
	 */
	private function is_modern_endpoint_version( string $endpoint ): bool {

		if ( ! preg_match( '#^v(\d+)/#', $endpoint, $matches ) ) {
			return false;
		}

		return (int) $matches[1] >= 3;
	}

	/**
	 * Read the bound owner action's `action_data` array.
	 *
	 * Returns null when no App_Action is bound or the owner doesn't expose
	 * the accessor — caller treats null as "no fallback available" and the
	 * Request VO ships without action_data.
	 *
	 * Also null for a call to a /v2 endpoint. Those integrations pass
	 * `$action_data` on the one call they want logged; attributing every
	 * other call in the action to it (a lookup before the write, say) puts
	 * several uap_api_log rows on one action, and a resend replays the first.
	 *
	 * @return array|null
	 */
	private function owner_action_data(): ?array {

		if ( null === $this->owner_action || ! method_exists( $this->owner_action, 'get_action_data' ) ) {
			return null;
		}

		if ( ! $this->is_modern_endpoint_version( (string) $this->api_endpoint ) ) {
			return null;
		}

		$data = (array) $this->owner_action->get_action_data();

		return ! empty( $data ) ? $data : null;
	}

	/**
	 * Optionally prepare credentials for use in API requests.
	 * This method allows integrations to customize, and sanitize how request credentials are prepared
	 * before they are used in API calls.
	 *
	 * @param array $credentials The credentials to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return mixed The prepared credentials, format depends on integration needs.
	 * @throws Exception If credentials are invalid
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		return $credentials;
	}

	/**
	 * Turn a failed request into what the customer is told.
	 *
	 * On a /v3 (or later) endpoint, an answer from the platform is told by the
	 * one owner of those sentences, Platform_Failure_Message, from the answer's
	 * error.type (platform answers contract, section 4). The integration's
	 * check_for_errors() and its registered patterns are not consulted for it:
	 * an integration keeps only what is specific to its vendor, which reaches
	 * the customer as the platform's description. So no integration can tell a
	 * customer to reconnect over an answer that is not about their account.
	 *
	 * Everything else — a /v2 answer, a transport failure, a missing
	 * connection — goes through check_for_errors() as before.
	 *
	 * @param Exception $e         The failure.
	 * @param bool      $is_modern Whether the endpoint is /v3 or later.
	 * @param array     $args      The api_request() arguments.
	 *
	 * @return void
	 * @throws Action_Error_Exception With the customer's message.
	 */
	private function check_for_failure( Exception $e, bool $is_modern, array $args ) {

		// Refused on the site, before sending: final, in the customer's words already.
		if ( $e instanceof Refused_Before_Sending_Exception ) {
			throw $e;
		}

		if ( $is_modern && $e instanceof Platform_Error_Exception ) {
			$failure = $this->platform_failure_message( $e->failure() )->to_exception( (int) $e->getCode(), $e );
			throw $failure;
		}

		$this->check_for_errors(
			array(
				'error'      => $e->getMessage(),
				'statusCode' => $e->getCode(),
			),
			$args
		);
	}

	/**
	 * The customer's message for a platform failure on this integration.
	 *
	 * For a connection the platform and this site disagree about, the answer
	 * has already been handed to connection sync (Connection_Error_Handler,
	 * inside Client::send()); what it left decides between "being re-checked,
	 * run it again" and the connect prompt.
	 *
	 * @param Platform_Failure $failure Which thing failed.
	 *
	 * @return Platform_Failure_Message
	 */
	private function platform_failure_message( Platform_Failure $failure ) {

		return Platform_Failure_Message::for_integration( $failure, (string) $this->helpers->get_integration(), (string) $this->helpers->get_name() );
	}

	/**
	 * Check for errors.
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs
	 */
	public function check_for_errors( $response, $args = array() ) {
		$status = $response['statusCode'] ?? 0;
		if ( $status >= 400 && $status < 500 ) {
			$this->handle_400_error( $response, $args );
		}
	}

	/**
	 * Handle 400-level errors with custom messages
	 *
	 * The exception carries the error code the HTTP status gives, so the
	 * action's error log records it instead of guessing from the message.
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Action_Error_Exception If an error occurs
	 */
	protected function handle_400_error( $response, $args ) {

		$error_text = $this->get_error_text( $response );

		// Match against registered patterns first (e.g. credential errors).
		if ( $error_text ) {
			foreach ( $this->error_messages as $pattern => $error ) {
				if ( false !== strpos( $error_text, $pattern ) ) {
					// The pattern's own message replaces the vendor's, so the code
					// comes from the vendor's text.
					throw new Action_Error_Exception(
						esc_html( $this->format_error_message( $error ) ),
						Error_Code::from_http_status( absint( $response['statusCode'] ?? 0 ), $error_text ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- An Error_Code slug, never output.
					);
				}
			}
		}

		// Surface the real platform/vendor message. When nothing usable is
		// present we leave it to the original re-thrown exception in api_request.
		$message = $this->extract_error_message( $response );
		if ( '' !== $message ) {
			throw Action_Error_Exception::from_http_status( esc_html( $message ), absint( $response['statusCode'] ?? 0 ) );
		}
	}

	/**
	 * Extract a human-readable message from an error response.
	 *
	 * The platform wraps every upstream failure in a consistent envelope: the
	 * actionable reason lives in error.description (with error.message as a
	 * generic summary). On api_request's exception path that message is re-passed
	 * as a plain $response['error'] string. Integrations should rely on this
	 * shared extraction rather than reinventing it (or dumping the whole
	 * response / falling back to a generic status-code string).
	 *
	 * @param array $response The response.
	 *
	 * @return string The message, or '' when nothing usable is present.
	 */
	protected function extract_error_message( $response ) {

		$message = '';

		// Vendor-native message, if the platform forwarded the upstream body.
		if ( ! empty( $response['data']['message'] ) && is_string( $response['data']['message'] ) ) {
			$message = $response['data']['message'];
		} else {
			// Platform error envelope: array { description, message } on the
			// normal path, or a plain string on api_request's exception path.
			$error = $response['error'] ?? '';

			if ( is_array( $error ) ) {
				$message = $error['description'] ?? $error['message'] ?? '';
			} elseif ( is_string( $error ) ) {
				$message = $error;
			}

			// Vendor error nested in data.
			if ( '' === $message && ! empty( $response['data']['error'] ) && is_string( $response['data']['error'] ) ) {
				$message = $response['data']['error'];
			}
		}

		// Strip the legacy Api_Server prefix so callers (and post-processors like
		// the WhatsApp caller) get the clean upstream text either way.
		$message = preg_replace( '/^API has responded with an error message:\s*/i', '', (string) $message );

		return trim( $message );
	}

	/**
	 * Get error text from response.
	 *
	 * @param array $response The response.
	 *
	 * @return string|false
	 */
	protected function get_error_text( $response ) {
		// Check from common locations.
		$error = $response['data']['message']
			?? $response['data']['error']
			?? $response['error']
			?? false;

		// Return the error text if it's a string.
		return is_string( $error )
			? strtolower( $error )
			: false;
	}

	/**
	 * Format error message with help link
	 *
	 * @param array $error The error.
	 *
	 * @return string
	 */
	protected function format_error_message( $error ) {
		// If the message is not set, return an empty string.
		if ( ! isset( $error['message'] ) ) {
			return '';
		}

		// If no help link is provided, return the message as is.
		if ( empty( $error['help_link'] ) ) {
			return esc_html( $error['message'] );
		}

		// Only use sprintf if the message contains a placeholder.
		if ( false !== strpos( $error['message'], '%s' ) ) {
			return sprintf( esc_html( $error['message'] ), esc_url( $error['help_link'] ) );
		}

		// If no placeholder found, return the message as is.
		return esc_html( $error['message'] );
	}

	/**
	 * Register additional error messages
	 *
	 * @param array $messages Additional error messages to register
	 * Format: [
	 *     'pattern' => [
	 *         'message'   => 'User-friendly message with [optional help link](%s)',
	 *         'help_link' => 'settings|https://help.example.com',
	 *     ]
	 * ]
	 *
	 * @return void
	 */
	protected function register_error_messages( $messages ) {
		$this->error_messages = array_merge( $this->error_messages, $messages );
	}

	/**
	 * Validate the action context.
	 *
	 * @return void
	 * @throws Exception If not called during process_action
	 */
	protected function validate_action_context() {
		if ( ! did_action( 'automator_before_process_action' ) ) {
			throw new Exception( esc_html_x( 'This action may only be used in an Automator action context.', 'API error message', 'uncanny-automator' ) );
		}
	}
}

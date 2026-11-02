<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Auth_Log;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

/**
 * Administrator-only authorization code and consent endpoint.
 */
final class OAuth_Authorization_Controller {

	/**
	 * Consent-page CSP. Browsers enforce form-action against the REDIRECT of a
	 * form submission, and approving consent redirects to the client's
	 * registered callback, so the sources mirror exactly what registration
	 * allows: https anywhere, http only on loopback. Narrowing this back to
	 * 'self' silently blocks every OAuth client after consent is consumed.
	 */
	public const CONSENT_CSP = "default-src 'none'; style-src 'unsafe-inline'; font-src data:; form-action 'self' https: http://127.0.0.1:* http://localhost:* http://[::1]:*; frame-ancestors 'none'; base-uri 'none'";

	private const AUTHORIZE_ROUTE = '/automator/v1/oauth/authorize';

	private const LOOPBACK_HOSTS = array( '127.0.0.1', '[::1]', '::1', 'localhost' );

	private const CONSENT_LIFETIME = 600;

	private const CODE_LIFETIME = 300;

	/**
	 * CORS response headers that WordPress core adds to REST responses.
	 *
	 * OAuth begins with a cross-site browser navigation, not a credentialed
	 * cross-origin API request. None of these grants is needed for the consent
	 * page or its same-origin form submission.
	 */
	private const CORS_RESPONSE_HEADERS = array(
		'Access-Control-Allow-Origin',
		'Access-Control-Allow-Methods',
		'Access-Control-Allow-Credentials',
		'Access-Control-Allow-Headers',
		'Access-Control-Expose-Headers',
		'Access-Control-Max-Age',
		'Access-Control-Allow-Private-Network',
	);

	private OAuth_Server_Configuration $configuration;

	private OAuth_Client_Registry $clients;

	private OAuth_Scope_Policy $scope_policy;

	private OAuth_Artifact_Store $store;

	private OAuth_Rate_Limiter $rate_limiter;

	/** @var string|null Consent view override used by focused tests. */
	private ?string $consent_view_path;

	/** Whether this request's direct errors render as a page for a person. */
	private bool $html_errors = false;

	/**
	 * Constructor.
	 */
	public function __construct(
		?OAuth_Server_Configuration $configuration = null,
		?OAuth_Client_Registry $clients = null,
		?OAuth_Scope_Policy $scope_policy = null,
		?OAuth_Artifact_Store $store = null,
		?OAuth_Rate_Limiter $rate_limiter = null,
		?string $consent_view_path = null
	) {
		$this->configuration     = $configuration ?? new OAuth_Server_Configuration();
		$this->clients           = $clients ?? new OAuth_Client_Registry();
		$this->scope_policy      = $scope_policy ?? new OAuth_Scope_Policy();
		$this->store             = $store ?? new OAuth_Artifact_Store();
		$this->rate_limiter      = $rate_limiter ?? new OAuth_Rate_Limiter();
		$this->consent_view_path = $consent_view_path;
	}

	/**
	 * Preserve normal WordPress cookie auth on this endpoint.
	 *
	 * Consent POSTs use their own single-purpose nonce and server-side state.
	 *
	 * @param mixed $result Existing authentication result.
	 * @return mixed
	 */
	public function preserve_cookie_auth( $result ) {
		if ( ! empty( $result ) || ! $this->is_current_authorization_request() || ! current_user_can( 'manage_options' ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Register the browser authorization endpoint.
	 */
	public function register_routes(): void {
		register_rest_route(
			'automator/v1',
			'/oauth/authorize',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'begin' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'decide' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Validate an authorization request and create server-side consent state.
	 */
	public function begin( WP_REST_Request $request ): WP_REST_Response {
		OAuth_Cache_Policy::no_cache();
		$this->html_errors = $this->prefers_html( $request );
		if ( ! $this->configuration->can_authorize() ) {
			$reason = $this->configuration->get_unavailable_description();
			return $this->oauth_error( 'temporarily_unavailable', '' !== $reason ? $reason : 'OAuth authorization is unavailable on this site.', 503, 'UA-MCP-301' );
		}
		// The rate limiter deliberately runs before the capability check:
		// unauthenticated hits must consume the bucket, or the login-redirect
		// path below would be free to flood.
		// Keep the pre-authentication bucket IP-only. A caller-chosen client ID
		// must not create one WordPress transient per request.
		$allowed = $this->rate_limiter->allows( 'authorize_ip', '', 20, MINUTE_IN_SECONDS );
		if ( null === $allowed ) {
			return $this->oauth_error( 'temporarily_unavailable', OAuth_Rate_Limiter::STORAGE_FAILURE_MESSAGE, 503, 'UA-MCP-350' );
		}
		if ( false === $allowed ) {
			return $this->oauth_error( 'temporarily_unavailable', 'Too many authorization requests.', 429, 'UA-MCP-302' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			// WordPress demotes a cookie user without a REST nonce to anonymous
			// before this callback runs, so the cookie itself says whether someone
			// is signed in. Sending a signed-in non-administrator to wp-login only
			// bounces them straight back here, forever, burning the IP bucket.
			$signed_in_user_id = $this->signed_in_user_id();
			if ( $signed_in_user_id > 0 ) {
				return $this->administrator_required_page( $signed_in_user_id );
			}

			$login_url = wp_login_url( $this->current_url() );
			return new WP_REST_Response(
				null,
				302,
				array(
					'Location'      => $login_url,
					// A guest redirect must never be replayed by an edge cache to the
					// administrator who just signed in.
					'Cache-Control' => 'no-store',
					'Vary'          => 'Cookie',
				)
			);
		}

		$validated = $this->validate_authorization_request( $request );
		if ( $validated instanceof WP_REST_Response ) {
			return $validated;
		}

		$result = $this->store->with_lock(
			'client',
			(string) $validated['client_id'],
			function () use ( $validated ): WP_REST_Response {
				// The request already validated client metadata before this lock.
				// Recheck local retirement without fetching CIMD metadata again.
				if ( $this->clients->is_revoked( (string) $validated['client_id'], true ) ) {
					return $this->oauth_error( 'invalid_request', 'The client registration is unavailable.', 400, 'UA-MCP-317' );
				}
				if ( 'dcr' === ( $validated['client_source'] ?? '' ) ) {
					// Cleanup can retire a pending client after request validation but
					// before this lock. Never show approval for a missing registration.
					$clients = $this->clients->confirmed_clients();
					if ( null === $clients ) {
						return $this->oauth_error( 'temporarily_unavailable', 'The client registration could not be checked. Try again.', 503, 'UA-MCP-349' );
					}
					$current = $clients[ (string) $validated['client_id'] ] ?? null;
					if ( ! is_array( $current ) || empty( $current['active'] ) || 'dcr' !== ( $current['source'] ?? '' ) ) {
						return $this->oauth_error( 'invalid_request', 'The client registration expired. Start the connection again from your app.', 400, 'UA-MCP-321' );
					}
				}

				// Explain the missing setup before offering approval or storing consent.
				$license_error = Hosted_Agent_Access::site_license_error( (array) $validated['scopes'] );
				if ( null !== $license_error ) {
					return $this->site_license_required_page( $license_error, $validated );
				}

				$consent_id           = str_replace( '-', '', wp_generate_uuid4() ) . wp_generate_password( 24, false, false );
				$record               = $validated;
				$record['user_id']    = get_current_user_id();
				$record['expires_at'] = time() + self::CONSENT_LIFETIME;
				if ( ! $this->store->put( 'consent', $consent_id, $record ) ) {
					return $this->oauth_error( 'server_error', 'The consent request could not be stored.', 500, 'UA-MCP-303' );
				}

				Mcp_Auth_Log::record(
					'authorization',
					'consent_shown',
					array(
						'subject' => (string) $record['client_name'] . ' (' . (string) $record['client_id'] . ')',
						'user_id' => get_current_user_id(),
					)
				);

				$script = $this->read_local_file( dirname( __DIR__ ) . '/static/js/consent.js' );
				$html   = $this->render_consent( $consent_id, $record, $script );
				if ( null === $html ) {
					// The administrator cannot approve a page that did not render. Consume
					// its one-time state immediately instead of leaving an unusable consent
					// artifact until expiry.
					$this->store->consume( 'consent', $consent_id );
					return $this->oauth_error( 'server_error', 'The authorization page is unavailable. Ask the site administrator to verify the Automator plugin files and permissions.', 500, 'UA-MCP-346' );
				}

				// Permit only the bundled loading script; other inline scripts remain blocked.
				$csp = self::CONSENT_CSP . "; script-src 'sha256-" . base64_encode( hash( 'sha256', $script, true ) ) . "'";

				return new OAuth_Html_Response( $html, 200, $csp );
			}
		);
		return $result ?? $this->oauth_error( 'temporarily_unavailable', 'The client connection is busy. Try again.', 503, 'UA-MCP-347' );
	}

	/**
	 * Restore the authorization return address after login.
	 *
	 * Login-redirect and membership plugins commonly override redirect_to with a
	 * dashboard or account page. When the requested address is this site's own
	 * authorization route, that override strands a pending connection, so it
	 * wins at the last possible priority.
	 *
	 * @param string           $redirect_to           Destination chosen so far.
	 * @param string           $requested_redirect_to Destination the login form asked for.
	 * @param WP_User|\WP_Error $user                  Login result.
	 * @return string
	 */
	public function restore_authorization_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if (
			! $user instanceof WP_User
			|| ! is_string( $requested_redirect_to )
			|| '' === $requested_redirect_to
			|| $redirect_to === $requested_redirect_to
			|| ! $this->is_authorization_url( $requested_redirect_to )
		) {
			return $redirect_to;
		}

		return $requested_redirect_to;
	}

	/**
	 * Consume server-side consent and issue one short-lived code.
	 */
	public function decide( WP_REST_Request $request ): WP_REST_Response {
		OAuth_Cache_Policy::no_cache();
		$this->html_errors = $this->prefers_html( $request );
		if ( ! $this->configuration->can_authorize() || ! current_user_can( 'manage_options' ) ) {
			return $this->oauth_error( 'access_denied', 'Administrator authorization is required.', 403, 'UA-MCP-304' );
		}
		$content_type = strtolower( trim( (string) strtok( (string) $request->get_header( 'content-type' ), ';' ) ) );
		if ( 'application/x-www-form-urlencoded' !== $content_type ) {
			return $this->oauth_error( 'invalid_request', 'Consent must be submitted as a form.', 400, 'UA-MCP-305' );
		}

		$consent_id = $this->body_param( $request, 'consent_id' );
		$nonce      = $this->body_param( $request, '_wpnonce' );
		$decision   = $this->body_param( $request, 'decision' );
		if ( ! in_array( $decision, array( 'approve', 'deny' ), true ) ) {
			return $this->oauth_error( 'invalid_request', 'The consent decision is invalid.', 400, 'UA-MCP-306' );
		}
		// This route is administrator-only. Key its secondary bucket by the
		// authenticated user, never by the caller-supplied consent identifier.
		$allowed = $this->rate_limiter->allows( 'consent', (string) get_current_user_id(), 20, MINUTE_IN_SECONDS );
		if ( null === $allowed ) {
			return $this->oauth_error( 'temporarily_unavailable', OAuth_Rate_Limiter::STORAGE_FAILURE_MESSAGE, 503, 'UA-MCP-350' );
		}
		if ( false === $allowed ) {
			return $this->oauth_error( 'temporarily_unavailable', 'Too many consent requests.', 429, 'UA-MCP-307' );
		}
		$preview = $this->store->get( 'consent', $consent_id );
		// The random, user-bound consent record and its dedicated WordPress nonce
		// are the CSRF boundary. Origin and Fetch Metadata are useful diagnostics,
		// but cannot be authorization requirements: browsers, CDNs and WordPress
		// reverse proxies may omit or rewrite them during a valid top-level form
		// submission. This route also removes credentialed CORS, so another origin
		// cannot read either value from the consent page.
		if (
			! is_array( $preview )
			|| (int) ( $preview['user_id'] ?? 0 ) !== get_current_user_id()
			|| (int) ( $preview['expires_at'] ?? 0 ) < time()
			|| ! wp_verify_nonce( $nonce, 'automator_mcp_oauth_consent_' . $consent_id )
		) {
			return $this->oauth_error( 'invalid_request', 'The consent request is invalid or expired.', 400, 'UA-MCP-308' );
		}

		$result = $this->store->with_lock(
			'client',
			(string) $preview['client_id'],
			function () use ( $consent_id, $decision, $preview ): WP_REST_Response {
				// Consent holds the metadata already validated for this user.
				// A network refetch here would invalidate an approved snapshot.
				if ( $this->clients->is_revoked( (string) $preview['client_id'], true ) ) {
					return $this->oauth_error( 'invalid_request', 'The client registration is unavailable.', 400, 'UA-MCP-317' );
				}

				$record = 'approve' === $decision ? $this->store->get( 'consent', $consent_id ) : $this->store->consume( 'consent', $consent_id );
				if ( ! is_array( $record ) ) {
					return $this->oauth_error( 'invalid_request', 'The consent request was already used.', 400, 'UA-MCP-309' );
				}

				if ( 'approve' !== $decision ) {
					OAuth_Telemetry::record( 'authorization_consent', 'denied', (string) $record['client_id'], get_current_user_id() );
					// Approval is what binds a first-use redirect address; a denied
					// record leaves it unbound, and an unbound address never earns a
					// redirect (OAuth 2.1: error page, not error redirect).
					if ( ! empty( $record['bind_redirect'] ) ) {
						return $this->denied_consent_page();
					}
					return $this->authorization_redirect(
						$record,
						array(
							'error'             => 'access_denied',
							'error_description' => 'The administrator denied access.',
						)
					);
				}

				// The license may have been removed while the approval dialog was open.
				// Denial above still works without a license.
				$license_error = Hosted_Agent_Access::site_license_error( (array) $record['scopes'] );
				if ( null !== $license_error ) {
					return $this->site_license_required_page( $license_error, $record );
				}

				if ( ! empty( $record['bind_redirect'] ) && ! $this->clients->bind_redirect( (string) $record['client_id'], (string) $record['redirect_uri'] ) ) {
					if ( $this->clients->mutation_was_busy() ) {
						return $this->oauth_error( 'temporarily_unavailable', 'The client registry is busy. Submit the decision again.', 503, 'UA-MCP-348' );
					}
					return $this->oauth_error( 'invalid_request', 'The redirect address could not be confirmed. Start the connection again from your app.', 400, 'UA-MCP-310' );
				}
				if (
				'dcr' === ( $record['client_source'] ?? '' )
				&& ! $this->clients->mark_dynamic_client_approved( (string) $record['client_id'] )
				) {
					if ( $this->clients->mutation_was_busy() ) {
						return $this->oauth_error( 'temporarily_unavailable', 'The client registry is busy. Submit the decision again.', 503, 'UA-MCP-348' );
					}
					return $this->oauth_error( 'invalid_request', 'The client registration expired. Start the connection again from your app.', 400, 'UA-MCP-321' );
				}
				if (
				'cimd' === ( $record['client_source'] ?? '' )
				&& ! $this->clients->remember_approved_cimd_client(
					(string) $record['client_id'],
					(string) $record['client_name'],
					(array) ( $record['client_redirect_uris'] ?? array() )
				)
				) {
					if ( $this->clients->mutation_was_busy() ) {
						return $this->oauth_error( 'temporarily_unavailable', 'The client registry is busy. Submit the decision again.', 503, 'UA-MCP-348' );
					}
					return $this->oauth_error( 'server_error', 'The approved client could not be saved for later disconnection.', 500, 'UA-MCP-319' );
				}

				$record = $this->store->consume( 'consent', $consent_id );
				if ( ! is_array( $record ) ) {
					return $this->oauth_error( 'invalid_request', 'The consent request was already used.', 400, 'UA-MCP-309' );
				}

				$code        = 'mac_' . str_replace( '-', '', wp_generate_uuid4() ) . '_' . wp_generate_password( 32, false, false );
				$code_record = array(
					'user_id'        => (int) $record['user_id'],
					'client_id'      => (string) $record['client_id'],
					'client_type'    => (string) $record['client_type'],
					'redirect_uri'   => (string) $record['redirect_uri'],
					'scopes'         => (array) $record['scopes'],
					'resource'       => (string) $record['resource'],
					'code_challenge' => (string) $record['code_challenge'],
					'issued_at'      => microtime( true ),
					'expires_at'     => time() + self::CODE_LIFETIME,
				);
				if ( ! $this->store->put( 'code', $code, $code_record ) ) {
					return $this->oauth_error( 'server_error', 'The authorization code could not be issued.', 500, 'UA-MCP-311' );
				}
				OAuth_Telemetry::record( 'authorization_code_issued', 'success', (string) $record['client_id'], get_current_user_id() );

				return $this->authorization_redirect( $record, array( 'code' => $code ) );
			}
		);
		return $result ?? $this->oauth_error( 'temporarily_unavailable', 'The client connection is busy. Submit the decision again.', 503, 'UA-MCP-347' );
	}

	/**
	 * Serve consent HTML without WordPress JSON encoding.
	 */
	public function serve_html( $served, $result, $request, $server ): bool {
		if ( $request instanceof WP_REST_Request && $this->is_authorization_route( $request ) ) {
			// Core reflects arbitrary REST origins with credentials. This browser
			// route needs cross-site navigation, but never CORS; remove core and
			// third-party grants after they have been emitted.
			foreach ( self::CORS_RESPONSE_HEADERS as $header ) {
				$server->remove_header( $header );
			}
		}

		if ( ! $result instanceof OAuth_Html_Response ) {
			return (bool) $served;
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Content-Security-Policy: ' . ( $result->get_content_security_policy() ?? self::CONSENT_CSP ) );
			header( 'X-Frame-Options: DENY' );
			header( 'Referrer-Policy: no-referrer' );
			// no-transform keeps CDN content rewriters (email obfuscation, script
			// injection) away from a page whose CSP would break them anyway.
			header( 'Cache-Control: no-store, no-transform' );
		}

		echo $result->get_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fully escaped server-generated document.
		return true;
	}

	/**
	 * Validate all client-controlled authorization fields before redirect trust.
	 *
	 * @return array|WP_REST_Response
	 */
	private function validate_authorization_request( WP_REST_Request $request ) {
		$client_id      = $this->query_param( $request, 'client_id' );
		$redirect_uri   = $this->query_param( $request, 'redirect_uri' );
		$response_type  = $this->query_param( $request, 'response_type' );
		$resource       = $this->requested_resource( $request );
		$challenge      = $this->query_param( $request, 'code_challenge' );
		$challenge_type = $this->query_param( $request, 'code_challenge_method' );
		$state          = $this->query_param( $request, 'state' );

		if ( '' === $client_id || strlen( $client_id ) > 2048 ) {
			return $this->oauth_error( 'invalid_request', 'A valid client_id is required.', 400, 'UA-MCP-312' );
		}
		$client = $this->clients->resolve( $client_id );
		if ( ! is_array( $client ) ) {
			$failure = $this->clients->get_last_resolution_failure();
			return $this->oauth_error(
				'unauthorized_client',
				'The OAuth client is unknown or revoked.',
				400,
				'UA-MCP-313',
				'' !== $failure ? 'The OAuth client could not be resolved: ' . $failure . '.' : ''
			);
		}
		$registered_redirects = (array) $client['redirect_uris'];
		$bind_redirect        = false;
		if ( array() === $registered_redirects && 'pre_registered' === ( $client['source'] ?? '' ) ) {
			// Registered without a redirect: the first administrator-approved
			// sign-in binds the presented address, so it must at least be one
			// registration itself would accept.
			if ( ! $this->clients->is_acceptable_redirect( $redirect_uri ) ) {
				return $this->oauth_error( 'invalid_request', 'The redirect_uri is not an acceptable address.', 400, 'UA-MCP-314' );
			}
			$bind_redirect = true;
		} elseif ( ! $this->redirect_uri_is_registered( $redirect_uri, $registered_redirects ) ) {
			return $this->oauth_error( 'invalid_request', 'The redirect_uri does not exactly match the client registration.', 400, 'UA-MCP-315' );
		}
		// Length-check state before any path that echoes it into a Location
		// header; an oversized value would otherwise ride three error redirects.
		if ( strlen( $state ) > 2048 ) {
			return $this->validated_request_error( $bind_redirect, $redirect_uri, '', 'invalid_request', 'The state value is too long.', 'UA-MCP-344' );
		}
		if ( 'code' !== $response_type ) {
			return $this->validated_request_error( $bind_redirect, $redirect_uri, $state, 'unsupported_response_type', 'Only the code response type is supported.', 'UA-MCP-341' );
		}
		if ( ! hash_equals( $this->configuration->get_resource(), $resource ) ) {
			return $this->validated_request_error( $bind_redirect, $redirect_uri, $state, 'invalid_target', 'The resource is not this MCP endpoint.', 'UA-MCP-342' );
		}
		if ( 'S256' !== $challenge_type || 1 !== preg_match( '/^[A-Za-z0-9._~-]{43,128}$/D', $challenge ) ) {
			return $this->validated_request_error( $bind_redirect, $redirect_uri, $state, 'invalid_request', 'PKCE S256 is required.', 'UA-MCP-343' );
		}

		try {
			$scopes = $this->scope_policy->parse( $this->query_param( $request, 'scope' ), Hosted_Agent_Access::allows_client( $client ) );
		} catch ( \InvalidArgumentException $error ) {
			return $this->validated_request_error( $bind_redirect, $redirect_uri, $state, 'invalid_scope', $error->getMessage(), 'UA-MCP-345' );
		}

		return array(
			'client_id'            => $client_id,
			'client_name'          => (string) $client['name'],
			'client_type'          => (string) $client['type'],
			'client_source'        => (string) ( $client['source'] ?? '' ),
			'client_redirect_uris' => $registered_redirects,
			'redirect_uri'         => $redirect_uri,
			'resource'             => $resource,
			'scopes'               => $scopes,
			'code_challenge'       => $challenge,
			'state'                => $state,
			'bind_redirect'        => $bind_redirect,
		);
	}

	/**
	 * The resource the client named, or this site's MCP endpoint when it named none.
	 *
	 * RFC 8707 makes the indicator optional, and this server protects one
	 * resource, so an omitted or empty value (RFC 6749 section 3.1) can only
	 * mean this site. A value that is present is returned as sent, so a wrong
	 * one is still refused.
	 */
	private function requested_resource( WP_REST_Request $request ): string {
		$params = $request->get_query_params();
		if ( ! isset( $params['resource'] ) || '' === $params['resource'] ) {
			return $this->configuration->get_resource();
		}

		return untrailingslashit( $this->query_param( $request, 'resource' ) );
	}

	/**
	 * Render the consent form in the approving administrator's own language.
	 *
	 * A REST route is neither wp-admin nor a JSON request, so WordPress would
	 * otherwise use the site locale for a page only this user sees.
	 */
	private function render_consent( string $consent_id, array $record, string $script ): ?string {
		$switched = switch_to_locale( get_user_locale( get_current_user_id() ) );
		try {
			return $this->render_consent_document( $consent_id, $record, $script );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * Render a minimal consent form whose only mutable field is the decision.
	 */
	private function render_consent_document( string $consent_id, array $record, string $script ): ?string {
		$display_client         = array(
			'name'          => $record['client_name'],
			'redirect_uris' => $record['client_redirect_uris'] ?? array(),
		);
		$is_hosted_app          = Hosted_Agent_Access::allows_client( $display_client );
		$client_name            = Hosted_Agent_Access::client_name( $display_client );
		$client_origin          = (string) wp_parse_url( (string) $record['client_id'], PHP_URL_HOST );
		$redirect_host          = (string) wp_parse_url( (string) $record['redirect_uri'], PHP_URL_HOST );
		$is_local_redirect      = in_array( $redirect_host, self::LOOPBACK_HOSTS, true );
		$remember_redirect      = ! empty( $record['bind_redirect'] );
		$capabilities           = $this->capability_descriptions( (array) $record['scopes'] );
		$is_agent_access        = Hosted_Agent_Access::requested( (array) $record['scopes'] );
		$user_display_name      = wp_get_current_user()->display_name;
		$site_url               = home_url( '/' );
		$authorization_endpoint = $this->configuration->get_authorization_endpoint();
		$static_root            = dirname( __DIR__ ) . '/static';
		$styles                 = $this->dialog_styles();
		$brand_mark             = $this->read_local_svg( $static_root . '/images/automator-icon.svg' );
		$view                   = $this->consent_view_path ?? dirname( __DIR__ ) . '/views/consent.php';

		if ( ! is_readable( $view ) ) {
			return null;
		}

		$buffer_level = ob_get_level();
		ob_start();
		try {
			// A consent view can render more than once in one request. Do not use include_once or require_once here.
			require $view;
			$html = ob_get_clean();
		} catch ( \Throwable $error ) {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
			return null;
		}

		return is_string( $html ) ? $html : null;
	}

	/**
	 * Expand protocol scopes into concrete, plain-language capabilities.
	 *
	 * A scope name is useful to a protocol client but too broad for meaningful
	 * administrator consent. Keep the read baseline explicit, then add only the
	 * capabilities enabled by the requested write and offline scopes.
	 *
	 * @return array<string, string[]> Scope-grouped descriptions; the consent view escapes them.
	 */
	// phpcs:disable Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Return translated text; the sole consumer escapes each description in the consent view.
	private function capability_descriptions( array $scopes ): array {
		$capabilities = array();

		if ( in_array( 'mcp:read', $scopes, true ) ) {
			$capabilities['read'] = array(
				_x( 'Read site content, activity logs and Automator recipes.', 'MCP consent capability', 'uncanny-automator' ),
				_x( 'Read code and files through the tools you allow.', 'MCP consent capability', 'uncanny-automator' ),
				_x( 'Read database tables and records, including Uncanny Automator records and logs, which can contain personal data.', 'MCP consent capability', 'uncanny-automator' ),
				_x( 'Find the tools, plugins and Automator actions available on this site.', 'MCP consent capability', 'uncanny-automator' ),
			);
		}

		if ( in_array( 'mcp:read', $scopes, true ) ) {
			$capabilities['read'][] = _x( 'Read information through supported WordPress REST API routes and enabled WordPress abilities provided by WordPress and other plugins.', 'MCP consent capability', 'uncanny-automator' );
		}

		if ( in_array( 'mcp:write', $scopes, true ) ) {
			$capabilities['write'][] = _x( 'Create, change and delete Automator recipes, and run actions such as publishing posts or sending emails.', 'MCP consent capability', 'uncanny-automator' );
			$capabilities['write'][] = _x( 'Use WordPress management tools to install and update plugins and themes, activate or deactivate plugins, switch themes, moderate comments and handle settings, translations, maintenance and routine upkeep.', 'MCP consent capability', 'uncanny-automator' );
			$capabilities['write'][] = _x( 'Make changes through supported WordPress REST API routes and enabled WordPress abilities, within your WordPress permissions.', 'MCP consent capability', 'uncanny-automator' );
		}

		if ( Hosted_Agent_Access::requested( $scopes ) ) {
			$capabilities['agent'][] = _x( 'Read and change posts, pages, media and Page Builder designs with your WordPress account permissions.', 'MCP consent capability', 'uncanny-automator' );
			$capabilities['agent'][] = _x( "Read this site's shared conversations and files from Uncanny Agent, and save private copies in your Uncanny Automator account. The copies stay in your account after you disconnect this site.", 'MCP consent capability', 'uncanny-automator' );
		}

		if ( in_array( 'offline_access', $scopes, true ) ) {
			$capabilities['offline'][] = sprintf(
				/* translators: %d: Number of days this connection can renew access. */
				_x( 'Renew access for up to %d days without signing in again.', 'MCP consent capability', 'uncanny-automator' ),
				(int) ( OAuth_Refresh_Token_Service::LIFETIME / DAY_IN_SECONDS )
			);
		}

		return $capabilities;
	}

	// phpcs:enable Uncanny_Automator.Strings.AutoContextTranslation.MissingContext

	/**
	 * Keep a denied first-use redirect on WordPress with a readable result.
	 */
	private function denied_consent_page(): OAuth_Html_Response {
		Mcp_Auth_Log::record(
			'authorization',
			'access_denied',
			array(
				'detail' => 'The administrator denied access.',
				'ref'    => 'UA-MCP-316',
			)
		);

		$switched = switch_to_locale( get_user_locale( get_current_user_id() ) );
		try {
			$body = '<div class="alert info"><p>' . esc_html__( 'You did not give this app access. You can close this page or return to the app to try again.', 'uncanny-automator' ) . '</p></div>';
			return $this->status_page( esc_html_x( 'App not connected', 'MCP OAuth dialog', 'uncanny-automator' ), $body, 403 );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/** Show the site's setup instructions before granting a hosted connection. */
	private function site_license_required_page( \WP_Error $error, array $authorization ): OAuth_Html_Response {
		// WordPress builds this site's admin URL, including multisite/subdirectory paths.
		$license_url = admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=general&general=license' );
		$link_url    = ( new OAuth_Account_Link() )->link_url( $authorization );
		if ( null !== $link_url ) {
			$body  = '<div class="alert"><p>' . esc_html__( 'WordPress could not find an Automator license for this site. Connect to automatorplugin.com to set up its free license.', 'uncanny-automator' ) . '</p></div>';
			$body .= '<p>' . esc_html__( 'Once that’s done, you’ll return here to review and approve the connection.', 'uncanny-automator' ) . '</p>';
		} else {
			$body  = '<div class="alert"><p>' . esc_html( $error->get_error_message() ) . '</p></div>';
			$body .= '<p>' . esc_html__( 'After setup, return to the Uncanny Automator web app where you started this connection and connect this site again.', 'uncanny-automator' ) . '</p>';
		}
		return $this->status_page(
			esc_html_x( 'Set up this site’s Automator license', 'MCP OAuth dialog', 'uncanny-automator' ),
			$body,
			403,
			null !== $link_url ? $link_url : $license_url,
			null !== $link_url ? esc_html_x( 'Connect to automatorplugin.com', 'MCP OAuth dialog', 'uncanny-automator' ) : esc_html_x( 'Open License settings', 'MCP OAuth dialog', 'uncanny-automator' ),
			esc_html_x( 'Connecting Uncanny Automator MCP to WordPress', 'MCP OAuth dialog', 'uncanny-automator' )
		);
	}

	/**
	 * Tell a signed-in non-administrator what is missing instead of looping
	 * through wp-login.
	 */
	private function administrator_required_page( int $user_id ): OAuth_Html_Response {
		Mcp_Auth_Log::record(
			'authorization',
			'administrator_required',
			array(
				'detail'  => 'A signed-in user without permission to manage options opened the authorization page.',
				'ref'     => 'UA-MCP-320',
				'user_id' => $user_id,
			)
		);

		$body = '<div class="alert"><p>' . esc_html_x( 'This WordPress account cannot approve app connections. Sign in with an administrator account to continue.', 'MCP OAuth access denied explanation', 'uncanny-automator' ) . '</p></div>';
		return $this->status_page(
			esc_html_x( 'An administrator must approve this connection', 'MCP OAuth access denied heading', 'uncanny-automator' ),
			$body,
			403,
			wp_login_url( $this->current_url(), true ),
			esc_html_x( 'Sign in as an administrator', 'MCP OAuth access denied link', 'uncanny-automator' )
		);
	}

	/**
	 * Explain a direct error to a person whose browser landed here.
	 *
	 * These errors happen before any return address is trusted, so the page
	 * never links or redirects to the app. Every value is escaped here.
	 */
	private function error_page( string $ref, string $description, int $status ): OAuth_Html_Response {
		$switched = switch_to_locale( get_user_locale( get_current_user_id() ) );
		try {
			list( $what_happened, $reason, $next_step ) = $this->error_page_text( $ref, $description );

			$body = '<div class="alert"><p>' . $what_happened . '</p>';
			if ( '' !== $reason ) {
				$body .= '<p>' . $reason . '</p>';
			}
			$body .= '</div><p>' . $next_step . '</p>';
			if ( '' !== $ref ) {
				/* translators: %s Support reference, for example UA-MCP-313. */
				$body .= '<p class="fine">' . sprintf( esc_html_x( 'Reference: %s', 'MCP OAuth error page reference', 'uncanny-automator' ), esc_html( $ref ) ) . '</p>';
			}
			return $this->status_page( esc_html_x( 'The app could not connect', 'MCP OAuth error page title', 'uncanny-automator' ), $body, $status );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * Render recovery screens with the same pinned header and footer as consent.
	 *
	 * Callers compose and escape body markup; action URLs are WordPress recovery
	 * destinations, never unverified client callbacks.
	 *
	 * @since 8.0.0
	 */
	private function status_page( string $dialog_title, string $body, int $status, string $action_url = '', string $action_label = '', string $connection_context = '' ): OAuth_Html_Response {
		$static_root       = dirname( __DIR__ ) . '/static';
		$styles            = $this->dialog_styles();
		$brand_mark        = $this->read_local_svg( $static_root . '/images/automator-icon.svg' );
		$site_url          = home_url( '/' );
		$user_display_name = wp_get_current_user()->display_name;
		if ( '' === $connection_context ) {
			// Error requests may not have a verified client identity yet.
			$connection_context = esc_html_x( 'Connecting an app to WordPress', 'MCP OAuth dialog', 'uncanny-automator' );
		}
		$buffer_level = ob_get_level();
		ob_start();
		try {
			require dirname( __DIR__ ) . '/views/status.php';
			$html = (string) ob_get_clean();
		} catch ( \Throwable $error ) {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
			// An incomplete plugin install must not break the error explaining it.
			$html = '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $dialog_title ) . '</title></head><body><h1>' . esc_html( $dialog_title ) . '</h1>' . $body . '</body></html>';
		}
		return new OAuth_Html_Response( $html, $status );
	}

	/**
	 * Embed the bundled font so consent has no external asset dependencies.
	 *
	 * @since 8.0.0
	 */
	private function dialog_styles(): string {
		$static_root  = dirname( __DIR__ ) . '/static';
		$font         = $this->read_local_file( $static_root . '/fonts/figtree-normal-6b96ce6d4783.woff2' );
		$encoded_font = base64_encode( $font ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Embed the bundled WOFF2, not executable code.
		return '@font-face{font-family:Figtree;font-style:normal;font-weight:300 900;font-display:swap;src:url(data:font/woff2;base64,' . $encoded_font . ') format("woff2")}'
			. $this->read_local_file( $static_root . '/css/consent.css' );
	}

	/**
	 * What happened, the known reason, and the next step, already escaped.
	 *
	 * @return string[]
	 */
	private function error_page_text( string $ref, string $description ): array {
		$connect_again = esc_html_x( 'Go back to the app and select Connect again.', 'MCP OAuth error page next step', 'uncanny-automator' );

		switch ( $ref ) {
			case 'UA-MCP-301':
				return array(
					esc_html_x( 'Apps cannot connect to this site right now.', 'MCP OAuth error page', 'uncanny-automator' ),
					esc_html( $description ),
					esc_html_x( 'After a site administrator fixes this, go back to the app and select Connect again.', 'MCP OAuth error page next step', 'uncanny-automator' ),
				);
			case 'UA-MCP-302':
			case 'UA-MCP-307':
				return array(
					esc_html_x( 'This site received too many connection attempts in the last minute.', 'MCP OAuth error page', 'uncanny-automator' ),
					'',
					esc_html_x( 'Wait a minute, then go back to the app and select Connect again.', 'MCP OAuth error page next step', 'uncanny-automator' ),
				);
			case 'UA-MCP-350':
				return array(
					esc_html_x( 'This site could not save a security check.', 'MCP OAuth error page', 'uncanny-automator' ),
					'',
					esc_html_x( 'Try again. If the problem continues, ask your web host to check WordPress database and object-cache storage.', 'MCP OAuth error page next step', 'uncanny-automator' ),
				);
			case 'UA-MCP-308':
				return array( esc_html_x( 'This approval page expired or was opened by a different account. Approval pages work for 10 minutes.', 'MCP OAuth error page', 'uncanny-automator' ), '', $connect_again );
			case 'UA-MCP-309':
				return array( esc_html_x( 'This approval was already submitted.', 'MCP OAuth error page', 'uncanny-automator' ), '', $connect_again );
			case 'UA-MCP-312':
			case 'UA-MCP-313':
				return array( esc_html_x( 'This site does not recognize the app that sent you here.', 'MCP OAuth error page', 'uncanny-automator' ), $this->client_resolution_reason( $ref ), $connect_again );
			case 'UA-MCP-314':
			case 'UA-MCP-315':
				return array( esc_html_x( 'The app’s return address does not match its registration on this site.', 'MCP OAuth error page', 'uncanny-automator' ), '', $connect_again );
			case 'UA-MCP-317':
				return array( esc_html_x( 'This app’s registration was disconnected on this site.', 'MCP OAuth error page', 'uncanny-automator' ), '', $connect_again );
			case 'UA-MCP-321':
				return array( esc_html_x( 'This app’s registration expired before the approval was saved.', 'MCP OAuth error page', 'uncanny-automator' ), '', $connect_again );
			case 'UA-MCP-347':
			case 'UA-MCP-348':
				return array( esc_html_x( 'This site was busy with another request for this app.', 'MCP OAuth error page', 'uncanny-automator' ), '', $connect_again );
		}

		return array( esc_html( $description ), '', $connect_again );
	}

	/**
	 * Why the client did not resolve, in words safe to show anyone.
	 */
	private function client_resolution_reason( string $ref ): string {
		$failure = 'UA-MCP-313' === $ref ? $this->clients->get_last_resolution_failure() : '';
		if ( 'revoked' === $failure ) {
			return esc_html_x( 'This app’s registration was disconnected on this site.', 'MCP OAuth error page', 'uncanny-automator' );
		}
		if ( 'unknown_client' === $failure ) {
			return esc_html_x( 'This app’s registration is not on this site.', 'MCP OAuth error page', 'uncanny-automator' );
		}
		if ( 0 === strpos( $failure, 'cimd_fetch_failed:' ) ) {
			return esc_html_x( 'This site could not download the app’s public description from the app’s address. Check that this site can make outbound HTTPS requests.', 'MCP OAuth error page', 'uncanny-automator' );
		}

		return '';
	}

	/**
	 * Whether the caller is a browser navigation that should get a readable page.
	 *
	 * Browsers ask for text/html when they navigate; API clients ask for JSON or
	 * send no preference. A tie keeps the JSON contract.
	 */
	private function prefers_html( WP_REST_Request $request ): bool {
		$html = 0.0;
		$json = 0.0;
		foreach ( explode( ',', strtolower( (string) $request->get_header( 'accept' ) ) ) as $range ) {
			$parts   = array_map( 'trim', explode( ';', $range ) );
			$quality = 1.0;
			foreach ( array_slice( $parts, 1 ) as $parameter ) {
				if ( 0 === strpos( $parameter, 'q=' ) ) {
					$quality = (float) substr( $parameter, 2 );
				}
			}
			if ( in_array( $parts[0], array( 'text/html', 'application/xhtml+xml' ), true ) ) {
				$html = max( $html, $quality );
			} elseif ( 'application/json' === $parts[0] ) {
				$json = max( $json, $quality );
			}
		}

		return $html > $json;
	}

	/**
	 * The user WordPress would recognise from the login cookie, if any.
	 */
	private function signed_in_user_id(): int {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			return $user_id;
		}

		return (int) wp_validate_auth_cookie( '', 'logged_in' );
	}


	/**
	 * Whether a URL is this site's own authorization route.
	 */
	private function is_authorization_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url( '/' ) );
		if (
			! is_array( $parts )
			|| ! is_array( $home )
			|| empty( $parts['host'] )
			|| strtolower( (string) $parts['host'] ) !== strtolower( (string) ( $home['host'] ?? '' ) )
		) {
			return false;
		}

		$path = untrailingslashit( (string) ( $parts['path'] ?? '' ) );
		if ( self::AUTHORIZE_ROUTE === substr( $path, -strlen( self::AUTHORIZE_ROUTE ) ) ) {
			return true;
		}

		parse_str( (string) ( $parts['query'] ?? '' ), $query );

		return isset( $query['rest_route'] )
			&& is_string( $query['rest_route'] )
			&& self::AUTHORIZE_ROUTE === untrailingslashit( $query['rest_route'] );
	}

	/**
	 * Read a bundled static file without creating a public asset request.
	 */
	private function read_local_file( string $path ): string {
		return is_readable( $path )
			? (string) file_get_contents( $path ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bundled plugin file, not a remote request.
			: '';
	}

	/**
	 * Read the bundled logo only when it contains an SVG document.
	 */
	private function read_local_svg( string $path ): string {
		$svg = $this->read_local_file( $path );

		return 0 === strpos( ltrim( $svg ), '<svg' ) ? $svg : '';
	}

	/**
	 * Redirect only to a previously exact-matched client URI.
	 */
	private function authorization_redirect( array $record, array $parameters ): WP_REST_Response {
		$parameters['iss'] = $this->configuration->get_issuer();
		if ( '' !== (string) ( $record['state'] ?? '' ) ) {
			$parameters['state'] = (string) $record['state'];
		}

		// add_query_arg() appends NEW parameters unencoded and re-encodes any
		// query the registered URI already carries, so it both corrupts a state
		// value containing & / + / # and rewrites bytes of an exact-matched
		// address. Build the query by hand: RFC 3986 encoding for our values,
		// the registered URI passed through untouched.
		$redirect_uri = (string) $record['redirect_uri'];
		$location     = $redirect_uri
			. ( false === strpos( $redirect_uri, '?' ) ? '?' : '&' )
			. http_build_query( $parameters, '', '&', PHP_QUERY_RFC3986 );

		return new WP_REST_Response(
			null,
			302,
			array(
				'Location'      => $location,
				'Cache-Control' => 'no-store',
			)
		);
	}

	/**
	 * Whether the presented redirect_uri matches one the client registered.
	 *
	 * Exact string match, with one exception: RFC 8252 section 7.3 requires
	 * accepting any port on loopback IP literal redirects, because native
	 * apps bind an ephemeral port for each authorization. Native apps that
	 * register http://localhost bind ports the same way, so it gets the same
	 * exception. Only the port may differ; scheme, host, path and query must
	 * match exactly.
	 */
	private function redirect_uri_is_registered( string $redirect_uri, array $registered ): bool {
		if ( in_array( $redirect_uri, $registered, true ) ) {
			return true;
		}

		$presented = $this->loopback_without_port( $redirect_uri );
		if ( null === $presented ) {
			return false;
		}

		foreach ( $registered as $candidate ) {
			if ( is_string( $candidate ) && $presented === $this->loopback_without_port( $candidate ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove only the port from a loopback redirect.
	 *
	 * @return string|null Null when the URI is not a plain http loopback redirect.
	 */
	private function loopback_without_port( string $uri ): ?string {
		$parts = wp_parse_url( $uri );
		if (
			! is_array( $parts )
			|| ! isset( $parts['scheme'], $parts['host'] )
			|| isset( $parts['fragment'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
		) {
			return null;
		}

		$scheme = (string) $parts['scheme'];
		$host   = (string) $parts['host'];
		if ( 'http' !== $scheme || ! in_array( $host, self::LOOPBACK_HOSTS, true ) ) {
			return null;
		}

		return 'http://' . $host
			. ( isset( $parts['path'] ) ? (string) $parts['path'] : '' )
			. ( isset( $parts['query'] ) ? '?' . (string) $parts['query'] : '' );
	}

	/**
	 * Error for a request whose redirect_uri validation stage is known.
	 *
	 * OAuth 2.1 permits an error redirect only when the redirect_uri is
	 * backed by a registration. During the first-use binding window the
	 * presented address has no registration behind it yet, so the error
	 * must stay on this site as JSON instead of becoming an open redirect.
	 */
	private function validated_request_error( bool $bind_redirect, string $redirect_uri, string $state, string $error, string $description, string $ref ): WP_REST_Response {
		if ( $bind_redirect ) {
			return $this->oauth_error( $error, $description, 400, $ref );
		}

		return $this->trusted_authorization_error( $redirect_uri, $state, $error, $description, $ref );
	}

	/**
	 * Build an error redirect after redirect_uri has been exact-matched.
	 */
	private function trusted_authorization_error( string $redirect_uri, string $state, string $error, string $description, string $ref = '' ): WP_REST_Response {
		Mcp_Auth_Log::record(
			'authorization',
			$error,
			array(
				'detail' => $description,
				'ref'    => $ref,
			)
		);
		if ( '' !== $ref ) {
			$description .= ' (' . $ref . ')';
		}

		return $this->authorization_redirect(
			array(
				'redirect_uri' => $redirect_uri,
				'state'        => $state,
			),
			array(
				'error'             => $error,
				'error_description' => $description,
			)
		);
	}

	/**
	 * Return a direct OAuth JSON error when no redirect is trusted.
	 */
	private function oauth_error( string $error, string $description, int $status, string $ref = '', string $log_detail = '' ): WP_REST_Response {
		$context = array(
			'detail' => '' !== $log_detail ? $log_detail : $description,
			'ref'    => $ref,
		);
		if ( current_user_can( 'manage_options' ) ) {
			Mcp_Auth_Log::record( 'authorization', $error, $context );
		} else {
			Mcp_Auth_Log::record_unauthenticated_failure( 'authorization', $error, $context );
		}
		if ( $this->html_errors ) {
			return $this->error_page( $ref, $description, $status );
		}
		if ( '' !== $ref ) {
			$description .= ' (' . $ref . ')';
		}

		return new WP_REST_Response(
			array(
				'error'             => $error,
				'error_description' => $description,
			),
			$status,
			array( 'Cache-Control' => 'no-store' )
		);
	}

	/**
	 * Detect the route WordPress will actually dispatch.
	 *
	 * REQUEST_URI is not authoritative here. A query-string rest_route takes
	 * precedence over the rewrite-derived path, so trusting the visible path
	 * could disable WordPress's REST cookie nonce check for a foreign route.
	 */
	private function is_current_authorization_request(): bool {
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) && is_string( $GLOBALS['wp']->query_vars['rest_route'] )
			? untrailingslashit( wp_unslash( $GLOBALS['wp']->query_vars['rest_route'] ) )
			: '';

		return self::AUTHORIZE_ROUTE === $route;
	}

	/**
	 * Whether the REST request targets this controller's exact route.
	 */
	private function is_authorization_route( WP_REST_Request $request ): bool {
		return self::AUTHORIZE_ROUTE === untrailingslashit( $request->get_route() );
	}

	/**
	 * Reconstruct the current URL for the WordPress login return target.
	 */
	private function current_url(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserved as an opaque login return URL and escaped by wp_login_url.
			? wp_unslash( $_SERVER['REQUEST_URI'] )
			: '';
		$home = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $home ) || ! isset( $home['scheme'], $home['host'] ) ) {
			return home_url( '/' );
		}

		$origin = strtolower( (string) $home['scheme'] ) . '://' . strtolower( (string) $home['host'] );
		if ( isset( $home['port'] ) ) {
			$origin .= ':' . (int) $home['port'];
		}

		return $origin . '/' . ltrim( $uri, '/' );
	}
	/**
	 * Query param.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param string $name The name.
	 * @return string
	 */
	private function query_param( WP_REST_Request $request, string $name ): string {
		$params = $request->get_query_params();
		return isset( $params[ $name ] ) && is_scalar( $params[ $name ] ) ? (string) $params[ $name ] : '';
	}
	/**
	 * Body param.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param string $name The name.
	 * @return string
	 */
	private function body_param( WP_REST_Request $request, string $name ): string {
		$params = $request->get_body_params();
		return isset( $params[ $name ] ) && is_scalar( $params[ $name ] ) ? (string) $params[ $name ] : '';
	}
}

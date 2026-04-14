<?php
namespace Uncanny_Automator\Recipe;

/**
 * Trait Closure_Redirect_Storage
 *
 * Handles storage and retrieval of closure redirects using transients.
 * Prevents race conditions by giving each user/client their own transient.
 * Implements safety caps to prevent database flooding during attacks.
 *
 * Uses WordPress transients for:
 * - Auto-expiration (60s TTL)
 * - Object cache support (Redis/Memcached)
 * - Simplified storage (no serialization overhead)
 *
 * Enforces 1000 transient limit via real-time database count check.
 *
 * @package Uncanny_Automator\Recipe
 * @since 6.8.0
 */
trait Closure_Redirect_Storage {

	/**
	 * Transient key prefix for storing pending redirects.
	 *
	 * Each user/client gets their own transient: closure_redirects_{identifier}
	 *
	 * @var string
	 */
	protected $redirects_transient_prefix = 'closure_redirects_';

	/**
	 * Option key for caching redirect closure existence.
	 *
	 * @var string
	 */
	protected $cache_option_key = 'has_redirect_closures';

	/**
	 * Redirect expiry time in seconds.
	 *
	 * @var int
	 */
	protected $redirect_expiry = 60;

	/**
	 * Maximum number of concurrent redirect transients allowed.
	 *
	 * Prevents database flooding during attacks or extreme traffic.
	 *
	 * @var int
	 */
	protected $max_redirect_transients = 1000;

	/**
	 * Whether a redirect was stored during the current request.
	 *
	 * Used by REST response filters to know when to add the header.
	 *
	 * @var bool
	 */
	protected $redirect_stored_this_request = false;

	/**
	 * The last redirect URL stored during this request.
	 *
	 * Needed by REST response filters which can't access the URL from the header.
	 *
	 * @var string
	 */
	protected $last_stored_redirect_url = '';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_wp_hooks() {
		add_action( 'save_post_uo-recipe', array( $this, 'clear_redirect_cache' ) );
		add_action( 'save_post_uo-closure', array( $this, 'clear_redirect_cache' ) );
		add_action( 'delete_post', array( $this, 'clear_redirect_cache' ) );

		// Add redirect header to REST API responses (WP_REST_Response objects).
		add_filter( 'rest_post_dispatch', array( $this, 'maybe_add_redirect_header_to_rest' ), 9999, 3 );

		// Expose custom header to fetch() API via Access-Control-Expose-Headers.
		add_filter( 'rest_pre_serve_request', array( $this, 'maybe_expose_redirect_header' ), 10, 4 );
	}

	/**
	 * Get unique identifier for current user/client.
	 *
	 * Uses a hierarchy of identifiers to prevent collisions:
	 * 1. Logged-in users: User ID (most secure)
	 * 2. Anonymous users: Client ID from cookie ONLY (set by JavaScript)
	 *
	 * Security: Client ID is ONLY accepted from cookie, never from URL parameter.
	 *
	 * @return string Empty string if no valid identifier found.
	 */
	protected function get_redirect_identifier() {
		// For logged-in users, always use user ID (most secure).
		if ( is_user_logged_in() ) {
			return 'user_' . get_current_user_id();
		}

		// For anonymous users, ONLY accept client ID from cookie (set by JS).
		// Do NOT accept from URL parameter to prevent abuse/flooding.
		$client_id_cookie = isset( $_COOKIE['automator_client_id'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['automator_client_id'] ) )
			: '';
		if ( ! empty( $client_id_cookie ) && preg_match( '/^[a-f0-9]{32}$/', $client_id_cookie ) ) {
			return 'client_' . $client_id_cookie;
		}

		// No valid identifier found - likely JS disabled/failed.
		// Redirect requires JavaScript, so no point storing redirect.
		automator_log(
			'Closure redirect failed: No client ID available. Redirect requires JavaScript to be enabled.',
			'closure-redirect-error',
			true,
			'closure-redirect'
		);

		return '';
	}

	/**
	 * Get the tab ID from the request header.
	 *
	 * Each browser tab sends a unique tab ID via X-Automator-Tab-Id header.
	 * This scopes redirect transients to the originating tab, preventing
	 * wrong-tab redirects when multiple tabs are open.
	 *
	 * @return string Validated tab ID (32 lowercase hex chars) or empty string.
	 */
	protected function get_tab_id() {
		$tab_id = isset( $_SERVER['HTTP_X_AUTOMATOR_TAB_ID'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_AUTOMATOR_TAB_ID'] ) )
			: '';

		// Validate format: 32 lowercase hex chars (matches JS generateClientId output).
		if ( '' !== $tab_id && 1 === preg_match( '/^[a-f0-9]{32}$/', $tab_id ) ) {
			return $tab_id;
		}

		return '';
	}

	/**
	 * Build the full transient key for a redirect, optionally scoped to a tab.
	 *
	 * @param string $identifier User/client identifier.
	 * @param string $tab_id     Tab ID (empty string for legacy/non-tab-scoped keys).
	 *
	 * @return string Transient key.
	 */
	private function build_redirect_transient_key( $identifier, $tab_id = '' ) {
		$key = $this->redirects_transient_prefix . $identifier;

		if ( '' !== $tab_id ) {
			$key .= '_tab_' . $tab_id;
		}

		return $key;
	}

	/**
	 * Store pending redirect in individual transient.
	 *
	 * Each user/client gets their own transient to prevent race conditions.
	 * When a tab ID is available, the transient is scoped to that specific tab,
	 * preventing wrong-tab redirects in multi-tab scenarios.
	 * Implements safety cap to prevent database flooding during attacks.
	 *
	 * @param string $identifier User/client identifier.
	 * @param string $redirect_url URL to redirect to.
	 *
	 * @return void
	 */
	private function store_pending_redirect( $identifier, $redirect_url ) {
		// Safety check: Prevent database flooding.
		if ( ! $this->check_redirect_limit() ) {
			automator_log(
				sprintf(
					'Closure redirect storage limit exceeded (%d max). Possible attack or extreme traffic.',
					$this->max_redirect_transients
				),
				'closure-redirect-limit',
				true,
				'closure-redirect'
			);
			return;
		}

		// Include tab ID in the transient key for per-tab isolation.
		$tab_id        = $this->get_tab_id();
		$transient_key = $this->build_redirect_transient_key( $identifier, $tab_id );

		set_transient( $transient_key, $redirect_url, $this->redirect_expiry );

		// Signal the redirect URL to the client via HTTP response header.
		// This piggybacks on the triggering AJAX/REST response — zero extra requests.
		// Safe for POST to admin-ajax.php and REST endpoints (never cached by CDNs).
		if ( 'cli' !== PHP_SAPI && ! headers_sent() ) {
			header( 'X-Automator-Redirect: ' . esc_url_raw( $redirect_url ) );
		}

		$this->last_stored_redirect_url     = $redirect_url;
		$this->redirect_stored_this_request = true;
	}

	/**
	 * Get pending redirect for identifier.
	 *
	 * Tries the tab-scoped transient first (per-tab isolation), then falls back
	 * to the legacy key (no tab suffix) for backward compatibility with redirects
	 * stored without a tab ID (non-jQuery requests, regular form POSTs).
	 *
	 * Deletes the transient after retrieval (one-time use).
	 *
	 * @param string $identifier User/client identifier.
	 *
	 * @return string|false Redirect URL or false if not found.
	 */
	private function get_pending_redirect( $identifier ) {
		$tab_id = $this->get_tab_id();

		// Try tab-scoped key first (exact tab match).
		if ( '' !== $tab_id ) {
			$tab_key      = $this->build_redirect_transient_key( $identifier, $tab_id );
			$redirect_url = get_transient( $tab_key );

			if ( false !== $redirect_url ) {
				delete_transient( $tab_key );

				return $redirect_url;
			}
		}

		// Fallback: legacy key without tab ID (graceful degradation).
		$legacy_key   = $this->build_redirect_transient_key( $identifier );
		$redirect_url = get_transient( $legacy_key );

		if ( false === $redirect_url ) {
			return false;
		}

		delete_transient( $legacy_key );

		return $redirect_url;
	}

	/**
	 * Check if we're under the redirect storage limit.
	 *
	 * Performs real-time count check on every call to immediately detect attacks.
	 * No caching - simple, predictable, and fast enough for our needs.
	 *
	 * Counts transients in wp_options table (stored as _transient_{name}).
	 *
	 * @return bool True if under limit, false if limit exceeded.
	 */
	private function check_redirect_limit() {
		global $wpdb;

		// Direct count query - simple and real-time.
		// Transients are stored in wp_options as _transient_{name}.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $this->redirects_transient_prefix ) . '%'
			)
		);

		return (int) $count < $this->max_redirect_transients;
	}

	/**
	 * Check if any redirect closures are configured in active recipes.
	 *
	 * Uses Automator option caching to avoid expensive queries on every page load.
	 *
	 * @return bool True if redirect closures exist, false otherwise.
	 */
	private function has_redirect_closures() {
		// Check cache first.
		$cached = automator_get_option( $this->cache_option_key, false );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		// Query for redirect closures in published recipes.
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND p.post_parent IN (
				SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = 'publish'
			)
			AND pm.meta_key = 'code'
			AND pm.meta_value = %s
			LIMIT 1",
			AUTOMATOR_POST_TYPE_CLOSURE,
			AUTOMATOR_POST_TYPE_RECIPE,
			$this->get_closure_code()
		);

		$has_closures = (int) $wpdb->get_var( $query ) > 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Cache result (cleared when recipes/closures are modified).
		automator_update_option( $this->cache_option_key, $has_closures ? 1 : 0 );

		return $has_closures;
	}

	/**
	 * Clear the redirect closures cache.
	 *
	 * Called when recipes or closures are saved/deleted to ensure cache stays accurate.
	 *
	 * @return void
	 */
	public function clear_redirect_cache() {
		automator_delete_option( $this->cache_option_key );
	}

	/**
	 * Add redirect header to WP REST API responses.
	 *
	 * Uses WP_REST_Response::header() so the header is included
	 * even when WordPress builds the response object internally.
	 *
	 * @param \WP_REST_Response $response REST response object.
	 * @param \WP_REST_Server   $server   REST server instance.
	 * @param \WP_REST_Request  $request  REST request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function maybe_add_redirect_header_to_rest( $response, $server, $request ) {
		if ( $this->redirect_stored_this_request && $response instanceof \WP_REST_Response ) {
			$response->header( 'X-Automator-Redirect', esc_url_raw( $this->last_stored_redirect_url ) );
		}

		return $response;
	}

	/**
	 * Expose the custom redirect header to fetch() API consumers.
	 *
	 * By default, fetch() only exposes CORS-safelisted headers.
	 * This adds X-Automator-Redirect to Access-Control-Expose-Headers
	 * so client-side JavaScript can read it from fetch responses.
	 *
	 * The `false` second argument to header() appends rather than replaces,
	 * preserving WordPress's own exposed headers (X-WP-Total, etc.).
	 *
	 * @param bool             $served  Whether the request has been served.
	 * @param \WP_REST_Response $result  REST response object.
	 * @param \WP_REST_Request  $request REST request object.
	 * @param \WP_REST_Server   $server  REST server instance.
	 *
	 * @return bool
	 */
	public function maybe_expose_redirect_header( $served, $result, $request, $server ) {
		if ( $this->redirect_stored_this_request ) {
			header( 'Access-Control-Expose-Headers: X-Automator-Redirect', false );
		}

		return $served;
	}
}

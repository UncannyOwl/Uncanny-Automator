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
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_wp_hooks() {
		add_action( 'save_post_uo-recipe', array( $this, 'clear_redirect_cache' ) );
		add_action( 'save_post_uo-closure', array( $this, 'clear_redirect_cache' ) );
		add_action( 'delete_post', array( $this, 'clear_redirect_cache' ) );
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
	 * Store pending redirect in individual transient.
	 *
	 * Each user/client gets their own transient to prevent race conditions.
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

		// Store in individual transient (no race condition possible, auto-expires).
		$transient_key = $this->redirects_transient_prefix . $identifier;

		set_transient( $transient_key, $redirect_url, $this->redirect_expiry );
	}

	/**
	 * Get pending redirect for identifier.
	 *
	 * Retrieves redirect from individual transient and deletes it (one-time use).
	 *
	 * @param string $identifier User/client identifier.
	 *
	 * @return string|false Redirect URL or false if not found.
	 */
	private function get_pending_redirect( $identifier ) {
		$transient_key = $this->redirects_transient_prefix . $identifier;
		$redirect_url  = get_transient( $transient_key );

		if ( false === $redirect_url ) {
			return false;
		}

		// Delete transient (one-time use).
		delete_transient( $transient_key );

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
				WHERE post_type = 'uo-recipe'
				AND post_status = 'publish'
			)
			AND pm.meta_key = 'code'
			AND pm.meta_value = %s
			LIMIT 1",
			'uo-closure',
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
}

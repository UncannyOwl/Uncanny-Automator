<?php

namespace Uncanny_Automator\Integrations\Redirect;

use Uncanny_Automator\Utilities;

/**
 * Class Redirect_Delivery
 *
 * Gets a redirect URL to the visitor's browser once per request, after the run's synchronous leg finished.
 *
 * Three strategies, chosen by request context: a direct 302 when headers are not sent and the request is
 * neither AJAX nor REST; a per-visitor, per-tab transient plus an `X-Automator-Redirect` response header for
 * AJAX and REST, read by the front-end script; an inline script when headers already went out. The transient
 * is the fallback the script polls when a late `shutdown` pass ran the recipe after the response was flushed.
 *
 * GDPR: no persistent storage; the anonymous client id is a session cookie the script sets.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator
 */
class Redirect_Delivery {

	/**
	 * Transient key prefix; each visitor (and tab) gets its own key.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'automator_redirects_';

	/**
	 * Seconds a pending redirect survives.
	 *
	 * @var int
	 */
	const REDIRECT_EXPIRY = 60;

	/**
	 * Cap on concurrent anonymous redirect transients; defends `wp_options` against cookie flooding.
	 *
	 * @var int
	 */
	const MAX_REDIRECT_TRANSIENTS = 1000;

	/**
	 * Shared instance the helper registers and the action talks to.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Whether hooks were registered in this request.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * `recipe_log_id => url` recorded by Redirect actions this request.
	 *
	 * @var array<int, string>
	 */
	private $pending = array();

	/**
	 * The URL delivered in this request, if any; later deliveries are dropped.
	 *
	 * @var string
	 */
	private $delivered = '';

	/**
	 * The run whose redirect was delivered in this request; 0 before any delivery.
	 *
	 * @var int
	 */
	private $delivered_for = 0;

	/**
	 * Whether a transient was stored this request; REST response filters add the header when true.
	 *
	 * @var bool
	 */
	private $redirect_stored_this_request = false;

	/**
	 * The URL stored this request, for the REST response header.
	 *
	 * @var string
	 */
	private $last_stored_redirect_url = '';

	/**
	 * The shared instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * No side effects; {@see self::register_hooks()} wires WordPress.
	 */
	public function __construct() {
	}

	/**
	 * Register every hook once per request: delivery, script, AJAX fallback, REST headers.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}
		self::$hooks_registered = true;
		add_action( 'automator_recipe_run_leg_finished', array( $this, 'on_leg_finished' ), 10, 1 );
		add_action( 'wp_loaded', array( $this, 'enqueue_script' ) );
		add_action( 'wp_ajax_automator_check_redirect', array( $this, 'ajax_check_redirect' ) );
		add_action( 'wp_ajax_nopriv_automator_check_redirect', array( $this, 'ajax_check_redirect' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'maybe_add_redirect_header_to_rest' ), 9999, 3 );
		add_filter( 'rest_pre_serve_request', array( $this, 'maybe_expose_redirect_header' ), 10, 4 );
	}

	/**
	 * Whether this request has a browser to redirect: not CLI, cron or Action Scheduler, not a background-job
	 * retry, poll or platform callback (each settles a run with nobody waiting on the response), and not a loop
	 * entity.
	 *
	 * @param array $action_data The action row; `loop` marks loop scope.
	 *
	 * @return bool
	 */
	public function can_deliver_in_this_request( array $action_data ): bool {
		if ( isset( $action_data['loop'] ) ) {
			return false;
		}
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
			return false;
		}
		if ( did_action( 'action_scheduler_before_execute' ) > did_action( 'action_scheduler_after_execute' ) ) {
			return false;
		}
		if ( doing_action( 'automator_background_site_retry' ) || doing_action( 'automator_background_job_poll' ) || doing_action( 'automator_platform_async_completed' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Validate a redirect URL: relative paths and http(s) absolute URLs only.
	 *
	 * @param string $url The parsed field value.
	 *
	 * @return string The URL, or '' when unsafe.
	 */
	public function validate_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return $url;
		}
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return '';
		}
		$parsed = wp_parse_url( $url );
		if ( ! isset( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Record the URL a run wants delivered. One per run; the first wins.
	 *
	 * @param int    $recipe_log_id The run.
	 * @param string $url           A validated URL.
	 *
	 * @return bool False when the run already holds a URL.
	 */
	public function set_pending( int $recipe_log_id, string $url ): bool {
		if ( isset( $this->pending[ $recipe_log_id ] ) ) {
			return false;
		}
		$this->pending[ $recipe_log_id ] = $url;
		return true;
	}

	/**
	 * The URL a run holds, if any.
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return string|null
	 */
	public function pending_for( int $recipe_log_id ): ?string {
		return $this->pending[ $recipe_log_id ] ?? null;
	}

	/**
	 * Whether a run other than this one already recorded or delivered a redirect in this request. Recipes started by one
	 * event run one after another in the request, and only the first redirect is delivered.
	 *
	 * @since 8.0.3
	 *
	 * @param int $recipe_log_id The run asking.
	 *
	 * @return bool
	 */
	public function redirected_by_another_run( int $recipe_log_id ): bool {
		if ( '' !== $this->delivered && $this->delivered_for !== $recipe_log_id ) {
			return true;
		}
		foreach ( array_keys( $this->pending ) as $run ) {
			if ( $run !== $recipe_log_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Terminal-event listener: deliver the run's pending redirect once its synchronous leg finished.
	 *
	 * @param object $result The leg's `Pipeline_Result`.
	 *
	 * @return void
	 */
	public function on_leg_finished( $result ): void {
		if ( ! is_object( $result ) || ! method_exists( $result, 'is_execution_ready' ) || ! method_exists( $result, 'get_recipe_log_id' ) ) {
			return;
		}
		if ( ! $result->is_execution_ready() ) {
			return;
		}
		$this->deliver( (int) $result->get_recipe_log_id() );
	}

	/**
	 * Deliver a run's pending redirect through the strategy the request allows. Once per request.
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return void
	 */
	public function deliver( int $recipe_log_id ): void {
		$url = $this->pending[ $recipe_log_id ] ?? '';
		unset( $this->pending[ $recipe_log_id ] );
		if ( '' === $url ) {
			return;
		}
		if ( '' !== $this->delivered ) {
			automator_log( sprintf( 'Redirect to %s for run %d dropped: this request already redirected to %s.', $url, $recipe_log_id, $this->delivered ), 'redirect' );
			return;
		}
		$this->delivered     = $url;
		$this->delivered_for = $recipe_log_id;
		$this->nudge_cron();

		switch ( $this->get_redirect_strategy() ) {
			case 'direct':
				wp_redirect( $this->encode_for_core_redirect( $url ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external targets are the point of the action; the URL passed validate_url().
				// No exit during shutdown: the trigger queue is still draining other recipes and other plugins' shutdown
				// work is still to come. WordPress has already set its own status by now, so the 302 stands.
				if ( $this->is_in_shutdown() ) {
					$this->finish_response();
					return;
				}
				// During `wp_loaded` the queue's pass may still hold other recipes: exit at the next priority, after the
				// pass and before WordPress resets the status for the page, so later redirects at `wp_loaded` lose to ours.
				$priority = $this->current_wp_loaded_priority();
				if ( null !== $priority && PHP_INT_MAX !== $priority ) {
					add_action( 'wp_loaded', array( $this, 'exit_after_redirect' ), $priority + 1 );
					return;
				}
				exit;
			case 'transient':
				$identifier = $this->get_redirect_identifier();
				if ( '' !== $identifier ) {
					$this->store_pending_redirect( $identifier, $url );
				}
				return;
			default:
				$this->output_redirect_script( $url );
				return;
		}
	}

	/**
	 * Ask WP-Cron to run now. The Redirect's own one-second job is due by the time the loopback lands, so the
	 * rest of the run starts in that request instead of on the next visit. `spawn_cron()` is a no-op while another
	 * spawn is fresh or when `DISABLE_WP_CRON` is set; the event then runs on the next cron opportunity and the
	 * poll re-arms it if it is ever lost. Non-blocking, so the redirect is not held up.
	 *
	 * @return void
	 */
	private function nudge_cron(): void {
		spawn_cron( time() + 2 );
	}

	/**
	 * Send the redirect response now, where the server can, so the visitor is not held while the rest of the
	 * request runs: other recipes the queue still holds and other plugins' shutdown work. The output buffers are
	 * flushed first, as WordPress does at shutdown, or the buffered response would be lost. Elsewhere the
	 * response goes out when the request ends.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	protected function finish_response(): void {
		// Once the response is closed, PHP ends the request at the next output unless it ignores the visitor's abort,
		// and the work still to run may print. WordPress does the same before finishing a cron request.
		ignore_user_abort( true );
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			wp_ob_end_flush_all();
			fastcgi_finish_request();
			return;
		}
		if ( function_exists( 'litespeed_finish_request' ) ) {
			wp_ob_end_flush_all();
			litespeed_finish_request();
		}
	}

	/**
	 * Enqueue the front-end script on `wp_loaded`. Front-end, cron and REST requests include this integration only
	 * when a published recipe carries a Redirect action, so that is when the script ships; the recipe editor and
	 * the Automator admin pages load every integration.
	 *
	 * @return void
	 */
	public function enqueue_script(): void {
		Utilities::enqueue_asset(
			'uap-redirect',
			'redirect',
			array(
				'localize' => array(
					'automatorRedirect' => array(
						'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
						'nonce'          => wp_create_nonce( 'automator_redirect' ),
						'fallbackEvents' => $this->fallback_events(),
					),
				),
			)
		);
	}

	/**
	 * Browser events after which the script asks the server for a pending redirect.
	 *
	 * A form submitted with fetch() never reaches the script's jQuery listeners, so the script cannot read the
	 * redirect header off its response; the event the form plugin fires once the response is back is the cue.
	 * Contact Form 7's `wpcf7submit` is the only such event known, so it is listed here; register them properly
	 * if more come up.
	 *
	 * @since 8.0.0
	 *
	 * @return string[]
	 */
	private function fallback_events(): array {
		return array( 'wpcf7submit' );
	}

	/**
	 * AJAX fallback: hand the visitor's pending transient to the script, once.
	 *
	 * @return void
	 */
	public function ajax_check_redirect(): void {
		if ( ! $this->verify_ajax_nonce() ) {
			wp_send_json_error();
		}
		$url = $this->get_pending_redirect( $this->get_redirect_identifier() );
		if ( '' !== $url ) {
			wp_send_json_success( array( 'redirect_url' => $url ) );
		}
		wp_send_json_error();
	}

	/**
	 * Add the redirect header to a REST response built in this request.
	 *
	 * @param mixed $response The response.
	 * @param mixed $server   The server.
	 * @param mixed $request  The request.
	 *
	 * @return mixed
	 */
	public function maybe_add_redirect_header_to_rest( $response, $server, $request ) {
		if ( $this->redirect_stored_this_request && $response instanceof \WP_REST_Response ) {
			$response->header( 'X-Automator-Redirect', esc_url_raw( $this->last_stored_redirect_url ) );
		}
		return $response;
	}

	/**
	 * Expose the redirect header to fetch() consumers.
	 *
	 * @param mixed $served  Whether served.
	 * @param mixed $result  The response.
	 * @param mixed $request The request.
	 * @param mixed $server  The server.
	 *
	 * @return mixed
	 */
	public function maybe_expose_redirect_header( $served, $result, $request, $server ) {
		if ( $this->redirect_stored_this_request && ! headers_sent() ) {
			header( 'Access-Control-Expose-Headers: X-Automator-Redirect', false );
		}
		return $served;
	}

	/**
	 * Forget request state. Tests only.
	 *
	 * @internal
	 *
	 * @return void
	 */
	public function reset_for_tests(): void {
		$this->pending                      = array();
		$this->delivered                    = '';
		$this->delivered_for                = 0;
		$this->redirect_stored_this_request = false;
		$this->last_stored_redirect_url     = '';
	}

	/**
	 * Seam for tests: the bootstrap flips headers_sent() permanently.
	 *
	 * @return bool
	 */
	protected function are_headers_sent(): bool {
		return headers_sent();
	}

	/**
	 * Whether the request is in its `shutdown` pass, where the trigger queue drains the triggers a page fired.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	protected function is_in_shutdown(): bool {
		return doing_action( 'shutdown' );
	}

	/**
	 * The `wp_loaded` priority running now, where the trigger queue makes its early pass.
	 *
	 * @since 8.0.0
	 *
	 * @return int|null Null outside `wp_loaded`.
	 */
	protected function current_wp_loaded_priority(): ?int {
		global $wp_filter;
		if ( ! doing_action( 'wp_loaded' ) || ! isset( $wp_filter['wp_loaded'] ) ) {
			return null;
		}
		$priority = $wp_filter['wp_loaded']->current_priority();

		return false === $priority ? null : (int) $priority;
	}

	/**
	 * End the request once the `wp_loaded` priority that delivered a redirect is done.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function exit_after_redirect(): void {
		exit;
	}

	/**
	 * Seam for tests: the nonce arrives in the query string, which a CLI test request cannot carry.
	 *
	 * @return bool
	 */
	protected function verify_ajax_nonce(): bool {
		$verified = wp_verify_nonce( automator_filter_input( 'nonce', INPUT_GET ), 'automator_redirect' );

		return false !== $verified;
	}

	/**
	 * Seam for tests: REST_REQUEST is a constant, so once any test defines it the rest of the run is a REST request.
	 *
	 * @return bool
	 */
	protected function is_rest_request(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Which strategy the request allows.
	 *
	 * @return string `direct`, `transient` or `javascript`.
	 */
	protected function get_redirect_strategy(): string {
		$is_rest = $this->is_rest_request();
		if ( ! $this->are_headers_sent() && ! wp_doing_ajax() && ! $is_rest ) {
			return 'direct';
		}
		if ( wp_doing_ajax() || $is_rest ) {
			return 'transient';
		}
		return 'javascript';
	}

	/**
	 * The visitor's identifier: the user id, or the 32-hex session client id the script set.
	 *
	 * @return string '' when no identifier is available.
	 */
	protected function get_redirect_identifier(): string {
		if ( is_user_logged_in() ) {
			return 'user_' . get_current_user_id();
		}
		$client_id = isset( $_COOKIE['automator_client_id'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['automator_client_id'] ) ) : '';
		if ( '' !== $client_id && 1 === preg_match( '/^[a-f0-9]{32}$/', $client_id ) ) {
			return 'client_' . $client_id;
		}
		automator_log( 'Redirect skipped: no client id cookie; the visitor needs JavaScript for the redirect.', 'redirect' );
		return '';
	}

	/**
	 * The tab id the script sends, so a redirect lands in the tab that caused it.
	 *
	 * @return string '' when absent or malformed.
	 */
	protected function get_tab_id(): string {
		$tab_id = isset( $_SERVER['HTTP_X_AUTOMATOR_TAB_ID'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_AUTOMATOR_TAB_ID'] ) ) : '';
		return ( '' !== $tab_id && 1 === preg_match( '/^[a-f0-9]{32}$/', $tab_id ) ) ? $tab_id : '';
	}

	/**
	 * Store the visitor's pending redirect and signal it on the response.
	 *
	 * @param string $identifier The visitor.
	 * @param string $url        The URL.
	 *
	 * @return void
	 */
	private function store_pending_redirect( string $identifier, string $url ): void {
		if ( 0 === strpos( $identifier, 'client_' ) && ! $this->under_redirect_limit() ) {
			automator_log( sprintf( 'Redirect storage limit exceeded (%d max). Possible flooding.', self::MAX_REDIRECT_TRANSIENTS ), 'redirect' );
			return;
		}
		set_transient( $this->transient_key( $identifier, $this->get_tab_id() ), $url, self::REDIRECT_EXPIRY );
		if ( 'cli' !== PHP_SAPI && ! headers_sent() ) {
			header( 'X-Automator-Redirect: ' . esc_url_raw( $url ) );
		}
		$this->last_stored_redirect_url     = $url;
		$this->redirect_stored_this_request = true;
	}

	/**
	 * The visitor's pending redirect, tab-scoped first, then the legacy unscoped key. One-time read.
	 *
	 * @param string $identifier The visitor.
	 *
	 * @return string '' when none.
	 */
	private function get_pending_redirect( string $identifier ): string {
		if ( '' === $identifier ) {
			return '';
		}
		foreach ( array_unique( array( $this->transient_key( $identifier, $this->get_tab_id() ), $this->transient_key( $identifier, '' ) ) ) as $key ) {
			$url = get_transient( $key );
			if ( false !== $url ) {
				delete_transient( $key );
				return (string) $url;
			}
		}
		return '';
	}

	/**
	 * The transient key for a visitor and tab.
	 *
	 * @param string $identifier The visitor.
	 * @param string $tab_id     The tab, or '' for the unscoped key.
	 *
	 * @return string
	 */
	private function transient_key( string $identifier, string $tab_id ): string {
		return self::TRANSIENT_PREFIX . $identifier . ( '' !== $tab_id ? '_tab_' . $tab_id : '' );
	}

	/**
	 * Whether anonymous redirect transients are under the cap.
	 *
	 * @return bool
	 */
	private function under_redirect_limit(): bool {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_' . self::TRANSIENT_PREFIX . 'client_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- real-time count is the point.
		return (int) $count < self::MAX_REDIRECT_TRANSIENTS;
	}

	/**
	 * Inline redirect for a page whose headers already went out; handles the iframe case.
	 *
	 * @param string $url The URL.
	 *
	 * @return void
	 */
	private function output_redirect_script( string $url ): void {
		printf(
			'<!-- Uncanny Automator Redirect --><script>(function(){var url=%s;try{if(window.parent&&window.parent!==window&&!window.parent.location.href.match(/\/wp-admin\//)){window.parent.location.href=url;}else{window.location.href=url;}}catch(e){window.location.href=url;}})();</script>',
			wp_json_encode( $url ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded for a script context.
		);
	}

	/**
	 * Percent-encode the characters `wp_sanitize_redirect()` strips ($, |, ').
	 *
	 * @param string $url A validated URL.
	 *
	 * @return string
	 */
	private function encode_for_core_redirect( string $url ): string {
		return strtr(
			$url,
			array(
				'$' => '%24',
				'|' => '%7C',
				"'" => '%27',
			)
		);
	}
}

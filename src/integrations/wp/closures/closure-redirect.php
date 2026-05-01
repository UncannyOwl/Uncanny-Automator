<?php

namespace Uncanny_Automator;

/**
 * Class CLOSURE_REDIRECT
 *
 * @package Uncanny_Automator
 */
class Closure_Redirect {
	use Recipe\Closure;
	use Recipe\Closure_Redirect_Storage;

	/**
	 * Closure_Redirect constructor.
	 */
	public function __construct() {
		$this->setup_closure();

		add_action( 'wp_loaded', array( $this, 'add_script' ) );
		// Add AJAX actions for checking closure redirect when triggered from AJAX request.
		add_action( 'wp_ajax_automator_check_closure_redirect', array( $this, 'ajax_check_closure_redirect' ) );
		add_action( 'wp_ajax_nopriv_automator_check_closure_redirect', array( $this, 'ajax_check_closure_redirect' ) );

		$this->register_wp_hooks();
	}

	/**
	 * Setup closure.
	 *
	 * @throws Automator_Exception
	 */
	protected function setup_closure() {
		$this->set_integration( 'WP' );
		$this->set_closure_code( 'REDIRECT' );
		$this->set_closure_meta( 'REDIRECTURL' );
		/* translators: Closure - WordPress */
		$this->set_sentence( sprintf( esc_attr_x( 'Redirect to {{a link:%1$s}} when recipe is completed', 'WordPress', 'uncanny-automator' ), $this->get_closure_meta() ) );
		/* translators: Closure - WordPress */
		$this->set_readable_sentence( esc_attr_x( 'Redirect when recipe is completed', 'WordPress', 'uncanny-automator' ) );
		$this->set_options( Automator()->helpers->recipe->get_redirect_url() );
		$this->register_closure();
	}

	/**
	 * Execute redirect closure.
	 *
	 * @param int   $user_id User ID.
	 * @param array $closure_data Closure configuration data.
	 * @param int   $recipe_id Recipe ID.
	 * @param array $args Additional arguments.
	 *
	 * @return void
	 */
	public function redirect( $user_id, $closure_data, $recipe_id, $args ) {
		$redirect_url_raw = $closure_data['meta'][ $this->get_closure_meta() ] ?? '';

		if ( empty( $redirect_url_raw ) ) {
			return;
		}

		// Parse tokens in the URL.
		$redirect_url_parsed = Automator()->parse->text( $redirect_url_raw, $recipe_id, $user_id, $args );

		// SECURITY: Validate redirect URL to prevent open redirect and XSS attacks.
		$redirect_url = $this->validate_redirect_url( $redirect_url_parsed );

		// If validation failed, log and abort.
		if ( empty( $redirect_url ) ) {
			automator_log(
				sprintf(
					'Closure redirect blocked: Invalid or unsafe URL "%s" (parsed from "%s")',
					$redirect_url_parsed,
					$redirect_url_raw
				),
				'closure-redirect-security',
				true,
				'closure-redirect'
			);
			return;
		}

		// Log the redirect for debugging.
		Automator()->db->closure->add_entry_meta(
			array(
				'user_id'                  => isset( $args['user_id'] ) ? $args['user_id'] : null,
				'automator_closure_id'     => isset( $closure_data['ID'] ) ? $closure_data['ID'] : null,
				'automator_closure_log_id' => isset( $args['closure_log_id'] ) ? $args['closure_log_id'] : null,
			),
			'field_values',
			wp_json_encode(
				array(
					'raw'       => $redirect_url_raw,
					'parsed'    => $redirect_url_parsed,
					'validated' => $redirect_url,
				)
			)
		);

		$this->set_cookie( $redirect_url );
	}

	/**
	 * Enqueue redirect script if redirect closures are configured.
	 *
	 * Reads X-Automator-Redirect response header from AJAX responses.
	 * Regular page loads use direct JavaScript injection (no script needed).
	 *
	 * @return void
	 */
	public function add_script() {

		// Performance optimization: Only load script if redirect closures exist.
		if ( ! $this->has_redirect_closures() ) {
			return;
		}

		Utilities::enqueue_asset(
			'uap-closure',
			'closure',
			array(
				'localize' => array(
					'automatorClosure' => array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'automator_closure_redirect' ),
					),
				),
			)
		);
	}

	/**
	 * Execute redirect using hybrid session-only approach.
	 *
	 * GDPR Compliance:
	 * - Regular page loads: Zero cookies (direct JavaScript injection)
	 * - AJAX requests: Session-only cookie (deleted on browser close, no persistent tracking)
	 *
	 * @param string $redirect_url Validated redirect URL.
	 *
	 * @return void
	 */
	public function set_cookie( $redirect_url ) {

		$strategy = $this->get_redirect_strategy();

		switch ( $strategy ) {
			case 'direct':
				// Strategy 1: Direct PHP redirect. Only works if headers not sent AND not AJAX/REST.
				wp_redirect( $this->encode_for_core_redirect( $redirect_url ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External URLs are valid closure redirect targets.
				exit;

			case 'transient':
				// Strategy 2: For AJAX/REST requests, use transient + response header signal.
				$identifier = $this->get_redirect_identifier();
				if ( ! empty( $identifier ) ) {
					$this->store_pending_redirect( $identifier, $redirect_url );
				}
				return;

			case 'javascript':
			default:
				// Strategy 3: For regular page loads with headers sent, output JavaScript immediately.
				$this->output_redirect_script( $redirect_url );
				return;
		}
	}

	/**
	 * Determine which redirect strategy to use based on the current request context.
	 *
	 * - 'direct': Headers not sent, not AJAX, not REST — can use wp_redirect().
	 * - 'transient': AJAX or REST request — store in transient for polling script.
	 * - 'javascript': Headers already sent on a regular page load — inline JS redirect.
	 *
	 * @return string One of 'direct', 'transient', or 'javascript'.
	 */
	protected function get_redirect_strategy() {

		if ( ! $this->are_headers_sent() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return 'direct';
		}

		$is_ajax = wp_doing_ajax() || ( defined( 'DOING_AJAX' ) && DOING_AJAX );
		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;

		if ( $is_ajax || $is_rest ) {
			return 'transient';
		}

		return 'javascript';
	}

	/**
	 * Wrapper around headers_sent() extracted to allow test subclasses to
	 * stub it. The Codeception bootstrap emits deprecation notices before
	 * any test runs, which flips headers_sent() to true for the rest of
	 * the process and makes the 'direct' branch otherwise unreachable.
	 */
	protected function are_headers_sent() {
		return headers_sent();
	}

	/**
	 * Output JavaScript redirect script.
	 *
	 * Zero-cookie redirect using direct JavaScript injection.
	 * Used for regular page loads (non-AJAX scenarios).
	 * Handles both parent window (iframe) and current window redirects.
	 *
	 * @param string $redirect_url Validated redirect URL.
	 *
	 * @return void
	 */
	private function output_redirect_script( $redirect_url ) {
		// Output JavaScript that performs immediate redirect.
		// Handles iframe scenarios where redirect should happen in parent window.
		printf(
			'<!-- Uncanny Automator Closure Redirect (Session-Only) --><script>(function(){var url=%s;try{if(window.parent&&window.parent!==window&&!window.parent.location.href.match(/\/wp-admin\//)){window.parent.location.href=url;}else{window.location.href=url;}}catch(e){window.location.href=url;}})();</script>',
			wp_json_encode( $redirect_url )
		);
	}

	/**
	 * AJAX handler to check for pending redirect.
	 *
	 * Security: Nonce protected.
	 *
	 * @return void
	 */
	public function ajax_check_closure_redirect() {
		if ( ! $this->verify_ajax_nonce() ) {
			wp_send_json_error();
		}

		$identifier   = $this->get_redirect_identifier();
		$redirect_url = $this->get_pending_redirect( $identifier );

		if ( $redirect_url ) {
			wp_send_json_success(
				array(
					'redirect_url' => $redirect_url,
				)
			);
		}

		wp_send_json_error();
	}

	/**
	 * Verify the AJAX nonce for closure redirect requests.
	 *
	 * Extracted to allow test subclasses to bypass filter_input() limitations in CLI.
	 *
	 * @return bool True if nonce is valid.
	 */
	protected function verify_ajax_nonce() {
		$nonce = automator_filter_input( 'nonce', INPUT_GET );
		return (bool) wp_verify_nonce( $nonce, 'automator_closure_redirect' );
	}

	/**
	 * Validate redirect URL for security.
	 *
	 * Blocks XSS vectors while allowing legitimate external redirects.
	 * Allows relative URLs and http/https absolute URLs.
	 *
	 * @param string $url URL to validate.
	 *
	 * @return string Validated URL or empty string if invalid.
	 */
	private function validate_redirect_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		$url = trim( $url );

		// Allow relative URLs (e.g., /thank-you, /page/success).
		// Block protocol-relative URLs (//example.com).
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return $url;
		}

		// Validate and sanitize absolute URLs.
		$url = esc_url_raw( $url );
		if ( empty( $url ) ) {
			return '';
		}

		// Block dangerous protocols - only allow http and https.
		$parsed            = wp_parse_url( $url );
		$allowed_protocols = array( 'http', 'https' );
		if ( ! isset( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], $allowed_protocols, true ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Percent-encode chars that wp_sanitize_redirect() drops.
	 *
	 * wp_redirect() runs wp_sanitize_redirect(), whose allowlist regex removes
	 * $, |, and ' even though esc_url() permits them. Pre-encoding lets the
	 * values survive the sanitizer; the browser decodes the percent-escapes
	 * back to literal characters on the landing page.
	 *
	 * @param string $url Validated redirect URL.
	 *
	 * @return string URL with sanitize-unsafe characters percent-encoded.
	 */
	private function encode_for_core_redirect( $url ) {
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

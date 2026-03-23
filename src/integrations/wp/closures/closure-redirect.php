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
	 * @param $redirect_url
	 *
	 * @return void
	 */
	public function set_cookie( $redirect_url ) {

		$cookie_name     = 'automator_closure_redirect';
		$cookie_lifetime = time() + ( 86400 * 30 ); // 86400 = 1 day * 30 = 30 days

		// If this is an AJAX or REST request, store in option for client-side to pick up.
		$is_ajax = wp_doing_ajax() || ( defined( 'DOING_AJAX' ) && DOING_AJAX );
		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;

		if ( $is_ajax || $is_rest ) {
			$identifier = $this->get_redirect_identifier();
			// Only store if we have a valid identifier.
			if ( ! empty( $identifier ) ) {
				$this->store_pending_redirect( $identifier, $redirect_url );
			}
			return;
		}

		// If headers have already been sent, add cookie via inline JavaScript.
		if ( headers_sent() ) {
			// LAST RESORT FALLBACK: Inline script output after page render.
			//
			// This code path executes when:
			// - Recipe triggered on regular page load (not AJAX/REST)
			// - Trigger fires late in request lifecycle (after wp_loaded)
			// - Recipe completes during shutdown hook
			// - Headers already sent, page HTML already output
			//
			// Known Limitations:
			// 1. Outputs <script> after </html> (invalid HTML, but browsers handle it)
			// 2. Blocked by strict CSP without 'unsafe-inline' directive
			// 3. Cannot use wp_add_inline_script() (wp_footer already fired)
			// 4. Not ideal, but only option when trigger queue processes at shutdown
			//
			// Most real-world scenarios use AJAX/REST path (option-based).
			// This path is rare edge case for late-firing triggers on regular page loads.
			$expiry_date = gmdate( 'D, d M Y H:i:s', $cookie_lifetime ) . ' GMT';
			echo '<script>document.cookie = "' . esc_js( $cookie_name ) . '=" + encodeURIComponent(' . wp_json_encode( $redirect_url ) . ') + "; expires=' . esc_js( $expiry_date ) . '; path=/";</script>';
			return;
		}

		// Set cookie.
		setcookie( $cookie_name, $redirect_url, $cookie_lifetime, '/' );
	}

	/**
	 * AJAX handler to check for pending redirect.
	 *
	 * Security: Nonce protected.
	 *
	 * @return void
	 */
	public function ajax_check_closure_redirect() {
		// Verify nonce for security.
		$nonce = automator_filter_input( 'nonce', INPUT_GET );
		if ( ! wp_verify_nonce( $nonce, 'automator_closure_redirect' ) ) {
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
}

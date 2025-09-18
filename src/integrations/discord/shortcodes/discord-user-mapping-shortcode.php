<?php
namespace Uncanny_Automator\Integrations\Discord;

/**
 * Discord User Mapping Shortcode
 *
 * Adds a shortcode to initiate individual WP User OAuth Discord -> WP User mapping.
 */
class Discord_User_Mapping_Shortcode {

	/**
	 * Nonce key for init.
	 *
	 * @var string
	 */
	const INIT_NONCE_KEY = 'automator_discord_identify_user_init';

	/**
	 * Nonce key for return.
	 *
	 * @var string
	 */
	const RETURN_NONCE_KEY = 'automator_discord_identify_user_return';

	/**
	 * Discord OAuth return query arg.
	 *
	 * @var string
	 */
	const IDENTIFY_QUERY_ARG = 'identify';

	/**
	 * Discord OAuth init query arg.
	 *
	 * @var string
	 */
	const INIT_QUERY_ARG = 'automator_discord_identify';

	/**
	 * Error transient key.
	 *
	 * @var string
	 */
	const ERROR_TRANSIENT_KEY = 'automator_discord_user_mapping_error_%d';

	/**
	 * Helpers
	 *
	 * @var Discord_App_Helpers
	 */
	protected $helpers;

	/**
	 * API
	 *
	 * @var Discord_Api_Caller
	 */
	protected $api;

	/**
	 * Discord_User_Mapping_Shortcode constructor.
	 *
	 * @param  Discord_App_Helpers $helpers
	 *
	 * @return void
	 */
	public function __construct( $dependencies ) {

		$this->helpers = $dependencies->helpers;
		$this->api     = $dependencies->api;

		$this->register_hooks();
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_shortcode( 'automator_discord_user_mapping', array( $this, 'render_shortcode' ) );
		add_action( 'template_redirect', array( $this, 'handle_oauth_flow' ) );
	}

	/**
	 * Render the shortcode
	 *
	 * @param  mixed $atts
	 *
	 * @return string The shortcode output.
	 */
	public function render_shortcode( $atts ) {

		// Validate current user.
		$current_user = $this->get_validated_user();
		if ( ! $current_user ) {
			return '';
		}

		// Shortcode attributes.
		$atts = shortcode_atts(
			array(
				'label'            => esc_html_x( 'Verify Discord Account', 'Discord', 'uncanny-automator' ),
				'css_class'        => 'uap-discord-user-mapping-button',
				'verified_message' => esc_html_x( 'Your Discord account is verified.', 'Discord', 'uncanny-automator' ),
				'error'            => esc_html_x( 'Unable to verify Discord account. Please try again.', 'Discord', 'uncanny-automator' ),
			),
			$atts
		);

		// Start output.
		$output = '';

		// Check for any error messages
		$error_message = get_transient( $this->get_error_transient_key( $current_user->ID ) );
		if ( ! empty( $error_message ) ) {
			delete_transient( $this->get_error_transient_key( $current_user->ID ) );
			$output .= sprintf(
				'<div class="uap-discord-error">%s</div>',
				esc_html( $error_message )
			);
		}

		// Check if the user already has the meta key set.
		if ( $this->helpers->get_mapped_wp_user_discord_id( $current_user->ID ) ) {
			return sprintf(
				'<div class="uap-discord-connected">%s</div>',
				$atts['verified_message']
			);
		}

		// Create internal OAuth init URL with nonce
		$oauth_init_url = add_query_arg(
			array(
				self::INIT_QUERY_ARG => $current_user->ID,
				'nonce'              => wp_create_nonce( self::INIT_NONCE_KEY ),
			),
			$this->get_current_page_url()
		);

		// Add the button to the output
		$output .= sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $oauth_init_url ),
			esc_attr( $atts['css_class'] ),
			esc_html( $atts['label'] )
		);

		return $output;
	}

	/**
	 * Get error transient key for user
	 *
	 * @param int $user_id The user ID
	 * @return string
	 */
	private function get_error_transient_key( $user_id ) {
		return sprintf( self::ERROR_TRANSIENT_KEY, $user_id );
	}

	/**
	 * Handle both OAuth initialization and return
	 *
	 * @return void
	 */
	public function handle_oauth_flow() {
		// Handle OAuth initialization
		if ( automator_filter_has_var( self::INIT_QUERY_ARG ) ) {
			$this->handle_oauth_init();
			return;
		}

		// Handle OAuth return
		if ( automator_filter_has_var( self::IDENTIFY_QUERY_ARG ) ) {
			$this->handle_oauth_return();
		}
	}

	/**
	 * Handle OAuth initialization
	 *
	 * @return void
	 */
	private function handle_oauth_init() {
		// Get current user.
		$current_user = $this->get_validated_user();
		if ( ! $current_user ) {
			wp_die( esc_html_x( 'You must be logged in to verify your Discord account.', 'Discord', 'uncanny-automator' ) );
		}

		// Validate user ID from query arg.
		$requested_user_id = absint( automator_filter_input( self::INIT_QUERY_ARG ) );
		if ( $current_user->ID !== $requested_user_id ) {
			wp_die( esc_html_x( 'Invalid request.', 'Discord', 'uncanny-automator' ) );
		}

		// Verify nonce
		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), self::INIT_NONCE_KEY ) ) {
			wp_die( esc_html_x( 'Invalid request.', 'Discord', 'uncanny-automator' ) );
		}

		// Get and validate the OAuth URL
		$oauth_url = $this->get_oauth_url();

		// Add filter for this specific redirect to the API server.
		add_filter(
			'allowed_redirect_hosts',
			function ( $hosts ) {
				$hosts[] = wp_parse_url( AUTOMATOR_API_URL, PHP_URL_HOST );
				return $hosts;
			}
		);

		// Redirect to Discord OAuth URL
		wp_safe_redirect( $oauth_url );
		exit;
	}

	/**
	 * Handle OAuth return
	 *
	 * @return void
	 */
	private function handle_oauth_return() {
		if ( automator_filter_input( self::IDENTIFY_QUERY_ARG ) !== 'discord-user' ) {
			return;
		}

		// Check for encoded message.
		if ( ! automator_filter_has_var( 'automator_api_message' ) ) {
			return;
		}

		$automator_message = automator_filter_input( 'automator_api_message' );
		if ( empty( $automator_message ) ) {
			return;
		}

		$current_user = $this->get_validated_user();
		if ( ! $current_user ) {
			return;
		}

		// Decode the message.
		$credentials = (array) \Uncanny_Automator\Automator_Helpers_Recipe::automator_api_decode_message( $automator_message, wp_create_nonce( self::RETURN_NONCE_KEY ) );

		// Validate the credentials.
		if ( empty( $credentials['discord_id'] ) ) {
			// Set error message in transient
			set_transient(
				$this->get_error_transient_key( $current_user->ID ),
				esc_html_x( 'Unable to verify Discord account. Please try again.', 'Discord', 'uncanny-automator' ),
				10
			);

			// Redirect back to the current page, removing our OAuth parameters
			wp_safe_redirect( $this->get_current_page_url( true ) );
			exit;
		}

		$discord_id = sanitize_text_field( $credentials['discord_id'] );
		$meta_key   = $this->helpers->get_const( 'DISCORD_USER_MAPPING_META_KEY' );
		update_user_meta( $current_user->ID, $meta_key, $discord_id );

		// Clear the verified members cache.
		$this->helpers->clear_verified_members_cache();

		// Redirect back to the current page, removing our OAuth parameters
		wp_safe_redirect( $this->get_current_page_url( true ) );
		exit;
	}

	/**
	 * Get the OAuth URL.
	 *
	 * @return string The OAuth URL.
	 */
	private function get_oauth_url() {
		$nonce = wp_create_nonce( self::RETURN_NONCE_KEY );

		// Get current URL and add our return parameter
		$return_url = add_query_arg(
			array(
				self::IDENTIFY_QUERY_ARG => 'discord-user',
				'nonce'                  => $nonce,
			),
			$this->get_current_page_url()
		);

		return add_query_arg(
			array(
				'action'                 => 'authorization_request',
				'nonce'                  => $nonce,
				'redirect_url'           => rawurlencode( $return_url ),
				'plugin_ver'             => AUTOMATOR_PLUGIN_VERSION,
				self::IDENTIFY_QUERY_ARG => 'discord-user',
			),
			AUTOMATOR_API_URL . $this->api->get_api_endpoint()
		);
	}

	/**
	 * Get the current page URL with existing query parameters
	 *
	 * @param bool $remove_params - Whether to remove the OAuth-specific parameters
	 * @return string
	 */
	private function get_current_page_url( $remove_params = false ) {

		// Get the current URL with existing query parameters.
		global $wp;
		$current_url = home_url( add_query_arg( array(), $wp->request ) );

		// Remove our OAuth-specific parameters if needed
		if ( $remove_params ) {
			$params = array(
				self::IDENTIFY_QUERY_ARG,
				'automator_api_message',
				'nonce',
				'error',
			);

			foreach ( $params as $param ) {
				$current_url = remove_query_arg( $param, $current_url );
			}
		}

		return $current_url;
	}

	/**
	 * Get current user with validation
	 *
	 * @return \WP_User|false The current user or false if invalid
	 */
	private function get_validated_user() {
		$current_user = wp_get_current_user();
		return $current_user->exists() ? $current_user : false;
	}
}

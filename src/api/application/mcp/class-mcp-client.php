<?php
/**
 * MCP Chat Client.
 *
 * Handles secure communication with the Model Context Protocol chat service.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Application\Mcp;

use Uncanny_Automator\Api\Application\Mcp\Agent\Agent_Context;
use Uncanny_Automator\Api\Application\Mcp\Agent\Url_Agent_Context;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client\Client_Context_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client\Client_Payload_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client\Client_Public_Key_Manager;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client\Client_Token_Service;
use Uncanny_Automator\Admin_Settings_Uncanny_Agent_General;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Traits\Singleton;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Mcp_Client
 *
 * @since 7.0.0 Moved to Application layer.
 */
// phpcs:disable WordPress.Security.NonceVerification -- MCP client uses custom authentication.
class Mcp_Client {

	use Singleton;

	/**
	 * Default inference service URL.
	 */
	const INFERENCE_URL = 'https://llm.automatorplugin.com';

	/**
	 * Default SDK URL for chat components.
	 */
	const SDK_URL = 'https://llm.automatorplugin.com/sdk.js';

	/**
	 * Default SDK CSS URL for chat components.
	 */
	const SDK_CSS_URL = 'https://llm.automatorplugin.com/sdk.css';

	/**
	 * Agent context builder.
	 *
	 * @var Agent_Context
	 */
	private Agent_Context $agent_context;

	/**
	 * Context helper.
	 *
	 * @var Client_Context_Service
	 */
	private Client_Context_Service $context_service;

	/**
	 * Public key helper.
	 *
	 * @var Client_Public_Key_Manager
	 */
	private Client_Public_Key_Manager $public_key_manager;

	/**
	 * Token helper.
	 *
	 * @var Client_Token_Service
	 */
	private Client_Token_Service $token_service;

	/**
	 * Payload helper.
	 *
	 * @var Client_Payload_Service
	 */
	private Client_Payload_Service $payload_service;

	/**
	 * Constructor.
	 *
	 * @param Agent_Context|null             $agent_context Optional agent context builder.
	 * @param Client_Context_Service|null    $context_service Optional context helper.
	 * @param Client_Public_Key_Manager|null $public_key_manager Optional public key helper.
	 * @param Client_Token_Service|null      $token_service Optional token helper.
	 * @param Client_Payload_Service|null    $payload_service Optional payload helper.
	 */
	public function __construct(
		?Agent_Context $agent_context = null,
		?Client_Context_Service $context_service = null,
		?Client_Public_Key_Manager $public_key_manager = null,
		?Client_Token_Service $token_service = null,
		?Client_Payload_Service $payload_service = null
	) {
		$this->agent_context      = $agent_context ? $agent_context : new Agent_Context();
		$this->context_service    = $context_service ? $context_service : new Client_Context_Service();
		$this->public_key_manager = $public_key_manager ? $public_key_manager : new Client_Public_Key_Manager();
		$this->token_service      = $token_service ? $token_service : new Client_Token_Service();
		$this->payload_service    = $payload_service ? $payload_service : Client_Payload_Service::builder()
			->with_token_service( $this->token_service )
			->with_public_key_manager( $this->public_key_manager )
			->build();

		$this->register_hooks();
	}

	/**
	 * Check whether the Uncanny Agent feature is enabled.
	 *
	 * @return bool
	 */
	private static function get_uncanny_agent_settings(): bool {
		return (bool) Admin_Settings_Uncanny_Agent_General::get_setting( Admin_Settings_Uncanny_Agent_General::ENABLED_KEY );
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_action( 'admin_footer', array( $this, 'load_chat_sdk' ), 10, 1 );
		add_action( 'admin_footer', array( $this, 'render_launcher' ), 20, 1 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'uap/v2',
			'/mcp/chat/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'refresh_payload' ),
				'permission_callback' => array( $this, 'ensure_admin_permissions' ),
				'args'                => array(
					'page_url' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_page_url' ),
					),
				),
			)
		);

		register_rest_route(
			'uap/v2',
			'/mcp/chat/launcher/(?P<recipe_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_launcher_html' ),
				'permission_callback' => array( $this, 'ensure_admin_permissions' ),
				'args'                => array(
					'recipe_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Validate the optional page_url parameter.
	 *
	 * @param mixed $value Candidate value.
	 * @return bool
	 */
	public function validate_page_url( $value ): bool {
		if ( null === $value || '' === $value ) {
			return true;
		}

		if ( ! is_string( $value ) ) {
			return false;
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return false;
		}

		if ( 0 === strpos( $value, '//' ) ) {
			return false;
		}

		if ( preg_match( '#^[a-zA-Z][a-zA-Z0-9+\-.]*:#', $value ) ) {
			$parts  = wp_parse_url( $value );
			$scheme = strtolower( $parts['scheme'] ?? '' );

			if ( false === $parts || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return false;
			}

			return ! empty( $parts['host'] );
		}

		return 0 === strpos( $value, '/' );
	}

	/**
	 * Permission callback used by the REST API route.
	 *
	 * @return bool
	 */
	public function ensure_admin_permissions(): bool {
		return $this->context_service->user_has_capability();
	}

	/**
	 * Get the current user's display name.
	 *
	 * @return string
	 */
	public function get_current_user_display_name(): string {
		return $this->context_service->get_current_user_display_name();
	}

	/**
	 * Load the MCP chat SDK in the admin.
	 *
	 * @return void
	 */
	public function load_chat_sdk(): void {
		if ( ! self::get_uncanny_agent_settings() || ! $this->context_service->can_access_client() ) {
			return;
		}

		$force_refresh = isset( $_GET['mcp_refresh_key'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['mcp_refresh_key'] ) );

		if ( ! $this->public_key_manager->ensure_public_key_ready( $force_refresh ) ) {
			return;
		}

		printf(
			'<script src="%s" type="module"></script> <link rel="stylesheet" href="%s">',  // phpcs:ignore WordPress.WP.EnqueuedResources -- MCP launcher web component requires inline loading.
			esc_url( $this->get_sdk_url() ),
			esc_url( $this->get_sdk_css_url() )
		);
	}

	/**
	 * Render the chat launcher button.
	 *
	 * @param mixed $post - WordPress' passed parameter.
	 * @return void
	 */
	public function render_launcher( $post ): void {

		if ( ! self::get_uncanny_agent_settings() ) {
			return;
		}

		// if ( ! $this->in_allowed_pages() ) {
		// 	return;
		// }

		if ( ! $this->context_service->should_render_button( $post ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in generate_launcher_html.
		echo $this->generate_launcher_html();
	}

	/**
	 * Check if the current admin page is one where the chat launcher should be rendered.
	 *
	 * Returns true for any page under the Automator menu: the uo-recipe post type
	 * screens (list, edit, add new, taxonomies) and all registered submenu pages.
	 *
	 * @return bool
	 */
	private function in_allowed_pages(): bool {
		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return false;
		}

		// Post type screens: All recipes, Add new, single recipe editor.
		if ( 'uo-recipe' === $current_screen->post_type ) {
			return true;
		}

		// Taxonomy screens: Categories (recipe_category), Tags (recipe_tag).
		if ( in_array( $current_screen->taxonomy, array( 'recipe_category', 'recipe_tag' ), true ) ) {
			return true;
		}

		// Custom submenu pages all follow the pattern "uo-recipe_page_*".
		if ( 0 === strpos( $current_screen->id, 'uo-recipe_page_' ) ) {
			return true;
		}

		// Hidden pages (e.g. recipe activity details) use "admin_page_uncanny-automator-*".
		if ( 0 === strpos( $current_screen->id, 'admin_page_uncanny-automator-' ) ) {
			return true;
		}

		// WordPress dashboard (/wp-admin/index.php).
		if ( 'dashboard' === $current_screen->id ) {
			return true;
		}

		return false;
	}

	/**
	 * Generate the launcher HTML element including CSS.
	 *
	 * Handles all the logic: payload generation, recipe fetching, context building, CSS styles, and HTML element. Returns empty string on any failure.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return string The CSS and launcher HTML, or empty string on failure.
	 */
	private function generate_launcher_html(): string {
		$payload = $this->payload_service->generate_encrypted_payload( array() );

		if ( '' === $payload ) {
			return '';
		}

		// Check if we can dock the widget to the right
		$can_dock_to_right = $this->in_allowed_pages();

		// Infer view mode based on the can dock to right flag
		$view_mode = $can_dock_to_right ? 'fab' : 'bottom-dock';

		$launcher = sprintf(
			'<ua-chat-launcher 
				server-url="%s" 
				payload="%s" 
				parent-selector="#wpbody" 
				consumer-server-url="%s" 
				consumer-nonce="%s"
				bundle-url="%s"
				bundle-css-url="%s"
				view-mode="%s"
				%s
			></ua-chat-launcher>',
			esc_attr( self::get_inference_url() ),
			esc_attr( $payload ),
			esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ),
			esc_attr( wp_create_nonce( 'wp_rest' ) ),
			esc_url( $this->get_sdk_url() ),
			esc_url( $this->get_sdk_css_url() ),
			esc_attr( $view_mode ),
			( $can_dock_to_right ? 'can-dock-to-right' : '' )
		);

		return $this->get_inline_css() . $launcher;
	}

	/**
	 * Inference service URL.
	 *
	 * @return string
	 */
	public static function get_inference_url(): string {
		$url = defined( 'AUTOMATOR_MCP_CLIENT_INFERENCE_URL' )
			&& AUTOMATOR_MCP_CLIENT_INFERENCE_URL
				? AUTOMATOR_MCP_CLIENT_INFERENCE_URL
				: self::INFERENCE_URL;

		return apply_filters( 'automator_mcp_client_inference_url', $url );
	}

	/**
	 * REST callback that returns a refreshed encrypted payload.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function refresh_payload( WP_REST_Request $request ) {
		$page_url = $request->get_param( 'page_url' );

		if ( null !== $page_url && ! is_string( $page_url ) ) {
			return new WP_Error(
				'invalid_page_url',
				esc_html_x( 'The supplied page URL must be a string.', 'MCP client validation error', 'uncanny-automator' ),
				array( 'status' => 400 )
			);
		}

		if ( is_string( $page_url ) && '' !== $page_url && ! $this->validate_page_url( $page_url ) ) {
			return new WP_Error(
				'invalid_page_url',
				esc_html_x( 'The supplied page URL is invalid.', 'MCP client validation error', 'uncanny-automator' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $this->public_key_manager->ensure_public_key_ready() ) {
			return new WP_Error(
				'public_key_unavailable',
				esc_html_x( 'Unable to load the required encryption key.', 'MCP client validation error', 'uncanny-automator' ),
				array( 'status' => 500 )
			);
		}

		$payload = $this->payload_service->generate_encrypted_payload(
			is_string( $page_url ) ? array( 'page_url' => $page_url ) : array()
		);

		if ( '' === $payload ) {
			return new WP_Error(
				'encryption_failed',
				esc_html_x( 'Could not generate the encrypted payload.', 'MCP client validation error', 'uncanny-automator' ),
				array( 'status' => 500 )
			);
		}

		$context = $this->build_context_for_refresh( $page_url );

		// Push updated context to the inference server so the AI agent
		// picks it up on the next turn without waiting for a new message.
		$this->send_context_to_inference_server( $payload, $context );

		return rest_ensure_response(
			array(
				'encrypted_payload' => $payload,
				'context'           => $context,
			)
		);
	}

	/**
	 * Build agent context for the refresh endpoint.
	 *
	 * When a page_url is provided (detached window mode), derives context from
	 * the URL instead of relying on WordPress globals.
	 *
	 * @param string|null $page_url Optional page URL from the request.
	 *
	 * @return array<string, mixed>
	 */
	private function build_context_for_refresh( ?string $page_url ): array {

		if ( is_string( $page_url ) && '' !== $page_url ) {
			return $this->create_url_agent_context( $page_url )->build();
		}

		return $this->agent_context->build();
	}

	/**
	 * Push updated context to the inference server.
	 *
	 * Fire-and-forget: a short timeout prevents blocking the REST response.
	 * Failures are silently ignored — the AI will still work with stale context
	 * until the next successful push.
	 *
	 * @param string              $encrypted_payload The freshly encrypted payload (used for auth on the inference side).
	 * @param array<string,mixed> $context           The ModelContext array.
	 *
	 * @return void
	 */
	private function send_context_to_inference_server( string $encrypted_payload, array $context ): void {

		$url = self::get_inference_url();

		if ( '' === $url ) {
			return;
		}

		$body = wp_json_encode(
			array(
				'encrypted_payload' => $encrypted_payload,
				'context'           => $context,
			)
		);

		if ( false === $body ) {
			return;
		}

		wp_remote_post(
			trailingslashit( $url ) . 'api/context/update',
			array(
				'headers'   => array( 'Content-Type' => 'application/json' ),
				'body'      => $body,
				'timeout'   => 30,
				'blocking'  => false,
				'sslverify' => true,
			)
		);
	}

	/**
	 * Create an Agent_Context that derives data from a URL.
	 *
	 * Extracted as a protected method so tests can substitute a stub.
	 *
	 * @param string $page_url The admin page URL.
	 *
	 * @return Agent_Context
	 */
	protected function create_url_agent_context( string $page_url ): Agent_Context {
		return new Url_Agent_Context( $page_url );
	}

	/**
	 * REST callback that returns the chat launcher HTML.
	 *
	 * This endpoint is used to fetch the chat launcher button after the user has selected a recipe type.
	 * Temporary solution until the recipe type selector is removed.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function get_launcher_html( WP_REST_Request $request ) {
		$html = $this->generate_launcher_html( $request->get_param( 'recipe_id' ) );

		return rest_ensure_response(
			array(
				'html' => $html,
			)
		);
	}

	/**
	 * Get the SDK URL.
	 *
	 * Appends a license hash parameter for beta enrollment verification.
	 * The hash is computed as HMAC-SHA256(license_key, license_key) to avoid
	 * exposing raw license keys in URLs.
	 *
	 * @return string
	 */
	private function get_sdk_url(): string {
		// Check if developer explicitly defined a custom SDK URL.
		$is_custom_url = defined( 'AUTOMATOR_MCP_CLIENT_SDK_URL' ) && AUTOMATOR_MCP_CLIENT_SDK_URL;

		$url = $is_custom_url
			? AUTOMATOR_MCP_CLIENT_SDK_URL
			: self::SDK_URL;

		// Only validate URLs that aren't explicitly defined by developers.
		// This allows localhost URLs for development while protecting against injection in production.
		if ( ! $is_custom_url && ! wp_http_validate_url( $url ) ) {
			$url = self::SDK_URL;
		}

		// Allow URL overwrite via filter.
		$url = apply_filters( 'automator_mcp_client_sdk_url', $url );

		// Append license hash for beta enrollment check.
		$license_key = Api_Server::get_license_key();
		if ( ! empty( $license_key ) ) {
			$license_hash = hash_hmac( 'sha256', $license_key, $license_key );
			$url          = add_query_arg( 'l', $license_hash, $url );
		}

		// Append plugin version for cache busting.
		$version = AUTOMATOR_PLUGIN_VERSION; // No need to check constant - defined in main plugin file.
		$url     = add_query_arg( 'v', $version, $url );

		return $url;
	}

	/**
	 * Get the SDK CSS URL.
	 *
	 * @return string
	 */
	private function get_sdk_css_url(): string {
		$url = defined( 'AUTOMATOR_MCP_CLIENT_SDK_CSS_URL' ) && AUTOMATOR_MCP_CLIENT_SDK_CSS_URL
			? AUTOMATOR_MCP_CLIENT_SDK_CSS_URL
			: self::SDK_CSS_URL;

		return apply_filters( 'automator_mcp_client_sdk_css_url', $url );
	}

	/**
	 * Returns the inline CSS styles for the chat launcher and its container.
	 *
	 * @return string
	 */
	private function get_inline_css(): string {
		return '<style>
			#poststuff {
				container-type: inline-size;
				container-name: recipe-container;
				min-width: auto !important;
			}

			@container recipe-container (max-width: 800px) {
				#post-body {
					display: flex;
					flex-direction: column;
					align-items: flex-start;
					margin-right: 0 !important;
				}

				#post-body,
				#postbox-container-1,
				#postbox-container-2,
				#side-sortables {
					margin-right: 0 !important;
					width: 100% !important;
				}
			}

			ua-chat-launcher {
				--ua-mpc-chat-launcher-z-index: 159900;
			}
		</style>';
	}
}

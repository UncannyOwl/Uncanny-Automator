<?php
/**
 * MCP Chat Client.
 *
 * Handles secure communication with the Model Context Protocol chat service.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Application\Mcp;

use InvalidArgumentException;
use Uncanny_Automator\App\Application\Mcp\Agent\Agent_Context;
use Uncanny_Automator\App\Application\Mcp\Agent\Url_Agent_Context;
use Uncanny_Automator\App\Components\Conversation_Starter\Domain\Conversation_Starter;
use Uncanny_Automator\App\Components\Conversation_Starter\Registry\Conversation_Registry;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Client_Context_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Client_Payload_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Client_Public_Key_Manager;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Client_Token_Service;
use Uncanny_Automator\Admin_Settings_Uncanny_Agent_General;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Class Mcp_Client
 *
 * @since 7.0.0 Moved to Application layer.
 * @todo Move SDK and launcher gating decisions to the Uncanny_Agent bounded
 *       context. Keep this class responsible for delivery after the context
 *       permits access.
 */
// phpcs:disable WordPress.Security.NonceVerification -- MCP client uses custom authentication.
class Mcp_Client {

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
	 * Valid license status returned by the Automator licensing API.
	 */
	private const LICENSE_STATUS_VALID = 'valid';

	/**
	 * One-way SDK license hash query parameter.
	 */
	private const SDK_LICENSE_HASH_QUERY_ARG = 'l';

	/**
	 * Decryptable SDK license package query parameter.
	 */
	private const SDK_LICENSE_PAYLOAD_QUERY_ARG = 'm';

	/**
	 * SDK render cache schema version.
	 */
	private const SDK_RENDER_CACHE_SCHEMA_VERSION = 'sdk-render-v2';

	/**
	 * Producer-side negative render-decision TTL.
	 */
	private const SDK_RENDER_DENY_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Runtime instance constructed by the MCP composition root.
	 *
	 * Host integrations use this accessor to mount Automator's SDK on custom
	 * surfaces without constructing application dependencies themselves.
	 *
	 * @var self|null
	 */
	private static $instance = null;

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
	 * Conversation starter registry.
	 *
	 * @var Conversation_Registry
	 */
	private Conversation_Registry $conversation_registry;

	/**
	 * License reader used by the SDK producer path.
	 *
	 * @var Mcp_License_Provider_Interface
	 */
	private $license_provider;

	/**
	 * Per-request SDK render context cache.
	 *
	 * @var array<string,mixed>|null
	 */
	private $sdk_render_context = null;

	/**
	 * Per-request Uncanny Agent presentation settings.
	 *
	 * @var array{enabled: bool, top_bar_button_enabled: bool}|null
	 */
	private $uncanny_agent_settings = null;

	/**
	 * Constructor.
	 *
	 * @param Mcp_License_Provider_Interface $license_provider SDK license reader.
	 * @param Agent_Context|null             $agent_context Optional agent context builder.
	 * @param Client_Context_Service|null    $context_service Optional context helper.
	 * @param Client_Public_Key_Manager|null $public_key_manager Optional public key helper.
	 * @param Client_Token_Service|null      $token_service Optional token helper.
	 * @param Client_Payload_Service|null    $payload_service Optional payload helper.
	 * @param Conversation_Registry|null     $conversation_registry Optional conversation starter registry.
	 */
	public function __construct(
		Mcp_License_Provider_Interface $license_provider,
		?Agent_Context $agent_context = null,
		?Client_Context_Service $context_service = null,
		?Client_Public_Key_Manager $public_key_manager = null,
		?Client_Token_Service $token_service = null,
		?Client_Payload_Service $payload_service = null,
		?Conversation_Registry $conversation_registry = null
	) {

		$this->license_provider      = $license_provider;
		$this->agent_context         = $agent_context ? $agent_context : new Agent_Context();
		$this->context_service       = $context_service ? $context_service : new Client_Context_Service();
		$this->public_key_manager    = $public_key_manager ? $public_key_manager : new Client_Public_Key_Manager();
		$this->token_service         = $token_service ? $token_service : new Client_Token_Service();
		$this->payload_service       = $payload_service ? $payload_service : Client_Payload_Service::builder()
			->with_token_service( $this->token_service )
			->with_public_key_manager( $this->public_key_manager )
			->build();
		$this->conversation_registry = $conversation_registry ? $conversation_registry : new Conversation_Registry();
		self::$instance              = $this;

		$this->register_hooks();
	}

	/**
	 * Return the client instance constructed by the MCP composition root.
	 *
	 * This preserves the host-integration API without allowing the application
	 * layer to construct its infrastructure license provider.
	 *
	 * @return self
	 *
	 * @throws \LogicException When MCP bootstrap has not constructed the client.
	 */
	public static function get_instance(): self {

		if ( ! self::$instance instanceof self ) {
			throw new \LogicException( 'MCP client has not been initialized.' );
		}

		return self::$instance;
	}

	/**
	 * Get one Uncanny Agent presentation setting.
	 *
	 * @param string $key Setting key.
	 *
	 * @return bool
	 */
	private function get_uncanny_agent_setting( string $key ): bool {
		if ( null === $this->uncanny_agent_settings ) {
			$this->uncanny_agent_settings = Admin_Settings_Uncanny_Agent_General::get_settings( true );
		}

		return (bool) ( $this->uncanny_agent_settings[ $key ] ?? false );
	}

	/**
	 * Resolve the per-request SDK render context.
	 *
	 * Producer flow:
	 * 1. caller applies local pre-gates first
	 * 2. this method consults the producer-side render-deny cache
	 * 3. when render is allowed, it generates a fresh encrypted SDK payload
	 *
	 * @return array<string,mixed>
	 */
	private function get_sdk_render_context(): array {

		if ( is_array( $this->sdk_render_context ) ) {
			return $this->sdk_render_context;
		}

		$license = $this->get_sdk_license_data();
		$facts   = $this->build_sdk_render_facts( $license );

		if ( ! $this->should_attempt_sdk_render_from_local_license( $license, $facts['license_key'] ) ) {
			$this->sdk_render_context = $this->build_sdk_render_context( false, $facts['license_key'], '' );
			return $this->sdk_render_context;
		}

		if ( $this->has_cached_sdk_render_denial( $facts ) ) {
			$this->sdk_render_context = $this->build_sdk_render_context( false, $facts['license_key'], '' );
			return $this->sdk_render_context;
		}

		if ( ! $this->public_key_manager->ensure_public_key_ready() ) {
			$this->cache_sdk_render_denial( $facts );
			$this->sdk_render_context = $this->build_sdk_render_context( false, $facts['license_key'], '' );
			return $this->sdk_render_context;
		}

		$license_payload = $this->generate_sdk_license_payload( $license, $facts['license_key'] );

		if ( '' === $license_payload ) {
			$this->cache_sdk_render_denial( $facts );
			$this->sdk_render_context = $this->build_sdk_render_context( false, $facts['license_key'], '' );
			return $this->sdk_render_context;
		}

		$this->sdk_render_context = $this->build_sdk_render_context( true, $facts['license_key'], $license_payload );
		return $this->sdk_render_context;
	}

	/**
	 * Generate the decryptable SDK license package.
	 *
	 * @param array<string,mixed>|false $license License data.
	 * @param string                    $license_key Trusted license key.
	 *
	 * @return string
	 */
	private function generate_sdk_license_payload( $license, string $license_key ): string {

		if ( ! is_array( $license ) || '' === $license_key ) {
			return '';
		}

		return $this->payload_service->generate_encrypted_package(
			$this->build_sdk_license_payload( $license, $license_key )
		);
	}

	/**
	 * Get license data for SDK URL values.
	 *
	 * @return array<string,mixed>|false
	 */
	private function get_sdk_license_data() {
		$license = $this->license_provider->get_license_data();

		return is_array( $license ) ? $license : false;
	}

	/**
	 * Get the license key for SDK URL values.
	 *
	 * @param array<string,mixed>|false $license License data.
	 *
	 * @return string
	 */
	private function get_sdk_license_key( $license ): string {

		$license_key = is_array( $license ) ? ( $license['license_key'] ?? '' ) : '';

		if ( ! is_scalar( $license_key ) || '' === (string) $license_key ) {
			$license_key = $this->license_provider->get_key();
		}

		if ( ! is_scalar( $license_key ) || '' === (string) $license_key ) {
			return '';
		}

		return (string) $license_key;
	}

	/**
	 * Return whether local producer facts say the SDK may be attempted.
	 *
	 * This is intentionally broader than the backend `/sdk.js` allow rule. A valid
	 * local license may still be denied later by the backend rollout gate.
	 *
	 * @param array<string,mixed>|false $license License data.
	 * @param string                    $license_key Trusted license key.
	 *
	 * @return bool
	 */
	private function should_attempt_sdk_render_from_local_license( $license, string $license_key ): bool {

		if ( ! is_array( $license ) || '' === $license_key ) {
			return false;
		}

		$license_status = $license['license'] ?? $license['status'] ?? '';

		return self::LICENSE_STATUS_VALID === sanitize_text_field( (string) $license_status );
	}

	/**
	 * Build the sanitized SDK license package.
	 *
	 * @param array<string,mixed> $license License data.
	 * @param string              $license_key License key.
	 *
	 * @return array<string,mixed>
	 */
	private function build_sdk_license_payload( array $license, string $license_key ): array {

		return array(
			'license_key' => sanitize_text_field( $license_key ),
			'license_id'  => absint( $license['license_id'] ?? 0 ),
			'site_name'   => $this->get_sdk_site_name( $license ),
			'item_name'   => $this->get_sdk_item_name( $license ),
		);
	}

	/**
	 * Build the stable producer cache facts for SDK rendering.
	 *
	 * @param array<string,mixed>|false $license License data.
	 *
	 * @return array<string,mixed>
	 */
	private function build_sdk_render_facts( $license ): array {
		return array(
			'blog_id'                => function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0,
			'license_key'            => $this->get_sdk_license_key( $license ),
			'license_id'             => absint( is_array( $license ) ? ( $license['license_id'] ?? 0 ) : 0 ),
			'site_name'              => $this->get_sdk_site_name( $license ),
			'item_name'              => $this->get_sdk_item_name( $license ),
			'plugin_version'         => AUTOMATOR_PLUGIN_VERSION,
			'payload_schema_version' => self::SDK_RENDER_CACHE_SCHEMA_VERSION,
		);
	}

	/**
	 * Build one producer-side SDK cache key.
	 *
	 * @param string              $suffix Cache suffix.
	 * @param array<string,mixed> $facts  Stable cache facts.
	 *
	 * @return string
	 */
	private function build_sdk_render_cache_key( string $suffix, array $facts ): string {
		$encoded = wp_json_encode( $facts );
		$hash    = hash( 'sha256', is_string( $encoded ) ? $encoded : '' );

		return 'automator_mcp_sdk_' . $suffix . '_' . $hash;
	}

	/**
	 * Return whether producer-side render denial is currently cached.
	 *
	 * @param array<string,mixed> $facts Stable cache facts.
	 *
	 * @return bool
	 */
	private function has_cached_sdk_render_denial( array $facts ): bool {
		$value = get_transient( $this->build_sdk_render_cache_key( 'render', $facts ) );

		return 'deny' === $value;
	}

	/**
	 * Cache producer-side render denial after an operational failure.
	 *
	 * Successful renders intentionally do not write a cache entry because they
	 * still need a fresh public-key check and a freshly encrypted payload.
	 *
	 * @param array<string,mixed> $facts Stable cache facts.
	 *
	 * @return void
	 */
	private function cache_sdk_render_denial( array $facts ): void {
		set_transient(
			$this->build_sdk_render_cache_key( 'render', $facts ),
			'deny',
			self::SDK_RENDER_DENY_TTL
		);
	}

	/**
	 * Normalize the site name sent to the backend SDK gate.
	 *
	 * @param array<string,mixed>|false $license License data.
	 *
	 * @return string
	 */
	private function get_sdk_site_name( $license ): string {
		$site_name = is_array( $license ) ? ( $license['site_name'] ?? '' ) : '';

		if ( ! is_scalar( $site_name ) || '' === trim( (string) $site_name ) ) {
			$site_name = $this->license_provider->get_site_name();
		}

		return is_scalar( $site_name ) ? sanitize_text_field( trim( (string) $site_name ) ) : '';
	}

	/**
	 * Normalize the item name sent to the backend SDK gate.
	 *
	 * @param array<string,mixed>|false $license License data.
	 *
	 * @return string
	 */
	private function get_sdk_item_name( $license ): string {
		$item_name = is_array( $license ) ? ( $license['item_name'] ?? '' ) : '';

		if ( ! is_scalar( $item_name ) || '' === trim( (string) $item_name ) ) {
			$item_name = $this->license_provider->get_item_name();
		}

		return is_scalar( $item_name ) ? sanitize_text_field( trim( (string) $item_name ) ) : '';
	}

	/**
	 * Build the in-memory render context shared across this request.
	 *
	 * @param bool   $should_render            Whether the SDK should be rendered.
	 * @param string $license_key              Trusted license key.
	 * @param string $license_payload_query_value Freshly generated encrypted SDK payload.
	 *
	 * @return array<string,mixed>
	 */
	private function build_sdk_render_context( bool $should_render, string $license_key, string $license_payload_query_value ): array {
		return array(
			'should_render'               => $should_render,
			'license_key'                 => $license_key,
			'license_payload_query_value' => $license_payload_query_value,
		);
	}

	/**
	 * Return whether local producer facts say the SDK may be attempted.
	 *
	 * This is the producer's hard pre-gate. It intentionally ignores cached
	 * render decisions and operational readiness checks such as public-key
	 * availability so callers can decide how to handle those later failures.
	 *
	 * @return bool
	 */
	private function can_attempt_sdk_render_from_local_facts(): bool {
		$license = $this->get_sdk_license_data();

		return $this->should_attempt_sdk_render_from_local_license(
			$license,
			$this->get_sdk_license_key( $license )
		);
	}

	/**
	 * Build payload overrides shared by every encrypted chat payload.
	 *
	 * @param array<string,mixed> $overrides Additional call-site overrides.
	 *
	 * @return array<string,mixed>
	 */
	private function build_chat_payload_overrides( array $overrides = array() ): array {

		return array_merge(
			array(
				'page_builder_availability' => $this->get_page_builder_availability( $overrides ),
			),
			$overrides
		);
	}

	/**
	 * Resolve the Page Builder capability contract sent to the inference service.
	 *
	 * @param array<string,mixed> $request_context Request-scoped payload overrides.
	 *
	 * @return array{status:string,available:bool,enabled:bool,reason:string,canvasActive:bool}
	 */
	private function get_page_builder_availability( array $request_context = array() ): array {

		$availability = array(
			'status'       => 'unavailable',
			'available'    => false,
			'enabled'      => false,
			'reason'       => 'page_builder_not_registered',
			'canvasActive' => false,
		);

		$filtered = Dispatcher::filter( 'automator_mcp_page_builder_availability', $availability, $request_context );

		if ( ! is_array( $filtered ) ) {
			return $availability;
		}

		return array(
			'status'       => isset( $filtered['status'] ) && is_scalar( $filtered['status'] )
				? sanitize_key( (string) $filtered['status'] )
				: 'unavailable',
			'available'    => ! empty( $filtered['available'] ),
			'enabled'      => ! empty( $filtered['enabled'] ),
			'reason'       => isset( $filtered['reason'] ) && is_scalar( $filtered['reason'] )
				? sanitize_key( (string) $filtered['reason'] )
				: '',
			'canvasActive' => ! empty( $filtered['canvasActive'] ),
		);
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {

		add_action( 'admin_footer', array( $this, 'load_chat_sdk' ), 10, 1 );
		add_action( 'admin_footer', array( $this, 'render_default_launcher' ), 20, 1 );

		// Subscribe to the Automator admin-bar registration action so the quicklink is added
		// in the same callback execution as the Automator parent node — guarantees adjacency
		// regardless of other plugins' admin_bar_menu priorities.
		add_action( 'automator_admin_bar_register', array( $this, 'render_admin_bar_quicklink' ) );
		add_action( 'admin_print_styles', array( $this, 'print_admin_bar_quicklink_styles' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Handshake for app.uncannyagent.com site connection.
		( new Handshake\Handshake_Handler() )->init();

		// Agent context REST endpoint for standalone app.
		add_action( 'rest_api_init', array( new Agent_Context_Rest_Controller(), 'register_routes' ) );
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
						'sanitize_callback' => array( $this, 'sanitize_page_url_parameter' ),
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

		if ( '' === $value || 0 === strpos( $value, '//' ) ) {
			return false;
		}

		if ( preg_match( '#^[a-zA-Z][a-zA-Z0-9+\-.]*:#', $value ) ) {
			$parts = wp_parse_url( $value );

			return false !== $parts
				&& in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true )
				&& ! empty( $parts['host'] );
		}

		return 0 === strpos( $value, '/' );
	}

	/**
	 * Sanitize the optional page_url parameter without changing encoded route values.
	 *
	 * @param mixed $value Candidate value.
	 * @return string
	 */
	public function sanitize_page_url_parameter( $value ): string {
		return is_string( $value ) ? esc_url_raw( $value ) : '';
	}

	/**
	 * Permission callback used by the REST API route.
	 *
	 * @return bool
	 */
	public function ensure_admin_permissions(): bool {
		return $this->context_service->user_has_capability( $this->context_service->get_client_access_capability() );
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

		if (
			! $this->can_render_client_on_current_surface()
			|| ! $this->should_render_surface( 'admin_sdk' )
		) {
			return;
		}

		$sdk_render_context = $this->get_sdk_render_context();

		if ( ! $sdk_render_context['should_render'] ) {
			return;
		}

		printf(
			'<script src="%s" type="module"></script> <link rel="stylesheet" href="%s">',  // phpcs:ignore WordPress.WP.EnqueuedResources -- MCP launcher web component requires inline loading.
			esc_url( $this->get_sdk_url( $sdk_render_context ) ),
			esc_url( $this->get_sdk_css_url() )
		);
	}

	/**
	 * Render Automator's default launcher when its presentation setting is enabled.
	 *
	 * @param mixed $post WordPress passed parameter.
	 *
	 * @return void
	 */
	public function render_default_launcher( $post ): void {
		if ( ! $this->get_uncanny_agent_setting( Admin_Settings_Uncanny_Agent_General::ENABLED_KEY ) ) {
			return;
		}

		$this->render_launcher( $post );
	}

	/**
	 * Render the chat launcher button.
	 *
	 * @param mixed $post - WordPress' passed parameter.
	 * @return void
	 */
	public function render_launcher( $post ): void {

		if ( ! $this->can_render_client_on_current_surface() ) {
			return;
		}

		if ( ! $this->context_service->should_render_button( $post ) ) {
			return;
		}

		if ( ! $this->should_render_surface( 'admin_launcher' ) ) {
			return;
		}

		$sdk_render_context = $this->get_sdk_render_context();

		if ( ! $sdk_render_context['should_render'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in generate_launcher_html.
		echo $this->generate_launcher_html( $sdk_render_context );
	}

	/**
	 * Mount the Uncanny Agent quicklink inside the WordPress admin bar.
	 *
	 * Rendered as a top-level item next to the Automator menu. Subscribed to the
	 * `automator_admin_bar_register` action fired by Automator_WP_Admin_Bar so the
	 * quicklink is added in the same callback execution as the Automator parent node,
	 * guaranteeing adjacency regardless of other plugins' admin_bar_menu priorities.
	 * The quicklink and canonical launcher use the same SDK runtime. Their presentation
	 * settings remain independent.
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar instance.
	 *
	 * @return void
	 */
	public function render_admin_bar_quicklink( \WP_Admin_Bar $admin_bar ): void {

		if (
			! $this->get_uncanny_agent_setting( Admin_Settings_Uncanny_Agent_General::TOP_BAR_BUTTON_ENABLED_KEY )
			|| ! $this->context_service->can_access_client()
			|| ! $this->should_render_surface( 'admin_bar_quicklink' )
		) {
			return;
		}

		$sdk_render_context = $this->get_sdk_render_context();

		if ( ! $sdk_render_context['should_render'] ) {
			return;
		}

		$payload = $this->payload_service->generate_encrypted_payload( $this->build_chat_payload_overrides() );

		if ( '' === $payload ) {
			return;
		}

		$can_dock_to_right = $this->in_allowed_pages();

		$shared_attributes = sprintf(
			'variant="wp-quicklink"
			server-url="%s"
			payload="%s"
			consumer-server-url="%s"
			consumer-nonce="%s"
			bundle-url="%s"
			bundle-css-url="%s"
			locale="%s"
			%s',
			esc_attr( self::get_inference_url() ),
			esc_attr( $payload ),
			esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ),
			esc_attr( wp_create_nonce( 'wp_rest' ) ),
			esc_url( $this->get_sdk_url( $sdk_render_context ) ),
			esc_url( $this->get_sdk_css_url() ),
			esc_attr( $this->context_service->get_user_locale_bcp47() ),
			( $can_dock_to_right ? 'can-dock-to-right' : '' )
		);

		$quicklink = sprintf(
			'<uaai-f-widget-launcher %1$s class="uncanny-agent-quicklink--desktop"></uaai-f-widget-launcher><uaai-f-widget-launcher %1$s icon-only class="uncanny-agent-quicklink--mobile"></uaai-f-widget-launcher>',
			$shared_attributes
		);

		$admin_bar->add_node(
			array(
				'id'    => 'uncanny-agent-quicklink',
				'title' => $quicklink,
				'href'  => false,
				'meta'  => array(
					'class' => 'uncanny-agent-quicklink',
				),
			)
		);
	}

	/**
	 * Print inline styles that keep the Uncanny Agent quicklink visible on mobile and swap to the icon-only variant.
	 *
	 * WordPress's responsive admin bar hides every non-core top-level item below 783px (`#wp-toolbar > ul > li { display: none; }`). The override re-shows the quicklink node so it stays reachable on phones, and toggles between the full and `icon-only` web component instances around the same breakpoint.
	 *
	 * @return void
	 */
	public function print_admin_bar_quicklink_styles(): void {

		if (
			! is_admin_bar_showing()
			|| ! $this->get_uncanny_agent_setting( Admin_Settings_Uncanny_Agent_General::TOP_BAR_BUTTON_ENABLED_KEY )
			|| ! $this->context_service->can_access_client()
			|| ! $this->should_render_surface( 'admin_bar_quicklink_styles' )
		) {
			return;
		}

		$sdk_render_context = $this->get_sdk_render_context();

		if ( ! $sdk_render_context['should_render'] ) {
			return;
		}

		echo '<style id="uncanny-agent-quicklink-css">
			#wpadminbar #wp-admin-bar-uncanny-agent-quicklink .uncanny-agent-quicklink--mobile {
				display: none;
			}
			@media screen and (max-width: 782px) {
				#wpadminbar #wp-admin-bar-uncanny-agent-quicklink {
					display: block;
				}
				#wpadminbar #wp-admin-bar-uncanny-agent-quicklink .uncanny-agent-quicklink--desktop {
					display: none;
				}
				#wpadminbar #wp-admin-bar-uncanny-agent-quicklink .uncanny-agent-quicklink--mobile {
					display: inline-block;
				}
			}
		</style>';
	}

	/**
	 * Check if the current admin page is one where the chat launcher should be rendered.
	 *
	 * Returns true for any page under the Automator menu: the uo-recipe post type
	 * screens (list, edit, add new, taxonomies) and all registered submenu pages.
	 *
	 * @see self::generate_launcher_html() — sole caller, uses the result for $can_dock_to_right.
	 *
	 * @return bool
	 */
	private function in_allowed_pages(): bool {
		$allowed = false;

		if ( ! function_exists( 'get_current_screen' ) ) {
			return (bool) Dispatcher::filter( 'automator_mcp_in_allowed_pages', $allowed, null );
		}

		$current_screen = get_current_screen();

		if ( ! $current_screen instanceof \WP_Screen ) {
			return (bool) Dispatcher::filter( 'automator_mcp_in_allowed_pages', $allowed, null );
		}

		// Post type screens: All recipes, Add new, single recipe editor.
		if ( AUTOMATOR_POST_TYPE_RECIPE === $current_screen->post_type ) {
			$allowed = true;
		}

		// Taxonomy screens: Categories (recipe_category), Tags (recipe_tag).
		if ( ! $allowed && in_array( $current_screen->taxonomy, array( 'recipe_category', 'recipe_tag' ), true ) ) {
			$allowed = true;
		}

		// Custom submenu pages all follow the pattern "uo-recipe_page_*".
		if ( ! $allowed && 0 === strpos( $current_screen->id, 'uo-recipe_page_' ) ) {
			$allowed = true;
		}

		// Hidden pages (e.g. recipe activity details) use "admin_page_uncanny-automator-*".
		if ( ! $allowed && 0 === strpos( $current_screen->id, 'admin_page_uncanny-automator-' ) ) {
			$allowed = true;
		}

		// WordPress dashboard (/wp-admin/index.php).
		if ( ! $allowed && 'dashboard' === $current_screen->id ) {
			$allowed = true;
		}

		return (bool) Dispatcher::filter( 'automator_mcp_in_allowed_pages', $allowed, $current_screen );
	}

	/**
	 * Generate the launcher HTML element including CSS.
	 *
	 * Handles all the logic: payload generation, recipe fetching, context building, CSS styles, and HTML element. Returns empty string on any failure.
	 *
	 * @return string The CSS and launcher HTML, or empty string on failure.
	 */
	private function generate_launcher_html( array $sdk_render_context ): string {

		if ( ! ( $sdk_render_context['should_render'] ?? false ) ) {
			return '';
		}

		$payload = $this->payload_service->generate_encrypted_payload( $this->build_chat_payload_overrides() );

		if ( '' === $payload ) {
			return '';
		}

		// Check if we can dock the widget to the right
		$can_dock_to_right = $this->in_allowed_pages();

		// Infer view mode based on the can dock to right flag
		$view_mode       = $can_dock_to_right ? 'fab' : 'bottom-dock';
		$parent_selector = (string) Dispatcher::filter( 'automator_mcp_launcher_parent_selector', '#wpbody' );
		if ( '' === trim( $parent_selector ) ) {
			$parent_selector = '#wpbody';
		}

		$launcher = sprintf(
			'<uaai-f-widget-launcher
				server-url="%s"
				payload="%s"
				parent-selector="%s"
				consumer-server-url="%s"
				consumer-nonce="%s"
				bundle-url="%s"
				bundle-css-url="%s"
				view-mode="%s"
				locale="%s"
				%s
			></uaai-f-widget-launcher>',
			esc_attr( self::get_inference_url() ),
			esc_attr( $payload ),
			esc_attr( $parent_selector ),
			esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ),
			esc_attr( wp_create_nonce( 'wp_rest' ) ),
			esc_url( $this->get_sdk_url( $sdk_render_context ) ),
			esc_url( $this->get_sdk_css_url() ),
			esc_attr( $view_mode ),
			esc_attr( $this->context_service->get_user_locale_bcp47() ),
			( $can_dock_to_right ? 'can-dock-to-right' : '' )
		);

		return $this->get_inline_css() . $launcher;
	}

	/**
	 * Determine whether the chat client may render on the current surface.
	 *
	 * Admin rendering keeps the existing manage_options gate. Frontend rendering
	 * is opt-in so integrations can mount the agent on controlled canvases.
	 *
	 * @return bool
	 */
	private function can_render_client_on_current_surface(): bool {

		if ( $this->context_service->can_access_client() ) {
			return true;
		}

		if ( is_admin() ) {
			return false;
		}

		return (bool) Dispatcher::filter( 'automator_mcp_show_on_frontend', false );
	}

	/**
	 * Allow admin integrations to suppress specific client surfaces.
	 *
	 * Use this to disable Automator's host-page SDK/launcher/quicklink output
	 * on custom admin screens while leaving other requests untouched.
	 *
	 * @param string $surface Surface identifier.
	 *
	 * @return bool
	 */
	private function should_render_surface( string $surface ): bool {
		return (bool) Dispatcher::filter( 'automator_mcp_should_render_surface', true, $surface );
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

		return Dispatcher::filter( 'automator_mcp_client_inference_url', $url );
	}

	/**
	 * REST callback that returns a refreshed encrypted payload.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function refresh_payload( WP_REST_Request $request ) {

		if ( ! $this->can_attempt_sdk_render_from_local_facts() ) {
			return new WP_Error(
				'agent_render_unavailable',
				esc_html_x(
					'Uncanny Agent is not available for this site right now.',
					'MCP client validation error',
					'uncanny-automator'
				),
				array( 'status' => 403 )
			);
		}

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

		if ( is_string( $page_url ) ) {
			$page_url = Client_Page_Url_Sanitizer::sanitize( $page_url, admin_url() );
		}

		$force_public_key_refresh = $request->get_param( 'force_public_key_refresh' );

		if ( null !== $force_public_key_refresh && ! is_bool( $force_public_key_refresh ) ) {
			return new WP_Error(
				'invalid_force_public_key_refresh',
				esc_html_x( 'The public key refresh flag must be a boolean.', 'MCP client validation error', 'uncanny-automator' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $this->public_key_manager->ensure_public_key_ready( true === $force_public_key_refresh ) ) {
			return new WP_Error(
				'public_key_unavailable',
				esc_html_x( 'Unable to load the required encryption key.', 'MCP client validation error', 'uncanny-automator' ),
				array( 'status' => 500 )
			);
		}

		$payload = $this->payload_service->generate_encrypted_payload(
			$this->build_chat_payload_overrides(
				is_string( $page_url ) ? array( 'page_url' => $page_url ) : array()
			)
		);

		if ( '' === $payload ) {
			$payload_error = $this->payload_service->get_last_error();
			if ( $payload_error instanceof WP_Error ) {
				return $payload_error;
			}

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
				'encrypted_payload'     => $payload,
				'context'               => $context,
				'conversation_starters' => $this->load_conversation_starters_for_refresh( $page_url, $context ),
			)
		);
	}

	/**
	 * Load conversation starters for the refresh response.
	 *
	 * @param string|null          $page_url Optional page URL from the request.
	 * @param array<string,mixed>  $context  Agent context.
	 *
	 * @return array<int,array{id:int,label:string,prompt:string}>
	 */
	private function load_conversation_starters_for_refresh( ?string $page_url, array $context ): array {

		$url      = $this->get_conversation_starter_url( $page_url, $context );
		$starters = $this->conversation_registry->load_by_context( $url, $this->get_conversation_starter_post_type( $url, $context ) );

		$rows = array_map(
			static fn( Conversation_Starter $starter ): array => $starter->to_array(),
			$starters
		);

		/**
		 * Filters the conversation starters resolved for the current page.
		 *
		 * Integrations own the starters for their surfaces (Uncanny Page
		 * Builder declares the canvas editor's starters) and may replace the
		 * registry set entirely when the URL is theirs. Every filtered row is
		 * re-validated through the domain object below, so malformed rows are
		 * dropped rather than corrupting the chat SDK response shape.
		 *
		 * @param array<int,array{id:int,label:string,prompt:string}> $rows Resolved starter rows.
		 * @param string                                              $url  URL used for context matching.
		 */
		try {
			$rows = Dispatcher::filter( 'automator_mcp_conversation_starters', $rows, $url );
		} catch ( \Throwable $e ) {
			// Integrations may degrade the starters, never the chat refresh:
			// a throwing extension callback falls back to the registry rows.
			unset( $e );
		}

		$validated = array();

		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			try {
				$validated[] = Conversation_Starter::from_array( $row )->to_array();
			} catch ( InvalidArgumentException $e ) {
				continue;
			}
		}

		return array_slice( $validated, 0, 5 );
	}

	/**
	 * Resolve the URL used for conversation starter matching.
	 *
	 * @param string|null         $page_url Optional page URL from the request.
	 * @param array<string,mixed> $context  Agent context.
	 *
	 * @return string
	 */
	private function get_conversation_starter_url( ?string $page_url, array $context ): string {

		if ( is_string( $page_url ) && '' !== $page_url ) {
			return $page_url;
		}

		$context_url = $context['WordPress']['currentScreen']['url'] ?? '';

		return is_string( $context_url ) ? $context_url : '';
	}

	/**
	 * Resolve the post type used for conversation starter matching.
	 *
	 * @param string              $url     URL used for starter matching.
	 * @param array<string,mixed> $context Agent context.
	 *
	 * @return string
	 */
	private function get_conversation_starter_post_type( string $url, array $context ): string {

		$current_post = $context['WordPress']['currentPost'] ?? false;

		if ( is_array( $current_post ) && ! empty( $current_post['type'] ) && is_string( $current_post['type'] ) ) {
			return sanitize_key( $current_post['type'] );
		}

		$post_type = $this->get_post_type_from_url( $url );

		if ( '' !== $post_type ) {
			return $post_type;
		}

		$screen_id = $context['WordPress']['currentScreen']['id'] ?? '';

		if ( is_string( $screen_id ) && 0 === strpos( $screen_id, 'edit-' ) ) {
			return sanitize_key( substr( $screen_id, strlen( 'edit-' ) ) );
		}

		return '';
	}

	/**
	 * Resolve a post type from a WordPress admin URL.
	 *
	 * @param string $url URL used for starter matching.
	 *
	 * @return string
	 */
	private function get_post_type_from_url( string $url ): string {

		$path     = wp_parse_url( $url, PHP_URL_PATH );
		$basename = is_string( $path ) ? basename( $path ) : '';
		$query    = array();

		$query_string = wp_parse_url( $url, PHP_URL_QUERY );

		if ( is_string( $query_string ) ) {
			wp_parse_str( $query_string, $query );
		}

		if ( isset( $query['post_type'] ) && is_scalar( $query['post_type'] ) ) {
			return sanitize_key( (string) $query['post_type'] );
		}

		if ( in_array( $basename, array( 'edit.php', 'post-new.php' ), true ) ) {
			return 'post';
		}

		if ( 'admin.php' === $basename && isset( $query['page'] ) && is_scalar( $query['page'] ) ) {
			return $this->get_post_type_from_admin_page( (string) $query['page'] );
		}

		return '';
	}

	/**
	 * Resolve known admin page slugs to post types.
	 *
	 * @param string $page Admin page slug.
	 *
	 * @return string
	 */
	private function get_post_type_from_admin_page( string $page ): string {

		$page = sanitize_key( $page );

		if ( 'wc-orders' === $page ) {
			return 'shop_order';
		}

		if ( 'wc-orders--shop_subscription' === $page ) {
			return 'shop_subscription';
		}

		if ( 0 === strpos( $page, 'uncanny-automator-' ) || 0 === strpos( $page, 'uo-recipe-' ) ) {
			return defined( 'AUTOMATOR_POST_TYPE_RECIPE' ) ? AUTOMATOR_POST_TYPE_RECIPE : 'uo-recipe';
		}

		return '';
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
			$context = $this->create_url_agent_context( $page_url )->build();
		} else {
			$context = $this->agent_context->build();
		}

		return $context;
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
		unset( $request );

		if ( ! $this->get_uncanny_agent_setting( Admin_Settings_Uncanny_Agent_General::ENABLED_KEY ) ) {
			return rest_ensure_response(
				array(
					'html' => '',
				)
			);
		}

		$sdk_render_context = $this->get_sdk_render_context();
		$html               = $this->generate_launcher_html( $sdk_render_context );

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
	private function get_sdk_url( array $sdk_render_context ): string {

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
		$url = Dispatcher::filter( 'automator_mcp_client_sdk_url', $url );

		$query_args  = array();
		$license_key = isset( $sdk_render_context['license_key'] ) && is_string( $sdk_render_context['license_key'] )
			? $sdk_render_context['license_key']
			: '';
		if ( ! empty( $license_key ) ) {
			$query_args[ self::SDK_LICENSE_HASH_QUERY_ARG ] = hash_hmac( 'sha256', $license_key, $license_key );
		}

		$license_payload_query_value = isset( $sdk_render_context['license_payload_query_value'] ) && is_string( $sdk_render_context['license_payload_query_value'] )
			? $sdk_render_context['license_payload_query_value']
			: '';

		if ( ! empty( $license_key ) && '' !== $license_payload_query_value ) {
			$query_args[ self::SDK_LICENSE_PAYLOAD_QUERY_ARG ] = rawurlencode( $license_payload_query_value );
		}

		$query_args['v'] = AUTOMATOR_PLUGIN_VERSION; // No need to check constant - defined in main plugin file.

		return add_query_arg( $query_args, $url );
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

		return Dispatcher::filter( 'automator_mcp_client_sdk_css_url', $url );
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

			uaai-f-widget-launcher {
				--ua-mpc-chat-launcher-z-index: 159900;
			}
		</style>';
	}
}

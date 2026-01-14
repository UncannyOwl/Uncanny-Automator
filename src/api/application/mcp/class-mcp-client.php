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

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client\Client_Context_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client\Client_Payload_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client\Client_Public_Key_Manager;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client\Client_Token_Service;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Traits\Singleton;
use WP_Error;
use WP_Post;
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
	 * @param Client_Context_Service|null    $context_service Optional context helper.
	 * @param Client_Public_Key_Manager|null $public_key_manager Optional public key helper.
	 * @param Client_Token_Service|null      $token_service Optional token helper.
	 * @param Client_Payload_Service|null    $payload_service Optional payload helper.
	 */
	public function __construct(
		?Client_Context_Service $context_service = null,
		?Client_Public_Key_Manager $public_key_manager = null,
		?Client_Token_Service $token_service = null,
		?Client_Payload_Service $payload_service = null
	) {
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
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_action( 'admin_footer', array( $this, 'load_chat_sdk' ) );
		add_action( 'edit_form_after_title', array( $this, 'render_launcher' ) );
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
		if ( ! $this->context_service->can_access_client() ) {
			return;
		}

		if ( ! $this->context_service->is_recipe_screen() ) {
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
	 * @param WP_Post|null $post Current post.
	 * @return void
	 */
	public function render_launcher( ?WP_Post $post ): void {
		if ( ! $this->context_service->should_render_button( $post ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in generate_launcher_html.
		echo $this->generate_launcher_html( $post->ID );
	}

	/**
	 * Generate the launcher HTML element including CSS.
	 *
	 * Handles all the logic: payload generation, recipe fetching, context building, CSS styles, and HTML element. Returns empty string on any failure.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return string The CSS and launcher HTML, or empty string on failure.
	 */
	private function generate_launcher_html( int $recipe_id ): string {

		// Ensure the post is a valid recipe.
		if ( 'uo-recipe' !== get_post_type( $recipe_id ) ) {
			return '';
		}

		// Overwrite with the recipe edit page if the recipe ID is available.
		if ( ! empty( $recipe_id ) ) {
			$overrides = array(
				'page_url' => wp_make_link_relative( get_edit_post_link( $recipe_id ) ),
			);
		}

		$payload = $this->payload_service->generate_encrypted_payload( $overrides );

		if ( '' === $payload ) {
			return '';
		}

		$recipe = Automator()->get_recipe_object( $recipe_id, ARRAY_A );

		if ( empty( $recipe ) || ! is_array( $recipe ) ) {
			return '';
		}

		// Skip if recipe type is not set yet - button will be loaded via AJAX after user selects type.
		if ( empty( $recipe['recipe_type'] ) ) {
			return '';
		}

		$context = array(
			'current_mode'      => array( 'recipe building', 'running action' ),
			'current_user'      => array(
				'firstname' => $this->context_service->get_current_user_display_name() ?? '',
			),
			'current_recipe'    => $this->transform_recipe_to_context( $recipe ),
			'current_user_plan' => array(
				'id'                        => $this->context_service->get_current_user_plan(),
				'name'                      => $this->context_service->get_current_user_plan_name(),
				'can_add_scheduled_actions' => 'lite' !== $this->context_service->get_current_user_plan(),
				'can_run_loops'             => 'lite' !== $this->context_service->get_current_user_plan(),
				'can_add_action_conditions' => 'lite' !== $this->context_service->get_current_user_plan(),
			),
			'metadata'          => $this->get_metadata(),
		);

		$css = '<style>
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

		$launcher = sprintf(
			'<ua-chat-launcher server-url="%s" payload="%s" initial-context=\'%s\' parent-selector="#wpbody" consumer-server-url="%s" consumer-nonce="%s"></ua-chat-launcher>',
			esc_attr( self::get_inference_url() ),
			esc_attr( $payload ),
			esc_attr( base64_encode( wp_json_encode( $context ) ) ),
			esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ),
			esc_attr( wp_create_nonce( 'wp_rest' ) )
		);

		return $css . $launcher;
	}

	/**
	 * Transform recipe data structure to match the expected context schema.
	 *
	 * This method maps the recipe data from Automator()->get_recipe_object()
	 * to the structure expected by the MCP chat client (validated by Zod/Pydantic).
	 *
	 * @param array $recipe Recipe data from Automator()->get_recipe_object().
	 * @return array Transformed recipe context with keys: id, title, triggers, actions, conditions_group.
	 */
	private function transform_recipe_to_context( array $recipe ): array {

		// Initialize context with safe defaults.
		$context = array(
			'id'               => 0,
			'title'            => '',
			'recipe_type'      => '',
			'triggers'         => array(),
			'actions'          => array(),
			'conditions_group' => array(),
		);

		// Extract and validate recipe ID.
		if ( isset( $recipe['recipe_id'] ) ) {
			$context['id'] = absint( $recipe['recipe_id'] );
		}

		// Extract and sanitize recipe title.
		if ( isset( $recipe['title'] ) && is_string( $recipe['title'] ) ) {
			$context['title'] = sanitize_text_field( $recipe['title'] );
		}

		// Extract recipe user type ('user' or 'anonymous').
		if ( isset( $recipe['recipe_type'] ) && is_string( $recipe['recipe_type'] ) ) {
			$context['recipe_type'] = sanitize_text_field( $recipe['recipe_type'] );
		}

		// Transform triggers.
		$context['triggers'] = $this->extract_triggers( $recipe );

		// Transform actions and condition groups.
		$actions_data = $this->extract_actions_and_conditions( $recipe );

		$context['actions']          = $actions_data['actions'] ?? array();
		$context['conditions_group'] = $actions_data['conditions_group'] ?? array();

		/**
		 * Filter the transformed recipe context before it is sent to the MCP client.
		 *
		 * @param array $context Transformed recipe context.
		 * @param array $recipe  Original recipe data.
		 */
		return apply_filters( 'automator_mcp_transform_recipe_context', $context, $recipe );
	}

	/**
	 * Extract triggers from recipe data.
	 *
	 * @param array $recipe Recipe data.
	 * @return array Array of trigger objects with id and sentence.
	 */
	private function extract_triggers( array $recipe ): array {

		$triggers = array();

		if ( ! isset( $recipe['triggers']['items'] ) || ! is_array( $recipe['triggers']['items'] ) ) {
			return $triggers;
		}

		foreach ( $recipe['triggers']['items'] as $trigger ) {

			if ( ! is_array( $trigger ) ) {
				continue;
			}

			// Validate required fields.
			if ( ! isset( $trigger['id'] ) || ! isset( $trigger['backup']['sentence'] ) ) {
				continue;
			}

			// Skip if sentence is empty.
			if ( empty( $trigger['backup']['sentence'] ) || ! is_string( $trigger['backup']['sentence'] ) ) {
				continue;
			}

			$triggers[] = array(
				'id'       => absint( $trigger['id'] ),
				'sentence' => sanitize_text_field( $trigger['backup']['sentence'] ),
			);

		}

		return $triggers;
	}

	/**
	 * Extract actions and condition groups from recipe data.
	 *
	 * @param array $recipe Recipe data.
	 * @return array Array with 'actions' and 'conditions_group' keys.
	 */
	private function extract_actions_and_conditions( array $recipe ): array {

		$actions          = array();
		$conditions_group = array();

		if ( ! isset( $recipe['actions']['items'] ) || ! is_array( $recipe['actions']['items'] ) ) {
			return array(
				'actions'          => $actions,
				'conditions_group' => $conditions_group,
			);
		}

		foreach ( $recipe['actions']['items'] as $item ) {

			if ( ! is_array( $item ) ) {
				continue;
			}

			$item_type = $item['type'] ?? '';

			// Handle root-level actions.
			if ( 'action' === $item_type ) {
				$action = $this->extract_single_action( $item );
				if ( ! empty( $action ) ) {
					$actions[] = $action;
				}
			}

			// Handle condition groups (filters).
			if ( 'filter' === $item_type ) {
				$group = $this->extract_condition_group( $item );
				// Only add non-empty groups.
				if ( ! empty( $group['conditions'] ) || ! empty( $group['actions'] ) ) {
					$conditions_group[] = $group;
				}
			}
		}

		return array(
			'actions'          => $actions,
			'conditions_group' => $conditions_group,
		);
	}

	/**
	 * Extract a single action from item data.
	 *
	 * @param array $item Action item data.
	 * @return array Action object with id and sentence, or empty array if invalid.
	 */
	private function extract_single_action( array $item ): array {

		// Validate required fields.
		if ( ! isset( $item['id'] ) || ! isset( $item['backup']['sentence'] ) ) {
			return array();
		}

		// Skip if sentence is empty.
		if ( empty( $item['backup']['sentence'] ) || ! is_string( $item['backup']['sentence'] ) ) {
			return array();
		}

		return array(
			'id'       => absint( $item['id'] ),
			'sentence' => sanitize_text_field( $item['backup']['sentence'] ),
		);
	}

	/**
	 * Extract a condition group (filter) from item data.
	 *
	 * @param array $item Filter item data.
	 * @return array Group object with conditions and actions arrays.
	 */
	private function extract_condition_group( array $item ): array {

		$group = array(
			'conditions' => array(),
			'actions'    => array(),
		);

		// Extract conditions.
		if ( isset( $item['conditions'] ) && is_array( $item['conditions'] ) ) {

			foreach ( $item['conditions'] as $condition ) {

				if ( ! is_array( $condition ) ) {
					continue;
				}

				// Validate required fields.
				if ( ! isset( $condition['id'] ) || ! isset( $condition['backup']['sentence'] ) ) {
					continue;
				}

				// Skip if sentence is empty.
				if ( empty( $condition['backup']['sentence'] ) || ! is_string( $condition['backup']['sentence'] ) ) {
					continue;
				}

				$group['conditions'][] = array(
					'id'       => sanitize_text_field( $condition['id'] ),
					'sentence' => sanitize_text_field( $condition['backup']['sentence'] ),
				);

			}
		}

		// Extract actions within the filter.
		if ( isset( $item['items'] ) && is_array( $item['items'] ) ) {

			foreach ( $item['items'] as $filter_action ) {

				if ( ! is_array( $filter_action ) ) {
					continue;
				}

				// Only process action types.
				if ( 'action' !== ( $filter_action['type'] ?? '' ) ) {
					continue;
				}

				$action = $this->extract_single_action( $filter_action );

				if ( ! empty( $action ) ) {
					$group['actions'][] = $action;
				}
			}
		}

		return $group;
	}

	/**
	 * Inference service URL.
	 *
	 * @return string
	 */
	public static function get_inference_url(): string {
		$url = defined( 'AUTOMATOR_MCP_CLIENT_INFERENCE_URL' ) && AUTOMATOR_MCP_CLIENT_INFERENCE_URL
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

		return rest_ensure_response(
			array(
				'encrypted_payload' => $payload,
			)
		);
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
	 * Get metadata to send to the MCP client.
	 *
	 * @return string
	 */
	private function get_metadata(): string {

		global $wp;

		$current_url = home_url( add_query_arg( $_GET, $wp->request ) );

		$server_software = sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- No sensitive data.

		$metadata = array(
			'plugin_version'  => AUTOMATOR_PLUGIN_VERSION,
			'current_url'     => $current_url,
			'php_version'     => PHP_VERSION,
			'wp_version'      => get_bloginfo( 'version' ),
			'server_software' => ! empty( $server_software ) ? $server_software : 'unknown',
		);

		// Safe to ignore false return - array is well-formed. Using base64 to safely encode JSON for payload.
		return base64_encode( wp_json_encode( $metadata ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- No sensitive data. 
	}
}

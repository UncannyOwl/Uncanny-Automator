<?php
/**
 * Reviewed exposure of native REST operations through MCP.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Site_Management_Settings;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Abilities\Automator_Abilities;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Catalog;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Database_Access_Settings;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Authorization_Policy;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Availability_Settings;
use Uncanny_Automator\App\Transports\Restful\Post_Content\Post_Content_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Post_Meta\Post_Meta_Rest_Controller;

/**
 * Exposure is separate from native permission callbacks, which still run.
 */
final class Rest_Operation_Policy {

	public const ABILITY_ROUTE            = '/wp-abilities/v1/abilities/(?P<name>[a-zA-Z0-9\-\/]+?)/run';
	public const DEFAULT_MAX_INPUT_BYTES  = 262144;
	public const MAX_REVIEWED_INPUT_BYTES = 1572864;

	/** Refuse force=true. Only native controllers with trash support guarantee a trash operation. */
	public const TRASH_ONLY_REQUEST_SCHEMA = array(
		'type'       => 'object',
		'properties' => array(
			'query' => self::NO_FORCE,
			'body'  => self::NO_FORCE,
		),
	);

	private const NO_FORCE = array(
		'type'       => 'object',
		'properties' => array(
			'force' => array(
				'description' => 'Forced deletion is not available here. Leave force out or set it to false. Native WordPress routes with trash support move items to the trash; other plugin routes may delete permanently without force.',
				'type'        => 'boolean',
				'enum'        => array( false ),
			),
		),
	);

	/**
	 * Return reviewed operation metadata, or null for an unexposed operation.
	 *
	 * An administrator reaches every registered route except the closed ones, as
	 * in wp-admin. Automator's own routes open only through an explicit policy.
	 * Extensions may narrow or close any route through the policy filters.
	 */
	public function describe( string $route, string $method, array $handler, array $path_params = array() ): ?array {
		if ( false === ( $handler['show_in_index'] ?? true ) || ! is_callable( $handler['permission_callback'] ?? null ) ) {
			return null;
		}
		if ( self::ABILITY_ROUTE === $route ) {
			return $this->ability_policy( $method, $handler, $path_params['name'] ?? '' );
		}
		// Site editing has its own native-handler, effect and settings boundaries.
		if ( Site_Editor_Operations::owns( $route ) ) {
			return Site_Editor_Operations::describe( $route, $method, $handler );
		}
		if ( self::is_closed( $route, $method ) ) {
			return null;
		}

		$policy   = self::default_policy( $route, $method, $handler );
		$callback = $handler['callback'] ?? null;
		if ( Post_Content_Rest_Controller::ROUTE === $route && is_array( $callback ) && $callback[0] instanceof Post_Content_Rest_Controller ) {
			if ( 'GET' === $method && 'get_item' === $callback[1] ) {
				$policy = array(
					'read_only' => true,
					'tools'     => array( 'get_posts', 'post_apply_content_edit' ),
				);
			} elseif ( 'POST' === $method && 'post_apply_content_edit' === $callback[1] ) {
				$policy = array(
					'read_only' => false,
					'tools'     => array( 'get_posts', 'post_apply_content_edit' ),
				);
			}
		}
		if ( Post_Meta_Rest_Controller::ROUTE === $route && is_array( $callback ) && $callback[0] instanceof Post_Meta_Rest_Controller ) {
			if ( 'GET' === $method && 'get_item' === $callback[1] ) {
				$policy = array(
					'read_only' => true,
					'tools'     => array( 'get_posts', 'post_apply_meta_edit' ),
				);
			} elseif ( 'POST' === $method && 'post_apply_meta_edit' === $callback[1] ) {
				$policy = array(
					'read_only' => false,
					'tools'     => array( 'get_posts', 'post_apply_meta_edit' ),
				);
			}
		}
		// Binary uploads use their own transfer path, not model arguments.
		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) && $callback[0] instanceof \WP_REST_Attachments_Controller && 'create_item' === $callback[1] ) {
			$policy = null;
		}

		$media_upload = '/wp/v2/media' === $route && 'POST' === $method;
		if ( $media_upload && ! Media_Upload_Transfer::is_operation( $route, $method, $handler ) ) {
			// Replaced handlers cannot opt out of the native upload boundary.
			return null;
		}
		if ( $media_upload ) {
			$policy = array(
				'read_only' => false,
				'tools'     => array( 'get_posts' ),
				'upload'    => Media_Upload_Transfer::contract(),
			);
		}

		$policy = Dispatcher::filter( 'automator_mcp_rest_operation_policy', $policy, $route, $method, $handler );
		// Owning modules can close unsupported routes after ordinary extension opt-in.
		$policy = Dispatcher::filter( 'automator_mcp_rest_operation_policy_review', $policy, $route, $method, $handler );
		if ( ! is_array( $policy ) || ! isset( $policy['read_only'], $policy['tools'] ) || ! is_bool( $policy['read_only'] ) || ! is_array( $policy['tools'] ) ) {
			return null;
		}
		foreach ( $policy['tools'] as $tool ) {
			if ( ! is_string( $tool ) || '' === $tool || in_array( $tool, array( 'list_users', 'run_ability', 'mysql_get_tables', 'mysql_get_table_columns', 'mysql_select_from_table', 'mysql_get_cell_chunk' ), true ) ) {
				return null;
			}
		}

		$reviewed = array(
			'read_only'       => $policy['read_only'],
			'tools'           => array_values( array_unique( $policy['tools'] ) ),
			'max_input_bytes' => self::DEFAULT_MAX_INPUT_BYTES,
		);
		if ( $media_upload ) {
			// Extensions may close this operation, but cannot weaken its invariants.
			$reviewed['read_only'] = false;
			$reviewed['tools']     = array_values( array_unique( array_merge( $reviewed['tools'], array( 'get_posts' ) ) ) );
			$reviewed['upload']    = Media_Upload_Transfer::contract();
		}
		if ( array_key_exists( 'max_input_bytes', $policy ) ) {
			if ( ! is_int( $policy['max_input_bytes'] ) || $policy['max_input_bytes'] < 1 || $policy['max_input_bytes'] > self::MAX_REVIEWED_INPUT_BYTES ) {
				return null;
			}
			$reviewed['max_input_bytes'] = $policy['max_input_bytes'];
		}
		// Extensions can add bounded, data-only contracts for sparse native routes.
		foreach ( array( 'request_schema', 'native_tool', 'target_fields' ) as $field ) {
			if ( isset( $policy[ $field ] ) ) {
				if ( ! is_array( $policy[ $field ] ) || ! self::is_data( $policy[ $field ] ) ) {
					return null;
				}
				$reviewed[ $field ] = $policy[ $field ];
			}
		}
		foreach ( $reviewed['target_fields'] ?? array() as $field ) {
			if ( ! is_string( $field ) || 1 !== preg_match( '/^(?:query|body)(?:\.[a-z_][a-z0-9_]*)*$/D', $field ) ) {
				return null;
			}
		}
		return $reviewed;
	}

	/** Operations worse than an administrator's own click: account takeover, lockout, file deletion and requests to other services. */
	private static function is_closed( string $route, string $method ): bool {
		$read = in_array( $method, array( 'GET', 'HEAD' ), true );
		// Users can be read; changing one, or any application password, can take over or lock out an account.
		if ( preg_match( '#^/wp/v2/users(?:/|$)#i', $route ) && ( ! $read || preg_match( '#/application-passwords(?:/|$)#i', $route ) ) ) {
			return true;
		}
		// Uninstalling deletes a plugin's files, which site_manage never does.
		if ( 'DELETE' === $method && preg_match( '#^/wp/v2/plugins(?:/|$)#i', $route ) ) {
			return true;
		}
		// The MCP and OAuth routes hold this connection; batch and oEmbed proxy dispatch nested or outbound requests;
		// URL details fetches any address; rendering runs arbitrary block and widget callbacks; the navigation
		// fallback writes on GET; font routes take binary uploads, which have their own transfer path.
		return 1 === preg_match( '#^/(?:automator/v1/(?:mcp|oauth)(?:/|$)|wp-abilities/|batch/|oembed/|wp-block-editor/v1/url-details|wp/v2/(?:block-renderer(?:/|$)|widget-types/[^/]+/(?:render|encode)$|navigation-fallback$|font-families|font-faces))#i', $route );
	}

	/** Every other route behind the owner's matching tool switch; Automator's own routes need an explicit policy. */
	private static function default_policy( string $route, string $method, array $handler ): ?array {
		if ( preg_match( '#^/(?:automator|uap|uncanny-page-builder)(?:/|$)#i', $route ) ) {
			return null;
		}
		$callback   = $handler['callback'] ?? null;
		$controller = is_array( $callback ) ? ( $callback[0] ?? null ) : null;
		// Known native controllers without trash are excluded; plugin controllers own their deletion behavior.
		if ( 'DELETE' === $method && ! self::has_trash( $controller ) ) {
			return null;
		}
		$policy = array(
			'read_only' => in_array( $method, array( 'GET', 'HEAD' ), true ),
			'tools'     => self::tool_gates( $route, $controller ),
		);
		if ( 'DELETE' === $method ) {
			$policy['request_schema'] = self::TRASH_ONLY_REQUEST_SCHEMA;
		}
		return $policy;
	}

	/**
	 * Whether WordPress's own controller can move this item to the trash, from the site's trash settings.
	 * Other controllers decide for themselves, and a forced delete is refused for them too.
	 */
	private static function has_trash( $controller ): bool {
		if ( $controller instanceof \WP_REST_Terms_Controller || $controller instanceof \WP_REST_Menu_Items_Controller ) {
			return false;
		}
		if ( $controller instanceof \WP_REST_Attachments_Controller ) {
			return EMPTY_TRASH_DAYS > 0 && MEDIA_TRASH;
		}
		if ( $controller instanceof \WP_REST_Posts_Controller || $controller instanceof \WP_REST_Comments_Controller ) {
			return EMPTY_TRASH_DAYS > 0;
		}
		return true;
	}

	/** The owner's tool switch each operation follows; plugin routes follow rest_request alone. */
	private static function tool_gates( string $route, $controller ): array {
		// This gate covers the core plugins controller, not every third-party installer.
		// Plugin routes (including wc-admin installers) retain native permissions unless
		// an integration adds a policy. Enabling REST access accepts that limitation;
		// route names cannot reliably identify every plugin's installation or refund effects.
		if ( $controller instanceof \WP_REST_Plugins_Controller ) {
			return array( 'site_manage' );
		}
		if ( $controller instanceof \WP_REST_Terms_Controller ) {
			return array( 'get_terms' );
		}
		if ( $controller instanceof \WP_REST_Posts_Controller || self::is_core_controller( $route, $controller ) ) {
			return array( 'get_posts' );
		}
		return array();
	}

	/** WordPress's own controllers in the core namespace. */
	private static function is_core_controller( string $route, $controller ): bool {
		if ( ! $controller instanceof \WP_REST_Controller || 0 !== strpos( $route, '/wp/v2/' ) ) {
			return false;
		}
		$file = ( new \ReflectionClass( $controller ) )->getFileName();
		return is_string( $file ) && 0 === strpos( wp_normalize_path( $file ), wp_normalize_path( ABSPATH . WPINC ) . '/' );
	}

	/** Reject callback objects and unbounded extension metadata. */
	private static function is_data( $value, int $depth = 0 ): bool {
		if ( $depth > 16 ) {
			return false;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $child ) {
				if ( ! self::is_data( $child, $depth + 1 ) ) {
					return false;
				}
			}
			return true;
		}
		return null === $value || is_scalar( $value );
	}

	/** Only the native runner can expose an owned or explicitly enabled ability. */
	private function ability_policy( string $method, array $handler, string $name ): ?array {
		$callback   = $handler['callback'] ?? null;
		$permission = $handler['permission_callback'] ?? null;
		if ( ! is_array( $callback ) || ! isset( $callback[0], $callback[1] ) || ! is_object( $callback[0] ) || 'WP_REST_Abilities_V1_Run_Controller' !== get_class( $callback[0] ) || 'execute_ability' !== $callback[1] || array( $callback[0], 'check_ability_permissions' ) !== $permission || ! function_exists( 'wp_has_ability' ) || ! function_exists( 'wp_get_ability' ) || ! wp_has_ability( $name ) ) {
			return null;
		}
		$ability = wp_get_ability( $name );
		if ( ! $ability instanceof \WP_Ability || true !== $ability->get_meta_item( 'show_in_rest', false ) ) {
			return null;
		}
		$policy = Automator_Abilities::policy_for( $ability );
		if ( null !== $policy ) {
			if ( Automator_Abilities::method_for( $ability ) !== $method ) {
				return null;
			}
		} else {
			$catalog = new Ability_Catalog();
			// An Automator bridge that lost its identity cannot opt back in via metadata.
			if ( Automator_Abilities::is_operation_name( $name ) || null !== $ability->get_meta_item( 'automator_operation' ) || $catalog->find_enabled( $name ) !== $ability ) {
				return null;
			}
			$annotations = $ability->get_meta_item( 'annotations', array() );
			$annotations = is_array( $annotations ) ? $annotations : array();
			$read_only   = $catalog->is_read_only( $ability );
			// WordPress chooses the native verb from provider metadata; it does not grant access.
			$verb = ! empty( $annotations['readonly'] ) ? 'GET' : ( ! empty( $annotations['destructive'] ) && ! empty( $annotations['idempotent'] ) ? 'DELETE' : 'POST' );
			if ( $method !== $verb ) {
				return null;
			}
			$policy = array(
				'read_only' => $read_only,
				'tools'     => array( 'run_ability' ),
			);
		}
		$policy['ability_name']    = $name;
		$policy['max_input_bytes'] = self::DEFAULT_MAX_INPUT_BYTES;
		return $policy;
	}

	/**
	 * Explain the current caller's transport-level availability.
	 */
	public function unavailable_reason( array $policy, User_Context $context, string $entry_tool ): ?string {
		if ( null === $context->get_executor() || ! user_can( $context->get_executor(), 'manage_options' ) ) {
			return 'administrator_required';
		}
		if ( ! $policy['read_only'] && ! $context->can_write() ) {
			return 'write_scope_required';
		}
		foreach ( $policy['tools'] as $tool ) {
			// Once the owner turns site management off, it is off for every caller.
			if ( Site_Management_Settings::requires_consent( $tool ) && ! ( new Site_Management_Settings() )->is_enabled() ) {
				return 'site_management_consent_required';
			}
			if ( Tool_Authorization_Policy::is_first_party_only( $tool ) && ! $context->is_first_party_caller() ) {
				return 'first_party_only';
			}
			$database = new Database_Access_Settings();
			if ( Database_Access_Settings::requires_consent( $tool ) && ! $database->is_enabled() && ( ! $context->is_first_party_caller() || $database->applies_to_first_party() ) ) {
				return 'database_consent_required';
			}
		}
		if ( ! $context->is_first_party_caller() ) {
			$settings = new Tool_Availability_Settings();
			foreach ( array_merge( $policy['tools'], array( $entry_tool, 'rest_request' ) ) as $tool ) {
				if ( $settings->is_disabled( $tool ) ) {
					return 'tool_disabled';
				}
			}
		}

		return null;
	}
}

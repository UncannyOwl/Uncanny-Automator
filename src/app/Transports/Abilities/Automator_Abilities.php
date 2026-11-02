<?php
/**
 * Native WordPress abilities backed by validated Automator operations.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Abilities;

// These translations are JSON protocol values, not HTML output.
// phpcs:disable Uncanny_Automator.Strings.TranslationFunction.UnescapedTranslation, Uncanny_Automator.Strings.AutoContextTranslation.MissingContext

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Mcp_Settings_Guard;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Protected_Setting_Refused;
use ReflectionProperty;
use Uncanny_Automator\App\Recipe_Builder\Shared\Security\Security;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Schema_Validator;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\MCP_Tool_Interface;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Operation_Policy;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Operation_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Request_Scope;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Authorization_Policy;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Availability_Settings;
use WP_Ability;
use WP_Error;

/** Shares operation handlers without another HTTP or JSON-RPC dispatch. */
final class Automator_Abilities {

	/** Server-owned operations; transport runners are deliberately excluded. */
	private const OPERATIONS = array(
		'get_recipe'              => Catalog\Recipes\Get_Recipe_Tool::class,
		'list_recipes'            => Catalog\Recipes\List_Recipes_Tool::class,
		'save_recipe'             => Catalog\Recipes\Save_Recipe_Tool::class,
		'search'                  => Catalog\Search_Tool::class,
		'get_component_schema'    => Catalog\Get_Component_Schema_Tool::class,
		'get_field_options'       => Catalog\Get_Field_Options_Tool::class,
		'get_posts'               => Catalog\Get_Posts_Tool::class,
		'post_apply_content_edit' => Catalog\Posts\Post_Apply_Content_Edit_Tool::class,
		'post_apply_meta_edit'    => Catalog\Posts\Post_Apply_Meta_Edit_Tool::class,
		'get_terms'               => Catalog\Get_Terms_Tool::class,
		'list_plugins'            => Catalog\List_Plugins_Tool::class,
		'get_site_context'        => Catalog\Get_Site_Context_Tool::class,
		'list_users'              => Catalog\List_Users_Tool::class,
		'list_roles'              => Catalog\List_Roles_Tool::class,
		'save_trigger'            => Catalog\Triggers\Save_Trigger_Tool::class,
		'save_action'             => Catalog\Actions\Save_Action_Tool::class,
		'save_block'              => Catalog\Blocks\Save_Block_Tool::class,
		'save_filter_condition'   => Catalog\Blocks\Save_Filter_Condition_Tool::class,
		'save_loop'               => Catalog\Loops\Save_Loop_Tool::class,
		'save_loop_filter'        => Catalog\Loops\Save_Loop_Filter_Tool::class,
		'run_recipe'              => Catalog\Recipes\Run_Recipe_Tool::class,
		'run_action'              => Catalog\Actions\Run_Action_Tool::class,
		'get_tokens'              => Catalog\Get_Tokens_Tool::class,
		'get_logs'                => Catalog\Get_Logs_Tool::class,
		'delete'                  => Catalog\Delete_Tool::class,
	);

	/**
	 * Operation handlers also supply settings independently of MCP exposure.
	 *
	 * @return array<string,MCP_Tool_Interface>
	 */
	public static function operation_tools(): array {
		$tools = array();
		foreach ( self::OPERATIONS as $operation => $class ) {
			$tools[ $operation ] = new $class();
		}
		return $tools;
	}

	/** @var array<string,array> Exact registered objects and their original contracts. */
	private static array $registered = array();

	/** Avoid recursive probes from plugins that build a catalog during REST initialization. */
	private static bool $checking_native_route = false;

	/** Register lazily on the native API hooks when WordPress supplies them. */
	public function init(): void {
		// A compatibility package may load the API after this plugin. Its native
		// hooks are safe to subscribe to before those functions become available.
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/** An operation can leave the root catalog only while its native bridge and runner are trusted. */
	public static function has_native_operation( string $operation ): bool {
		if ( self::$checking_native_route || ! isset( self::OPERATIONS[ $operation ] ) || ! function_exists( 'wp_get_ability' ) || ! function_exists( 'wp_has_ability' ) || ! did_action( 'init' ) ) {
			return false;
		}
		$name = 'automator/' . str_replace( '_', '-', $operation );
		if ( ! wp_has_ability( $name ) ) {
			return false;
		}
		$ability = wp_get_ability( $name );
		if ( ! $ability instanceof WP_Ability || ! self::owns( $ability ) ) {
			return false;
		}
		// Check the same concrete runner identity and route precedence as dispatch.
		// This does not execute a permission callback or retry a denied operation:
		// the original root handler still applies its own credential and tool policy.
		self::$checking_native_route = true;
		try {
			$resolved = ( new Rest_Operation_Service() )->resolve( '/wp-abilities/v1/abilities/' . $name . '/run', self::method_for( $ability ) );
			return is_array( $resolved ) && ( $resolved['policy']['ability_name'] ?? null ) === $name;
		} finally {
			self::$checking_native_route = false;
		}
	}

	/** Every operation retains its owned native ability and trusted runner. */
	public static function native_operations_available(): bool {
		foreach ( array_keys( self::OPERATIONS ) as $operation ) {
			if ( ! self::has_native_operation( $operation ) ) {
				return false;
			}
		}
		return true;
	}

	/** Retired root names still belong to their original handlers, including fallback use. */
	public static function is_operation_tool( MCP_Tool_Interface $tool ): bool {
		return ( self::OPERATIONS[ $tool->get_name() ] ?? null ) === get_class( $tool );
	}

	/** Register the native catalogue category before its abilities. */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) || ! function_exists( 'wp_has_ability_category' ) || wp_has_ability_category( 'automator' ) ) {
			return;
		}
		wp_register_ability_category(
			'automator',
			array(
				'label'       => _x( 'Automator', 'Ability category', 'uncanny-automator' ),
				'description' => _x( 'Validated Automator operations for authenticated site administrators.', 'Ability category description', 'uncanny-automator' ),
			)
		);
	}

	/** Register the existing contracts and handlers, without the MCP tool registry. */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_has_ability' ) ) {
			return;
		}
		foreach ( self::OPERATIONS as $operation => $class ) {
			$name = 'automator/' . str_replace( '_', '-', $operation );
			if ( wp_has_ability( $name ) ) {
				continue;
			}
			try {
				$this->register_operation( $name, $operation, new $class() );
			} catch ( \Throwable $error ) {
				// One broken operation must not hide the others. Without a
				// trusted native ability it stays on its MCP root handler.
				continue;
			}
		}
	}

	/** Only the original registered object may receive this bridge's authority. */
	public static function owns( WP_Ability $ability ): bool {
		$entry = self::$registered[ $ability->get_name() ] ?? null;
		return null !== $entry
			&& $entry['ability'] === $ability
			&& function_exists( 'wp_has_ability' )
			&& wp_has_ability( $ability->get_name() )
			&& wp_get_ability( $ability->get_name() ) === $ability
			&& self::matches_registration( $ability, $entry );
	}

	/** Return the original operation, never a mutable metadata classification. */
	public static function operation_for( WP_Ability $ability ): ?string {
		return self::owns( $ability ) ? self::$registered[ $ability->get_name() ]['operation'] : null;
	}

	/** Reserved operation names cannot fall back to third-party annotations. */
	public static function is_operation_name( string $name ): bool {
		return 0 === strpos( $name, 'automator/' ) && isset( self::OPERATIONS[ str_replace( '-', '_', substr( $name, 10 ) ) ] );
	}

	/** Native REST exposure retains the corresponding operation's access gates. */
	public static function policy_for( WP_Ability $ability ): ?array {
		$operation = self::operation_for( $ability );
		if ( null === $operation ) {
			return null;
		}
		return array(
			'read_only' => Authenticated_Token_Context::SCOPE_READ === Tool_Authorization_Policy::resolve_core_tool_scope( $operation ),
			'tools'     => in_array( $operation, array( 'post_apply_content_edit', 'post_apply_meta_edit' ), true ) ? array( 'get_posts', $operation ) : array( $operation ),
		);
	}

	/** Match core's native HTTP method using server-owned operation rules. */
	public static function method_for( WP_Ability $ability ): ?string {
		$policy = self::policy_for( $ability );
		if ( null === $policy ) {
			return null;
		}
		return $policy['read_only'] ? 'GET' : ( 'delete' === self::operation_for( $ability ) ? 'DELETE' : 'POST' );
	}

	/** Bind one native ability to its validated operation. */
	private function register_operation( string $name, string $operation, MCP_Tool_Interface $tool ): void {
		$schema = $tool->get_schema();
		Schema_Validator::assert_schema_is_safe( $schema['inputSchema'] );
		if ( isset( $schema['outputSchema'] ) ) {
			Schema_Validator::assert_schema_is_safe( $schema['outputSchema'] );
		}
		$annotations = $schema['annotations'] ?? array();
		$meta        = array(
			'public'              => true,
			'show_in_rest'        => true,
			'automator_operation' => $operation,
			'mcp'                 => array( 'public' => true ),
			'annotations'         => array(
				'readonly'      => Authenticated_Token_Context::SCOPE_READ === Tool_Authorization_Policy::resolve_core_tool_scope( $operation ),
				'destructive'   => true === ( $annotations['destructiveHint'] ?? false ),
				'idempotent'    => true === ( $annotations['idempotentHint'] ?? false ),
				'openWorldHint' => true === ( $annotations['openWorldHint'] ?? true ),
			),
		);
		$read_only   = $meta['annotations']['readonly'];
		$permission  = static function () use ( $name ) {
			return null !== self::authorized_context( $name );
		};
		$execute     = static function ( $input ) use ( $name, $tool, $schema, $read_only ) {
			$context = self::authorized_context( $name );
			if ( null === $context ) {
				Rest_Request_Scope::refuse_before_running();
				return new WP_Error( 'automator_ability_forbidden', _x( 'This operation requires administrator permission and must be enabled for this caller.', 'Ability error', 'uncanny-automator' ), array( 'status' => 403 ) );
			}
			try {
				$input = self::typed_input( $input, $schema['inputSchema'] );
				if ( ! is_array( $input ) ) {
					throw new \InvalidArgumentException( 'Input must be an object.' );
				}
				Schema_Validator::validate_mcp_params( $input, $schema['inputSchema'] );
			} catch ( \InvalidArgumentException $error ) {
				Rest_Request_Scope::refuse_before_running();
				return new WP_Error(
					'automator_ability_invalid_input',
					(string) Security::sanitize( $error->getMessage() ),
					array(
						'status'  => 400,
						'outcome' => 'refused_before_running',
					)
				);
			} catch ( \Throwable $error ) {
				Rest_Request_Scope::refuse_before_running();
				return new WP_Error(
					'automator_ability_validation_failed',
					_x( 'The Automator operation could not be validated. Ask a WordPress administrator to check the connection and site logs.', 'Ability error', 'uncanny-automator' ),
					array(
						'status'  => 500,
						'outcome' => 'refused_before_running',
					)
				);
			}
			try {
				$result = Mcp_Settings_Guard::during(
					static function () use ( $tool, $context, $input ) {
						return $tool->execute( $context, $input );
					}
				);
				if ( self::refused_before_running( $result ) ) {
					Rest_Request_Scope::refuse_before_running();
				}
				return self::native_result( $result );
			} catch ( Protected_Setting_Refused $error ) {
				return new WP_Error(
					'automator_ability_refused',
					$error->getMessage() . ' ' . _x( 'If the operation made other changes before this refusal, check the site before retrying.', 'Ability error', 'uncanny-automator' ),
					array(
						'status'  => 403,
						'outcome' => 'unconfirmed',
					)
				);
			} catch ( \Throwable $error ) {
				$message = $read_only
					? _x( 'The Automator operation could not complete. If it can make changes, check the site before retrying.', 'Ability error', 'uncanny-automator' )
					: _x( 'The Automator operation could not complete. It may have saved part of its work. Check the affected site or connected service before retrying.', 'Ability error', 'uncanny-automator' );
				return new WP_Error(
					'automator_ability_failed',
					$message,
					array(
						'status'  => 500,
						'outcome' => 'unconfirmed',
					)
				);
			}
		};
		// A caller that sends no input reaches WP_Ability as null, and
		// WordPress then uses the schema's default. Without one, an operation
		// whose fields are all optional, such as get-site-context, refuses an
		// empty call as "input is not of type object". Empty input is a valid
		// object, so declare it; required fields are still checked, and the
		// REST contract publishes this default as {}.
		$input_schema = self::native_schema( $schema['inputSchema'] );
		if ( 'object' === ( $input_schema['type'] ?? null ) && ! array_key_exists( 'default', $input_schema ) ) {
			$input_schema['default'] = array();
		}
		$entry = array(
			'operation'           => $operation,
			'input_schema'        => $input_schema,
			'output_schema'       => $schema['outputSchema'] ?? array(),
			'meta'                => $meta,
			'execute_callback'    => $execute,
			'permission_callback' => $permission,
		);
		$args  = $entry;
		unset( $args['operation'] );
		$ability = wp_register_ability(
			$name,
			array_merge(
				$args,
				array(
					/* translators: %s: Automator operation name. */
					'label'       => sprintf( _x( 'Automator: %s', 'Ability label', 'uncanny-automator' ), $schema['title'] ),
					'description' => $schema['description'],
					'category'    => 'automator',
				)
			)
		);
		if ( $ability instanceof WP_Ability && self::matches_registration( $ability, $entry ) ) {
			$entry['ability']          = $ability;
			self::$registered[ $name ] = $entry;
		}
	}

	/** Recheck native registration filters and runtime replacement before trust. */
	private static function matches_registration( WP_Ability $ability, array $entry ): bool {
		// Recover availability through the original root handler, never by
		// relaxing trust in a registration changed by another plugin.
		if ( WP_Ability::class !== get_class( $ability ) || $entry['input_schema'] !== $ability->get_input_schema() || $entry['output_schema'] !== $ability->get_output_schema() ) {
			return false;
		}
		foreach ( $entry['meta'] as $key => $value ) {
			$actual = $ability->get_meta_item( $key );
			// Core fills in annotation keys it knows. Compare the keys Automator set.
			if ( is_array( $value ) && is_array( $actual ) ) {
				foreach ( $value as $item => $expected ) {
					if ( ! array_key_exists( $item, $actual ) || $actual[ $item ] !== $expected ) {
						return false;
					}
				}
				continue;
			}
			if ( $actual !== $value ) {
				return false;
			}
		}
		foreach ( array( 'execute_callback', 'permission_callback' ) as $property ) {
			try {
				$reflection = new ReflectionProperty( WP_Ability::class, $property );
			} catch ( \ReflectionException $error ) {
				return false;
			}
			if ( PHP_VERSION_ID < 80100 ) {
				$reflection->setAccessible( true );
			}
			if ( $entry[ $property ] !== $reflection->getValue( $ability ) ) {
				return false;
			}
		}
		return true;
	}

	/** Preserve MCP scope, or use the authenticated WordPress caller's native permissions. */
	private static function authorized_context( string $name ): ?User_Context {
		$entry = self::$registered[ $name ] ?? null;
		if ( null === $entry || ! self::owns( $entry['ability'] ) ) {
			return null;
		}
		$context = Rest_Request_Scope::current_context( $name );
		$policy  = self::policy_for( $entry['ability'] );
		if ( null !== $context ) {
			$reason = ( new Rest_Operation_Policy() )->unavailable_reason( $policy, $context, 'rest_request' );
			return null === $reason ? $context : null;
		}
		// Other MCP plugins authenticate their own WordPress user. An existing
		// Automator MCP call must never upgrade its scope through that ambient user.
		$executor = get_current_user_id();
		if ( Rest_Request_Scope::is_active() || Mcp_Settings_Guard::is_guarding() || ! $executor || ! user_can( $executor, 'manage_options' ) ) {
			return null;
		}
		$settings = new Tool_Availability_Settings();
		foreach ( $policy['tools'] as $tool ) {
			if ( $settings->is_disabled( $tool ) ) {
				return null;
			}
		}
		// Content and meta edits still delegate through the existing preparation/save bridge.
		if ( in_array( $entry['operation'], array( 'post_apply_content_edit', 'post_apply_meta_edit' ), true ) && $settings->is_disabled( 'rest_request' ) ) {
			return null;
		}
		if ( 'list_users' === $entry['operation'] && ! user_can( $executor, 'list_users' ) ) {
			return null;
		}
		return new User_Context( $executor, null );
	}

	/**
	 * WordPress 7.1 coerces native input to the input schema; 6.9 and 7.0 pass
	 * GET query values as strings. Coerce the same way as core, and only input
	 * that WordPress accepts but Automator's stricter validator would refuse.
	 *
	 * @param mixed $input  Ability input.
	 * @param array $schema Registered input schema.
	 * @return mixed
	 */
	private static function typed_input( $input, array $schema ) {
		if ( ! is_array( $input ) ) {
			return $input;
		}
		try {
			Schema_Validator::validate_mcp_params( $input, $schema );
			return $input;
		} catch ( \InvalidArgumentException $error ) {
			if ( is_wp_error( rest_validate_value_from_schema( $input, $schema, 'input' ) ) ) {
				return $input;
			}
			$coerced = rest_sanitize_value_from_schema( $input, $schema, 'input' );
			return self::contains_error( $coerced ) ? $input : $coerced;
		}
	}

	/**
	 * Sanitizing can return an error at any depth; such input stays as supplied.
	 *
	 * @param mixed $value Sanitized value.
	 */
	private static function contains_error( $value ): bool {
		if ( is_wp_error( $value ) ) {
			return true;
		}
		foreach ( is_array( $value ) ? $value : array() as $item ) {
			if ( self::contains_error( $item ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The schema as WordPress's validator reads it: arrays only.
	 *
	 * MCP schemas write an empty "properties" as an object so it encodes as {};
	 * WordPress indexes it as an array and fails on the first input key. An
	 * empty "properties" says nothing, so it is left out.
	 *
	 * @param mixed $schema Schema or part of one.
	 * @return mixed
	 */
	private static function native_schema( $schema ) {
		if ( is_object( $schema ) ) {
			$schema = get_object_vars( $schema );
		}
		if ( ! is_array( $schema ) ) {
			return $schema;
		}
		if ( array_key_exists( 'properties', $schema ) && ( is_object( $schema['properties'] ) || array() === $schema['properties'] ) && array() === (array) $schema['properties'] ) {
			unset( $schema['properties'] );
		}
		foreach ( $schema as $key => $child ) {
			$schema[ $key ] = self::native_schema( $child );
		}
		return $schema;
	}

	/** Whether the operation's own result says it stopped before doing anything. */
	private static function refused_before_running( array $result ): bool {
		if ( empty( $result['isError'] ) ) {
			return false;
		}
		$payload = json_decode( (string) ( $result['content'][0]['text'] ?? '' ), true );
		return is_array( $payload ) && 'refused_before_running' === ( $payload['data']['outcome'] ?? null );
	}

	/** Unwrap the shared operation result without changing completed side effects. */
	private static function native_result( array $result ) {
		if ( ! empty( $result['isError'] ) ) {
			$payload = json_decode( (string) ( $result['content'][0]['text'] ?? '' ), true );
			$message = is_array( $payload ) && is_string( $payload['message'] ?? null ) ? $payload['message'] : _x( 'The Automator operation failed.', 'Ability error', 'uncanny-automator' );
			$status  = $payload['data']['status'] ?? 400;
			// Conflict and temporary-failure statuses carry recovery semantics. Do
			// not turn them into invalid input merely because the transport changed.
			$data = array( 'status' => is_int( $status ) && $status >= 300 && $status <= 599 ? $status : 400 );
			if ( is_array( $payload ) && array_key_exists( 'data', $payload ) ) {
				$data['data'] = $payload['data'];
			}
			return new WP_Error( 'automator_ability_failed', $message, $data );
		}
		return $result['structuredContent'];
	}
}

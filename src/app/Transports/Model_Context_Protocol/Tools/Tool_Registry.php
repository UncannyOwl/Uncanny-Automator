<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\App\Transports\Abilities\Automator_Abilities;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Get_Tables_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Get_Table_Columns_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Select_From_Table_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Get_Cell_Chunk_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Abilities\Discover_Ability_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Abilities\Run_Ability_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Rest\Rest_Discover_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Rest\Rest_Request_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Site_Commands\Site_Command_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Site_Commands\Site_Manage_Tool;

// Infrastructure.
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Schema_Validator;
use Uncanny_Automator\App\Recipe_Builder\Shared\Security\Security;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;

/**
 * MCP Tool Registry.
 *
 * Manages registration, schema validation and discovery of MCP tools.
 *
 * @since 7.0.0
 */
class Tool_Registry {

	/**
	 * Server-owned authorization policy.
	 *
	 * @var Tool_Authorization_Policy
	 */
	private Tool_Authorization_Policy $authorization_policy;

	/**
	 * Registered tools.
	 *
	 * @since 7.0.0
	 * @var MCP_Tool_Interface[]
	 */
	private $tools = array();

	/**
	 * Tool schemas cache.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private $schemas_cache = array();

	/**
	 * Required scope for each exposed tool.
	 *
	 * @var array<string,string>
	 */
	private array $required_scopes = array();

	/** @var array<string,array<string,array{path:string[],type:string}>> */
	private array $header_maps = array();

	private Tool_Header_Map $header_map_parser;

	/**
	 * Construct the registry with server-owned authorization and header policies.
	 */
	public function __construct( ?Tool_Authorization_Policy $authorization_policy = null, ?Tool_Header_Map $header_map_parser = null ) {
		$this->authorization_policy = $authorization_policy ?? new Tool_Authorization_Policy();
		$this->header_map_parser    = $header_map_parser ?? new Tool_Header_Map();
	}

	/**
	 * Register a tool.
	 *
	 * @since 7.0.0
	 *
	 * @param MCP_Tool_Interface $tool Tool instance.
	 * @return bool Whether the tool was accepted.
	 */
	public function register_tool( MCP_Tool_Interface $tool ) {
		$name = (string) $tool->get_name();
		if ( Automator_Abilities::is_operation_name( 'automator/' . str_replace( '_', '-', $name ) ) && ! Automator_Abilities::is_operation_tool( $tool ) ) {
			// Removing a root entry does not release its name to extensions: callers
			// may still use that name to read trusted site context or apply writes.
			return false;
		}
		if ( '' === trim( $name ) || strlen( $name ) > 128 || isset( $this->tools[ $name ] ) ) {
			// A plugin extension must never replace a server-owned tool by reusing
			// its name. Duplicate registration therefore fails without modifying
			// the already-validated entry.
			return false;
		}

		$scope = $this->authorization_policy->resolve( $tool );
		if ( null === $scope ) {
			return false;
		}

		$this->tools[ $name ]           = $tool;
		$this->required_scopes[ $name ] = $scope;
		// Clear cache when new tool is registered
		unset( $this->schemas_cache[ $name ] );

		try {
			$this->get_tool_schema( $name );
		} catch ( \Throwable $error ) {
			unset( $this->tools[ $name ], $this->required_scopes[ $name ], $this->schemas_cache[ $name ], $this->header_maps[ $name ] );
			return false;
		}

		return true;
	}

	/**
	 * Return the server-enforced scope for an exposed tool.
	 */
	public function get_required_scope( string $name ): ?string {
		return $this->required_scopes[ $name ] ?? null;
	}

	/**
	 * Return the validated x-mcp-header routing map for a tool.
	 *
	 * @return array<string,array{path:string[],type:string}>
	 */
	public function get_header_map( string $name ): array {
		return $this->header_maps[ $name ] ?? array();
	}

	/**
	 * Get tool by name.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name Tool name.
	 * @return MCP_Tool_Interface|null Tool instance or null if not found.
	 */
	public function get_tool( $name ) {
		return $this->tools[ $name ] ?? null;
	}

	/**
	 * Get all registered tools.
	 *
	 * @since 7.0.0
	 *
	 * @return MCP_Tool_Interface[] Array of tool instances.
	 */
	public function get_tools() {
		return $this->tools;
	}

	/**
	 * Get tool names.
	 *
	 * @since 7.0.0
	 *
	 * @return string[] Array of tool names.
	 */
	public function get_tool_names() {
		return array_keys( $this->tools );
	}

	/**
	 * Get tool schema by name.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name Tool name.
	 * @return array|null Tool schema or null if not found.
	 */
	public function get_tool_schema( $name ) {
		if ( ! isset( $this->tools[ $name ] ) ) {
			return null;
		}

		// Use cached schema if available
		if ( isset( $this->schemas_cache[ $name ] ) ) {
			return $this->schemas_cache[ $name ];
		}

		// Generate and cache schema
		$schema = $this->tools[ $name ]->get_schema();
		if (
			! is_array( $schema )
			|| ( $schema['name'] ?? null ) !== $name
			|| ! isset( $schema['inputSchema'] )
			|| ! is_array( $schema['inputSchema'] )
			|| 'object' !== ( $schema['inputSchema']['type'] ?? null )
		) {
			throw new \RuntimeException( 'Invalid MCP tool schema.' );
		}

		Schema_Validator::assert_schema_is_safe( $schema['inputSchema'] );
		$title = $schema['title'] ?? null;
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$schema['title'] = ucfirst( str_replace( array( '_', '-' ), ' ', $name ) );
		}
		$annotations = is_array( $schema['annotations'] ?? null ) ? $schema['annotations'] : array();
		$defaults    = array(
			'readOnlyHint'    => false,
			'destructiveHint' => true,
			'idempotentHint'  => false,
			'openWorldHint'   => true,
		);
		foreach ( $defaults as $key => $default ) {
			if ( ! is_bool( $annotations[ $key ] ?? null ) ) {
				$annotations[ $key ] = $default;
			}
		}
		if ( Authenticated_Token_Context::SCOPE_WRITE === $this->required_scopes[ $name ] && $annotations['readOnlyHint'] ) {
			$annotations['readOnlyHint']    = false;
			$annotations['destructiveHint'] = true;
			$annotations['idempotentHint']  = false;
		}
		$schema['annotations']      = $annotations;
		$this->header_maps[ $name ] = $this->header_map_parser->extract( $schema['inputSchema'] );
		if ( isset( $schema['outputSchema'] ) ) {
			if ( ! is_array( $schema['outputSchema'] ) ) {
				throw new \RuntimeException( 'Invalid MCP tool output schema.' );
			}
			Schema_Validator::assert_schema_is_safe( $schema['outputSchema'] );
		}

		$this->schemas_cache[ $name ] = $schema;

		return $schema;
	}

	/**
	 * Get all tool schemas.
	 *
	 * @since 7.0.0
	 *
	 * @return array Array of tool schemas.
	 */
	public function get_all_schemas() {
		$schemas = array();
		$names   = array_keys( $this->tools );
		sort( $names, SORT_STRING );

		foreach ( $names as $name ) {
			$schemas[] = $this->get_tool_schema( $name );
		}

		return $schemas;
	}

	/**
	 * Get the schemas available to one authenticated client.
	 *
	 * @return array Tool schemas allowed for the supplied credential purpose.
	 */
	public function get_schemas_for_client( ?Authenticated_Token_Context $context ): array {
		$schemas = array();
		$names   = array_keys( $this->tools );
		sort( $names, SORT_STRING );

		foreach ( $names as $name ) {
			if ( $this->is_tool_available_to_client( $name, $context ) && $this->client_has_required_scope( $name, $context ) ) {
				$schemas[] = $this->get_tool_schema( $name );
			}
		}

		return $schemas;
	}

	/**
	 * Whether the client's credential could call the tool.
	 *
	 * tools/list advertises only what tools/call would accept, so a read-only
	 * credential does not see write tools. This scope check allows missing context
	 * for the settings page and first-party Uncanny Agent credentials; the catalog
	 * applies client availability separately.
	 *
	 * @param string                          $name    Tool name.
	 * @param Authenticated_Token_Context|null $context Authenticated credential context.
	 * @return bool
	 */
	private function client_has_required_scope( string $name, ?Authenticated_Token_Context $context ): bool {
		if ( ! $context instanceof Authenticated_Token_Context || $context->is_first_party_agent() ) {
			return true;
		}

		$required_scope = $this->required_scopes[ $name ] ?? null;

		return null !== $required_scope && $context->has_scope( $required_scope );
	}

	/**
	 * Determine whether one registered tool is available to a client.
	 */
	public function is_tool_available_to_client( string $name, ?Authenticated_Token_Context $context ): bool {
		return isset( $this->tools[ $name ] )
			&& $this->authorization_policy->is_available_to_client( $name, $context );
	}

	/**
	 * Explain a registered tool's refusal for an authenticated caller.
	 *
	 * @since 8.0.0
	 * @param string $name Tool name.
	 * @param Authenticated_Token_Context $context Caller.
	 * @return array|null Availability refusal, or null for unknown/available tools.
	 */
	public function tool_unavailable_reason( string $name, Authenticated_Token_Context $context ): ?array {
		return isset( $this->tools[ $name ] ) ? $this->authorization_policy->unavailable_reason( $name, $context ) : null;
	}

	/**
	 * Execute tool by name.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name   Tool name.
	 * @param mixed  $params Tool parameters.
	 * @return array|WP_Error Tool execution result.
	 */
	public function execute_tool( $name, $params ) {
		return $this->execute_tool_with_context( $name, $params, null );
	}

	/**
	 * Execute tool by name with custom User_Context (for testing).
	 *
	 * @since 7.0.0
	 *
	 * @param string            $name          Tool name.
	 * @param mixed             $params        Tool parameters.
	 * @param User_Context|null $user_context  Custom user context (null = auto-detect).
	 * @return array|WP_Error Tool execution result.
	 */
	public function execute_tool_with_context( $name, $params, ?User_Context $user_context = null ) {
		$tool = $this->get_tool( $name );

		if ( ! $tool ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Tool "%s" not found', $name )
			);
		}

		if ( ! is_array( $params ) ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Failed to execute tool "%s": invalid tool parameters.', $name ),
				array( 'outcome' => 'refused_before_running' )
			);
		}

		try {
			// Use provided context or create one from current user
			if ( null === $user_context ) {
				$executor     = get_current_user_id() ? get_current_user_id() : User_Context::ANONYMOUS;
				$user_context = new User_Context( $executor, null );
			}

			$schema = $this->get_tool_schema( $name );
			Schema_Validator::validate_mcp_params( $params, (array) $schema['inputSchema'] );
		} catch ( \InvalidArgumentException $e ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Failed to execute tool "%s": %s', $name, Security::sanitize( $e->getMessage() ) ),
				array( 'outcome' => 'refused_before_running' )
			);
		} catch ( \Throwable $e ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Failed to validate tool "%s" before execution.', $name ),
				array( 'outcome' => 'refused_before_running' )
			);
		}

		try {
			// Do not validate output after execution. WordPress actions can complete
			// side effects before returning loosely typed third-party data. Turning a
			// completed action into an MCP error invites unsafe duplicate retries.
			// AI work never changes the owner's MCP switches; see Mcp_Settings_Guard.
			return Mcp_Settings_Guard::during(
				static function () use ( $tool, $user_context, $params ) {
					return $tool->execute( $user_context, $params );
				}
			);
		} catch ( Protected_Setting_Refused $e ) {
			$guidance = 'If the operation made other changes before this refusal, check the site before retrying.';
			return Json_Rpc_Response::create_error_response(
				$e->getMessage() . ' ' . $guidance,
				array(
					'code'        => 'protected_setting_refused',
					'outcome'     => 'unconfirmed',
					'next_action' => $guidance,
				)
			);
		} catch ( \Throwable $e ) {
			$guidance = 'If this operation can make changes, check the site before retrying. If the problem continues, ask a WordPress administrator to check the connection and site logs.';
			return Json_Rpc_Response::create_error_response(
				sprintf(
					'The result of tool "%s" could not be confirmed. %s',
					$name,
					$guidance
				),
				array(
					'outcome'     => 'unconfirmed',
					'next_action' => $guidance,
				)
			);
		}
	}

	/**
	 * Register the built-in MCP tools, then allow extensions through the registration hook.
	 *
	 * Keep the tool count lean. LLM accuracy drifts when there are too many tools.
	 *
	 * @since 7.0.0
	 * @since 7.1.0 Consolidated from 49 to 23 server tools. 37 deprecated tools unloaded.
	 * @since 7.2.4 Added mysql_get_cell_chunk for bounded large-value retrieval.
	 *
	 * @return void
	 */
	public function auto_register_tools() {

		foreach ( Automator_Abilities::operation_tools() as $operation => $tool ) {
			// API presence alone does not prove this operation has a safe native
			// route. Keep the original handler if registration collided or changed;
			// never borrow the replacement ability's callbacks or permissions.
			if ( ! Automator_Abilities::has_native_operation( $operation ) ) {
				$this->register_tool( $tool );
			}
		}

		// Database readers remain root tools, never native Automator abilities.
		$this->register_tool( new Mysql_Get_Tables_Tool() );
		$this->register_tool( new Mysql_Get_Table_Columns_Tool() );
		$this->register_tool( new Mysql_Select_From_Table_Tool() );
		$this->register_tool( new Mysql_Get_Cell_Chunk_Tool() );
		$this->register_tool( new Discover_Ability_Tool() );
		$this->register_tool( new Run_Ability_Tool() );
		$this->register_tool( new Rest_Discover_Tool() );
		$this->register_tool( new Rest_Request_Tool() );
		$this->register_tool( new Site_Command_Tool() );
		// On unless the site owner turns it off; see Site_Management_Settings.
		$this->register_tool( new Site_Manage_Tool() );

		Dispatcher::action( 'uncanny_automator_mcp_tool_registry_register', $this );
	}
}

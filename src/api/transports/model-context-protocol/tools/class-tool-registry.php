<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools;

// Recipe tools.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\Get_Recipe_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\List_Recipes_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\Save_Recipe_Tool;

// Discovery tools.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Search_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Component_Schema_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Posts_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Terms_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\List_Plugins_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\List_Users_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\List_Roles_Tool;

// MySQL tools.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Get_Tables_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Get_Table_Columns_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Select_From_Table_Tool;

// Consolidated CRUD tools.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Triggers\Save_Trigger_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions\Save_Action_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Save_Condition_Group_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Save_Condition_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Save_Loop_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Save_Loop_Filter_Tool;

// Consolidated utility tools.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Execute_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Tokens_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Logs_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Delete_Tool;

// Infrastructure.
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\Security\Security;

/**
 * MCP Tool Registry.
 *
 * Manages registration and discovery of MCP tools with automatic schema generation.
 *
 * @since 7.0.0
 */
class Tool_Registry {

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
	 * Register a tool.
	 *
	 * @since 7.0.0
	 *
	 * @param MCP_Tool_Interface $tool Tool instance.
	 * @return void
	 */
	public function register_tool( MCP_Tool_Interface $tool ) {
		$this->tools[ $tool->get_name() ] = $tool;
		// Clear cache when new tool is registered
		unset( $this->schemas_cache[ $tool->get_name() ] );
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
		$schema                       = $this->tools[ $name ]->get_schema();
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

		foreach ( $this->tools as $name => $tool ) {
			$schemas[] = $this->get_tool_schema( $name );
		}

		return $schemas;
	}

	/**
	 * Execute tool by name.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name   Tool name.
	 * @param array  $params Tool parameters.
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
	 * @param array             $params        Tool parameters.
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

		try {
			// Use provided context or create one from current user
			if ( null === $user_context ) {
				$executor     = get_current_user_id() ? get_current_user_id() : User_Context::ANONYMOUS;
				$user_context = new User_Context( $executor, null );
			}

			return $tool->execute( $user_context, $params );
		} catch ( \Exception $e ) {

			return Json_Rpc_Response::create_error_response(
				sprintf(
					'Failed to execute tool "%s": %s',
					$name,
					Security::sanitize( $e->getMessage() )
				)
			);
		}
	}

	/**
	 * Auto-register all tools from the tools directory.
	 *
	 * 23 server-registered tools + 1 client-local (get_field_options) = 24 total.
	 * Keep the tool count lean. LLM accuracy drifts when there are too many tools.
	 *
	 * @since 7.0.0
	 * @since 7.1.0 Consolidated from 49 to 23 server tools. 37 deprecated tools unloaded.
	 *
	 * @return void
	 */
	public function auto_register_tools() {

		// Recipe tools.
		$this->register_tool( new Get_Recipe_Tool() );
		$this->register_tool( new List_Recipes_Tool() );
		$this->register_tool( new Save_Recipe_Tool() );

		// Discovery tools.
		$this->register_tool( new Search_Tool() );
		$this->register_tool( new Get_Component_Schema_Tool() );
		// Not auto-registered on the server: the MCP client injects a local tool exposed to the
		// model as get_field_options, backed by the standalone dropdown REST endpoint.
		$this->register_tool( new Get_Posts_Tool() );
		$this->register_tool( new Get_Terms_Tool() );
		$this->register_tool( new List_Plugins_Tool() );
		$this->register_tool( new List_Users_Tool() );
		$this->register_tool( new List_Roles_Tool() );

		// MySQL tools.
		$this->register_tool( new Mysql_Get_Tables_Tool() );
		$this->register_tool( new Mysql_Get_Table_Columns_Tool() );
		$this->register_tool( new Mysql_Select_From_Table_Tool() );

		// Consolidated CRUD tools (upsert pattern: id absent = create, id present = update).
		$this->register_tool( new Save_Trigger_Tool() );
		$this->register_tool( new Save_Action_Tool() );
		$this->register_tool( new Save_Condition_Group_Tool() );
		$this->register_tool( new Save_Condition_Tool() );
		$this->register_tool( new Save_Loop_Tool() );
		$this->register_tool( new Save_Loop_Filter_Tool() );

		// Consolidated utility tools.
		$this->register_tool( new Execute_Tool() );
		$this->register_tool( new Get_Tokens_Tool() );
		$this->register_tool( new Get_Logs_Tool() );
		$this->register_tool( new Delete_Tool() );

		do_action( 'uncanny_automator_mcp_tool_registry_register', $this );
	}
}

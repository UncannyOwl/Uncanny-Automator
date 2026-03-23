<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\Get_Recipe_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\List_Recipes_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\Run_Recipe_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\Save_Recipe_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\Delete_Recipe_Component_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Search_Components_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Component_Schema_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Field_Options_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Posts_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Terms_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\List_Plugins_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\List_Users_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\List_Roles_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Get_Tables_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Get_Table_Columns_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Select_From_Table_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Services\Automator_Explorer_Factory;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Triggers\Add_Trigger_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Triggers\Update_Trigger_Tool;

// Action catalog tools.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions\Update_Action_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions\Add_Action_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions\List_Actions_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions\Run_Action_Tool;

// Condition catalog tools.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\List_Conditions_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Create_Condition_Group_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Add_Action_To_Condition_Group_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Remove_Action_From_Condition_Group_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Add_Condition_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Update_Condition_Group_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Remove_Condition_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Update_Condition_Tool;

// Log catalog tools.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Logs\Get_Log_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Logs\Get_Recipe_Logs_Tool;

// Loop catalog tools.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Add_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_List_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Get_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Update_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Delete_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Filter_Add_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Filter_List_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Filter_Get_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Filter_Update_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Filter_Delete_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Filter_Delete_All_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Get_Tokens_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Get_Loopable_Tokens_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops\Loop_Filter_List_Available_Tool;

// Enum tools.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\Get_Recipe_Tokens_Tool;

// User selector catalog tools.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\User_Selector\Save_User_Selector_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\User_Selector\Get_User_Selector_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\User_Selector\Delete_User_Selector_Tool;

// Use User_Context and WP_Error.
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

// Json Rpc Response.
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;

// Security.
use Uncanny_Automator\Api\Components\Security\Security;

// Exception.
use Exception;

// WP_Error.
use WP_Error;

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
			return array(
				'error'   => 'tool_not_found',
				'message' => sprintf( 'Tool "%s" not found', $name ),
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
	 * Keep the tool count lean. LLM accuracy drifts when there are too many tools.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function auto_register_tools() {

		// Recipe catalog tools.
		$this->register_tool( new Get_Recipe_Tool() );
		$this->register_tool( new List_Recipes_Tool() );
		$this->register_tool( new Run_Recipe_Tool() );
		$this->register_tool( new Save_Recipe_Tool() );
		$this->register_tool( new Get_Recipe_Tokens_Tool() );
		$this->register_tool( new Delete_Recipe_Component_Tool() );

		// Discovery tools.
		$this->register_tool(
			new Search_Components_Tool(
				new Automator_Explorer_Factory()
			)
		);

		$this->register_tool( new Get_Component_Schema_Tool() );
		// Disabled: Using standalone REST endpoint instead (fetch_dropdown_options tool in Python agent).
		// $this->register_tool( new Get_Field_Options_Tool() );
		$this->register_tool( new Get_Posts_Tool() );
		$this->register_tool( new Get_Terms_Tool() );
		$this->register_tool( new List_Plugins_Tool() );
		$this->register_tool( new List_Users_Tool() );
		$this->register_tool( new List_Roles_Tool() );

		// MySQL tools for database exploration.
		$this->register_tool( new Mysql_Get_Tables_Tool() );
		$this->register_tool( new Mysql_Get_Table_Columns_Tool() );
		$this->register_tool( new Mysql_Select_From_Table_Tool() );

		// Trigger catalog tools.
		$this->register_tool( new Add_Trigger_Tool() );
		$this->register_tool( new Update_Trigger_Tool() );

		// Action catalog tools.
		$this->register_tool( new Update_Action_Tool() );
		$this->register_tool( new Add_Action_Tool() );
		$this->register_tool( new List_Actions_Tool() );
		$this->register_tool( new Run_Action_Tool() );

		// Condition catalog tools.
		$this->register_tool( new List_Conditions_Tool() );
		$this->register_tool( new Create_Condition_Group_Tool() );
		$this->register_tool( new Add_Action_To_Condition_Group_Tool() );
		$this->register_tool( new Remove_Action_From_Condition_Group_Tool() );
		$this->register_tool( new Add_Condition_Tool() );
		$this->register_tool( new Update_Condition_Group_Tool() );
		$this->register_tool( new Remove_Condition_Tool() );
		$this->register_tool( new Update_Condition_Tool() );

		// Log catalog tools.
		$this->register_tool( new Get_Log_Tool() );
		$this->register_tool( new Get_Recipe_Logs_Tool() );

		// Loop catalog tools.
		$this->register_tool( new Loop_Add_Tool() );
		$this->register_tool( new Loop_List_Tool() );
		$this->register_tool( new Loop_Get_Tool() );
		$this->register_tool( new Loop_Update_Tool() );
		$this->register_tool( new Loop_Delete_Tool() );
		$this->register_tool( new Loop_Get_Tokens_Tool() );
		$this->register_tool( new Loop_Get_Loopable_Tokens_Tool() );

		// Loop filter catalog tools.
		$this->register_tool( new Loop_Filter_Add_Tool() );
		$this->register_tool( new Loop_Filter_List_Tool() );
		$this->register_tool( new Loop_Filter_Get_Tool() );
		$this->register_tool( new Loop_Filter_Update_Tool() );
		$this->register_tool( new Loop_Filter_Delete_Tool() );
		$this->register_tool( new Loop_Filter_Delete_All_Tool() );
		$this->register_tool( new Loop_Filter_List_Available_Tool() );

		// User selector catalog tools.
		$this->register_tool( new Save_User_Selector_Tool() );
		$this->register_tool( new Get_User_Selector_Tool() );
		$this->register_tool( new Delete_User_Selector_Tool() );

		do_action( 'uncanny_automator_mcp_tool_registry_register', $this );
	}
}

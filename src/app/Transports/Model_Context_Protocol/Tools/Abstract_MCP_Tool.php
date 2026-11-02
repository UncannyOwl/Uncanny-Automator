<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Recipe_Builder\Shared\Traits\Empty_Array_To_Object;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Schema_Validator;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Switch_Guard;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Switched_Off_Exception;

/**
 * Abstract MCP Tool.
 *
 * Base class for MCP tools with User_Context integration and explicit schema definitions.
 *
 * @since 7.0.0
 */
abstract class Abstract_MCP_Tool implements MCP_Tool_Interface {

	use Empty_Array_To_Object;

	/**
	 * Get tool schema (uses manual schema definition).
	 *
	 * @since 7.0.0
	 *
	 * @return array MCP tool schema.
	 */
	public function get_schema() {
		$schema = array(
			'name'        => $this->get_name(),
			'title'       => $this->get_title(),
			'description' => $this->get_description(),
			'inputSchema' => $this->get_normalized_input_schema(),
			'annotations' => $this->get_annotations(),
		);

		$output = $this->output_schema_definition();
		if ( null !== $output ) {
			$schema['outputSchema'] = array(
				'type'       => 'object',
				'properties' => array(
					'message' => array( 'type' => 'string' ),
					'data'    => $output,
				),
				'required'   => array( 'message', 'data' ),
			);
		}

		return $schema;
	}

	/**
	 * Supply a human-readable title for the tool catalog.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	protected function get_title(): string {
		$titles = array(
			Catalog\Delete_Tool::class                     => 'Delete recipe component',
			Catalog\Get_Component_Schema_Tool::class       => 'Get component schema',
			Catalog\Get_Field_Options_Tool::class          => 'Get field options',
			Catalog\Get_Logs_Tool::class                   => 'Get recipe logs',
			Catalog\Get_Posts_Tool::class                  => 'Get posts',
			Catalog\Recipes\Get_Recipe_Tool::class         => 'Get recipe',
			Catalog\Get_Site_Context_Tool::class           => 'Get site context',
			Catalog\Get_Terms_Tool::class                  => 'Get terms',
			Catalog\Get_Tokens_Tool::class                 => 'Get recipe tokens',
			Catalog\List_Plugins_Tool::class               => 'List plugins',
			Catalog\Recipes\List_Recipes_Tool::class       => 'List recipes',
			Catalog\List_Roles_Tool::class                 => 'List roles',
			Catalog\List_Users_Tool::class                 => 'List users',
			Catalog\Mysql_Get_Cell_Chunk_Tool::class       => 'Read a database cell chunk',
			Catalog\Mysql_Get_Table_Columns_Tool::class    => 'List database columns',
			Catalog\Mysql_Get_Tables_Tool::class           => 'List database tables',
			Catalog\Mysql_Select_From_Table_Tool::class    => 'Query database tables',
			Catalog\Posts\Post_Apply_Content_Edit_Tool::class => 'Edit post content',
			Catalog\Posts\Post_Apply_Meta_Edit_Tool::class => 'Edit post custom fields',
			Catalog\Rest\Rest_Discover_Tool::class         => 'Discover WordPress operations',
			Catalog\Rest\Rest_Request_Tool::class          => 'Run a WordPress REST operation',
			Catalog\Abilities\Run_Ability_Tool::class      => 'Run a WordPress ability',
			Catalog\Actions\Run_Action_Tool::class         => 'Run an Automator action',
			Catalog\Recipes\Run_Recipe_Tool::class         => 'Run a recipe',
			Catalog\Actions\Save_Action_Tool::class        => 'Save action',
			Catalog\Blocks\Save_Block_Tool::class          => 'Save block',
			Catalog\Blocks\Save_Filter_Condition_Tool::class => 'Save filter condition',
			Catalog\Loops\Save_Loop_Tool::class            => 'Save loop',
			Catalog\Loops\Save_Loop_Filter_Tool::class     => 'Save loop filter',
			Catalog\Recipes\Save_Recipe_Tool::class        => 'Create or update recipe',
			Catalog\Triggers\Save_Trigger_Tool::class      => 'Save trigger',
			Catalog\Search_Tool::class                     => 'Search Automator capabilities',
			Catalog\Site_Commands\Site_Command_Tool::class => 'Inspect site and files',
			Catalog\Site_Commands\Site_Manage_Tool::class  => 'Manage site',
		);
		$name   = (string) $this->get_name();

		return $titles[ get_class( $this ) ] ?? ucfirst( str_replace( array( '_', '-' ), ' ', $name ) );
	}

	/**
	 * Define the input schema for this tool.
	 *
	 * Child classes MUST override this method to provide their schema definition.
	 * The schema should match the Value Objects used in the Core API.
	 *
	 * @since 7.0.0
	 *
	 * @return array JSON Schema for tool input parameters.
	 */
	abstract protected function schema_definition();

	/**
	 * Get MCP 2025-03-26 tool annotations.
	 *
	 * Unknown tools use conservative hints. Known read-only built-ins specify
	 * whether their data is limited to this installation's own domain.
	 *
	 * @since 7.1.0
	 *
	 * @return array Tool annotations.
	 */
	protected function get_annotations(): array {
		$closed_readers = array(
			Catalog\Abilities\Discover_Ability_Tool::class,
			Catalog\Get_Logs_Tool::class,
			Catalog\Get_Posts_Tool::class,
			Catalog\Get_Terms_Tool::class,
			Catalog\List_Plugins_Tool::class,
			Catalog\List_Roles_Tool::class,
			Catalog\List_Users_Tool::class,
			Catalog\Recipes\List_Recipes_Tool::class,
			Catalog\Mysql_Get_Cell_Chunk_Tool::class,
			Catalog\Mysql_Get_Table_Columns_Tool::class,
			Catalog\Mysql_Get_Tables_Tool::class,
			Catalog\Mysql_Select_From_Table_Tool::class,
			Catalog\Rest\Rest_Discover_Tool::class,
		);
		$open_readers   = array(
			Catalog\Get_Component_Schema_Tool::class,
			Catalog\Get_Field_Options_Tool::class,
			Catalog\Get_Site_Context_Tool::class,
			Catalog\Get_Tokens_Tool::class,
			Catalog\Recipes\Get_Recipe_Tool::class,
			Catalog\Search_Tool::class,
		);
		$closed         = in_array( get_class( $this ), $closed_readers, true );
		$read_only      = $closed || in_array( get_class( $this ), $open_readers, true );

		return array(
			'readOnlyHint'    => $read_only,
			'destructiveHint' => ! $read_only,
			'idempotentHint'  => $read_only,
			'openWorldHint'   => ! $closed,
		);
	}

	/**
	 * Define the output schema for this tool's response data.
	 *
	 * Returns the JSON Schema for the `data` field in the response envelope.
	 * The base class wraps this in `{ message: string, data: <this schema> }`.
	 *
	 * Override in child classes. Return null to omit outputSchema from tools/list.
	 *
	 * @since 7.1.0
	 *
	 * @return array|null JSON Schema for tool output data, or null.
	 */
	protected function output_schema_definition(): ?array {
		return null;
	}

	/**
	 * Execute the tool with User_Context.
	 *
	 * Tools can modify the executee based on their specific needs before calling execute_tool.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context from MCP auth layer.
	 * @param array        $params       Tool parameters.
	 * @return array Tool execution result.
	 */
	public function execute( User_Context $user_context, array $params ) {
		$this->require_authenticated_executor( $user_context );
		Schema_Validator::validate_mcp_params( (array) $params, $this->get_normalized_input_schema() );

		Ability_Switch_Guard::enter();
		try {
			$result = $this->execute_tool( $user_context, $params );
		} catch ( Ability_Switched_Off_Exception $stopped ) {
			$result = null;
		} finally {
			$stopped_abilities = Ability_Switch_Guard::leave();
		}

		// A switched-off ability was stopped somewhere in this call, perhaps
		// inside another plugin's ability: the call answers with the stop.
		if ( array() !== $stopped_abilities ) {
			return Json_Rpc_Response::create_error_response(
				Ability_Switch_Guard::message( $stopped_abilities ),
				array(
					'code'      => Ability_Switch_Guard::ERROR_CODE,
					'abilities' => $stopped_abilities,
				)
			);
		}

		return $result;
	}

	/**
	 * Normalize the shared advertised and enforced input contract.
	 *
	 * Keeping this policy in one method prevents tools/list and tools/call from
	 * drifting when the default object restrictions change.
	 *
	 * @since 7.6.0
	 *
	 * @return array JSON Schema for tool input parameters.
	 */
	private function get_normalized_input_schema(): array {
		$input_schema         = (array) $this->schema_definition();
		$input_schema['type'] = 'object';
		if ( ! array_key_exists( 'additionalProperties', $input_schema ) ) {
			$input_schema['additionalProperties'] = false;
		}

		return $input_schema;
	}

	/**
	 * Execute the tool with User_Context and typed parameters.
	 *
	 * Each tool implements this method and provides its input contract separately
	 * through schema_definition().
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context for this operation.
	 * @param array        $params       Tool parameters.
	 * @return array Tool execution result.
	 */
	abstract protected function execute_tool( User_Context $user_context, array $params );


	/**
	 * Require an admin executor.
	 *
	 * The MCP server is an admin-only surface. Every tool must reject callers
	 * that either (a) have no authenticated executor or (b) are not administrators.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context.
	 * @throws \InvalidArgumentException If the executor is missing or lacks admin capability.
	 */
	protected function require_authenticated_executor( User_Context $user_context ): void {
		$executor = $user_context->get_executor();

		if ( null === $executor ) {
			throw new \InvalidArgumentException( 'Authenticated user required for this operation' );
		}

		if ( ! user_can( $executor, 'manage_options' ) ) {
			throw new \InvalidArgumentException( 'Administrator capability required for this operation' );
		}
	}

	/**
	 * Require user target.
	 *
	 * Helper method for tools that require a target user.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context.
	 * @throws \InvalidArgumentException If no target user specified.
	 */
	protected function require_user_target( User_Context $user_context ): void {
		if ( null === $user_context->get_executee() ) {
			throw new \InvalidArgumentException( 'Target user required for this operation' );
		}
	}

	/**
	 * Parse a JSON parameter that may arrive as a string.
	 *
	 * Some MCP clients send object/array parameters as JSON strings rather than
	 * native objects. This method normalises the value to a PHP array. When the
	 * standard decode fails it retries after replacing single quotes with double
	 * quotes — a common client mistake.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $param Parameter value (string, array, or other).
	 * @return array Parsed array, or empty array on failure.
	 */
	protected function parse_json_param( $param ): array {
		if ( is_string( $param ) ) {
			$decoded = json_decode( $param, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				// Retry with single-quote → double-quote replacement (common client mistake).
				$decoded = json_decode( str_replace( "'", '"', $param ), true );
			}
			return ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) ? $decoded : array();
		}
		return is_array( $param ) ? $param : array();
	}

	/**
	 * A service error's message followed by the reasons a refusal carries
	 * (`data.violations[].message`), so the caller knows what to fix.
	 *
	 * @since 8.0.0
	 *
	 * @param \WP_Error $error Service error.
	 * @return string
	 */
	protected function error_with_reasons( \WP_Error $error ): string {
		$data       = $error->get_error_data();
		$violations = is_array( $data ) && is_array( $data['violations'] ?? null ) ? $data['violations'] : array();
		$reasons    = array_filter( array_map( 'strval', array_column( $violations, 'message' ) ) );

		return implode( ' ', array_merge( array( $error->get_error_message() ), $reasons ) );
	}
}

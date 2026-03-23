<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol;

/**
 * Tool Class Mapper.
 *
 * Maps MCP tool names to their corresponding class files for easy lookup and autoloading.
 * Only includes active tools, excludes disabled tools like ua_fetch and ua_search.
 *
 * @since 7.0.0
 */
class Tool_Class_Mapper {

	/**
	 * Get mapping of tool names to their fully qualified class names.
	 *
	 * @since 7.0.0
	 * @return array Array mapping tool names to class names.
	 */
	public static function get_tool_class_mapping() {
		return array(
			// Recipe Management Tools
			'save_recipe'                           => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\Save_Recipe_Tool',
			'list_recipes'                          => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\List_Recipes_Tool',
			'get_recipe'                            => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\Get_Recipe_Tool',
			'run_recipe'                            => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\Run_Recipe_Tool',
			'get_recipe_tokens'                     => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes\Get_Recipe_Tokens_Tool',

			// Trigger Management Tools
			'add_trigger'                           => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Triggers\Add_Trigger_Tool',
			'update_trigger'                        => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Triggers\Update_Trigger_Tool',

			// Action Management Tools
			'add_action'                            => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions\Add_Action_Tool',
			'update_action'                         => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions\Update_Action_Tool',
			'list_actions'                          => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions\List_Actions_Tool',
			'run_action'                            => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions\Run_Action_Tool',

			// Condition Management Tools
			'list_conditions'                       => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\List_Conditions_Tool',
			'create_condition_group'                => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Create_Condition_Group_Tool',
			'update_condition_group'                => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Update_Condition_Group_Tool',
			'add_action_to_condition_group'         => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Add_Action_To_Condition_Group_Tool',
			'remove_action_from_condition_group'    => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Remove_Action_From_Condition_Group_Tool',
			'add_condition'                         => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Add_Condition_Tool',
			'remove_condition'                      => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Remove_Condition_Tool',
			'update_condition'                      => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions\Update_Condition_Tool',

			// Logging Tools
			'get_log'                               => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Logs\Get_Log_Tool',
			'list_logs'                             => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Logs\Get_Recipe_Logs_Tool',

			// Discovery Tools
			'search_components'                     => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Search_Components_Tool',
			'get_component_schema'                  => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Component_Schema_Tool',
			'get_field_options'                     => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Field_Options_Tool',
			'get_posts'                             => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Posts_Tool',
			'get_terms'                             => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Get_Terms_Tool',
			'list_plugins'                          => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\List_Plugins_Tool',
			'list_users'                            => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\List_Users_Tool',
			'list_roles'                            => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\List_Roles_Tool',

			// MySQL Database Tools
			'mysql_get_tables'                      => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Get_Tables_Tool',
			'mysql_get_table_columns'               => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Get_Table_Columns_Tool',
			'mysql_select_from_table'               => 'Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Mysql_Select_From_Table_Tool',
		);
	}

	/**
	 * Get class name for a specific tool.
	 *
	 * @since 7.0.0
	 * @param string $tool_name The tool name to look up.
	 * @return string|null The fully qualified class name, or null if not found.
	 */
	public static function get_tool_class( $tool_name ) {
		$mapping = self::get_tool_class_mapping();
		return $mapping[ $tool_name ] ?? null;
	}

	/**
	 * Check if a tool name exists in the mapping.
	 *
	 * @since 7.0.0
	 * @param string $tool_name The tool name to check.
	 * @return bool True if the tool exists, false otherwise.
	 */
	public static function tool_exists( $tool_name ) {
		return array_key_exists( $tool_name, self::get_tool_class_mapping() );
	}

	/**
	 * Get all available tool names.
	 *
	 * @since 7.0.0
	 * @return array Array of tool names.
	 */
	public static function get_tool_names() {
		return array_keys( self::get_tool_class_mapping() );
	}

	/**
	 * Get tools grouped by category.
	 *
	 * @since 7.0.0
	 * @return array Array of tools grouped by category.
	 */
	public static function get_tools_by_category() {
		return array(
			'recipe_management'    => array(
				'save_recipe',
				'list_recipes',
				'get_recipe',
				'run_recipe',
				'get_recipe_tokens',
			),
			'trigger_management'   => array(
				'add_trigger',
				'update_trigger',
			),
			'action_management'    => array(
				'add_action',
				'update_action',
				'list_actions',
				'run_action',
			),
			'condition_management' => array(
				'list_conditions',
				'create_condition_group',
				'update_condition_group',
				'add_action_to_condition_group',
				'remove_action_from_condition_group',
				'add_condition',
				'remove_condition',
				'update_condition',
			),
			'logging'              => array(
				'get_log',
				'list_logs',
			),
			'discovery'            => array(
				'search_components',
				'get_component_schema',
				'get_field_options',
				'get_posts',
				'get_terms',
				'list_plugins',
				'list_users',
				'list_roles',
			),
			'database'             => array(
				'mysql_get_tables',
				'mysql_get_table_columns',
				'mysql_select_from_table',
			),
		);
	}
}

<?php
/**
 * List Roles Tool.
 *
 * Lists WordPress roles and their capabilities.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * List Roles Tool.
 */
class List_Roles_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'list_roles';
	}

	/**
	 * Get tool description.
	 *
	 * @return string
	 */
	public function get_description() {
		return 'List WordPress roles available on this site with their capabilities. Shows role names, display names, and summarized capabilities.';
	}

	/**
	 * Define input schema.
	 *
	 * @return array
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => (object) array(), // Cast to object to ensure JSON {} not [].
			'required'   => array(),
		);
	}

	/**
	 * Execute tool.
	 *
	 * @param User_Context $user_context User context.
	 * @param array        $params       Tool parameters.
	 * @return array
	 */
	protected function execute_tool( User_Context $user_context, array $params ) {
		// Get WordPress roles object.
		$roles_object = wp_roles();

		$items = array();

		foreach ( $roles_object->roles as $role_key => $role_data ) {
			// Get only the capabilities that are granted (value = true).
			$capabilities = array_keys( array_filter( $role_data['capabilities'] ) );

			$items[] = array(
				'name'         => $role_key,
				'display_name' => $role_data['name'],
				'capabilities' => $capabilities,
			);
		}

		return Json_Rpc_Response::create_success_response(
			'Roles retrieved',
			array(
				'items' => $items,
				'total' => count( $items ),
			)
		);
	}
}

<?php
/**
 * List Plugins Tool.
 *
 * Lists WordPress plugins with optional status filtering.
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
 * List Plugins Tool.
 */
class List_Plugins_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'list_plugins';
	}

	/**
	 * Get tool description.
	 *
	 * @return string
	 */
	public function get_description() {
		return 'List WordPress plugins installed on this site. Filter by active/inactive status or search by plugin name.';
	}

	/**
	 * Define input schema.
	 *
	 * @return array
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'status' => array(
					'type'        => 'string',
					'enum'        => array( 'active', 'inactive', 'all' ),
					'default'     => 'all',
					'description' => 'Filter by plugin status',
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Search plugins by name',
				),
			),
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
		$status = $params['status'] ?? 'all';
		$search = strtolower( $params['search'] ?? '' );

		// Get all plugins.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		$items = array();

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$is_active = in_array( $plugin_file, $active_plugins, true );

			// Status filter.
			if ( 'active' === $status && ! $is_active ) {
				continue;
			}
			if ( 'inactive' === $status && $is_active ) {
				continue;
			}

			// Search filter.
			if ( ! empty( $search ) && false === strpos( strtolower( $plugin_data['Name'] ), $search ) ) {
				continue;
			}

			$items[] = array(
				'name'    => $plugin_data['Name'],
				'slug'    => dirname( $plugin_file ),
				'version' => $plugin_data['Version'],
				'status'  => $is_active ? 'active' : 'inactive',
			);
		}

		return Json_Rpc_Response::create_success_response(
			'Plugins retrieved',
			array(
				'items' => $items,
				'total' => count( $items ),
			)
		);
	}
}

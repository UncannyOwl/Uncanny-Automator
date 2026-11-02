<?php
/**
 * Site information for clients that have no WordPress admin page open.
 *
 * @package Uncanny_Automator
 * @since 7.6.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Transports\Abilities\Automator_Abilities;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Mcp_Tools_Version;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Site\Site_Design_Context;

/**
 * Uses the normal MCP scope, tool switch, and administrator checks.
 */
class Get_Site_Context_Tool extends Abstract_MCP_Tool {

	/** @return string */
	public function get_name() {
		return 'get_site_context';
	}

	/** @return string */
	public function get_description() {
		return 'Read this site\'s name, current WordPress, Automator and MCP tools versions, acting WordPress user, active Automator integrations, and bounded native theme presets and content templates. This does not include an open admin page or grant permission to use other tools.';
	}

	/** @return array */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => new \stdClass(),
		);
	}

	/** @return array */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'schema_version'      => array(
					'type' => 'string',
					'enum' => array( '1.0' ),
				),
				'site_name'           => array( 'type' => 'string' ),
				'site_url'            => array( 'type' => 'string' ),
				'wordpress_version'   => array( 'type' => 'string' ),
				'automator_version'   => array( 'type' => 'string' ),
				'mcp_tools_version'   => array( 'type' => 'string' ),
				'native_abilities_available' => array( 'type' => 'boolean' ),
				'wordpress_user_id'   => array( 'type' => 'integer' ),
				'wordpress_user_name' => array( 'type' => 'string' ),
				'design'              => $this->design_schema(),
				'active_integrations' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'code' => array( 'type' => 'string' ),
							'name' => array( 'type' => 'string' ),
						),
						'required'   => array( 'code', 'name' ),
					),
				),
			),
			// Fields added later, such as mcp_tools_version, stay optional so the
			// 1.0 identity a client validates against does not change.
			'required'   => array( 'schema_version', 'site_name', 'wordpress_version', 'automator_version', 'wordpress_user_id', 'wordpress_user_name', 'active_integrations' ),
		);
	}


	/** The additive design field does not change the identity schema version. */
	private function design_schema(): array {
		$text   = array(
			'type' => 'string',
			'maxLength' => 512,
		);
		$preset = array(
			'type'       => 'array',
			'maxItems'   => Site_Design_Context::MAX_PRESETS,
			'items'      => array(
				'type'       => 'object',
				'properties' => array(
					'slug'   => $text,
					'name'   => $text,
					'value'  => $text,
					'origin' => array(
						'type' => 'string',
						'enum' => array( 'default', 'theme', 'custom' ),
					),
				),
				'required'   => array( 'slug', 'name', 'value', 'origin' ),
			),
		);
		return array(
			'type'       => 'object',
			'properties' => array(
				'theme'                     => array(
					'type'       => 'object',
					'properties' => array(
						'name'           => $text,
						'stylesheet'     => $text,
						'version'        => $text,
						'is_block_theme' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'name', 'stylesheet', 'version', 'is_block_theme' ),
				),
				'source'                    => array(
					'type' => 'string',
					'enum' => array( 'wp_get_global_settings' ),
				),
				'global_settings_available' => array( 'type' => 'boolean' ),
				'limitation'                => $text,
				'presets'                   => array(
					'type'       => 'object',
					'properties' => array(
						'colors'        => $preset,
						'font_sizes'    => $preset,
						'font_families' => $preset,
						'spacing_sizes' => $preset,
					),
					'required'   => array( 'colors', 'font_sizes', 'font_families', 'spacing_sizes' ),
				),
				'layout'                    => array(
					'type'       => 'object',
					'properties' => array(
						'content_size' => array(
							'type' => array( 'string', 'null' ),
							'maxLength' => 512,
						),
						'wide_size'    => array(
							'type' => array( 'string', 'null' ),
							'maxLength' => 512,
						),
					),
					'required'   => array( 'content_size', 'wide_size' ),
				),
				'templates'                 => array(
					'type'     => 'array',
					'maxItems' => Site_Design_Context::MAX_TEMPLATES,
					'items'    => array(
						'type'       => 'object',
						'properties' => array(
							'post_type' => $text,
							'slug' => $text,
							'name' => $text,
						),
						'required'   => array( 'post_type', 'slug', 'name' ),
					),
				),
				'truncated'                 => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'theme', 'source', 'global_settings_available', 'limitation', 'presets', 'layout', 'templates', 'truncated' ),
		);
	}

	/**
	 * Read current values; a version recorded during approval can become stale.
	 *
	 * @param User_Context $user_context Authenticated executor, checked by the base tool.
	 * @param array        $params       Empty arguments.
	 * @return array
	 */
	protected function execute_tool( User_Context $user_context, array $params ) {
		$user = get_userdata( $user_context->get_executor() );

		// Reuse Agent_Context's integration source, but not its admin-screen,
		// account, email, or server metadata. A hosted client has no open WP page.
		$data = array(
			'schema_version'      => '1.0',
			'site_name'           => get_bloginfo( 'name' ),
			// Match the API site-name value, preserving subdomains and paths.
			'site_url'            => ( new License_Manager() )->get_site_name(),
			'wordpress_version'   => get_bloginfo( 'version' ),
			'automator_version'   => defined( 'AUTOMATOR_PLUGIN_VERSION' ) ? AUTOMATOR_PLUGIN_VERSION : '',
			// Tool changes are versioned on their own; see Mcp_Tools_Version.
			'mcp_tools_version'   => Mcp_Tools_Version::VERSION,
			'native_abilities_available' => Automator_Abilities::native_operations_available(),
			'wordpress_user_id'   => (int) $user->ID,
			'wordpress_user_name' => $user->display_name,
			'active_integrations' => Integration_Registry_Service::get_instance()->get_active_integrations(),
			'design'              => ( new Site_Design_Context() )->read( (int) $user->ID ),
		);

		return Json_Rpc_Response::create_success_response( 'Site context retrieved', $data );
	}
}

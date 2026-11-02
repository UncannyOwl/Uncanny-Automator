<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;

/**
 * Server-owned authorization policy for MCP tools.
 *
 * Tool annotations are descriptive hints for clients and are never trusted for
 * authorization. Core tools are classified here. Extensions must expose an
 * explicit get_required_scope() method or opt in through the policy filter.
 */
final class Tool_Authorization_Policy {

	/**
	 * Site readers reserved for Uncanny Agent. Database tools have a separate
	 * site-owner consent gate before they can be used by third-party apps.
	 *
	 * @var string[]
	 */
	private const FIRST_PARTY_ONLY_TOOLS = array(
		'list_users',
	);

	/** @var string[] */
	private const WRITE_TOOLS = array(
		'post_apply_content_edit',
		'post_apply_meta_edit',
		'save_recipe',
		'save_trigger',
		'save_action',
		'save_block',
		'save_filter_condition',
		'save_loop',
		'save_loop_filter',
		'run_recipe',
		'run_action',
		'delete',
		'site_manage',
	);

	/** @var string[] */
	private const READ_TOOLS = array(
		'discover_ability',
		'rest_discover',
		// Effects depend on the chosen operation; the runner checks write scope.
		'rest_request',
		'get_recipe',
		'list_recipes',
		'search',
		'get_component_schema',
		'run_ability',
		'get_field_options',
		'get_posts',
		'get_terms',
		'list_plugins',
		'get_site_context',
		'list_users',
		'list_roles',
		'mysql_get_tables',
		'mysql_get_table_columns',
		'mysql_select_from_table',
		'mysql_get_cell_chunk',
		'get_tokens',
		'get_logs',
		'site_command',
	);

	/**
	 * Per-tool availability chosen by the site owner for third-party clients.
	 *
	 * @var Tool_Availability_Settings
	 */
	private Tool_Availability_Settings $availability;

	/**
	 * @param Tool_Availability_Settings|null $availability Availability settings; defaults to the stored option.
	 */
	public function __construct( ?Tool_Availability_Settings $availability = null ) {
		$this->availability = $availability ?? new Tool_Availability_Settings();
	}

	/**
	 * Resolve a tool's required scope, or null when it must not be exposed.
	 */
	public function resolve( MCP_Tool_Interface $tool ): ?string {
		$name  = (string) $tool->get_name();
		$scope = self::resolve_core_tool_scope( $name );

		if ( null !== $scope ) {
			return $scope;
		}

		$scope = null;
		if ( is_callable( array( $tool, 'get_required_scope' ) ) ) {
			$scope = $tool->get_required_scope();
		}

		/**
		 * Classify an extension MCP tool.
		 *
		 * Returning null excludes the tool. Only mcp:read and mcp:write are
		 * accepted so extensions cannot invent grants the server cannot enforce.
		 *
		 * @param string|null       $scope Proposed required scope.
		 * @param MCP_Tool_Interface $tool Tool being registered.
		 */
		$scope = apply_filters( 'automator_mcp_tool_required_scope', $scope, $tool );

		return in_array(
			$scope,
			array( Authenticated_Token_Context::SCOPE_READ, Authenticated_Token_Context::SCOPE_WRITE ),
			true
		) ? $scope : null;
	}

	/**
	 * Resolve a built-in tool without constructing its schema.
	 *
	 * The public MCP endpoint uses this immutable map before bearer
	 * authentication. Do not replace it with Tool_Registry lookup there: building
	 * the registry validates every schema and may enumerate WordPress object
	 * types, which would hand expensive work to anonymous callers. Extension
	 * tools intentionally return null and are resolved from the registry only
	 * after the request has authenticated.
	 *
	 * Retired root names remain authorization keys for native Automator abilities
	 * and the legacy fallback. In particular, get_site_context must keep its read
	 * scope so hosted callers can receive -32602 and try the native operation.
	 */
	public static function resolve_core_tool_scope( string $name ): ?string {
		if ( in_array( $name, self::WRITE_TOOLS, true ) ) {
			return Authenticated_Token_Context::SCOPE_WRITE;
		}

		if ( in_array( $name, self::READ_TOOLS, true ) ) {
			return Authenticated_Token_Context::SCOPE_READ;
		}

		return null;
	}

	/**
	 * Determine whether an authenticated client may discover or invoke a tool.
	 *
	 * A missing context is treated as public access so callers cannot expose a
	 * first-party tool merely by omitting the credential purpose.
	 */
	public function is_available_to_client( string $name, ?Authenticated_Token_Context $context ): bool {
		return null === $this->unavailable_reason( $name, $context );
	}

	/**
	 * Explain the same availability decision without treating absence as denial.
	 *
	 * @since 8.0.0
	 * @param string $name Registered tool name.
	 * @param Authenticated_Token_Context|null $context Authenticated caller.
	 * @return array|null Refusal details, or null when available.
	 */
	public function unavailable_reason( string $name, ?Authenticated_Token_Context $context ): ?array {
		// Site management changes plugins, themes and site settings, so once
		// the owner turns it off it is off for every caller, Uncanny Agent
		// included.
		if ( Site_Management_Settings::requires_consent( $name ) && ! ( new Site_Management_Settings() )->is_enabled() ) {
			return $this->disabled_reason( 'Site management', 'Automator > Settings > MCP settings > Site tools' );
		}

		// A network subsite's database setting applies to every client.
		$database = new Database_Access_Settings();
		if ( Database_Access_Settings::requires_consent( $name ) && ! $database->is_enabled() && ( ! $context instanceof Authenticated_Token_Context || ! $context->is_first_party_agent() || $database->applies_to_first_party() ) ) {
			return $this->disabled_reason( 'Database access', 'Automator > Settings > MCP settings > Site tools' );
		}

		// Existing Agent tools retain their caller policy. Abilities are now shared,
		// but their catalog and execution always require the owner's per-ability opt-in.
		if ( $context instanceof Authenticated_Token_Context && $context->is_first_party_agent() ) {
			return null;
		}

		if ( self::is_first_party_only( $name ) ) {
			return array(
				'code' => 'site_tool_unauthorized',
				'message' => 'This tool is not available to this connection. Ask an administrator to check the connection permissions.',
			);
		}

		// Precise edits need the existing source/hash read path. Do not advertise
		// an edit workflow when its preparation operation is switched off.
		if ( in_array( $name, array( 'post_apply_content_edit', 'post_apply_meta_edit' ), true ) && ( $this->availability->is_disabled( 'rest_request' ) || $this->availability->is_disabled( 'get_posts' ) ) ) {
			return $this->disabled_reason( 'The content-edit preparation tools', 'Automator > Settings > MCP settings > Site tools' );
		}

		// A missing context is treated as a third-party client, so the settings
		// page and any unauthenticated path see exactly what such apps see.
		return $this->availability->is_disabled( $name )
			? $this->disabled_reason( $name, 'Automator > Settings > MCP settings > Site tools' )
			: null;
	}


	/**
	 * @since 8.0.0
	 * @param string $label Disabled capability.
	 * @param string $setting WordPress admin location.
	 * @return array Refusal details.
	 */
	private function disabled_reason( string $label, string $setting ): array {
		$administrator = is_multisite() && in_array( $label, array( 'Database access', 'Site management' ), true ) ? 'network super administrator' : 'site administrator';
		return array(
			'code' => 'site_tool_disabled',
			'message' => $label . ' is disabled on this site. Ask a ' . $administrator . ' to enable it under ' . $setting . ', then try again. No Reconnect is needed.',
			'setting' => $setting,
		);
	}

	/**
	 * Whether a tool is reserved for first-party Uncanny Agent credentials.
	 *
	 * @param string $name Tool name.
	 * @return bool
	 */
	public static function is_first_party_only( string $name ): bool {
		return in_array( $name, self::FIRST_PARTY_ONLY_TOOLS, true );
	}
}

<?php

namespace Uncanny_Automator;

use Throwable;
use Uncanny_Automator\App\Transports\Abilities\Automator_Abilities;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Authorization_Policy;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Database_Access_Settings;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Database_Table_Access_Settings;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\File_Reading_Settings;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Site_Management_Settings;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Availability_Settings;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Registry;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Model Context Protocol tools settings.
 *
 * @since 7.6
 * @package Uncanny_Automator
 */
class Admin_Settings_Model_Context_Protocol_Tools {

	/**
	 * AJAX action that switches one tool on or off for third-party AI apps.
	 */
	public const TOGGLE_ACTION = 'automator_mcp_toggle_tool';

	/**
	 * Nonce action protecting the toggle request.
	 */
	public const TOGGLE_NONCE_ACTION = 'automator_mcp_toggle_tool';

	/** AJAX and nonce action for the explicit database access consent. */
	public const DATABASE_ACCESS_ACTION = 'automator_mcp_database_access';

	/** Admin-post and nonce action for the database table allowlist. */
	public const DATABASE_TABLES_ACTION = 'automator_mcp_database_tables';

	/** AJAX and nonce action that turns code and file reading on or off. */
	public const FILE_READING_ACTION = 'automator_mcp_file_reading';

	/** AJAX and nonce action that saves the files and folders AI apps may never open. */
	public const FILE_READING_EXCLUSIONS_ACTION = 'automator_mcp_file_reading_exclusions';

	/**
	 * Most bytes of the exclusions list read from a request: room for the
	 * most entries at their longest. A longer list is refused and the saved
	 * one kept.
	 */
	private const MAX_EXCLUSIONS_INPUT_BYTES = 65536;

	/**
	 * Error codes after which the saved state is unknown.
	 *
	 * @var string[]
	 */
	private const OUTCOME_UNKNOWN_CODES = array( 'mcp_tool_toggle_unknown', 'mcp_database_access_unknown', 'mcp_file_reading_unknown' );

	/**
	 * Tools whose effect depends on what they dispatch.
	 *
	 * REST and ability dispatchers accept read-scoped credentials, but authorize
	 * the selected operation separately. Their transport scope must not label
	 * a content write or recipe mutation as unconditionally read-only.
	 *
	 * @var string[]
	 */
	private const VARIABLE_ACCESS_TOOLS = array( 'rest_request', 'run_ability' );


	/**
	 * MCP tool registry.
	 *
	 * The registry is populated only when this tab renders. Constructing every
	 * tool during unrelated admin requests would add unnecessary work and could
	 * run extension registration hooks outside the MCP tools page.
	 *
	 * @var Tool_Registry|null
	 */
	private $registry;

	/**
	 * Whether an injected registry is already populated.
	 *
	 * @var bool
	 */
	private $registry_is_populated;

	/**
	 * Class constructor.
	 *
	 * @param Tool_Registry|null $registry Optional pre-populated registry.
	 */
	/**
	 * Per-tool availability chosen for third-party AI apps.
	 *
	 * @var Tool_Availability_Settings
	 */
	private $availability;

	/**
	 * Site-owner consent for the database tools.
	 *
	 * @var Database_Access_Settings
	 */
	private $database_access;

	/**
	 * Site-owner consent for site management.
	 *
	 * @var Site_Management_Settings
	 */
	private $site_management;

	/**
	 * Site-owner choices for reading the site's code and files.
	 *
	 * @var File_Reading_Settings
	 */
	private $file_reading;

	/**
	 * @param Tool_Registry|null              $registry        Tool registry; defaults to the server catalog.
	 * @param Tool_Availability_Settings|null $availability    Availability settings; defaults to the stored option.
	 * @param Database_Access_Settings|null   $database_access Database consent; defaults to the stored option.
	 * @param Site_Management_Settings|null   $site_management Site management consent; defaults to the stored option.
	 * @param File_Reading_Settings|null      $file_reading    Code and file reading choices; defaults to the stored options.
	 */
	public function __construct( ?Tool_Registry $registry = null, ?Tool_Availability_Settings $availability = null, ?Database_Access_Settings $database_access = null, ?Site_Management_Settings $site_management = null, ?File_Reading_Settings $file_reading = null ) {
		$this->registry              = $registry;
		$this->registry_is_populated = null !== $registry;
		$this->availability          = $availability ?? new Tool_Availability_Settings();
		$this->database_access       = $database_access ?? new Database_Access_Settings();
		$this->site_management       = $site_management ?? new Site_Management_Settings();
		$this->file_reading          = $file_reading ?? new File_Reading_Settings();

		add_filter( 'automator_settings_model_context_protocol_tabs', array( $this, 'create_tab' ), 30, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 100 );
		add_action( 'wp_ajax_' . self::TOGGLE_ACTION, array( $this, 'handle_toggle' ) );
		add_action( 'wp_ajax_' . self::DATABASE_ACCESS_ACTION, array( $this, 'handle_database_access' ) );
		add_action( 'admin_post_' . self::DATABASE_TABLES_ACTION, array( $this, 'handle_database_tables' ) );
		add_action( 'wp_ajax_' . self::FILE_READING_ACTION, array( $this, 'handle_file_reading' ) );
		add_action( 'wp_ajax_' . self::FILE_READING_EXCLUSIONS_ACTION, array( $this, 'handle_file_reading_exclusions' ) );
	}

	/**
	 * Register the Tools subtab.
	 *
	 * @param array<string,object> $tabs Registered subtabs.
	 * @return array<string,object>
	 */
	public function create_tab( $tabs ) {
		$tabs['tools'] = (object) array(
			'name'     => esc_html__( 'Site tools', 'uncanny-automator' ),
			'function' => array( $this, 'tab_output' ),
			'preload'  => false,
			'icon'     => 'wrench',
		);

		return $tabs;
	}

	/**
	 * Output the Tools subtab.
	 *
	 * @return void
	 */
	public function tab_output() {
		global $wpdb;
		$tool_groups             = array(
			'read'     => array(),
			'write'    => array(),
			'variable' => array(),
		);
		$tool_counts             = array(
			'total'    => 0,
			'read'     => 0,
			'write'    => 0,
			'variable' => 0,
		);
		$load_error              = false;
		$database_access_enabled = $this->database_access->is_enabled();
		$database_extra_tables   = ( new Database_Table_Access_Settings() )->tables();
		$database_table_prefix   = (string) $wpdb->prefix;
		$database_tables_notice  = automator_filter_input( 'mcp_database_tables' );
		$can_manage_tools        = $this->can_manage_tools();
		// Database consent is reserved for network administrators, even though
		// the tools are restricted to the current site's tables.
		$database_consent_allowed = $this->database_access->current_user_can_grant();
		// Every site of a network shares the same files, so turning file
		// reading back on, or removing a protected entry, needs a super admin.
		$file_reading_enabled       = $this->file_reading->is_enabled();
		$file_reading_grant_allowed = $this->file_reading->current_user_can_grant();
		$file_reading_exclusions    = $this->file_reading->exclusions();

		try {
			$tool_groups = $this->get_tool_groups();
			$tool_counts = $this->get_tool_counts( $tool_groups );
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] Tool catalog loading failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type, never tool or credential data.
			$load_error = true;
		}

		include Utilities::automator_get_view( 'admin-settings/tab/model-context-protocol/tools.php' );
	}

	/**
	 * Build settings for MCP tools exposed at the root of the live catalog.
	 *
	 * @return array{read:array<int,array<string,mixed>>,write:array<int,array<string,mixed>>,variable:array<int,array<string,mixed>>}
	 */
	public function get_tool_groups(): array {
		return $this->get_operation_groups( false );
	}

	/**
	 * Build settings for native Automator abilities, retaining their saved switches.
	 *
	 * @return array{read:array,write:array,variable:array}
	 */
	public function get_ability_groups(): array {
		return $this->get_operation_groups( true );
	}

	/**
	 * Add Automator to the WordPress ability providers without changing its permissions.
	 *
	 * @param array $groups Providers built from the WordPress ability catalog.
	 * @return array
	 */
	public function add_ability_provider( array $groups ): array {

		$can_manage_tools = $this->can_manage_tools();
		$load_error       = false;
		$ability_groups   = array(
			'read'     => array(),
			'write'    => array(),
			'variable' => array(),
		);

		try {
			$ability_groups = $this->get_ability_groups();
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] Native ability settings failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Never log credential or request data.
			$load_error = true;
		}

		$ability_counts = $this->get_tool_counts( $ability_groups );
		if ( ! $load_error && 0 === $ability_counts['total'] ) {
			return $groups;
		}

		// Provider grouping is presentation only. Native operations retain their
		// existing switch keys and authorization; they are not generic opt-ins.
		$rows = array_merge( $ability_groups['read'], $ability_groups['write'], $ability_groups['variable'] );
		foreach ( $rows as &$row ) {
			$row['eligible'] = true;
		}
		unset( $row );

		$groups[] = array(
			'slug'             => 'uncanny-automator',
			'name'             => esc_html_x( 'Automator', 'WordPress ability provider', 'uncanny-automator' ),
			'abilities'        => $rows,
			'operation_groups' => $ability_groups,
			'can_manage_tools' => $can_manage_tools,
			'runner_off'       => $this->availability->is_disabled( 'rest_request' ),
			'load_error'       => $load_error,
		);

		return $groups;
	}

	/**
	 * Separate the live public kind without changing persisted permission keys.
	 *
	 * @param bool $abilities True for native abilities, false for root tools.
	 * @return array{read:array,write:array,variable:array}
	 */
	private function get_operation_groups( bool $abilities ): array {
		$registry = $this->get_settings_registry();
		$groups   = array(
			'read'     => array(),
			'write'    => array(),
			'variable' => array(),
		);

		$names = $registry->get_tool_names();
		sort( $names, SORT_STRING );

		// List every tool a third-party app could be offered, including ones the
		// site owner has switched off, so they can be switched back on here.
		// First-party-only tools are reserved for Uncanny Agent and never shown.
		foreach ( $names as $name ) {
			if ( Tool_Authorization_Policy::is_first_party_only( $name ) ) {
				continue;
			}
			// A missing or altered native registration still exposes its root
			// fallback. Its permission belongs on the tools page in that case.
			if ( Automator_Abilities::has_native_operation( $name ) !== $abilities ) {
				continue;
			}

			$schema = $registry->get_tool_schema( $name );
			$scope  = $registry->get_required_scope( $name );

			if ( ! in_array( $scope, array( Authenticated_Token_Context::SCOPE_READ, Authenticated_Token_Context::SCOPE_WRITE ), true ) ) {
				continue;
			}

			if ( in_array( $name, self::VARIABLE_ACCESS_TOOLS, true ) ) {
				$group_key = 'variable';
			} else {
				$group_key = Authenticated_Token_Context::SCOPE_WRITE === $scope ? 'write' : 'read';
			}

			$groups[ $group_key ][] = $this->prepare_tool( $schema, $scope, 'variable' === $group_key );
		}

		// Sort by the translated names administrators see on the page.
		foreach ( array_keys( $groups ) as $group_key ) {
			usort(
				$groups[ $group_key ],
				static function ( array $left, array $right ): int {
					$comparison = strcasecmp(
						wp_specialchars_decode( $left['title'], ENT_QUOTES ),
						wp_specialchars_decode( $right['title'], ENT_QUOTES )
					);

					return 0 !== $comparison ? $comparison : strcmp( $left['name'], $right['name'] );
				}
			);
		}

		return $groups;
	}

	/**
	 * Return a settings-only catalog; never use this to expose MCP tools.
	 */
	private function get_settings_registry(): Tool_Registry {
		if ( ! $this->registry instanceof Tool_Registry ) {
			$this->registry = new Tool_Registry();
		}

		if ( ! $this->registry_is_populated ) {
			$this->registry->auto_register_tools();
			foreach ( Automator_Abilities::operation_tools() as $name => $tool ) {
				if ( null === $this->registry->get_tool( $name ) ) {
					$this->registry->register_tool( $tool );
				}
			}
			$this->registry_is_populated = true;
		}

		return $this->registry;
	}

	/**
	 * Prepare one validated tool schema for the view.
	 *
	 * @param array<string,mixed> $schema   Tool schema.
	 * @param string              $scope    Server-enforced scope.
	 * @param bool                $variable Whether the tool's effect depends on what it dispatches.
	 * @return array<string,mixed>
	 */
	private function prepare_tool( array $schema, string $scope, bool $variable = false ): array {
		$name          = isset( $schema['name'] ) && is_string( $schema['name'] ) ? $schema['name'] : '';
		$description   = isset( $schema['description'] ) && is_string( $schema['description'] ) ? $schema['description'] : '';
		$friendly_copy = $this->get_friendly_copy( $name );
		$input_schema  = isset( $schema['inputSchema'] ) && is_array( $schema['inputSchema'] ) ? $schema['inputSchema'] : array();
		$properties    = isset( $input_schema['properties'] ) && is_array( $input_schema['properties'] ) ? $input_schema['properties'] : array();
		$required      = isset( $input_schema['required'] ) && is_array( $input_schema['required'] ) ? $input_schema['required'] : array();
		$annotations   = isset( $schema['annotations'] ) && is_array( $schema['annotations'] ) ? $schema['annotations'] : array();
		$inputs        = array();

		foreach ( $properties as $property_name => $property_schema ) {
			if ( ! is_string( $property_name ) || ! is_array( $property_schema ) ) {
				continue;
			}

			$enum = isset( $property_schema['enum'] ) && is_array( $property_schema['enum'] )
				? array_values( array_filter( $property_schema['enum'], 'is_scalar' ) )
				: array();

			$inputs[] = array(
				'name'        => $property_name,
				'type'        => $this->format_schema_type( $property_schema ),
				'required'    => in_array( $property_name, $required, true ),
				'description' => isset( $property_schema['description'] ) && is_string( $property_schema['description'] ) ? $property_schema['description'] : '',
				'enum'        => array_map( 'strval', $enum ),
			);
		}

		$ability_name = 'automator/' . str_replace( '_', '-', $name );
		// A core API function alone does not prove this operation registered safely.
		$public_name = Automator_Abilities::has_native_operation( $name ) ? $ability_name : $name;

		$requires_database_consent = Database_Access_Settings::requires_consent( $name );
		$consent_missing           = $requires_database_consent && ! $this->database_access->is_enabled();
		// Site management has no separate consent: its switch is the consent, on by default.
		$site_management_off = Site_Management_Settings::requires_consent( $name ) && ! $this->site_management->is_enabled();

		return array(
			'name'            => $name,
			'public_name'     => $public_name,
			'title'           => $friendly_copy['title'],
			'purpose'         => $friendly_copy['purpose'],
			'description'     => $description,
			'scope'           => $scope,
			'variable'        => $variable,
			'warning'         => $this->get_change_warning( $name ),
			'capabilities'    => $this->get_capabilities( $name ),
			'enabled'         => ! $consent_missing && ! $site_management_off && ! $this->availability->is_disabled( $name ),
			'consent_missing' => $consent_missing,
			'database_tool'   => $requires_database_consent,
			'annotations'     => array(
				'read_only'   => $this->get_boolean_annotation( $annotations, 'readOnlyHint' ),
				'destructive' => $this->get_boolean_annotation( $annotations, 'destructiveHint' ),
				'idempotent'  => $this->get_boolean_annotation( $annotations, 'idempotentHint' ),
				'open_world'  => $this->get_boolean_annotation( $annotations, 'openWorldHint' ),
			),
			'inputs'          => $inputs,
		);
	}

	/**
	 * Enqueue the same permission controls on tools and WordPress abilities.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		unset( $hook_suffix );

		if ( ! $this->is_operation_settings_page() ) {
			return;
		}

		wp_enqueue_script(
			'automator-mcp-tools',
			plugins_url(
				'src/core/admin/admin-settings/tabs/model-context-protocol-assets/tools.js',
				AUTOMATOR_BASE_FILE
			),
			array( 'uap-admin' ),
			Utilities::automator_get_version(),
			true
		);

		wp_localize_script(
			'automator-mcp-tools',
			'AutomatorMcpTools',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'action'         => self::TOGGLE_ACTION,
				'nonce'          => wp_create_nonce( self::TOGGLE_NONCE_ACTION ),
				'databaseAction' => self::DATABASE_ACCESS_ACTION,
				'databaseNonce'  => wp_create_nonce( self::DATABASE_ACCESS_ACTION ),
				'filesAction'    => self::FILE_READING_ACTION,
				'filesNonce'     => wp_create_nonce( self::FILE_READING_ACTION ),
				'excludeAction'  => self::FILE_READING_EXCLUSIONS_ACTION,
				'excludeNonce'   => wp_create_nonce( self::FILE_READING_EXCLUSIONS_ACTION ),
				'strings'        => array(
					'errorHeading'   => esc_html__( 'Check this setting', 'uncanny-automator' ),
					'unknownError'   => esc_html__( 'The change could not be saved. Try again.', 'uncanny-automator' ),
					'unknownOutcome' => esc_html__( 'The saved state could not be confirmed. Refresh the page to check it.', 'uncanny-automator' ),
					'saving'         => esc_html__( 'Saving…', 'uncanny-automator' ),
					/* translators: %s Name of the tool that was switched on. */
					'savedOn'        => esc_html__( '%s is on.', 'uncanny-automator' ),
					/* translators: %s Name of the tool that was switched off. */
					'savedOff'       => esc_html__( '%s is off.', 'uncanny-automator' ),
					'listSaved'      => esc_html_x( 'Saved. AI apps won’t open these files and folders.', 'MCP file reading exclusions saved', 'uncanny-automator' ),
					'listEmpty'      => esc_html_x( 'Saved. Only the files that are always protected are kept from AI apps.', 'MCP file reading exclusions saved empty', 'uncanny-automator' ),
					/* translators: %s Entries that could not be used, separated by commas. */
					'listRejected'   => esc_html_x( 'These entries can’t be used and were left out: %s', 'MCP file reading exclusions rejected', 'uncanny-automator' ),
					/* translators: %d Most entries the list keeps. */
					'listTruncated'  => esc_html_x( 'Only the first %d entries were kept.', 'MCP file reading exclusions limit', 'uncanny-automator' ),
				),
			)
		);
	}

	/**
	 * AJAX: switch one tool on or off for third-party AI apps.
	 *
	 * @return void
	 */
	public function handle_toggle() {
		nocache_headers();

		try {
			$result = $this->process_toggle_request(
				$this->get_post_string( 'nonce' ),
				$this->get_post_string( 'tool' ),
				'1' === $this->get_post_string( 'enabled' )
			);
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] Tool toggle failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
			$result = new WP_Error( 'mcp_tool_toggle_unknown' );
		}

		$this->send_result( $result );
	}

	/**
	 * Validate a toggle request and persist it.
	 *
	 * Only tools a third-party app could be offered can be toggled: unknown
	 * names and first-party-only tools are refused. Uncanny Agent is never
	 * affected by the stored setting.
	 *
	 * @param string $nonce   Submitted nonce.
	 * @param string $tool    Tool name.
	 * @param bool   $enabled True to allow the tool, false to block it.
	 * @return array{tool:string,enabled:bool}|WP_Error
	 */
	private function process_toggle_request( string $nonce, string $tool, bool $enabled ) {
		if ( ! $this->can_manage_tools() ) {
			return new WP_Error( 'mcp_tool_forbidden' );
		}

		if ( ! wp_verify_nonce( $nonce, self::TOGGLE_NONCE_ACTION ) ) {
			return new WP_Error( 'mcp_tool_invalid_nonce' );
		}

		if ( ! $this->is_toggleable_tool( $tool ) ) {
			return new WP_Error( 'mcp_tool_unknown' );
		}

		if ( $enabled && Database_Access_Settings::requires_consent( $tool ) && ! $this->database_access->is_enabled() ) {
			return new WP_Error( 'mcp_database_consent_required' );
		}

		if ( Site_Management_Settings::requires_consent( $tool ) ) {
			if ( $enabled && ! $this->site_management->current_user_can_grant() ) {
				return new WP_Error( 'mcp_site_management_network_admin_required' );
			}
			// Turning it off revokes the consent first, so a failure later never leaves it on.
			if ( ! $enabled && ! $this->site_management->set_enabled( false ) ) {
				return new WP_Error( 'mcp_tool_toggle_failed' );
			}
		}

		if ( ! $this->availability->set_tool_disabled( $tool, ! $enabled ) ) {
			return new WP_Error( 'mcp_tool_toggle_failed' );
		}

		if ( $enabled && Site_Management_Settings::requires_consent( $tool ) && ! $this->site_management->set_enabled( true ) ) {
			return new WP_Error( 'mcp_tool_toggle_failed' );
		}

		return array(
			'tool'    => $tool,
			'enabled' => $enabled,
		);
	}

	/**
	 * AJAX: enable database access only after explicit acknowledgement, or revoke it.
	 */
	public function handle_database_access() {
		nocache_headers();

		try {
			$result = $this->process_database_access_request(
				$this->get_post_string( 'nonce' ),
				'1' === $this->get_post_string( 'enabled' ),
				'1' === $this->get_post_string( 'consent' )
			);
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] Database access setting failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
			$result = new WP_Error( 'mcp_database_access_unknown' );
		}

		$this->send_result( $result );
	}

	/**
	 * Validate the administrator's consent separately from ordinary tool switches.
	 *
	 * @return array{enabled:bool}|WP_Error
	 */
	private function process_database_access_request( string $nonce, bool $enabled, bool $consent ) {
		if ( ! $this->can_manage_tools() ) {
			return new WP_Error( 'mcp_tool_forbidden' );
		}

		if ( ! wp_verify_nonce( $nonce, self::DATABASE_ACCESS_ACTION ) ) {
			return new WP_Error( 'mcp_tool_invalid_nonce' );
		}

		if ( $enabled && ! $consent ) {
			return new WP_Error( 'mcp_database_consent_required' );
		}

		if ( $enabled && ! $this->database_access->current_user_can_grant() ) {
			return new WP_Error( 'mcp_database_network_admin_required' );
		}

		if ( ! $this->database_access->set_enabled( $enabled ) ) {
			return new WP_Error( 'mcp_database_access_failed' );
		}

		return array( 'enabled' => $enabled );
	}

	/**
	 * Save the owner's exact extra table names and return to Site tools.
	 *
	 * @since 8.0.0
	 * @return void
	 */
	public function handle_database_tables(): void {
		nocache_headers();
		try {
			$tables = isset( $_POST['tables'] ) && is_string( $_POST['tables'] ) ? $this->get_post_lines( 'tables' ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Validated before the request can change the list.
			$result = $this->process_database_tables_request( $this->get_post_string( '_wpnonce' ), $tables );
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] Database table setting failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log the exception type only.
			$result = new WP_Error( 'mcp_database_tables_unknown' );
		}

		$status = is_wp_error( $result ) ? $result->get_error_code() : 'saved';
		$url    = Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'tools' );
		wp_safe_redirect( add_query_arg( 'mcp_database_tables', $status, $url ) );
		exit;
	}

	/**
	 * Validate the owner and nonce before changing the table boundary.
	 *
	 * @since 8.0.0
	 * @param string        $nonce Submitted nonce.
	 * @param string[]|null $tables Submitted table names; null means the input was too long.
	 * @return true|WP_Error
	 */
	private function process_database_tables_request( string $nonce, ?array $tables ) {
		if ( ! $this->can_manage_tools() || is_multisite() ) {
			return new WP_Error( 'mcp_database_tables_forbidden' );
		}
		if ( ! wp_verify_nonce( $nonce, self::DATABASE_TABLES_ACTION ) ) {
			return new WP_Error( 'mcp_database_tables_nonce' );
		}
		if ( null === $tables ) {
			return new WP_Error( 'mcp_database_tables_invalid' );
		}

		return ( new Database_Table_Access_Settings() )->set_tables( array_map( 'trim', $tables ) );
	}

	/**
	 * AJAX: turn code and file reading on or off.
	 *
	 * @return void
	 */
	public function handle_file_reading() {
		nocache_headers();

		try {
			$result = $this->process_file_reading_request(
				$this->get_post_string( 'nonce' ),
				'1' === $this->get_post_string( 'enabled' )
			);
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] File reading setting failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
			$result = new WP_Error( 'mcp_file_reading_unknown' );
		}

		$this->send_result( $result );
	}

	/**
	 * Validate and save the owner's file reading choice.
	 *
	 * Turning it off is always allowed. On a network, turning it back on
	 * needs a super admin, because every site shares the same files.
	 *
	 * @param string $nonce   Submitted nonce.
	 * @param bool   $enabled The requested state.
	 * @return array{enabled:bool}|WP_Error
	 */
	private function process_file_reading_request( string $nonce, bool $enabled ) {
		if ( ! $this->can_manage_tools() ) {
			return new WP_Error( 'mcp_tool_forbidden' );
		}

		if ( ! wp_verify_nonce( $nonce, self::FILE_READING_ACTION ) ) {
			return new WP_Error( 'mcp_tool_invalid_nonce' );
		}

		if ( $enabled && ! $this->file_reading->current_user_can_grant() ) {
			return new WP_Error( 'mcp_file_reading_network_admin_required' );
		}

		if ( ! $this->file_reading->set_enabled( $enabled ) ) {
			return new WP_Error( 'mcp_file_reading_failed' );
		}

		return array( 'enabled' => $enabled );
	}

	/**
	 * AJAX: save the files and folders AI apps may never open.
	 *
	 * @return void
	 */
	public function handle_file_reading_exclusions() {
		nocache_headers();

		try {
			$result = $this->process_file_reading_exclusions_request(
				$this->get_post_string( 'nonce' ),
				$this->get_post_lines( 'exclusions' )
			);
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] File reading exclusions failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
			$result = new WP_Error( 'mcp_file_reading_unknown' );
		}

		$this->send_result( $result );
	}

	/**
	 * Validate and save the owner's own entries.
	 *
	 * Adding an entry is always allowed. On a network, removing one lets AI
	 * apps open those files again, so it needs a super admin.
	 *
	 * @param string        $nonce   Submitted nonce.
	 * @param string[]|null $entries What the owner typed, one entry per line; null when it was too long to read.
	 * @return array{exclusions:string[],rejected:string[],truncated:bool}|WP_Error
	 */
	private function process_file_reading_exclusions_request( string $nonce, ?array $entries ) {
		if ( ! $this->can_manage_tools() ) {
			return new WP_Error( 'mcp_tool_forbidden' );
		}

		if ( ! wp_verify_nonce( $nonce, self::FILE_READING_EXCLUSIONS_ACTION ) ) {
			return new WP_Error( 'mcp_tool_invalid_nonce' );
		}

		if ( null === $entries ) {
			return new WP_Error( 'mcp_file_reading_list_too_long' );
		}

		$requested = File_Reading_Settings::normalize( $entries );
		if ( array() !== array_diff( $this->file_reading->exclusions(), $requested ) && ! $this->file_reading->current_user_can_grant() ) {
			return new WP_Error( 'mcp_file_reading_removal_network_admin_required' );
		}

		if ( ! $this->file_reading->set_exclusions( $entries ) ) {
			return new WP_Error( 'mcp_file_reading_failed' );
		}

		// Usable entries past the limit are dropped; say so rather than hide it.
		return array(
			'exclusions' => $this->file_reading->exclusions(),
			'rejected'   => File_Reading_Settings::rejected( $entries ),
			'truncated'  => count( File_Reading_Settings::usable( $entries ) ) > File_Reading_Settings::MAX_EXCLUSIONS,
		);
	}

	/**
	 * Send a settings result, or a refusal the screen can show.
	 *
	 * @param mixed $result The saved state, or the reason it was not saved.
	 * @return void
	 */
	private function send_result( $result ) {
		if ( $result instanceof WP_Error ) {
			$status = in_array( $result->get_error_code(), array( 'mcp_tool_forbidden', 'mcp_tool_invalid_nonce' ), true ) ? 403 : 400;
			wp_send_json_error(
				array(
					'message'         => $this->get_toggle_error_message( $result->get_error_code() ),
					'outcome_unknown' => in_array( $result->get_error_code(), self::OUTCOME_UNKNOWN_CODES, true ),
				),
				$status
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Whether a tool is one a third-party app could be offered.
	 *
	 * @param string $tool Tool name.
	 * @return bool
	 */
	private function is_toggleable_tool( string $tool ): bool {
		if ( '' === $tool || Tool_Authorization_Policy::is_first_party_only( $tool ) ) {
			return false;
		}

		return null !== $this->get_settings_registry()->get_tool( $tool );
	}

	/**
	 * Whether the current user may change MCP tool availability.
	 *
	 * @return bool
	 */
	private function can_manage_tools(): bool {
		return current_user_can( automator_get_admin_capability() ) // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Automator settings capability is configurable.
			&& current_user_can( 'manage_options' );
	}

	/**
	 * Whether this page contains root-tool or native-ability permission controls.
	 *
	 * @return bool
	 */
	private function is_operation_settings_page(): bool {
		return AUTOMATOR_POST_TYPE_RECIPE === automator_filter_input( 'post_type' )
			&& 'uncanny-automator-config' === automator_filter_input( 'page' )
			&& 'model-context-protocol' === automator_filter_input( 'tab' )
			&& in_array( automator_filter_input( 'model-context-protocol' ), array( 'tools', 'wordpress-abilities' ), true );
	}

	/**
	 * Read a sanitized string from the POST body.
	 *
	 * @param string $key Field name.
	 * @return string
	 */
	private function get_post_string( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller validates the action-specific nonce after reading it through this helper.
			return '';
		}

		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller validates the action-specific nonce before mutation.
	}

	/**
	 * Read a textarea from the POST body as its non-empty lines.
	 *
	 * @param string $key Field name.
	 * @return string[]|null Null when the field is longer than any list this screen saves.
	 */
	private function get_post_lines( string $key ): ?array {
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller validates the action-specific nonce after reading it through this helper.
			return array();
		}

		// Literal, not sanitized: sanitizing drops bytes a file name may hold,
		// such as "%20", and would protect another name. Each line is checked
		// by Denylist::owner_entry() before it is stored, and escaped on display.
		$raw = (string) wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked by the caller; validated per line by File_Reading_Settings.
		if ( strlen( $raw ) > self::MAX_EXCLUSIONS_INPUT_BYTES ) {
			return null;
		}
		$lines = preg_split( '/\R/', $raw );

		return array_values(
			array_filter(
				is_array( $lines ) ? $lines : array(),
				static function ( $line ) {
					return '' !== trim( $line );
				}
			)
		);
	}

	/**
	 * Map a toggle error code to a message safe to show an administrator.
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	private function get_toggle_error_message( string $code ): string {
		if ( in_array( $code, self::OUTCOME_UNKNOWN_CODES, true ) ) {
			return esc_html__( 'The saved state could not be confirmed. Refresh the page to check it.', 'uncanny-automator' );
		}

		switch ( $code ) {
			case 'mcp_tool_forbidden':
				return esc_html__( 'You do not have permission to manage these settings. Contact a site administrator.', 'uncanny-automator' );
			case 'mcp_tool_invalid_nonce':
				return esc_html__( 'The security check failed. Refresh the page and try again.', 'uncanny-automator' );
			case 'mcp_tool_unknown':
				return esc_html__( 'This tool is no longer available to connected AI apps. Refresh the page.', 'uncanny-automator' );
			case 'mcp_database_consent_required':
				return esc_html__( 'Review the database warning and enable database access before turning on a database tool.', 'uncanny-automator' );
			case 'mcp_database_network_admin_required':
				return esc_html__( 'On a multisite network, only a super administrator can enable database access.', 'uncanny-automator' );
			case 'mcp_site_management_network_admin_required':
				return esc_html__( 'On a multisite network, only a super administrator can turn on site management.', 'uncanny-automator' );
			case 'mcp_file_reading_network_admin_required':
				return esc_html__( 'On a multisite network, only a super administrator can turn code and file reading back on.', 'uncanny-automator' );
			case 'mcp_file_reading_removal_network_admin_required':
				return esc_html__( 'On a multisite network, only a super administrator can remove an entry from this list.', 'uncanny-automator' );
			case 'mcp_file_reading_list_too_long':
				/* translators: %d: Most entries the list holds. */
				return sprintf( esc_html__( 'This list is too long to save, so your saved list was kept. Keep it to %d entries or fewer.', 'uncanny-automator' ), File_Reading_Settings::MAX_EXCLUSIONS );
		}

		return esc_html__( 'The change could not be saved. Try again.', 'uncanny-automator' );
	}

	/**
	 * Everything a tool that bundles many changes can do, so the owner sees
	 * all of it before turning it on.
	 *
	 * @param string $name Registered tool name.
	 * @return string[] Escaped lines; empty for other tools.
	 */
	private function get_capabilities( string $name ): array {
		if ( Site_Management_Settings::TOOL_NAME !== $name ) {
			return array();
		}

		return array(
			esc_html_x( 'Install plugins and themes from WordPress.org', 'MCP site management capability', 'uncanny-automator' ),
			esc_html_x( 'Update plugins, themes and translations', 'MCP site management capability', 'uncanny-automator' ),
			esc_html_x( 'Turn plugins on and off, switch themes, and choose which plugins and themes update automatically', 'MCP site management capability', 'uncanny-automator' ),
			esc_html_x( 'Approve, unapprove and mark comments as spam, or take them out of spam', 'MCP site management capability', 'uncanny-automator' ),
			esc_html_x( 'Allow or discourage search engines', 'MCP site management capability', 'uncanny-automator' ),
			esc_html_x( 'Install a language and switch the site’s language', 'MCP site management capability', 'uncanny-automator' ),
			esc_html_x( 'Run scheduled tasks now', 'MCP site management capability', 'uncanny-automator' ),
			esc_html_x( 'Clear the object cache, remove expired temporary data, and refresh permalinks', 'MCP site management capability', 'uncanny-automator' ),
			esc_html_x( 'Show the maintenance page for up to 10 minutes, at most once an hour. It covers the whole site, the admin area included', 'MCP site management capability', 'uncanny-automator' ),
		);
	}

	/**
	 * Warning shown on tools that run or remove things.
	 *
	 * Derived from what the tool does, not only from its verb: the run tools
	 * dispatch actions with side effects, and delete removes recipe items.
	 *
	 * @param string $name Registered tool name.
	 * @return string Escaped warning, or '' when the tool carries none.
	 */
	private function get_change_warning( string $name ): string {
		if ( in_array( $name, array( 'run_recipe', 'run_action' ), true ) ) {
			return esc_html_x( 'Running a recipe or action can change your site or connected services. Some actions cannot be undone.', 'MCP run operation warning', 'uncanny-automator' );
		}

		if ( 'delete' === $name ) {
			return esc_html_x( 'Removing an item can change how a recipe works. The removal may not be reversible.', 'MCP remove tool warning', 'uncanny-automator' );
		}

		if ( Site_Management_Settings::TOOL_NAME === $name ) {
			return esc_html_x( 'On by default for every AI app you connect, Uncanny Agent included. AI apps can make the changes listed above, the same ones you can make under Plugins, Appearance, Comments and Settings. Plugins, themes, comments and content are never deleted, and Automator never updates or deactivates itself, though an AI app may turn on its automatic updates. Plugins installed this way may add their own abilities, which you can manage under WordPress abilities. As with any update, keep regular backups. You can turn this off here at any time.', 'MCP site management warning', 'uncanny-automator' );
		}

		return '';
	}

	/**
	 * Return administrator-friendly copy for a built-in tool.
	 *
	 * Extension tools use a readable version of their registered name and a
	 * neutral explanation. Their exact registered description remains visible
	 * in the technical details.
	 *
	 * @param string $name Registered tool name.
	 * @return array{title:string,purpose:string}
	 */
	private function get_friendly_copy( string $name ): array {
		$copy = array(
			'search'                  => array( esc_html_x( 'Find actions, triggers and abilities', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Find available actions, triggers, recipe components and enabled WordPress abilities.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'get_terms'               => array( esc_html_x( 'Find categories and terms', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Find categories, tags and other WordPress terms.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'get_posts'               => array( esc_html_x( 'Find WordPress content', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Find content on this WordPress site. Reading or changing content through Run a WordPress operation also requires this switch. Changes require Read and make changes access.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'mysql_get_tables'        => array( esc_html_x( 'List database tables', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Find available database tables and their approximate record counts.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'list_recipes'            => array( esc_html_x( 'List recipes', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Find recipes by title, status or type.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'site_command'            => array( esc_html_x( 'Run read-only site commands', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Read site information with familiar WP-CLI-style commands such as plugin list. Commands are built into Automator; there is no shell or command-line access.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'site_manage'             => array( esc_html_x( 'Site management', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Let connected AI apps look after plugins, themes, comments and routine upkeep for you. Every change is made with WordPress’s own functions. Nothing runs on your server’s command line.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'list_plugins'            => array( esc_html_x( 'List WordPress plugins', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'List plugins on this WordPress site.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'list_roles'              => array( esc_html_x( 'List WordPress roles', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'List the user roles on this WordPress site.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'mysql_select_from_table' => array( esc_html_x( 'Read database records', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Read selected records from available tables, with filters and related-table queries.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'mysql_get_cell_chunk'    => array( esc_html_x( 'Read long database text', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Read a long text value from one database record in smaller parts.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'get_recipe'              => array( esc_html_x( 'View a recipe', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'View a recipe’s triggers, actions, conditions and loops.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'get_component_schema'    => array( esc_html_x( 'View component fields', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'View the fields used to configure a recipe component.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'mysql_get_table_columns' => array( esc_html_x( 'View database columns', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'View column names, data types and defaults for available database tables.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'get_field_options'       => array( esc_html_x( 'View field choices', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'View the available choices for a recipe component’s dropdown field.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'get_logs'                => array( esc_html_x( 'View recipe activity', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'View recipe run history and the details of individual runs.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'get_tokens'              => array( esc_html_x( 'View recipe tokens', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'View the data placeholders available in a recipe. These are not access tokens.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'list_users'              => array( esc_html_x( 'Find WordPress users', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Find users on this site by details such as name, email or role.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'save_filter_condition'   => array( esc_html_x( 'Create or update a filter condition', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Add or update a rule in a filter block that controls whether its actions run.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'save_block'              => array( esc_html_x( 'Create or update a block', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Add or update a filter, delay or schedule block that controls the actions inside it.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'save_loop'               => array( esc_html_x( 'Create or update a loop', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Add or update a loop that repeats actions for matching users, posts or data items.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'save_loop_filter'        => array( esc_html_x( 'Create or update a loop filter', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Choose which users, posts or data items a recipe loop processes.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'save_recipe'             => array( esc_html_x( 'Create or update a recipe', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Create a draft recipe or update an existing recipe’s settings and status.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'save_trigger'            => array( esc_html_x( 'Create or update a trigger', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Configure an event that starts a recipe.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'save_action'             => array( esc_html_x( 'Create or update an action', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Configure a task that runs as part of a recipe.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'delete'                  => array( esc_html_x( 'Remove a recipe item', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Remove an item from a recipe.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'run_recipe'              => array( esc_html_x( 'Run a recipe', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Request a run of a published recipe with a manual trigger. Requires Uncanny Automator Pro.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'run_action'              => array( esc_html_x( 'Run an action', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Run an action immediately, outside a recipe.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'get_site_context'        => array( esc_html_x( 'View site context', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Read site, language and Automator information needed to understand this connection.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'post_apply_content_edit' => array( esc_html_x( 'Apply content edits', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Apply precise edits to existing WordPress content after checking that the saved content has not changed.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'post_apply_meta_edit'    => array( esc_html_x( 'Change custom fields', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Change custom fields of posts and pages after checking that they have not changed, keeping each value in the form its plugins expect.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'rest_discover'           => array( esc_html_x( 'Find WordPress operations', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Find supported site operations and the information each one needs.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'rest_request'            => array( esc_html_x( 'Run a WordPress operation', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Run a supported site operation or an Automator ability. Automator’s abilities require this tool as well as their own switches. The selected operation determines the required access and whether it makes changes.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'discover_ability'        => array( esc_html_x( 'Find WordPress abilities', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Search enabled abilities and read their required inputs. This is a shortcut to the ability search and schema lookup. It does not run abilities or change your site.', 'MCP tool purpose', 'uncanny-automator' ) ),
			'run_ability'             => array( esc_html_x( 'Run a WordPress ability', 'MCP tool title', 'uncanny-automator' ), esc_html_x( 'Run an enabled ability from WordPress or another provider. Automator’s abilities use Run a WordPress operation instead. The ability determines the required access and whether it changes your site or connected services.', 'MCP tool purpose', 'uncanny-automator' ) ),
		);

		if ( isset( $copy[ $name ] ) ) {
			return array(
				'title'   => $copy[ $name ][0],
				'purpose' => $copy[ $name ][1],
			);
		}

		$title = trim( str_replace( array( '_', '-' ), ' ', $name ) );

		return array(
			'title'   => '' === $title ? esc_html_x( 'Tool added by an extension', 'MCP extension tool title', 'uncanny-automator' ) : esc_html( ucfirst( $title ) ),
			'purpose' => esc_html_x( 'An extension provides this tool. Open its details to review what it does.', 'MCP extension tool purpose', 'uncanny-automator' ),
		);
	}

	/**
	 * Format a JSON Schema type for administrators.
	 *
	 * @param array<string,mixed> $schema Property schema.
	 */
	private function format_schema_type( array $schema ): string {
		$type = $schema['type'] ?? '';

		if ( is_array( $type ) ) {
			$type = implode( ' / ', array_filter( $type, 'is_string' ) );
		}

		if ( ! is_string( $type ) || '' === $type ) {
			$type = isset( $schema['oneOf'] ) || isset( $schema['anyOf'] ) ? esc_html_x( 'Multiple formats', 'MCP tool input type', 'uncanny-automator' ) : esc_html_x( 'Any format', 'MCP tool input type', 'uncanny-automator' );
		}

		if ( 'array' === $type && isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			return sprintf(
				/* translators: %s Type of item contained in a list. */
				esc_html_x( 'List of %s', 'MCP tool input type', 'uncanny-automator' ),
				$this->format_schema_type( $schema['items'] )
			);
		}

		return esc_html( $type );
	}

	/**
	 * Return a declared boolean annotation, or null when it is absent/invalid.
	 *
	 * @param array<string,mixed> $annotations Tool annotations.
	 * @param string              $key         Annotation key.
	 */
	private function get_boolean_annotation( array $annotations, string $key ): ?bool {
		return isset( $annotations[ $key ] ) && is_bool( $annotations[ $key ] ) ? $annotations[ $key ] : null;
	}

	/**
	 * Count tools by enforced access level.
	 *
	 * @param array{read:array<int,array<string,mixed>>,write:array<int,array<string,mixed>>,variable:array<int,array<string,mixed>>} $groups Tool groups.
	 * @return array{total:int,read:int,write:int,variable:int}
	 */
	private function get_tool_counts( array $groups ): array {
		$variable = isset( $groups['variable'] ) ? count( $groups['variable'] ) : 0;

		return array(
			'total'    => count( $groups['read'] ) + count( $groups['write'] ) + $variable,
			'read'     => count( $groups['read'] ),
			'write'    => count( $groups['write'] ),
			'variable' => $variable,
		);
	}
}

// Share one controller so rendering abilities never registers extra settings hooks.
new Admin_Settings_Model_Context_Protocol_WordPress_Abilities( new Admin_Settings_Model_Context_Protocol_Tools() );

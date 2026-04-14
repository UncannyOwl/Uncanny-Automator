<?php
/**
 * Integration Loader (Orchestrator)
 *
 * Composes Integration_Discovery, Integration_Registrar, Recipe_Part_Loader,
 * Class_Resolver, Load_Error_Handler, and Addon_Registry into a single entry
 * point for the integration loading pipeline.
 *
 * This class replaces the scattered loading logic that was previously split
 * between Set_Up_Automator and Initialize_Automator.
 *
 * @package Uncanny_Automator\Integration_Loader
 * @since   7.2
 */

namespace Uncanny_Automator\Integration_Loader;

/**
 * Class Integration_Loader
 *
 * Single responsibility: wire and orchestrate the integration loading sub-systems.
 * Each phase (discover → register → load helpers → load recipe parts) delegates
 * to a focused, single-responsibility class.
 */
class Integration_Loader {

	/**
	 * Integration file and directory discovery.
	 *
	 * @var Integration_Discovery
	 */
	private $discovery;

	/**
	 * Legacy integration registration (add-*-integration.php files).
	 *
	 * @var Integration_Registrar
	 */
	private $registrar;

	/**
	 * Recipe part loader (helpers, tokens, triggers, actions, etc.).
	 *
	 * @var Recipe_Part_Loader
	 */
	private $part_loader;

	/**
	 * Class name resolution from file paths.
	 *
	 * @var Class_Resolver
	 */
	private $resolver;

	/**
	 * Error handler for loading failures.
	 *
	 * @var Load_Error_Handler
	 */
	private $error_handler;

	/**
	 * Addon registration handler.
	 *
	 * @var Addon_Registry
	 */
	private $addon_registry;

	/**
	 * Integration_Loader constructor.
	 *
	 * Creates all sub-components and wires their dependencies.
	 *
	 * @param string $integrations_directory_path Absolute path to src/integrations/.
	 */
	public function __construct( $integrations_directory_path ) {
		$this->error_handler  = new Load_Error_Handler();
		$this->resolver       = new Class_Resolver( $integrations_directory_path );
		$this->discovery      = new Integration_Discovery( $integrations_directory_path );
		$this->registrar      = new Integration_Registrar( $this->resolver, $this->error_handler );
		$this->part_loader    = new Recipe_Part_Loader( $this->resolver, $this->error_handler );
		$this->addon_registry = new Addon_Registry();
	}

	/**
	 * Get the Integration_Discovery instance.
	 *
	 * @return Integration_Discovery
	 */
	public function get_discovery() {
		return $this->discovery;
	}

	/**
	 * Get the Integration_Registrar instance.
	 *
	 * Exposed so Pro's finalize_directory_merge() can update active directories
	 * and directories_to_include after its merge pass.
	 *
	 * @return Integration_Registrar
	 */
	public function get_registrar() {
		return $this->registrar;
	}

	/**
	 * Get the Recipe_Part_Loader instance.
	 *
	 * @return Recipe_Part_Loader
	 */
	public function get_part_loader() {
		return $this->part_loader;
	}

	/**
	 * Get the Class_Resolver instance.
	 *
	 * @return Class_Resolver
	 */
	public function get_resolver() {
		return $this->resolver;
	}

	/**
	 * Get the Load_Error_Handler instance.
	 *
	 * @return Load_Error_Handler
	 */
	public function get_error_handler() {
		return $this->error_handler;
	}

	/**
	 * Get the Addon_Registry instance.
	 *
	 * Exposed so addons can access the registry directly if needed.
	 *
	 * @return Addon_Registry
	 */
	public function get_addon_registry() {
		return $this->addon_registry;
	}

	/**
	 * Phase 1: Discover integration directories and build the file map.
	 *
	 * Delegates to Integration_Discovery::get_autoload_directories().
	 * Side effect: populates Set_Up_Automator::$all_integrations.
	 *
	 * @return array Array of directory paths for auto-loading.
	 */
	public function discover_integrations() {
		return $this->discovery->get_autoload_directories();
	}

	/**
	 * Phase 2: Register legacy integrations.
	 *
	 * Iterates add-*-integration.php files, instantiates classes, checks
	 * plugin_active(), and builds the active_directories map.
	 *
	 * @return void
	 */
	public function register_integrations() {
		$this->registrar->register_integrations();
	}

	/**
	 * Phase 3: Load helpers for all active integrations.
	 *
	 * Uses the registrar's active_directories and directories_to_include,
	 * or accepts overrides (for when Pro modifies these maps via hooks).
	 *
	 * @param array|null $active_directories     Override active directories. Defaults to registrar's.
	 * @param array|null $directories_to_include Override directories to include. Defaults to registrar's.
	 *
	 * @return void
	 */
	public function load_helpers( $active_directories = null, $directories_to_include = null ) {
		$this->part_loader->load_helpers(
			null !== $active_directories ? $active_directories : $this->registrar->get_active_directories(),
			null !== $directories_to_include ? $directories_to_include : $this->registrar->get_directories_to_include()
		);
	}

	/**
	 * Phase 4: Load recipe parts (triggers, actions, closures, conditions, loop-filters, tokens).
	 *
	 * Uses the registrar's active_directories and directories_to_include,
	 * or accepts overrides (for when Pro modifies these maps via hooks).
	 *
	 * @param array|null $active_directories     Override active directories. Defaults to registrar's.
	 * @param array|null $directories_to_include Override directories to include. Defaults to registrar's.
	 *
	 * @return void
	 */
	public function load_recipe_parts( $active_directories = null, $directories_to_include = null ) {
		$this->part_loader->load_recipe_parts(
			null !== $active_directories ? $active_directories : $this->registrar->get_active_directories(),
			null !== $directories_to_include ? $directories_to_include : $this->registrar->get_directories_to_include()
		);
	}

	/**
	 * Register the admin notice hook for load errors.
	 *
	 * Should be called once during plugin bootstrap.
	 *
	 * @return void
	 */
	public function register_error_notice_hook() {
		add_action( 'admin_notices', array( Load_Error_Handler::class, 'display_notice' ) );

		// Automator pages nuke all admin_notices via remove_all_actions() in admin_head,
		// then fire this internal hook. Without it, load errors are invisible on recipe
		// editor and other Automator screens — the one place admins most need to see them.
		add_action( 'automator_show_internal_admin_notice', array( Load_Error_Handler::class, 'display_notice' ) );

		Load_Error_Handler::register_dismiss_handler();

		// Auto-clear the load-errors transient at the end of every clean
		// bootstrap. Fires after all helpers, triggers, and actions have been
		// loaded; if no Load_Error_Handler::handle() call ran during this
		// request, the transient is wiped so the admin notice disappears as
		// soon as the underlying issue is fixed — no manual dismiss required.
		add_action(
			'automator_add_integration_recipe_parts',
			array( Load_Error_Handler::class, 'maybe_clear_on_clean_load' ),
			PHP_INT_MAX
		);
	}

	/**
	 * Register an external addon's integration files.
	 *
	 * Convenience method that delegates to Addon_Registry::register().
	 * Addons can also access the registry directly via get_addon_registry().
	 *
	 * @since 7.2
	 *
	 * @param array $config Addon configuration. See Addon_Registry::register() for keys.
	 *
	 * @return bool True if registration succeeded, false if validation failed.
	 */
	public function register_addon( array $config ) {
		return $this->addon_registry->register( $config );
	}
}

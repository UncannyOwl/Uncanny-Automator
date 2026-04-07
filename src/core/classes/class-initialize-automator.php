<?php
/**
 * Initialize Automator
 *
 * Orchestrates the full integration loading pipeline:
 *   1. Discovery  — find integration directories and build file maps
 *   2. Framework  — load modern load.php integrations and AI providers
 *   3. Register   — instantiate legacy add-*-integration.php classes
 *   4. Helpers    — load helper classes for active integrations
 *   5. Parts      — load triggers, actions, closures, conditions, loop-filters, tokens
 *
 * Delegates heavy lifting to Integration_Loader and its sub-components.
 * Maintains public properties (active_directories, directories_to_include)
 * for backward compatibility with Pro and third-party addons.
 *
 * @package Uncanny_Automator
 * @since   3.0
 */

namespace Uncanny_Automator;

use Error;
use Exception;
use Uncanny_Automator\Core\Lib\AI\Default_Providers_Loader;
use Uncanny_Automator\Integration_Loader\Integration_Loader;

/**
 * Class Initialize_Automator
 *
 * Entry point for integration loading. Creates an Integration_Loader orchestrator
 * and coordinates the five-phase loading pipeline via WordPress hooks.
 */
class Initialize_Automator extends Set_Up_Automator {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * The integration loading orchestrator.
	 *
	 * Composes Integration_Discovery, Integration_Registrar, Recipe_Part_Loader,
	 * Class_Resolver, and Load_Error_Handler.
	 *
	 * @var Integration_Loader
	 */
	private $loader;

	/**
	 * Initialize_Automator constructor.
	 *
	 * Sets the integrations directory path, creates the Integration_Loader,
	 * and hooks into WordPress init for the configuration pipeline.
	 *
	 * @throws Exception On unexpected initialization errors.
	 */
	public function __construct() {

		self::$instance = $this;

		$this->integrations_directory_path = UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'integrations';

		// DOWNSTREAM: When adding a new recipe part type (e.g. 'blocks'),
		// append its directory name in global-functions.php → automator_get_default_directories()
		// AND in these locations:
		//   1. globals.php — add AUTOMATOR_POST_TYPE_* constant
		//   2. global-functions.php — append to automator_get_recipe_post_types() / automator_get_direct_recipe_child_types() / automator_get_loop_child_types() / automator_get_gated_directory_types() (if gated)
		//   3. Recipe_Part_Loader::find_class_in_integration() — type loop
		//   4. generate-item-map.php — $code_setters map (build-time)
		//   5. Recipe_Manifest::full_rebuild() — SQL query post types
		$this->default_directories = \automator_get_default_directories();

		// Create the integration loading orchestrator.
		$this->loader = new Integration_Loader( $this->integrations_directory_path );

		// Register admin notice hook for load errors.
		$this->loader->register_error_notice_hook();

		add_action( 'init', array( $this, 'automator_configure' ), AUTOMATOR_CONFIGURATION_PRIORITY_TRIGGER_ENGINE );
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return self|null The instance, or null if not yet constructed.
	 */
	public static function get_instance() {
		return self::$instance;
	}

	/**
	 * Get the Integration_Loader orchestrator.
	 *
	 * Exposed so Pro can access sub-components (e.g. the Registrar for
	 * finalize_directory_merge).
	 *
	 * @return Integration_Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Configure Automator.
	 *
	 * Main entry point hooked to WordPress `init`. Runs the five-phase
	 * loading pipeline.
	 *
	 * @return void
	 *
	 * @throws Exception On loading failure.
	 */
	public function automator_configure() {

		// Add all extensions --- hook here to add your own triggers and actions
		do_action_deprecated( 'automator_configure', array(), '4.2', 'automator_before_configure' );
		do_action( 'automator_before_configure', $this );

		$this->load_integrations();

		// Loads all internal triggers, actions, and closures then provides hooks for external ones
		// All extensions are loaded.
		do_action( 'automator_configuration_complete', $this );

		$this->automator_configuration_complete_func();
	}

	/**
	 * Complete the configuration after all extensions have loaded.
	 *
	 * Runs legacy integration registration, fires hooks for Pro/third-party
	 * to merge their data, then schedules helpers and recipe parts loading.
	 *
	 * @return void
	 *
	 * @throws Automator_Exception On loading failure.
	 */
	public function automator_configuration_complete_func() {

		//Let others hook in and add integrations
		do_action_deprecated( 'uncanny_automator_add_integration', array(), '3.0', 'automator_add_integration' );

		do_action( 'automator_add_integration' );

		// Pro/addons may have hooked automator_item_map during automator_add_integration.
		// Invalidate the early-cached item map so load_recipe_parts() picks up the merged map.
		Recipe_Manifest::get_instance()->invalidate_item_map();

		// Phase 3: Register legacy integrations.
		try {
			$this->initialize_add_integrations();
		} catch ( Error $e ) {
			throw new Automator_Error( esc_html( $e->getMessage() ) );
		} catch ( Exception $e ) {
			throw new Automator_Exception( esc_html( $e->getMessage() ) );
		}

		// Pro hooks here to merge its data into active_directories and directories_to_include.
		do_action( 'automator_after_add_integrations', $this );

		//Let others hook in to the directories and add their integration's actions / triggers etc
		self::$auto_loaded_directories = apply_filters_deprecated( 'uncanny_automator_integration_directory', array( self::$auto_loaded_directories ), '3.0', 'automator_integration_directory' );
		self::$auto_loaded_directories = apply_filters( 'automator_integration_directory', self::$auto_loaded_directories );

		//Let others hook in and add integrations
		do_action_deprecated( 'uncanny_automator_add_recipe_type', array(), '3.0', 'automator_add_recipe_type' );
		do_action( 'automator_add_recipe_type' );

		// Phase 4: Load helpers.
		$this->load_helpers();

		// Phase 5: Load recipe parts (triggers, actions, closures, conditions, loop-filters, tokens).
		// Deferred to a later init priority for 6.7 translation fix with trigger engine.
		add_action( 'init', array( $this, 'load_recipe_parts' ), AUTOMATOR_RECIPE_PARTS_PRIORITY_TRIGGER_ENGINE );
	}

	/**
	 * Phase 1 & 2: Discover integrations and load framework integrations.
	 *
	 * Populates Set_Up_Automator::$auto_loaded_directories and
	 * Set_Up_Automator::$all_integrations from cache or fresh discovery.
	 * Then loads modern framework integrations (load.php files) and AI providers.
	 *
	 * @return void
	 *
	 * @throws Automator_Exception On discovery failure.
	 * @throws Exception           On discovery failure.
	 */
	public function load_integrations() {

		$this->restore_cached_directories();

		if ( empty( self::$auto_loaded_directories ) || empty( self::$all_integrations ) ) {
			$this->discover_and_cache_directories();
		}

		$this->load_framework_integrations();
		$this->load_framework_ai();
	}

	/**
	 * Restore integration directories from cache or transients.
	 *
	 * Tries the object cache first, then falls back to transients when no
	 * persistent object cache is available.
	 *
	 * @return void
	 */
	private function restore_cached_directories() {

		self::$auto_loaded_directories = Automator()->cache->get( 'automator_integration_directories_loaded' );
		self::$all_integrations        = Automator()->cache->get( 'automator_get_all_integrations' );

		if ( wp_using_ext_object_cache() ) {
			return;
		}

		if ( empty( self::$auto_loaded_directories ) ) {
			self::$auto_loaded_directories = get_transient( 'automator_integration_directories_loaded' );
		}

		if ( empty( self::$all_integrations ) ) {
			self::$all_integrations = get_transient( 'automator_get_all_integrations' );
		}
	}

	/**
	 * Discover integrations fresh and persist them in cache and transients.
	 *
	 * @return void
	 *
	 * @throws Automator_Exception On discovery failure.
	 */
	private function discover_and_cache_directories() {

		self::$auto_loaded_directories = $this->loader->discover_integrations();

		Automator()->cache->set( 'automator_integration_directories_loaded', self::$auto_loaded_directories, 'automator', Automator()->cache->long_expires );

		if ( wp_using_ext_object_cache() ) {
			return;
		}

		set_transient( 'automator_integration_directories_loaded', self::$auto_loaded_directories, DAY_IN_SECONDS );
		set_transient( 'automator_get_all_integrations', self::$all_integrations, DAY_IN_SECONDS );
	}

	/**
	 * Phase 3: Register legacy integrations.
	 *
	 * Delegates to Integration_Registrar via the loader, then syncs the
	 * registrar's state back to the public instance properties for backward
	 * compatibility with Pro's finalize_directory_merge().
	 *
	 * @return void
	 *
	 * @throws Exception On instantiation failure.
	 */
	public function initialize_add_integrations() {

		$this->loader->register_integrations();

		// Sync registrar state → public properties (Pro accesses these via hooks).
		$registrar                    = $this->loader->get_registrar();
		$this->active_directories     = $registrar->get_active_directories();
		$this->directories_to_include = $registrar->get_directories_to_include();
	}

	/**
	 * Phase 4: Load helpers for all active integrations.
	 *
	 * Delegates to Recipe_Part_Loader::load_helpers() via the loader.
	 * Uses $this->active_directories (which Pro may have modified via hooks).
	 *
	 * @return void
	 *
	 * @throws Automator_Exception On loading failure.
	 */
	public function load_helpers() {
		try {
			$this->loader->load_helpers( $this->active_directories, $this->directories_to_include );
		} catch ( Error $e ) {
			throw new Automator_Error( esc_html( $e->getMessage() ) );
		} catch ( Exception $e ) {
			throw new Automator_Exception( esc_html( $e->getMessage() ) );
		}

		// Let others hook in and add options
		do_action_deprecated( 'uncanny_automator_add_integration_helpers', array(), '3.0', 'automator_add_integration_helpers' );
		do_action( 'automator_add_integration_helpers' );
	}

	/**
	 * Phase 5: Load recipe parts (triggers, actions, closures, conditions, loop-filters, tokens).
	 *
	 * Delegates to Recipe_Part_Loader::load_recipe_parts() via the loader.
	 * Uses $this->active_directories (which Pro may have modified via hooks).
	 *
	 * @return void
	 *
	 * @throws Automator_Exception On loading failure.
	 */
	public function load_recipe_parts() {
		try {
			$this->loader->load_recipe_parts( $this->active_directories, $this->directories_to_include );
		} catch ( Error $e ) {
			throw new Automator_Error( esc_html( $e->getMessage() ) );
		} catch ( Exception $e ) {
			throw new Automator_Exception( esc_html( $e->getMessage() ) );
		}

		// Let others hook in and add triggers actions or tokens
		do_action_deprecated( 'uncanny_automator_add_integration_triggers_actions_tokens', array(), '3.0', 'automator_add_integration_recipe_parts' );
		do_action( 'automator_add_integration_recipe_parts' );
	}

	/**
	 * Load framework integrations (modern load.php pattern).
	 *
	 * Loads FREE's own framework integrations (src/integrations/&#42;/load.php) via the
	 * pre-built vendor/composer/autoload_filemap.php. This file is generated at zip time
	 * and contains only integrations that ship inside this plugin.
	 *
	 * INTEGRATION DISCOVERY — four distinct paths exist:
	 *
	 *   1. FREE framework integrations (this method)
	 *      Source : vendor/composer/autoload_filemap.php (pre-built, internal only)
	 *      Covers : src/integrations/&#42;/load.php — never contains third-party add-ons.
	 *
	 *   2. FREE legacy integrations
	 *      Source : vendor/composer/autoload_integrations_map.php (pre-built, internal only)
	 *      Handler: Integration_Registrar::register_integrations()
	 *      Covers : add-&#42;-integration.php files — never contains third-party add-ons.
	 *
	 *   3. Third-party add-ons — LEGACY pattern (e.g. AcyMailing)
	 *      Entry  : automator_add_integration_directory( $code, $dir ) called at plugins_loaded.
	 *      Effect : pushes $dir into Set_Up_Automator::$auto_loaded_directories before init fires.
	 *      Handler: Integration_Registrar picks it up on the same pass.
	 *
	 *   4. Third-party add-ons — MODERN pattern (e.g. Custom User Fields, Dynamic Content)
	 *      Entry  : new \Uncanny_Automator\Integration() called directly (plugins_loaded or
	 *               automator_add_integration hook). The constructor self-registers via setup().
	 *      Effect : bypasses $auto_loaded_directories entirely — unaffected by filemap.
	 *
	 * @return void
	 */
	public function load_framework_integrations() {

		$vendor_dir = dirname( AUTOMATOR_BASE_FILE ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR;

		$automator_file_map = include $vendor_dir . 'autoload_filemap.php';

		if ( empty( $automator_file_map ) ) {
			return;
		}

		$manifest = Recipe_Manifest::get_instance();

		$error_handler = $this->loader->get_error_handler();

		// Full load on: recipe editor, escape hatches, first deploy, or Automator admin pages.
		if ( $manifest->should_load_all() ) {
			foreach ( $automator_file_map as $file ) {
				try {
					include_once $file;
				} catch ( \Throwable $e ) {
					$error_handler->handle( basename( $file, '.php' ), $e );
				}
			}
			return;
		}

		// Frontend / cron / REST — only include load.php for integrations with active codes.
		$dir_codes = $manifest->get_directory_code_map();

		foreach ( $automator_file_map as $file ) {
			if ( $this->should_include_framework_integration( $file, $dir_codes, $manifest ) ) {
				try {
					include_once $file;
				} catch ( \Throwable $e ) {
					$error_handler->handle( basename( $file, '.php' ), $e );
				}
			}
		}
	}

	/**
	 * Determine if a framework integration file should be included.
	 *
	 * Integrations not in the directory-to-code map are loaded unconditionally
	 * (third-party or unmapped — safe fallback). Mapped integrations are only
	 * loaded when the manifest says they are needed.
	 *
	 * @param string          $file      The load.php file path.
	 * @param array           $dir_codes Directory name → integration code map.
	 * @param Recipe_Manifest $manifest  The manifest instance.
	 *
	 * @return bool true to include.
	 */
	private function should_include_framework_integration( $file, $dir_codes, $manifest ) {

		$dir = basename( dirname( $file ) );

		// Not in item map — third-party or unmapped, safe fallback: load it.
		if ( ! isset( $dir_codes[ $dir ] ) ) {
			return true;
		}

		return $manifest->is_integration_needed( $dir_codes[ $dir ] );
	}

	/**
	 * Load the AI framework.
	 *
	 * Loads the default AI providers (OpenAI, Claude, etc.).
	 *
	 * @return void
	 */
	public function load_framework_ai() {
		$loader = new Default_Providers_Loader();
		$loader->load_providers();
	}
}

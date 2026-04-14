<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Integrations
 * @since   4.14
 * @version 4.14
 * @author  Ajay V.
 * @package Uncanny_Automator
 */


namespace Uncanny_Automator;

use Uncanny_Automator\Integration_Loader\Load_Error_Handler;

/**
 * Abstract Integrations
 *
 * @package Uncanny_Automator
 */
abstract class Integration {

	/**
	 * @var
	 */
	protected $name;

	/**
	 * @var
	 */
	protected $icon_url;

	/**
	 * @var
	 */
	protected $integration;

	/**
	 * @var bool
	 */
	protected $connected = null;

	/**
	 * @var string
	 */
	protected $settings_url = '';

	/**
	 * @var string
	 */
	protected $plugin_file_path = '';

	/**
	 * helpers
	 *
	 * Use this variable for dependency injection
	 *
	 * @var mixed
	 */
	protected $helpers;

	/**
	 * Is third party flag
	 *
	 * @var null|bool - Null if not set
	 */
	protected $is_third_party = null;

	/**
	 * __construct
	 *
	 * @param mixed $helpers
	 *
	 * @return void
	 */
	final public function __construct( $helpers = null ) {
		$this->helpers = $helpers;
		$this->setup();

		$registration_data = array(
			'name'             => $this->get_name(),
			'icon_svg'         => $this->get_icon_url(),
			'is_third_party'   => $this->get_is_third_party(),
			'plugin_file_path' => $this->get_plugin_file_path(),
		);

		// If uses manifest trait, extract manifest data
		if ( $this->uses_manifest_trait() && is_callable( array( $this, 'extract_manifest_data' ) ) ) {
			/** @var array $manifest */
			$manifest                      = call_user_func( array( $this, 'extract_manifest_data' ) );
			$registration_data['manifest'] = $manifest;
		}

		Automator()->set_all_integrations(
			$this->get_integration(),
			$registration_data
		);

		$plugin_active = $this->plugin_active();
		$plugin_active = apply_filters( 'automator_integration_plugin_active', $plugin_active, $this );
		if ( $plugin_active ) {
			Set_Up_Automator::set_active_integration_code( $this->get_integration() );

			$this->load_recipe_parts();

			add_action( 'automator_integrations', array( $this, 'register_integration' ) );
			add_filter( 'uncanny_automator_maybe_add_integration', array( $this, 'override_plugin_status' ), 10, 2 );
		}
	}

	/**
	 * Load recipe parts with per-item gating via the item map.
	 *
	 * In full-load mode (recipe editor, escape hatches): calls $this->load() as-is.
	 * In targeted mode: runs load_shared_hooks() first (tokens, filters, AJAX, migrations),
	 * then only instantiates triggers/actions/closures whose codes appear in the manifest.
	 * Falls back to $this->load() for integrations not in the item map (third-party addons).
	 *
	 * @return void
	 */
	private function load_recipe_parts() {

		$manifest = Recipe_Manifest::get_instance();

		// Recipe editor, escape hatch, or Automator admin page — load everything.
		if ( $manifest->should_load_all() ) {
			try {
				$this->load();
			} catch ( \Throwable $e ) {
				( new Load_Error_Handler() )->handle( get_class( $this ), $e );
			}
			return;
		}

		// Unconnected App_Integration — can't execute on frontend, skip entirely.
		if ( $this instanceof \Uncanny_Automator\App_Integrations\App_Integration
			&& false === $this->get_connected() ) {
			return;
		}

		$item_map         = $manifest->get_item_map();
		$integration_code = $this->get_integration();

		// Token-only integrations (e.g., COMMON) have no triggers/actions, so they
		// never appear in the manifest. They ARE in the item map with empty type arrays
		// or absent entirely. Always call load() so their tokens register.
		if ( empty( $item_map[ $integration_code ] ) ) {
			try {
				$this->load();
			} catch ( \Throwable $e ) {
				( new Load_Error_Handler() )->handle( get_class( $this ), $e );
			}
			return;
		}

		// Integration not needed by any published recipe.
		if ( ! $manifest->is_integration_needed( $integration_code ) ) {
			return;
		}

		// Shared hooks run whenever the integration is needed — tokens, filters, AJAX, migrations.
		$this->load_shared_hooks();

		// Targeted mode — only instantiate items whose codes are active.
		$integration_items = $item_map[ $integration_code ];
		$args              = $this->get_load_arguments();

		foreach ( array( 'triggers', 'actions', 'closures', 'conditions', 'loop_filters' ) as $type ) {

			if ( empty( $integration_items[ $type ] ) ) {
				continue;
			}

			foreach ( $integration_items[ $type ] as $composite_key => $entry ) {

				if ( ! $manifest->is_code_active( $composite_key ) ) {
					continue;
				}

				$class = $entry['class'];

				// Skip if already instantiated (e.g. Free loaded it, Pro re-encounters it).
				if ( false !== Utilities::get_class_instance( $class ) ) {
					continue;
				}

				if ( class_exists( $class ) ) {
					try {
						$instance = new $class( ...$args );
						Utilities::add_class_instance( $class, $instance );
					} catch ( \Throwable $e ) {
						( new Load_Error_Handler() )->handle( $class, $e );
					}
				}
			}
		}
	}

	/**
	 * Arguments to pass to trigger/action constructors in targeted loading mode.
	 *
	 * Plain Integration subclasses pass $this->helpers (set in setup()).
	 * App_Integration overrides this to return $this->dependencies (stdClass).
	 *
	 * @return array Constructor arguments.
	 */
	protected function get_load_arguments() {
		return null !== $this->helpers ? array( $this->helpers ) : array();
	}

	/**
	 * Shared hooks that must run whenever the integration is needed.
	 *
	 * Override this method to register non-class side effects that are required
	 * for execution — universal tokens, dynamic filters, AJAX handlers, migrations,
	 * and shared dependencies that triggers/actions rely on at runtime.
	 *
	 * This runs in targeted mode BEFORE per-item gating. In full-load mode,
	 * load() handles everything, so this is NOT called.
	 *
	 * DOWNSTREAM: If your integration registers WP hooks (add_action/add_filter)
	 * inside load() that must fire even when only a subset of triggers/actions are
	 * loaded, move those registrations into this method. Without the override,
	 * targeted mode will skip them silently. This applies to Pro integrations and
	 * third-party addons extending this class — Free integrations were audited at
	 * the time this method was introduced.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
	}

	/**
	 * load
	 *
	 * Override this method and instantiate the integration classes in it
	 *
	 * @return void
	 */
	protected function load() {
	}

	/**
	 * @return string
	 */
	public function get_settings_url() {
		return $this->settings_url;
	}

	/**
	 * @param $settings_url
	 */
	public function set_settings_url( $settings_url ) {
		$this->settings_url = $settings_url;
	}

	/**
	 * @return mixed
	 */
	public function get_connected() {
		return $this->connected;
	}

	/**
	 * @param mixed $connected
	 */
	public function set_connected( $connected ) {
		$this->connected = $connected;
	}

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	abstract protected function setup();

	/**
	 * Pass plugin path, i.e., uncanny-automator/uncanny-automator.php to check if plugin is active. By default it
	 * returns true for an integration.
	 *
	 * @return mixed|bool
	 */
	public function plugin_active() {
		return true;
	}

	/**
	 * @param $name
	 */
	protected function name( $name ) {
		$this->name = $name;
	}

	/**
	 * @param $integration
	 */
	protected function integration( $integration ) {
		$this->integration = $integration;
	}

	/**
	 * @return mixed
	 */
	public function get_integration() {
		return $this->integration;
	}

	/**
	 * @return mixed
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * @param $path
	 */
	public function register_integration( $integrations ) {
		$integrations[ $this->integration ] = array(
			'name'         => $this->get_name(),
			'icon_svg'     => $this->get_icon_url(),
			'connected'    => apply_filters( 'automator_integration_connected', $this->get_connected(), $this->integration, $this ),
			'settings_url' => $this->get_settings_url(),
		);

		return $integrations;
	}

	/**
	 * Placeholder function to be able to use protected method
	 * @return string
	 */
	public function get_integration_icon() {
		return $this->get_icon_url();
	}

	/**
	 * @return string
	 */
	protected function get_icon_url() {
		return $this->icon_url;
	}

	/**
	 * @return string
	 */
	public function set_icon_url( $url ) {
		$this->icon_url = $url;
	}

	/**
	 * @param $integration
	 */
	protected function set_integration( $integration ) {
		$this->integration = $integration;
	}

	/**
	 * @param mixed $name
	 */
	protected function set_name( $name ) {
		$this->name = $name;
	}

	/**
	 * Set is_third_party
	 *
	 * @param bool $is_third_party
	 *
	 * @return void
	 */
	protected function set_is_third_party( $is_third_party ) {
		$this->is_third_party = (bool) $is_third_party;
	}

	/**
	 * Get is_third_party
	 *
	 * @return bool
	 */
	protected function get_is_third_party() {
		// Check if value has not been set yet.
		if ( is_null( $this->is_third_party ) ) {
			$this->set_is_third_party( Automator()->is_third_party_integration_by_class( $this ) );
		}

		return $this->is_third_party;
	}

	/**
	 * override_plugin_status
	 *
	 * Previously Automator checked if the integrtiion plugin is active or not,
	 * now each integration has to perform this check in the plugin_active method.
	 *
	 * @param mixed $active
	 * @param mixed $integration
	 *
	 * @return void
	 */
	public function override_plugin_status( $active, $integration ) {

		if ( $integration !== $this->get_integration() ) {
			return $active;
		}

		return true;
	}

	/**
	 * Get plugin file path.
	 *
	 * Returns plugin_file_path property. If Integration_Manifest trait is used,
	 * the trait's method will override this one.
	 *
	 * @return string Plugin file path
	 */
	public function get_plugin_file_path() {
		return $this->plugin_file_path ?? '';
	}

	/**
	 * Set plugin file path.
	 *
	 * Sets the plugin_file_path property. If Integration_Manifest trait is used,
	 * the trait's method will override this one.
	 *
	 * @param string $file_path Plugin file path
	 * @return void
	 */
	public function set_plugin_file_path( $file_path ) {
		$this->plugin_file_path = (string) $file_path;
	}

	/**
	 * Check if integration uses Integration_Manifest trait.
	 *
	 * @return bool True if trait is used
	 */
	private function uses_manifest_trait() {
		$traits = class_uses( get_class( $this ) );
		return in_array( 'Uncanny_Automator\Integration_Manifest', $traits, true );
	}
}

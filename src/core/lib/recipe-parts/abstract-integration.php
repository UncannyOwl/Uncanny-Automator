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
	 * Plugin metadata setters/getters — plugin_file_path, developer_name,
	 * integration_type, distribution_type, plugin_required, integration_required,
	 * plugin_variations — available to every integration without per-class
	 * plumbing. Integrations declare their values by calling the setters in
	 * setup(); those that declare nothing register exactly as they did before.
	 */
	use Integration_Manifest;

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
	 * Integration instances keyed by integration code, then by owner (the root
	 * namespace of the integration class: Free, Pro or an add-on) — the only
	 * bridge the lazy trigger pipeline needs to reach pre-constructed
	 * dependencies. Populated in `__construct()`; read via
	 * `load_arguments_for()` and `helpers_for()`.
	 *
	 * @var array<string, array<string, self>>
	 */
	private static $instances_by_code = array();

	/**
	 * How many integrations are running their load() right now.
	 *
	 * A load() can build another integration, so this is a count, not a flag.
	 *
	 * @since 8.0.0
	 *
	 * @var int
	 */
	private static $loading = 0;

	/**
	 * Whether an integration's load() is running.
	 *
	 * The integration's own guard records a failure inside load() under the integration and
	 * skips the rest of it. A settings page set up there leaves its refusal to that guard
	 * (see Premium_Integrations::setup_settings()).
	 *
	 * @internal
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public static function is_loading() {
		return 0 < self::$loading;
	}

	/**
	 * Resolve an integration's helpers object by code. Returns null when the
	 * integration wasn't loaded this request or has no helpers class.
	 *
	 * The helpers alone. A lazily built trigger is given
	 * {@see load_arguments_for()} instead, which is also right for an
	 * App_Integration's dependencies object. Pass the class of the
	 * part being built: a Free trigger then gets Free's helpers and a Pro
	 * trigger Pro's, even when Free and Pro share the integration code.
	 * Without a class, or when the part's plugin registered no helpers for
	 * the code, the last registered instance wins.
	 *
	 * @param string $code  Integration code (e.g. 'SEO_BY_RANK_MATH').
	 * @param string $class Optional. Fully qualified class of the part being built.
	 *
	 * @return mixed|null
	 */
	public static function helpers_for( $code, $class = '' ) {

		$instance = self::instance_for( $code, $class );

		return null !== $instance ? $instance->helpers : null;
	}

	/**
	 * The constructor arguments the owning integration hands its parts on the
	 * eager path (`get_load_arguments()`): the helpers for a plain Integration,
	 * the dependencies object (helpers, api, webhooks) for an App_Integration.
	 * `Trigger_Late_Resolver` builds lazy triggers with these, so a lazily built
	 * trigger gets what an eagerly built one does. The owning instance is
	 * picked as in {@see helpers_for()}.
	 *
	 * @since 8.0.0
	 *
	 * @param string $code       Integration code.
	 * @param string $part_class Optional. Fully qualified class of the part being built.
	 *
	 * @return array Constructor arguments; empty when the integration wasn't loaded this request.
	 */
	public static function load_arguments_for( $code, $part_class = '' ) {

		$instance = self::instance_for( $code, $part_class );

		return null !== $instance ? $instance->get_load_arguments() : array();
	}

	/**
	 * The registered integration that owns a part: the part's own plugin's
	 * instance for the code, else the last registered one.
	 *
	 * @since 8.0.0
	 *
	 * @param string $code       Integration code.
	 * @param string $part_class Fully qualified class of the part being built, or ''.
	 *
	 * @return self|null
	 */
	private static function instance_for( $code, $part_class ) {

		if ( empty( self::$instances_by_code[ $code ] ) ) {
			return null;
		}

		$instances = self::$instances_by_code[ $code ];
		$owner     = self::owner_of( $part_class );

		if ( '' !== $owner && isset( $instances[ $owner ] ) ) {
			return $instances[ $owner ];
		}

		return end( $instances );
	}

	/**
	 * The root namespace of a class (`Uncanny_Automator`, `Uncanny_Automator_Pro`
	 * or an add-on's), or '' for a class outside any namespace.
	 *
	 * @param string $class Fully qualified class name.
	 *
	 * @return string
	 */
	private static function owner_of( $class ) {

		$class = ltrim( (string) $class, '\\' );
		$pos   = strpos( $class, '\\' );

		return false === $pos ? '' : substr( $class, 0, $pos );
	}

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

		// Register self so the lazy trigger pipeline can find helpers by code.
		// Only an instance that actually has helpers wins — prevents a
		// helperless Pro integration (e.g. conditions/loop-filters only)
		// from clobbering Free's helpers-bearing entry when both share a code.
		$code = $this->get_integration();
		if ( '' !== $code && null !== $this->helpers ) {
			$owner = self::owner_of( static::class );
			// Re-registering an owner moves it to the end, so "last registered" stays true.
			unset( self::$instances_by_code[ $code ][ $owner ] );
			self::$instances_by_code[ $code ][ $owner ] = $this;
		}

		$registration_data = array(
			'name'             => $this->get_name(),
			'icon_svg'         => $this->get_icon_url(),
			'is_third_party'   => $this->get_is_third_party(),
			'plugin_file_path' => $this->get_plugin_file_path(),
			'app_platform'     => $this->uses_app_platform(),
		);

		// Only integrations that declared something carry a manifest key —
		// extract_manifest_data() returns non-empty values only, so an
		// integration that declares nothing registers exactly as it did before.
		$manifest = $this->extract_manifest_data();
		if ( ! empty( $manifest ) ) {
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

			// Auto-register the remote-data filter for helpers that extend
			// Abstract_Helpers. Runs unconditionally on active integrations
			// because the registration is just a single add_filter() call —
			// the actual handler is only invoked on REST dispatch.
			$this->maybe_register_helpers_remote_data();

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
	 * then only instantiates triggers/actions whose codes appear in the manifest.
	 * Falls back to $this->load() for integrations not in the item map (third-party addons).
	 *
	 * @return void
	 */
	private function load_recipe_parts() {

		$manifest = Recipe_Manifest::get_instance();

		// Recipe editor, escape hatch, or Automator admin page — load everything.
		if ( $manifest->should_load_all() ) {
			$this->run_load();
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
			$this->run_load();
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

		foreach ( array( 'triggers', 'actions', 'conditions', 'loop_filters' ) as $type ) {

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

				// Lazy path: if the trigger class declares definition(), let
				// Trigger_Metadata_Loader handle it. This check runs at init:1
				// (Integration construction is inline), BEFORE the loader fires
				// at init:30 — so we cannot rely on Automator()->has_trigger()
				// here. The presence of a non-null definition() is a stable,
				// code-level signal that the stub will be registered later.
				// Actions / conditions / loop filters have no
				// definition() method and so never take this branch.
				//
				// method_exists() guard: a legacy trigger (no definition()) can be
				// listed in the manifest — e.g. a half-migrated integration, or a
				// version-mismatched add-on. Calling definition() on it is a fatal
				// "Call to undefined method". Guard so it falls through to normal
				// (legacy) instantiation below instead of crashing the loader.
				if ( 'triggers' === $type && class_exists( $class ) && method_exists( $class, 'definition' ) && null !== $class::definition() ) {
					continue;
				}

				if ( class_exists( $class ) ) {
					try {
						// Loop_Filter constructors are ($filter_id, $args, $loop_id,
						// $item_helpers) and only self-register into the global
						// filter registry when $filter_id is empty — the helper must
						// go 4th, never 1st, or runtime entity resolution finds no
						// filter class and every loop yields zero entities.
						$instance = 'loop_filters' === $type
							? new $class( null, array(), null, $args[0] ?? null )
							: new $class( ...$args );
						Utilities::add_class_instance( $class, $instance );
					} catch ( \Throwable $e ) {
						( new Load_Error_Handler() )->handle( $class, $e );
					}
				}
			}
		}
	}

	/**
	 * Run load(). A failure there is recorded under this integration for the load-errors
	 * notice, the rest of load() is skipped, and the site keeps running.
	 *
	 * While it runs, is_loading() is true.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function run_load() {

		++self::$loading;

		try {
			$this->load();
		} catch ( \Throwable $e ) {
			( new Load_Error_Handler() )->handle( get_class( $this ), $e );
		} finally {
			--self::$loading;
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
	 * Whether the Automator App Platform holds this integration's connections.
	 * Only an app integration on its connections can; it says so.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function uses_app_platform() {
		return false;
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
	 * Auto-register the remote-data filter when helpers extend Abstract_Helpers.
	 *
	 * Defaults `remote_data_id` from the integration code when the helper
	 * does not set it explicitly, then registers the
	 * `automator_remote_data_instance_{id}` filter so the REST controller
	 * can resolve this helper instance at dispatch time.
	 *
	 * @return void
	 */
	private function maybe_register_helpers_remote_data() {

		if ( ! $this->helpers instanceof \Uncanny_Automator\Recipe\Abstract_Helpers ) {
			return;
		}

		// Default remote_data_id from the integration code if not already set.
		if ( empty( $this->helpers->get_remote_data_id() ) ) {
			$this->helpers->set_remote_data_id( sanitize_key( $this->get_integration() ) );
		}

		$this->helpers->register_remote_data_filter();
	}
}

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
			$this->load();
			add_action( 'automator_integrations', array( $this, 'register_integration' ) );
			add_filter( 'uncanny_automator_maybe_add_integration', array( $this, 'override_plugin_status' ), 10, 2 );
		}
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

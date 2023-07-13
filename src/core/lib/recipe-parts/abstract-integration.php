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
	 * helpers
	 *
	 * Use this variable for dependency injection
	 *
	 * @var mixed
	 */
	protected $helpers;

	/**
	 * __construct
	 *
	 * @param  mixed $helpers
	 * @return void
	 */
	final public function __construct( $helpers = null ) {
		$this->helpers = $helpers;
		$this->setup();

		Automator()->set_all_integrations(
			$this->get_integration(),
			array(
				'name'     => $this->get_name(),
				'icon_svg' => $this->get_icon_url(),
			)
		);

		if ( $this->plugin_active() ) {
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
	protected function load() {}

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
		return apply_filters( 'automator_integration_plugin_active', true, $this );
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
			'connected'    => $this->get_connected(),
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
	 * override_plugin_status
	 *
	 * Previously Automator checked if the integrtiion plugin is active or not,
	 * now each integration has to perform this check in the plugin_active method.
	 *
	 * @param  mixed $active
	 * @param  mixed $integration
	 * @return void
	 */
	public function override_plugin_status( $active, $integration ) {

		if ( $integration !== $this->get_integration() ) {
			return $active;
		}

		return true;
	}
}

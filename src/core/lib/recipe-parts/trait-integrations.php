<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Integrations
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
 */


namespace Uncanny_Automator\Recipe;

use Uncanny_Automator\Utilities;

/**
 * Trait Integrations
 *
 * @package Uncanny_Automator
 */
trait Integrations {
	/**
	 * @var
	 */
	protected $name;
	/**
	 * @var
	 */
	protected $icon;

	/**
	 * @var
	 */
	protected $icon_path;
	/**
	 * @var
	 */
	protected $integration;
	/**
	 * @var
	 */
	protected $plugin_file_path;
	/**
	 * @var
	 */
	protected $external_integration;

	/**
	 * @var bool
	 */
	protected $connected = null;

	/**
	 * @var string
	 */
	protected $settings_url = '';

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
	 * @return mixed
	 */
	public function is_external_integration() {
		return $this->external_integration;
	}

	/**
	 * @param mixed $external_integration
	 */
	public function set_external_integration( $external_integration ) {
		$this->external_integration = $external_integration;
	}

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	abstract protected function setup();

	/**
	 * @return mixed
	 */
	public function get_icon_path() {
		return $this->icon_path;
	}

	/**
	 * @param mixed $icon_path
	 */
	public function set_icon_path( $icon_path ) {
		$this->icon_path = $icon_path;
	}

	/**
	 * Pass plugin path, i.e., uncanny-automator/uncanny-automator.php to check if plugin is active. By default it
	 * returns true for an integration.
	 *
	 * @return mixed|bool
	 */
	public function plugin_active() {
		$plugin = apply_filters( 'automator_modify_plugin_path', $this->get_plugin_file_path() );

		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		$active = ! empty( $plugin ) ? is_plugin_active( $plugin ) : false;

		return apply_filters( 'automator_is_integration_plugin_active', $active, $plugin, $this->get_plugin_file_path() );
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
	 * @param $path
	 */
	protected function plugin_path( $path ) {
		$this->plugin_file_path = $path;
	}

	/**
	 * @param $path
	 */
	protected function get_plugin_file_path() {
		return $this->plugin_file_path;
	}

	/**
	 * @param $icon
	 */
	protected function icon( $icon ) {
		$this->icon = $icon;
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
	 * @return mixed
	 */
	public function get_icon() {
		return $this->icon;
	}

	/**
	 * @param $path
	 */
	public function add_integration() {
		Automator()->register->integration(
			$this->integration,
			array(
				'name'         => $this->get_name(),
				'icon_svg'     => $this->get_icon_url(),
				'connected'    => $this->get_connected(),
				'settings_url' => $this->get_settings_url(),
			)
		);
	}

	/**
	 * @return string
	 */
	protected function get_icon_url() {
		$icon_path = $this->get_icon_path() . $this->get_icon();
		if ( $this->is_external_integration() ) {
			$icon_path = str_replace( dirname( $this->get_plugin_file_path() ), '', $icon_path );
			$icon_url  = plugins_url( $icon_path, $this->get_plugin_file_path() );
		} else {
			$icon_url = Utilities::automator_get_integration_icon( $icon_path );
		}

		return $icon_url;
	}

	/**
	 * @param $integration
	 */
	protected function set_integration( $integration ) {
		$this->integration = $integration;
	}

	/**
	 * @param mixed $plugin_file_path
	 */
	protected function set_plugin_file_path( $plugin_file_path ) {
		$this->plugin_file_path = $plugin_file_path;
	}

	/**
	 * @param mixed $icon
	 */
	protected function set_icon( $icon ) {
		$this->icon = $icon;
	}

	/**
	 * @param mixed $name
	 */
	protected function set_name( $name ) {
		$this->name = $name;
	}

	/**
	 * @param $directory
	 * @param $path
	 *
	 * @return mixed
	 */
	public function add_integration_directory_func( $directory = array(), $path = '' ) {

		$directory[] = dirname( $path ) . '/helpers';
		$directory[] = dirname( $path ) . '/actions';
		$directory[] = dirname( $path ) . '/triggers';
		$directory[] = dirname( $path ) . '/tokens';
		$directory[] = dirname( $path ) . '/closures';
		$directory[] = dirname( $path ) . '/img';

		return $directory;
	}
}

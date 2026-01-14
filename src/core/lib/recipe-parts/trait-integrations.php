<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Integrations
 * @since   3.0
 * @version 3.0
 * @author  Saad S.
 * @package Uncanny_Automator
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
	 * @var string
	 */
	protected $plugin_file_path = '';

	/**
	 * Loopable tokens.
	 *
	 * @var array
	 */
	protected $loopable_tokens = array();

	/**
	 * Set the loopable tokens.
	 *
	 * @param array $loopable_tokens
	 *
	 * @return void
	 */
	public function set_loopable_tokens( array $loopable_tokens = array() ) {
		$this->loopable_tokens = $loopable_tokens;
	}

	/**
	 * Get the loopable tokens.
	 *
	 * @return array
	 */
	public function get_loopable_tokens() {
		return $this->loopable_tokens;
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
		$registration_data = array(
			'name'             => $this->get_name(),
			'icon_svg'         => $this->get_icon_url(),
			'connected'        => apply_filters( 'automator_integration_connected', $this->get_connected(), $this->integration, $this ),
			'settings_url'     => $this->get_settings_url(),
			'loopable_tokens'  => $this->get_loopable_tokens(),
			'plugin_file_path' => $this->get_plugin_file_path(),
		);

		// If uses manifest trait, extract manifest data
		if ( $this->uses_manifest_trait() && is_callable( array( $this, 'extract_manifest_data' ) ) ) {
			/** @var array $manifest */
			$manifest                      = call_user_func( array( $this, 'extract_manifest_data' ) );
			$registration_data['manifest'] = $manifest;
		}

		Automator()->register->integration(
			$this->integration,
			$registration_data
		);
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

	/**
	 * Get plugin file path.
	 *
	 * Returns plugin_file_path property. If Integration_Manifest trait is used,
	 * the trait's method will override this one.
	 *
	 * @return string Plugin file path
	 */
	public function get_plugin_file_path() {
		return isset( $this->plugin_file_path ) ? $this->plugin_file_path : '';
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

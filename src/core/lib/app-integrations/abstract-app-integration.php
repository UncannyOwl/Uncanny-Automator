<?php

namespace Uncanny_Automator\App_Integrations;

use Exception;
use Uncanny_Automator\Integration;
use stdClass;
use ReflectionClass;

/**
 * Abstract class layer for app integrations.
 *
 * @package Uncanny_Automator
 */
abstract class App_Integration extends Integration {

	/**
	 * The API endpoint ( e.g. /v2/discord )
	 *
	 * @var string
	 */
	protected $api_endpoint;

	/**
	 * The settings id ( used for the settings tab id and url )
	 *
	 * @var string
	 */
	protected $settings_id;

	/**
	 * The icon id ( used for the icon in the settings page ).
	 *
	 * @var string
	 */
	protected $icon;

	/**
	 * Whether the integration requires credits.
	 * Set to true by default - 3rd party apps should override this.
	 *
	 * @var bool
	 */
	protected $requires_credits = true;

	/**
	 * Dependencies for this integration.
	 *
	 * @var stdClass|null The dependencies for this integration.
	 */
	protected $dependencies = null;

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	abstract protected function is_app_connected();

	/**
	 * Setup the app integration properties.
	 *
	 * @param array $config
	 *
	 * @throws Exception If required arguments are missing or invalid
	 * @return void
	 */
	protected function setup_app_integration( $config = array() ) {

		// Set the integration if not set.
		if ( empty( $this->get_integration() ) ) {
			if ( ! isset( $config['integration'] ) ) {
				throw new Exception( 'Integration must be set' );
			}
			$this->set_integration( $config['integration'] );
		}

		// Set the name if not set.
		if ( empty( $this->get_name() ) ) {
			if ( ! isset( $config['name'] ) ) {
				throw new Exception( 'Integration name must be set' );
			}
			$this->set_name( $config['name'] );
		}

		// Set the icon ( ID ) if not set.
		if ( empty( $this->get_icon() ) ) {
			if ( ! isset( $config['icon'] ) ) {
				// Use the integration ID as the icon ID.
				$config['icon'] = $this->get_integration();
			}
			$this->set_icon( $config['icon'] );
		}

		// Set the API endpoint if not set.
		if ( empty( $this->get_api_endpoint() ) ) {
			if ( ! isset( $config['api_endpoint'] ) ) {
				throw new Exception( 'API endpoint is required' );
			}
			$this->set_api_endpoint( $config['api_endpoint'] );
		}

		if ( ! isset( $config['settings_id'] ) ) {
			$config['settings_id'] = sanitize_title( $this->get_integration() );
		}
		$this->set_settings_id( sanitize_title( $config['settings_id'] ) );

		/**
		 * Add action to allow for integration-specific setup.
		 *
		 * @param App_Integration $integration The app integration instance.
		 */
		do_action( 'automator_app_integration_setup_' . $this->get_integration(), $this );

		// Initialize the app integration.
		$this->initialize_app_integration();
	}

	/**
	 * Initialize the app integration
	 *
	 * @return void
	 */
	protected function initialize_app_integration() {
		// Check and set the connected status using the is_app_connected method.
		$this->set_connected( $this->is_app_connected() );

		// Set the settings URL.
		$this->set_settings_url(
			automator_get_premium_integrations_settings_url(
				$this->get_settings_id()
			)
		);

		// Set core dependencies by creating instances directly.
		$this->set_dependency( 'helpers', $this->helpers );
		$this->set_dependency( 'api', $this->get_api_instance() );
		$this->set_dependency( 'webhooks', $this->get_webhooks_instance() );

		// Pass dependencies to helpers.
		$this->helpers->set_dependencies( $this->dependencies );
		// Pass dependencies to api.
		if ( $this->dependencies->api ) {
			$this->dependencies->api->set_dependencies( $this->dependencies );
		}
		// Pass dependencies to webhooks.
		if ( $this->dependencies->webhooks ) {
			$this->dependencies->webhooks->set_dependencies( $this->dependencies );
		}

		// Allow child classes to register custom dependencies.
		$this->register_dependencies();

		// Initialize webhooks if they exist and integration is connected.
		if ( ! is_null( $this->dependencies->webhooks ) && $this->get_connected() ) {
			$this->dependencies->webhooks->initialize();
		}

		// Register integration-specific hooks
		$this->register_hooks();

		/**
		 * Add action to allow for integration-specific setup.
		 *
		 * @param App_Integration $integration The app integration instance.
		 */
		do_action( 'automator_app_integration_initialized_' . $this->get_integration(), $this );
	}

	/**
	 * Set the API endpoint
	 *
	 * @param string $endpoint
	 *
	 * @return void
	 */
	public function set_api_endpoint( $endpoint ) {
		$this->api_endpoint = $endpoint;
	}

	/**
	 * Get the API endpoint ( e.g. /v2/discord )
	 *
	 * @return string
	 */
	public function get_api_endpoint() {
		return $this->api_endpoint;
	}

	/**
	 * Set the settings ID
	 *
	 * @param string $id
	 *
	 * @return void
	 */
	protected function set_settings_id( $id ) {
		$this->settings_id = $id;
	}

	/**
	 * Get the settings ID
	 *
	 * @return string
	 */
	public function get_settings_id() {
		return $this->settings_id;
	}

	/**
	 * Get whether the integration requires credits.
	 *
	 * @return bool
	 */
	public function get_requires_credits() {
		return $this->requires_credits;
	}

	/**
	 * Set whether the integration requires credits.
	 *
	 * @param bool $requires_credits
	 *
	 * @return void
	 */
	public function set_requires_credits( $requires_credits ) {

		if ( ! is_bool( $requires_credits ) ) {
			throw new Exception( 'Requires credits must be a boolean' );
		}

		$this->requires_credits = $requires_credits;
	}

	/**
	 * Get the icon
	 *
	 * @return string
	 */
	public function get_icon() {
		return $this->icon;
	}

	/**
	 * Set the icon
	 *
	 * @param string $icon
	 *
	 * @return void
	 */
	public function set_icon( $icon ) {
		$this->icon = $icon;
	}

	/**
	 * Get the settings configuration array for the integration.
	 *
	 * @return array Settings configuration
	 */
	protected function get_settings_config() {
		return array(
			'id'               => $this->get_settings_id(),
			'integration'      => $this->get_integration(),
			'icon'             => $this->get_icon(),
			'name'             => $this->get_name(),
			'endpoint'         => $this->get_api_endpoint(),
			'is_connected'     => $this->is_app_connected(),
			'requires_credits' => $this->get_requires_credits(),
			'is_third_party'   => $this->get_is_third_party(),
		);
	}

	/**
	 * Register webhook specific hooks
	 *
	 * @return void
	 */
	protected function register_hooks() {
		// Override in child class if needed.
	}

	/**
	 * Set a dependency for this integration.
	 *
	 * @param string $key The dependency key.
	 * @param mixed  $instance The dependency instance.
	 *
	 * @return void
	 */
	protected function set_dependency( $key, $instance ) {
		if ( is_null( $this->dependencies ) ) {
			$this->dependencies = new stdClass();
		}
		$this->dependencies->$key = $instance;
	}

	/**
	 * Register custom dependencies specific to this integration.
	 * Override in child classes to add custom dependencies.
	 *
	 * @return void
	 */
	protected function register_dependencies() {
		// Override in child classes to add custom dependencies
	}

	/**
	 * Get all dependencies for this integration.
	 *
	 * @return stdClass
	 */
	public function get_dependencies() {
		return $this->dependencies;
	}

	/**
	 * Get the API instance for this integration.
	 * Override in child classes to provide custom API instances.
	 *
	 * @return Api_Caller
	 * @throws Exception If the API class doesn't exist
	 */
	protected function get_api_instance() {
		$api_class = $this->get_integration_class_name( 'Api_Caller' );
		if ( ! class_exists( $api_class ) ) {
			throw new Exception(
				sprintf(
					'API class %s does not exist for integration %s',
					esc_html( $api_class ),
					esc_html( static::class )
				)
			);
		}
		return new $api_class( $this->helpers );
	}

	/**
	 * Get the webhooks instance for this integration.
	 * Webhooks are optional - returns null if the webhooks class doesn't exist.
	 * Override in child classes to provide custom webhooks instances.
	 *
	 * @return App_Webhooks|null
	 */
	protected function get_webhooks_instance() {
		$webhooks_class = $this->get_integration_class_name( 'Webhooks' );
		return class_exists( $webhooks_class )
			? new $webhooks_class( $this->helpers, $this->is_app_connected() )
			: null;
	}

	/**
	 * Get the class name for an integration component (API, Webhooks, etc.).
	 * Override in child classes if needed.
	 *
	 * @param string $component The component type (e.g., 'Api_Caller', 'Webhooks').
	 * @return string
	 */
	protected function get_integration_class_name( $component ) {
		$reflection = new ReflectionClass( get_class( $this ) );

		// Replace '_Integration' with the component name to get the component class.
		// Examples:
		// - Github_Integration becomes Github_Api_Caller.
		// - Github_Pro_Integration becomes Github_Pro_Api_Caller.
		$class_name = str_replace(
			'_Integration',
			'_' . $component,
			$reflection->getShortName()
		);

		return $reflection->getNamespaceName() . '\\' . $class_name;
	}
}

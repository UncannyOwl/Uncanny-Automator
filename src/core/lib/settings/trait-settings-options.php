<?php
namespace Uncanny_Automator\Settings;

/**
 * Trait for managing registered options in settings pages
 * Provides a standardized way to register, validate, and store settings page options
 * with built-in sanitization and validation w/o using the Settings API.
 *
 * @package Uncanny_Automator\Settings
 */
trait Settings_Options {

	/**
	 * Defines the IDs of the options used in this settings page
	 * Each option can have additional properties for sanitization
	 *
	 * @var Array {
	 *     @type array $option_id {
	 *         @type string $type     Field type (text, email, etc)
	 *         @type string $default  Default value
	 *         @type callable $sanitize_callback Custom sanitization callback
	 *     }
	 * }
	 */
	protected $options = array();

	/**
	 * Returns the registered options
	 *
	 * @return Array The options
	 */
	public function get_options() {
		return $this->options;
	}

	/**
	 * Register the options.
	 * Override this method in the extending class to register specific options.
	 *
	 * @return void
	 */
	public function register_options() {}

	/**
	 * Registers an option with optional sanitization settings
	 *
	 * @param string $option_name The WordPress option name
	 * @param array $args {
	 *     Optional. Option arguments.
	 *     @type string $type     Field type (text, email, etc)
	 *     @type string $default  Default value
	 *     @type callable $sanitize_callback Custom sanitization callback
	 *     @type bool   $autoload Whether to autoload this option. Default true.
	 * }
	 */
	public function register_option( $option_name, $args = array() ) {
		// Check if this setting wasn't added already
		if ( ! isset( $this->options[ $option_name ] ) ) {
			$this->options[ $option_name ] = wp_parse_args(
				$args,
				array(
					'type'              => 'text',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
					'autoload'          => true,
				)
			);
		}
	}

	/**
	 * Validate registered options from POST data
	 *
	 * @param mixed $data - array of keyed data or null if using POST data
	 *
	 * @return array The validated options
	 * @throws Exception If validation fails
	 */
	protected function validate_registered_options( $data = null ) {
		if ( empty( $this->options ) ) {
			return array();
		}

		$options = array();

		// Process all registered options
		foreach ( $this->options as $option_name => $settings ) {
			$options[ $option_name ] = ! is_null( $data )
				// If data is provided, use the data
				? $this->get_data_option( $option_name, $data )
				// Otherwise, use the POST data
				: $this->get_posted_option( $option_name );
		}

		return $options;
	}

	/**
	 * Get a sanitized value from POST data for a registered option
	 *
	 * @param string $option_name The option name to get
	 * @param mixed $default_value The default value to return if the option is not set
	 *
	 * @return mixed The sanitized value or the default value if not set
	 * @throws Exception If the sanitize callback is not callable
	 */
	protected function get_posted_option( $option_name, $default_value = '' ) {
		if ( ! isset( $this->options[ $option_name ] ) ) {
			return $default_value;
		}

		$value = automator_filter_has_var( $option_name, INPUT_POST )
			? automator_filter_input( $option_name, INPUT_POST )
			: $this->options[ $option_name ]['default'];

		return $this->sanitize_option_value( $option_name, $value );
	}

	/**
	 * Get a sanitized value from passed data for a registered option
	 *
	 * @param string $option_name The option name to get

	 * @param mixed $default_value The default value to return if the option is not set
	 *
	 * @return mixed The sanitized value or the default value if not set
	 * @throws Exception If the sanitize callback is not callable
	 */
	protected function get_data_option( $option_name, $data, $default_value = '' ) {
		if ( ! isset( $this->options[ $option_name ] ) ) {
			return $default_value;
		}

		$value = isset( $data[ $option_name ] )
			? $data[ $option_name ]
			: $this->options[ $option_name ]['default'];

		return $this->sanitize_option_value( $option_name, $value );
	}

	/**
	 * Sanitize an option value using the registered sanitize callback
	 *
	 * @param string $option_name The option name
	 * @param mixed $value The value to sanitize
	 *
	 * @return mixed The sanitized value
	 * @throws Exception If the sanitize callback is not callable
	 */
	protected function sanitize_option_value( $option_name, $value ) {
		$sanitize_callback = $this->options[ $option_name ]['sanitize_callback'];

		if ( ! is_callable( $sanitize_callback ) ) {
			throw new Exception(
				sprintf(
					// translators: %s is the option name
					esc_html_x( 'Invalid sanitize callback for option "%s"', 'Integration settings', 'uncanny-automator' ),
					esc_html( $option_name )
				)
			);
		}

		return call_user_func( $sanitize_callback, $value );
	}

	/**
	 * Store registered options in the database
	 *
	 * @param array $options The validated options to store
	 * @param bool  $autoload Optional. Whether to autoload the options. Defaults to the option's registered setting.
	 */
	protected function store_registered_options( $options, $autoload = null ) {
		foreach ( $options as $option_name => $value ) {
			// Use the option's registered autoload setting if not explicitly provided
			$should_autoload = null !== $autoload
				? $autoload
				: $this->options[ $option_name ]['autoload'];

			automator_update_option( $option_name, $value, $should_autoload );
		}
	}
}

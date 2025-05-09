<?php

namespace Uncanny_Automator\Services\Plugin;

use WP_Error;
use Exception;
use Plugin_Upgrader;
use Automatic_Upgrader_Skin;

/**
 * Manager
 *
 * @package Uncanny_Automator\Services\Plugin
 */
class Manager {

	/**
	 * Manager translations. This trait handles the translations.
	 */
	use Manager_Translations;

	/**
	 * Manager verification. This trait handles validation and errors.
	 */
	use Manager_Verification;

	/**
	 * URL to the plugin ZIP.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * The name of the plugin.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The plugin file path relative to plugins directory.
	 *
	 * @var string|false
	 */
	private $plugin;

	/**
	 * Activate the plugin after operation.
	 *
	 * @var bool
	 */
	private $activate;

	/**
	 * Is plugin activated
	 */
	private $activated = false;

	/**
	 * Current operation
	 *
	 * @param string
	 */
	private $operation;

	/**
	 * Upgrader instance.
	 *
	 * @var Plugin_Upgrader
	 */
	private $upgrader = null;

	/**
	 * Nonce.
	 *
	 * @var string
	 */
	private $nonce;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	final public function __construct() {
	}

	/**
	 * Install plugin
	 *
	 * @param mixed array|string $args - URL to the plugin ZIP or array of args.
	 * @param string $nonce - Nonce for secure actions.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function install( $args, $nonce ) {
		// Set class properties
		$this->operation = 'install';
		$args            = is_string( $args ) ? array( 'url' => $args ) : $args;
		$this->initialize( $args, $nonce );

		// Process
		return $this->process( $this->operation );
	}

	/**
	* Update plugin
	*
	* @param mixed array|string $args - Path to the plugin file relative to the plugins directory or array of args.
	* @param string $nonce - Nonce for secure actions.
	*
	* @return array
	* @throws Exception
	*/
	public function update( $args, $nonce ) {
		// Set up the operation.
		$this->operation = 'update';
		$args            = is_string( $args ) ? array( 'plugin' => $args ) : $args;
		$this->initialize( $args, $nonce );
		// Process
		return $this->process( $this->operation );
	}

	/**
	 * Remote update
	 *
	 * @param mixed array|string $args - URL to the plugin ZIP or array of args.
	 * @param string $nonce - Nonce for secure actions.
	 *
	 * @return array
	 */
	public function remote_update( $args, $nonce ) {
		// Set up the operation.
		$this->operation = 'remote_update';

		// Add our package options filter
		add_filter(
			'upgrader_package_options',
			array(
				$this,
				'remote_update_upgrader_package_options',
			)
		);

		$args = is_string( $args ) ? array( 'url' => $args ) : $args;
		$this->initialize( $args, $nonce );
		return $this->process( $this->operation );
	}

	/**
	 * Activate plugin
	 *
	 * @param mixed array|string $args - Path to the plugin file relative to the plugins directory or array of args.
	 * @param string $nonce - Nonce for secure actions.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function activate( $args, $nonce ) {
		// Set up the operation.
		$this->operation = 'activate';
		$args            = is_string( $args ) ? array( 'plugin' => $args ) : $args;
		$this->initialize( $args, $nonce );

		// Process
		return $this->process( $this->operation );
	}

	/**
	 * Process valid operations
	 *
	 * @param string $action - name of the operation
	 *
	 * @return array - The success response of the operation.
	 * @throws Exception
	 */
	private function process( $action ) {
		try {

			// Verify nonce, permissions and request params.
			$this->verify_request( $this->nonce );

			switch ( $action ) {
				// Install plugin.
				case 'install':
					$result = $this->upgrader->install( $this->url );
					break;
				// Remote update plugin ( uses install with overwrite package ).
				case 'remote_update':
					// Process the install upgrade.
					$result = $this->upgrader->install( $this->url );
					// Remove the filter.
					remove_filter(
						'upgrader_package_options',
						array(
							$this,
							'remote_update_upgrader_package_options',
						)
					);
					break;
				// Update plugin.
				case 'update':
					$result = $this->upgrader->upgrade(
						$this->plugin,
						array( 'clear_update_cache' => true )
					);
					break;
				// Activate plugin
				case 'activate':
					$result = activate_plugin( $this->plugin );
					break;
			}

			// Check for errors.
			$this->verify_results( $result );

			// If the action is activate, we can stop here.
			if ( 'activate' === $action ) {
				$this->activated = true;
				return $this->handle_success_response();
			}

			// Set the plugin file from the upgrader.
			$this->plugin = $this->upgrader->plugin_info();

			// Set if the plugin is activated.
			$this->activated = is_plugin_active( $this->plugin );

			// Maybe activate.
			$this->maybe_activate();

			// All good.
			return $this->handle_success_response();

		} catch ( Exception $e ) {

			$message = $this->get_error_message(
				$this->action_i18n['error']['failed'],
				$e->getCode(),
				$e->getMessage()
			);

			throw new Exception( esc_html( $message ), $e->getCode() );
		}
	}

	/**
	 * Maybe Activate the plugin.
	 *
	 * @return void
	 * @throws Exception
	 */
	private function maybe_activate() {
		// No need to activate the plugin ( not requested or already activated ).
		if ( ! $this->activate || $this->activated ) {
			return;
		}

		// Activate the plugin ( null|WP_Error Null on success, WP_Error on invalid file ).
		$result          = activate_plugin( $this->plugin );
		$this->activated = is_null( $result );

		if ( ! $this->activated ) {
			// Throw WP_Error.
			$this->verify_results( $result );
		}
	}

	/**
	 * Handle success response
	 *
	 * @param array $args - Optional args for data response.
	 * @property bool success  - Whether the operation succeeded.
	 * @property bool activated - Whether the plugin was activated.
	 * @property string message - Operation success message.
	 *
	 * @return array Redirect response structure
	 */
	private function handle_success_response( $args = array() ) {

		$data = array(
			'success' => true,
			'data'    => array(
				'activated'    => $this->activated,
				'message'      => $this->get_success_message(),
				'redirect_url' => $this->maybe_get_search_redirect_url(),
			),
		);
		return wp_parse_args( $args, $data );
	}

	/**
	 * Maybe get the search redirect URL.
	 *
	 * @return string
	 */
	private function maybe_get_search_redirect_url() {

		// Check if the plugin is not activated and if it was supposed to be activated.
		if ( ! $this->activated && $this->activate ) {

			// Check if the name isn't already set and if the upgrader was set.
			if ( empty( $this->name ) && ! is_null( $this->upgrader ) ) {
				// Get the plugin name from the upgrader.
				$data       = $this->upgrader->new_plugin_data;
				$this->name = $data['name'] ?? '';
			}

			// Check if the name is now set and get the search URL.
			if ( ! empty( $this->name ) ) {
				return Info::get_plugin_search_url( $this->name );
			}
		}

		return '';
	}

	/**
	 * Load required WordPress dependencies
	 *
	 * @return void
	 */
	private function load_dependencies() {
		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();
	}

	/**
	 * Set class properties.
	 *
	 * @param array $args
	 * @param string $nonce
	 *
	 * @return void
	 */
	private function initialize( $args, $nonce ) {

		// Parse defaults.
		$args = wp_parse_args(
			$args,
			array(
				'url'      => '',
				'plugin'   => '',
				'name'     => '',
				'activate' => true,
			)
		);

		// Set the properties.
		$this->nonce    = $nonce;
		$this->url      = $args['url'];
		$this->plugin   = sanitize_text_field( $args['plugin'] );
		$this->name     = sanitize_text_field( $args['name'] );
		$this->activate = filter_var(
			strtolower( $args['activate'] ),
			FILTER_VALIDATE_BOOLEAN
		);

		// Translations.
		$this->set_translations();

		if ( 'activate' !== $this->operation ) {
			// Load the dependencies.
			$this->load_dependencies();
			// Instantiate the upgrader with an automatic skin (suppressing output).
			$this->upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		}
	}

	/**
	 * Remote update upgrader package options filter.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function remote_update_upgrader_package_options( $options ) {
		$options['clear_update_cache']          = true;
		$options['overwrite_package']           = true;
		$options['clear_destination']           = true;
		$options['abort_if_destination_exists'] = false;
		return $options;
	}
}

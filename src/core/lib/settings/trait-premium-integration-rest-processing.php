<?php
namespace Uncanny_Automator\Settings;

use Exception;

/**
 * Trait for common REST processing functionality
 * Extracted from App_Integration_Settings to allow reuse in Pro integrations
 *
 * @package Uncanny_Automator\Settings
 */
trait Premium_Integration_Rest_Processing {

	/**
	 * Process a REST request
	 *
	 * @param WP_REST_Request $request
	 * @param Action_Manager $manager
	 *
	 * @return array
	 * @throws Exception if any errors encountered
	 */
	public function process_rest_request( $request, $manager ) {
		// Revalidate the request in case it's called directly.
		if ( ! $manager->check_rest_permissions( $request ) ) {
			throw new Exception( 'Forbidden' );
		}

		// Get the action
		$action = $request->get_param( 'action' );
		$data   = $request->get_param( 'data' )
			? $request->get_param( 'data' )
			: array();

		// Common actions handled by framework
		switch ( $action ) {
			case 'authorize':
				return $this->handle_authorize( $data );
			case 'disconnect':
				return $this->handle_disconnect( $data );
			case 'save_settings':
				return $this->handle_save_settings( $data );
			case 'oauth_init':
				$manager->validate_action_exists( $this, 'handle_oauth_init' );
				return $this->handle_oauth_init( $data, $manager );
			default:
				// Dynamic action handling
				$method_name = 'handle_' . sanitize_key( $action );
				$manager->validate_action_exists( $this, $method_name );
				$response = array(
					'success' => true,
					'reload'  => false,
				);
				return call_user_func_array( array( $this, $method_name ), array( $response, $data ) );
		}
	}

	/**
	 * Format array data
	 *
	 * @param string $key - The array key to format
	 * @param array $data - The REST request data
	 *
	 * @return array
	 */
	protected function get_array_data( $key, $data ) {
		$results = array();
		$pattern = '/^' . preg_quote( $key, '/' ) . '\\[([^\\]]+)\\]$/';
		foreach ( $data as $i => $value ) {
			if ( preg_match( $pattern, $i, $matches ) ) {
				$results[ $matches[1] ] = $value;
			}
		}
		return $results;
	}

	/**
	 * Handle authorization action.
	 *
	 * @param array $data Optional data passed from REST request
	 *
	 * @return array - formatted response for the REST request.
	 * @throws Exception If authorization fails
	 */
	private function handle_authorize( $data = array() ) {
		// Set default response
		$response = array(
			'success' => true,
			'reload'  => true,
		);

		// Allow integration to perform pre-authorization actions and modify response
		$response = $this->before_authorization( $response, $data );

		// For API key integrations, validate and save registered options.
		$options = $this->validate_registered_options( $data );
		$this->store_registered_options( $options );

		// Check if authorize_account method exists
		if ( method_exists( $this, 'authorize_account' ) ) {
			$response = $this->authorize_account( $response, $options );
		}

		// Allow integration to perform any additional setup after authorization and modify response
		$response = $this->after_authorization( $response, $data );

		return $response;
	}

	/**
	 * Called before successful authorization
	 * Override this method in the integration class to perform additional setup
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If pre-authorization setup fails
	 */
	protected function before_authorization( $response = array(), $data = array() ) {
		// Default implementation does nothing
		return $response;
	}

	/**
	 * Called after successful authorization
	 * Override this method in the integration class to perform additional setup
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If post-authorization setup fails
	 */
	protected function after_authorization( $response = array(), $data = array() ) {
		// Default implementation does nothing
		return $response;
	}

	/**
	 * Handle disconnect action.
	 *
	 * @param array $data Optional data passed from REST request
	 *
	 * @return array - formatted response for the REST request.
	 * @throws Exception If disconnect fails
	 */
	private function handle_disconnect( $data = array() ) {
		// Set default response
		$response = array(
			'success' => true,
			'reload'  => true,
		);

		// Allow integration to perform any cleanup before default options are cleared and modify response
		$response = $this->before_disconnect( $response, $data );

		/**
		 * Filter to allow integrations to perform additional cleanup before default options are cleared
		 *
		 * @param array $response The current response array
		 * @param array $data The posted data
		 * @param object $this The integration settings object
		 *
		 * @return array Modified response array
		 *
		 * @example
		 *
		 * ```php
		 * add_filter( 'automator_before_disconnect_github', function( $response, $data, $settings_object ) {
		 *     // Register additional options that should be deleted
		 *     $settings_object->register_option( 'github_webhook_manager' );
		 *     return $response;
		 * }, 10, 3 );
		 * ```
		 */
		$response = apply_filters( 'automator_before_disconnect_' . $this->get_id(), $response, $data, $this );

		// Get all possible options that could have been registered
		$all_options = $this->get_all_registered_options();
		if ( ! empty( $all_options ) ) {
			// Clear all registered options
			foreach ( $all_options as $option_name => $args ) {
				automator_delete_option( $option_name );
			}
		}

		// Check if the helper class has delete_credentials method
		if ( method_exists( $this->helpers, 'delete_credentials' ) ) {
			$this->helpers->delete_credentials();
		}

		// Delete account info using helper method
		if ( method_exists( $this->helpers, 'delete_account_info' ) ) {
			$this->helpers->delete_account_info();
		}

		// Allow integration to perform any additional cleanup and modify response
		$response = $this->after_disconnect( $response, $data );

		/**
		 * Filter to allow integrations to perform additional cleanup after default options are cleared
		 *
		 * @param array $response The current response array
		 * @param array $data The posted data
		 * @param object $this The integration settings object
		 *
		 * @return array Modified response array
		 *
		 * @example
		 *
		 * ```php
		 * add_filter( 'automator_after_disconnect_github', function( $response, $data, $settings_object ) {
		 *     // Perform additional cleanup
		 *     automator_delete_option( 'github_webhook_manager' );
		 *     return $response;
		 * }, 10, 3 );
		 */
		return apply_filters( 'automator_after_disconnect_' . $this->get_id(), $response, $data, $this );
	}

	/**
	 * Called before options are cleared during disconnect
	 * Override this method in the integration class to perform cleanup
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If cleanup fails
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		// Default implementation returns response unchanged
		return $response;
	}

	/**
	 * Called after options are cleared during disconnect
	 * Override this method in the integration class to perform additional cleanup
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If cleanup fails
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		// Default implementation returns response unchanged
		return $response;
	}

	/**
	 * Handle save settings action.
	 *
	 * This method provides a way to save additional settings outside of the main connection flow.
	 * It can be used to:
	 * - Save settings that are only available after connection
	 * - Update configuration that can be changed at any time
	 * - Handle settings that are independent of the connection method
	 *
	 * To use this:
	 * 1. Register your options using register_option() in your integration class
	 * 2. Override before_save_settings() or after_save_settings() if needed
	 * 3. Call this method from your integration's form submission handler
	 *
	 * @param array $data Optional data passed from REST request
	 * @throws Exception If save settings fails
	 */
	private function handle_save_settings( $data = array() ) {
		// Set default response
		$response = array(
			'success' => true,
			'reload'  => false,
		);

		// Allow integration to perform validation and setup before saving settings
		$response = $this->before_save_settings( $response, $data );

		// Only process options that are available in the current context
		$options = $this->validate_registered_options( $data );
		$this->store_registered_options( $options );

		// Allow integration to perform additional setup and modify response after saving settings
		$response = $this->after_save_settings( $response, $options );

		return $response;
	}

	/**
	 * Called before saving settings
	 * Override this method in the integration class to perform validation and setup
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If pre-save validation fails
	 */
	protected function before_save_settings( $response = array(), $data = array() ) {
		// Default implementation returns response unchanged
		return $response;
	}

	/**
	 * Called after saving settings
	 * Override this method in the integration class to perform additional setup
	 *
	 * @param array $response The current response array
	 * @param array $options The saved options
	 *
	 * @return array Modified response array
	 * @throws Exception If post-save setup fails
	 */
	protected function after_save_settings( $response = array(), $options = array() ) {
		// Default implementation returns response unchanged
		return $response;
	}
}

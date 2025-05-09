<?php

namespace Uncanny_Automator\Services\Addons\Plugins;

use Uncanny_Automator\Addons as Addons_Admin;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Services\Plugin\Manager as Plugin_Manager;
use Uncanny_Automator\Services\Addons\Data\Plan_Resolver;
use Uncanny_Automator\Services\Addons\Lists\Plan_List;
use Uncanny_Automator\Services\Addons\Data\Calls_To_Action;

use Exception;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Manager
 *
 * @package Uncanny_Automator\Services\Addons\Plugins
 */
class Manager {

	/**
	 * Error call to action.
	 *
	 * @var array
	 */
	private $error_call_to_action = array();

	/**
	 * Whitelisted actions.
	 *
	 * @var array
	 */
	private $valid_actions = array(
		'update',
		'install',
		'activate',
		'download',
	);

	/**
	 * Connected user.
	 *
	 * @var array
	 */
	private $connected_user;

	/**
	 * Initialize the class.
	 *
	 * @return void
	 */
	public function __construct() {
	}

	/**
	 * Handle the rest request.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public function handle_rest_request( WP_REST_Request $request ) {

		// Reset the error call to action.
		$this->error_call_to_action = array();

		try {

			$action   = $request->get_param( 'action' );
			$addon_id = absint( $request->get_param( 'addon_id' ) );

			// Validate the action.
			if ( ! in_array( $action, $this->valid_actions, true ) ) {
				// Set error and throw exception.
				$this->error_call_to_action = Calls_To_Action::get_error_refresh();
				throw new Exception( esc_html_x( 'Invalid action requested', 'Addons', 'uncanny-automator' ), 400 );
			}

			// Validate the addon details.
			$addon = empty( $addon_id ) ? null : Addons_Admin::get_addon_details( $addon_id );
			if ( empty( $addon ) ) {
				// Set error and throw exception.
				$this->error_call_to_action = Calls_To_Action::get_error_refresh();
				throw new Exception( esc_html_x( 'Invalid addon requested', 'Addons', 'uncanny-automator' ), 400 );
			}

			// Handle the activate action.
			if ( 'activate' === $action ) {
				return $this->handle_rest_response(
					$this->activate_addon( $addon ),
					$addon
				);
			}

			// Validate the license
			$this->validate_license_access( $addon );

			// Validate the addon access
			$this->validate_addon_access( $addon );

			if ( 'download' === $action ) {
				return $this->handle_rest_response(
					$this->download_addon( $addon ),
					$addon
				);
			}

			// Process the addon.
			$results = 'update' === $action
				? $this->process_addon( $addon, 'remote_update' )
				: $this->process_addon( $addon, 'install' );

			return $this->handle_rest_response( $results, $addon );

		} catch ( Exception $e ) {

			$message = $e->getMessage();

			// Any known errors at this point should have already been handled.
			if ( empty( $this->error_call_to_action ) ) {
				// Send them to my account.
				$message = esc_html_x( 'There was an error processing your request. Please visit your account page to download the addon manually.', 'Addons', 'uncanny-automator' );
				$this->error_call_to_action = Calls_To_Action::get_my_account_redirect();
			}

			return $this->handle_rest_response(
				new WP_Error( $e->getCode(), $message ),
				$addon
			);
		}
	}

	/**
	 * Validate the license.
	 *
	 * @param array $addon The addon details.
	 *
	 * @return void
	 * @throws Exception - with code 422
	 */
	public function validate_license_access( $addon ) {

		// Set the connected user property.
		$this->set_connected_user();

		$license_key = $this->get_license_key();

		// Check license is valid.
		if ( 'valid' !== $this->connected_user['license'] ) {
			$this->error_call_to_action = Calls_To_Action::get_fix_license();
			throw new Exception( esc_html_x( 'License invalid', 'Addons', 'uncanny-automator' ), 422 );
		}
	}

	/**
	 * Validate the addon access.
	 *
	 * @param array $addon The addon details.
	 *
	 * @return void
	 * @throws Exception - with code 422
	 */
	public function validate_addon_access( $addon ) {
		if ( true === (bool) $addon['is_elite_specific'] ) {
			if ( ! $this->plan_resolver()->has_access_to_plan( 'elite' ) ) {
				$this->error_call_to_action = Calls_To_Action::get_upgrade_plan( 'error' );
				throw new Exception( esc_html_x( 'Please upgrade to the Elite plan to access this addon.', 'Addons', 'uncanny-automator' ), 422 );
			}
			return;
		}

		if ( ! $this->plan_resolver()->has_access_to_plan( 'plus' ) ) {
			$this->error_call_to_action = Calls_To_Action::get_upgrade_plan( 'error' );
			throw new Exception( esc_html_x( 'Please upgrade to the Plus plan to access this addon.', 'Addons', 'uncanny-automator' ), 422 );
		}
	}

	/**
	 * Get the license key.
	 *
	 * @return string The license key.
	 */
	public function get_license_key() {
		if ( empty( $this->connected_user ) ) {
			$this->set_connected_user();
		}
		return $this->connected_user['license_key'];
	}

	/**
	 * Set connected user.
	 *
	 * @return void
	 * @throws Exception - with code 422
	 */
	private function set_connected_user() {
		$this->connected_user = Api_Server::is_automator_connected();
		if ( empty( $this->connected_user ) ) {
			$this->error_call_to_action = Calls_To_Action::get_fix_license();
			throw new Exception( esc_html_x( 'License invalid', 'Addons', 'uncanny-automator' ), 422 );
		}
	}

	/**
	 * Activate the addon.
	 *
	 * @param array $addon The addon details.
	 *
	 * @return mixed array|WP_Error - The results / valid redirects
	 */
	private function activate_addon( $addon ) {
		try {
			return $this->manager()->activate(
				$addon['plugin_file'],
				wp_create_nonce( 'Aut0Mat0RPlug1nM@nag5r' ),
				array(
					'name' => $addon['name'],
				)
			);
		} catch ( Exception $e ) {
			// If failed to activate, return a CTA with a redirect to the plugins page.
			$this->error_call_to_action = Calls_To_Action::get_redirect_to_plugins_page( '', 'error', '', $addon['name'] );
			return new WP_Error( $e->getCode(), esc_html_x( 'Activation failed. Please try to activate manually.', 'Addons', 'uncanny-automator' ) );
		}
	}

	/**
	 * Download the addon.
	 *
	 * @param array $addon The addon details.
	 *
	 * @return mixed array|WP_Error - The results / valid redirects
	 */
	private function download_addon( $addon ) {
		try {
			// Get the download URL.
			$url = ( new EDD_Zip_URL( $addon, $this ) )->get_download_url();
			return array(
				'success'         => true,
				'direct_download' => $url,
			);
		} catch ( EDD_Zip_URL_Exception $e ) {
			return $this->handle_exception_error( $e->getCode() );
		}
	}

	/**
	 * Process addon installation or update.
	 *
	 * @param array $addon The addon details.
	 * @param string $operation Either 'install' or 'remote_update'
	 *
	 * @return mixed array|WP_Error - The results / valid redirects
	 */
	private function process_addon( $addon, $operation ) {
		$url = null;
		try {
			// Get the download URL.
			$url = ( new EDD_Zip_URL( $addon, $this ) )->get_download_url();
			return $this->manager()->$operation(
				$url,
				wp_create_nonce( 'Aut0Mat0RPlug1nM@nag5r' )
			);
			// If we get an EDD_Zip_URL_Exception we don't have a valid URL.
		} catch ( EDD_Zip_URL_Exception $e ) {
			// Return appropriate error message.
			return $this->handle_exception_error( $e->getCode() );
		} catch ( Exception $e ) {
			// Check if URL is valid return for direct download.
			if ( wp_http_validate_url( $url ) ) {
				return $this->handle_direct_download_error( $url, $operation, $e->getCode() );
			}
			// Return appropriate error message.
			return $this->handle_exception_error( $e->getCode() );
		}
	}

	/**
	 * Handle exception errors and generate appropriate responses.
	 *
	 * @param int $code The error code
	 *
	 * @return WP_Error
	 */
	private function handle_exception_error( $code ) {
		$error = esc_html_x( 'There was an error processing your request.', 'Addons', 'uncanny-automator' );

		// Handle refresh scenarios.
		if ( in_array( $code, array( 400, 401 ), true ) ) {
			$error .= ' ' . esc_html_x( 'Please refresh the page and try again.', 'Addons', 'uncanny-automator' );
			$this->error_call_to_action = Calls_To_Action::get_error_refresh();
			return new WP_Error( $code, $error );
		} 

		// Handle manual download scenarios.
		$error .= ' ' . esc_html_x( 'Please visit your account page to download the addon manually.', 'Addons', 'uncanny-automator' );
		$this->error_call_to_action = Calls_To_Action::get_my_account_redirect( 'error' );

		return new WP_Error( $code, $error );
	}

	/**
	 * Handle the direct download error.
	 * 
	 * We have a valid URL but failed to install or update the addon.
	 * We return the error message and a CTA to download the addon directly.
	 *
	 * @param string $url The URL to download.
	 * @param string $operation The operation being performed.
	 *
	 * @return WP_Error - Normalized error message.
	 */
	private function handle_direct_download_error( $url, $operation, $code ) {
		// Set the direct download CTA.
		$this->error_call_to_action = Calls_To_Action::get_direct_download_addon( $url, 'error' );
		// Get the verb for the operation being performed.
		$verb = 'installing' === $operation 
			? esc_html_x( 'installing', 'Addons', 'uncanny-automator' )
			: esc_html_x( 'updating', 'Addons', 'uncanny-automator' );
		// Return the error message.
		$error = sprintf( 
			// translators: %s: The verb for the operation being performed.
			esc_html_x( 'There was an error %s the addon. Please download and upload directly on the plugins page.', 'Addons', 'uncanny-automator' ),
			$verb
		);
		return new WP_Error( $code, $error );
	}

	/**
	 * Handle the rest response.
	 *
	 * @param mixed array|WP_Error
	 *
	 * @return WP_REST_Response The response object.
	 */
	private function handle_rest_response( $results, $addon ) {
		
		if ( is_wp_error( $results ) ) {
			$data = array(
				'success'           => false,
				'user_intervention' => $this->get_user_intervention( $results->get_error_message() ),
				'data'              => array(
					'addon' => ( new Plan_List() )->get_single_addon_list_item( $addon ),
				)
			);
			return new WP_REST_Response( $data, 200 );
		}

		// Add the settings URL if the addon was activated.
		$results['data'] = isset( $results['data'] ) ? $results['data'] : array();
		$activated       = $results['data']['activated'] ?? false;
		if ( $activated ) {
			$results['data']['redirect_url'] = Addons_Admin::get_addon_settings_url( $addon );
		}

		// Add any updated addon data to the response ( new CTAs )
		$results['data']['addon'] = ( new Plan_List() )->get_single_addon_list_item( $addon );

		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * Get the user intervention.
	 *
	 * @param string $message The error message.
	 *
	 * @return array The user intervention.
	 */
	private function get_user_intervention( $message ) {

		// Set the user intervention message.
		$user_intervention = array(
			'message' => $message,
		);

		// Set the user intervention CTA if it exists.
		if ( ! empty( $this->error_call_to_action ) ) {
			$user_intervention['cta'] = $this->error_call_to_action;
			$this->error_call_to_action = array();
		}

		return $user_intervention;
	}

	/**
	 * Get the plan resolver.
	 *
	 * @return Plan_Resolver The plan resolver.
	 */
	private function plan_resolver() {
		static $plan_resolver = null;
		if ( null === $plan_resolver ) {
			$plan_resolver = new Plan_Resolver();
		}
		return $plan_resolver;
	}

	/**
	 * Get the plugin manager.
	 *
	 * @return Plugin_Manager The plugin manager.
	 */
	private function manager() {
		static $manager = null;
		if ( null === $manager ) {
			$manager = new Plugin_Manager();
		}
		return $manager;
	}
}

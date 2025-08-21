<?php
/**
 * Pro Auto Install.
 *
 * Handles license activation, download, installation, and activation
 * of Automator Pro via EDD's Software Licensing API.
 *
 * @package Uncanny_Automator\Services\Admin_Post\Routes
 * @since   6.3
 */

namespace Uncanny_Automator\Services\Admin_Post\Routes;

use Exception;
use Plugin_Upgrader;

/**
 * Class Pro_Auto_Install
 *
 * Handles the complete workflow for automatically installing and activating
 * Automator Pro via license key validation and EDD Software Licensing API.
 */
class Pro_Auto_Install {

	const PRO_PLUGIN_ID             = 506;
	const ALLOWED_TIME_LIMIT        = 30;
	const PRO_PLUGIN_STORE_URL      = 'https://automatorplugin.com/';
	const PRO_PLUGIN_PATH           = 'uncanny-automator-pro/uncanny-automator-pro.php';
	const ADMIN_POST_ACTION         = 'admin_post_uncanny_automator_pro_auto_install';
	const LICENSE_ACTIVATION_FAILED = 'license_activation_failed';
	const DOWNLOAD_LINK_NOT_FOUND   = 'download_link_not_found';
	const PERMISSIONS_INSUFFICIENT  = 'permissions_insufficient';
	const DOWNLOAD_LINK_NOT_VALID   = 'invalid_download_link';
	const LICENSE_KEY_REQUIRED      = 'license_key_required';
	const ACTIVATION_FAILED         = 'activation_failed';
	const INSTALL_FAILED            = 'install_failed';

	/**
	 * Plugin upgrader instance.
	 *
	 * @var Plugin_Upgrader
	 */
	private $upgrader;

	/**
	 * Constructor to inject dependencies.
	 *
	 * @param Plugin_Upgrader $upgrader Plugin upgrader instance.
	 */
	public function __construct( Plugin_Upgrader $upgrader ) {
		$this->upgrader = $upgrader;
	}


	/**
	 * Process the complete installation workflow.
	 *
	 * @return void
	 */
	public function process_installation() {

		$this->disable_translation_auto_updates();
		$this->validate_admin_action();
		$this->validate_security();

		$license_key = trim( automator_filter_input( 'automator_pro_license', INPUT_POST ) );

		$this->validate_license_key( $license_key );
		$this->configure_environment();

		try {
			$download_url = $this->generate_download_link( $license_key );
			$this->install_and_activate( $download_url );
			$this->enable_translation_auto_updates();
			wp_safe_redirect( $this->get_license_settings_url( '', 'success' ) );
			die();
		} catch ( Exception $e ) {
			$this->enable_translation_auto_updates();
			wp_safe_redirect( $this->get_license_settings_url( esc_html( $e->getMessage() ) ) );
			die();
		}
	}

	/**
	 * Disable translation auto-updates.
	 *
	 * @return void
	 */
	private function disable_translation_auto_updates() {
		add_filter( 'auto_update_translation', '__return_false' );
	}

	/**
	 * Enable translation auto-updates.
	 *
	 * @return void
	 */
	private function enable_translation_auto_updates() {
		remove_filter( 'auto_update_translation', '__return_false' );
	}

	/**
	 * Get the license settings URL with optional error parameter.
	 *
	 * @param string $error Optional error message to include in URL.
	 *
	 * @return string License settings URL.
	 */
	public function get_license_settings_url( $error = '', $status = '' ) {
		$args = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-config',
		);

		if ( ! empty( $error ) ) {
			$args['error_message'] = rawurlencode( $error );
		}

		if ( ! empty( $status ) ) {
			$args['status'] = $status;
		}

		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	/**
	 * Validate that the current admin action matches expected action.
	 *
	 * @throws Exception If the current action is invalid.
	 *
	 * @return void
	 */
	private function validate_admin_action() {
		if ( self::ADMIN_POST_ACTION !== current_action() ) {
			throw new Exception( esc_html( 'Invalid request.' ) );
		}
	}

	/**
	 * Generate download link by activating license and retrieving download URL.
	 *
	 * @param string $license_key The license key to activate.
	 *
	 * @throws Exception If license activation or download link retrieval fails.
	 *
	 * @return string Download URL for the plugin ZIP file.
	 */
	private function generate_download_link( $license_key ) {
		$this->activate_license_request( $license_key );

		return $this->retrieve_download_link( $license_key );
	}

	/**
	 * Send license activation request to EDD and validate response.
	 *
	 * @param string $license_key The license key to activate.
	 *
	 * @throws Exception If activation request fails or license is invalid.
	 *
	 * @return void
	 */
	private function activate_license_request( $license_key ) {
		$args = array(
			'edd_action' => 'activate_license',
			'license'    => $license_key,
			'item_id'    => self::PRO_PLUGIN_ID,
			'url'        => home_url(),
		);

		$response = wp_remote_get(
			add_query_arg( $args, self::PRO_PLUGIN_STORE_URL ),
			array(
				'timeout' => self::ALLOWED_TIME_LIMIT,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html( $response->get_error_message() ) );
		}

		$data = (array) json_decode(
			wp_remote_retrieve_body( $response ),
			true
		);

		if ( empty( $data['success'] ) ) {
			throw new Exception( esc_html( self::LICENSE_ACTIVATION_FAILED ) );
		}
	}

	/**
	 * Retrieve single-use download link via EDD get_version action.
	 *
	 * @param string $license_key The activated license key.
	 *
	 * @throws Exception If download link retrieval fails or URL is invalid.
	 *
	 * @return string Validated download URL for the plugin ZIP file.
	 */
	private function retrieve_download_link( $license_key ) {
		$args = array(
			'edd_action' => 'get_version',
			'license'    => $license_key,
			'item_id'    => self::PRO_PLUGIN_ID,
			'url'        => home_url(),
		);

		$response = wp_remote_get(
			add_query_arg( $args, self::PRO_PLUGIN_STORE_URL ),
			array(
				'timeout' => self::ALLOWED_TIME_LIMIT,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html( $response->get_error_message() ) );
		}

		$data = (array) json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['download_link'] ) ) {
			throw new Exception( esc_html( self::DOWNLOAD_LINK_NOT_FOUND ) );
		}

		$parsed_url = wp_parse_url( $data['download_link'] );
		if ( empty( $parsed_url['host'] ) || 0 !== strcasecmp( $parsed_url['host'], wp_parse_url( self::PRO_PLUGIN_STORE_URL, PHP_URL_HOST ) ) ) {
			throw new Exception( esc_html( self::DOWNLOAD_LINK_NOT_VALID ) );
		}

		return $data['download_link'];
	}

	/**
	 * Perform nonce verification and capability checks.
	 *
	 * @throws Exception If security checks fail.
	 *
	 * @return void
	 */
	private function validate_security() {
		$this->validate_nonce();
		$this->validate_user_capabilities();
	}

	/**
	 * Validate nonce for admin post request.
	 *
	 * @return void
	 */
	protected function validate_nonce() {
		check_admin_referer(
			'uncanny_automator_pro_auto_install',
			'uncanny_automator_pro_auto_install'
		);
	}

	/**
	 * Validate user has required capabilities.
	 *
	 * @throws Exception If user lacks required capabilities.
	 *
	 * @return void
	 */
	private function validate_user_capabilities() {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new Exception( esc_html( self::PERMISSIONS_INSUFFICIENT ) );
		}
	}

	/**
	 * Validate license key input is not empty.
	 *
	 * @param string $license_key The license key to validate.
	 *
	 * @throws Exception If license key is empty or invalid.
	 *
	 * @return void
	 */
	private function validate_license_key( $license_key ) {
		if ( empty( $license_key ) ) {
			throw new Exception( esc_html( self::LICENSE_KEY_REQUIRED ) );
		}
	}

	/**
	 * Configure environment settings for plugin installation.
	 *
	 * Increases execution time limit to handle potentially large downloads.
	 *
	 * @return void
	 */
	private function configure_environment() {
		$time_limit = apply_filters( 'uncanny_automator_pro_auto_install_time_limit', absint( self::ALLOWED_TIME_LIMIT ) );
		$time_limit = max( 1, min( 300, $time_limit ) ); // Ensure the time limit is between 1 and 300 seconds.
		set_time_limit( $time_limit );
	}

	/**
	 * Install and activate plugin from ZIP URL.
	 *
	 * @param string $zip_url URL to the plugin ZIP file.
	 *
	 * @throws Exception If installation or activation fails.
	 *
	 * @return void
	 */
	private function install_and_activate( $zip_url ) {
		$result = $this->upgrader->install( $zip_url );

		if ( is_wp_error( $result ) ) {
			throw new Exception( esc_html( self::INSTALL_FAILED ) );
		}

		$plugin_file = self::PRO_PLUGIN_PATH;

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			throw new Exception( esc_html( self::INSTALL_FAILED ) );
		}

		$activate = activate_plugin( $plugin_file, '', false, true );

		if ( is_wp_error( $activate ) ) {
			throw new Exception( esc_html( self::ACTIVATION_FAILED ) );
		}
	}
}

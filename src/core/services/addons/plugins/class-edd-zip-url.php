<?php

namespace Uncanny_Automator\Services\Addons\Plugins;

use Exception;

/**
 * Class EDD_Zip_URL
 *
 * - Retrieves a tokenized zip URL for an addon.
 *
 * @package Uncanny_Automator\Services\Addons\Plugins
 */
class EDD_Zip_URL {

	/**
	 * The addon.
	 *
	 * @var array
	 */
	private $addon;

	/**
	 * The addon manager.
	 *
	 * @var Addons_Plugin_Manager
	 */
	private $addon_manager;

	/**
	 * Constructor.
	 *
	 * @param string $license_key The license key.
	 * @param string $addon_name The addon name.
	 *
	 * @return void
	 */
	public function __construct( $addon, $addon_manager ) {
		$this->addon         = $addon;
		$this->addon_manager = $addon_manager;
	}

	/**
	 * Get the download URL.
	 *
	 * @return string The download URL
	 * @throws EDD_Zip_URL_Exception - If validation or request fails.
	 */
	public function get_download_url() {

		// Validate request
		$this->validate_request();

		// Generate request URL
		$request_url = AUTOMATOR_STORE_URL .
			trailingslashit( 'wp-json/automator-addons/v1/addon' ) .
			implode(
				'/',
				array(
					absint( $this->addon['id'] ),
					sanitize_key( $this->addon_manager->get_license_key() ),
					sanitize_key( sanitize_title( $this->addon['name'] ) ),
				)
			);

		$response = wp_remote_get( $request_url );
		if ( is_wp_error( $response ) ) {
			throw new EDD_Zip_URL_Exception(
				sprintf(
					esc_html__( 'Request failed: %s', 'uncanny-automator' ),
					esc_html( $response->get_error_message() )
				),
				500 // Internal Server Error - failed to connect
			);
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$body    = empty( $body ) ? array() : $body;
		$body    = is_array( $body ) ? $body : array();
		$success = isset( $body['success'] ) ? $body['success'] : false;
		$url     = isset( $body['url'] ) ? $body['url'] : '';

		// Any response issues (invalid JSON, missing data, error response)
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $url ) ) {
			throw new EDD_Zip_URL_Exception(
				isset( $body['message'] )
					? esc_html( $body['message'] )
					: esc_html_x( 'Failed to get addon download URL.', 'Addons', 'uncanny-automator' ),
				422 // Unprocessable Entity - valid request but failed validation
			);
		}

		return $url;
	}

	/**
	 * Validate the request.
	 *
	 * @throws EDD_Zip_URL_Exception If validation fails
	 */
	private function validate_request() {

		$id = is_array( $this->addon ) && isset( $this->addon['id'] )
			? absint( $this->addon['id'] )
			: 0;

		// Validate request params
		if ( empty( $id ) ) {
			throw new EDD_Zip_URL_Exception(
				esc_html_x( 'Invalid request. Addon ID is required.', 'Addons', 'uncanny-automator' ),
				400 // Bad Request - missing required parameter
			);
		}

		// Validate license and addon access.
		try {
			$this->addon_manager->validate_license_access( $this->addon );
			$this->addon_manager->validate_addon_access( $this->addon );
		} catch ( Exception $e ) {
			// Catch all exceptions and throw our own.
			throw new EDD_Zip_URL_Exception(
				esc_html( $e->getMessage() ),
				422, // Unprocessable Entity - valid request but failed validation
				$e // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}
	}
}
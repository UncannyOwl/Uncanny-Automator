<?php

namespace Uncanny_Automator\Services\Plugin;

use Exception;

/**
 * Trait Manager_Verification
 *
 * @package Uncanny_Automator\Services\Plugin
 */
trait Manager_Verification {

	private $whitelisted_actions = array(
		'update',
		'install',
		'activate',
		'remote_update',
	);

	/**
	 * Verify the request
	 *
	 * @throws Exception
	 */
	public function verify_request( $nonce ) {

		// Validate the nonce.
		if ( ! wp_verify_nonce( $nonce, 'Aut0Mat0RPlug1nM@nag5r' ) ) {
			throw new Exception(
				esc_html( $this->i18n['security'] ),
				401 // Unauthorized
			);
		}

		// Validate the action.
		if ( ! in_array( $this->operation, $this->whitelisted_actions, true ) ) {
			throw new Exception(
				esc_html( $this->i18n['invalid_action'] ),
				400 // Bad request
			);
		}

		// Validate the user capability.
		$this->verify_permissions();

		// Validate action params.
		switch ( $this->operation ) {
			case 'install':
			case 'remote_update':
				$this->verify_url();
				break;
			case 'update':
				$this->verify_plugin();
				break;
			case 'activate':
				$this->verify_plugin();
				break;
		}
	}

	/**
	* Check for errors in the response.
	*
	* @param $result ( install, update )
	*
	* @return void
	* @throws Exception
	*/
	private function verify_results( $result ) {
		if ( is_wp_error( $result ) ) {
			throw new Exception(
				esc_html( $result->get_error_message() ),
				500 // Internal Server Error
			);
		}
		if ( false === $result ) {
			throw new Exception(
				// REVIEW - Can I get a descriptive error from class.
				esc_html( $this->i18n['unknown'] ),
				500 // Internal Server Error
			);
		}
	}

	/**
	 * Verify permissions.
	 *
	 * @return void
	 * @throws Exception
	 */
	private function verify_permissions() {

		$message = $this->action_i18n['error']['permissions'];

		if ( is_multisite() ) {
			if ( ! is_super_admin() ) {
				$message .= ' ' . $this->i18n['multisite'];
				throw new Exception(
					esc_html( $message ),
					403 // Forbidden
				);
			}
			return;
		}

		// Single site verification.
		if ( ! current_user_can( 'install_plugins' ) ) {
			throw new Exception(
				esc_html( $message ),
				403 // Forbidden
			);
		}
	}

	/**
	 * Verify download url
	 *
	 * @return void
	 * @throws Exception
	 */
	private function verify_url() {
		// // REVIEW - wp_http_validate_url ?
		if ( empty( $this->url ) || ! filter_var( $this->url, FILTER_VALIDATE_URL ) ) {
			throw new Exception(
				esc_html( $this->i18n['invalid_url'] ),
				400 // Bad Request
			);
		}
	}

	/**
	 * Verify plugin file path.
	 *
	 * @return void
	 * @throws Exception
	 */
	private function verify_plugin() {
		if ( empty( $this->plugin ) ) {
			throw new Exception(
				esc_html( $this->i18n['invalid_plugin'] ),
				400 // Bad Request
			);
		}
	}
}

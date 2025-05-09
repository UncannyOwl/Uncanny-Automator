<?php
/**
 * Creates the settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Joseph G.
 */

namespace Uncanny_Automator;

/**
 * Google_Sheet_Settings Settings
 */
class Google_Sheet_Settings extends Settings\Premium_Integration_Settings {

	protected $client;

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'google-sheet' );

		$this->set_icon( 'GOOGLESHEET' );

		$this->set_name( 'Google Sheets' );
	}

	/**
	 * Determines the status of the app integration in the settings page.
	 *
	 * @return string
	 */
	public function get_status() {

		$client = false;

		try {
			// The connection must have a Google credentials and must not have any missing scope.
			$client = $this->helpers->get_google_client() && ! $this->helpers->has_missing_scope();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Do nothing
		}

		// Show as disconnected if the user has generic drive scope.
		if ( $this->helpers->has_generic_drive_scope() ) {
			return '';
		}

		return false === $client ? '' : 'success';
	}

	/**
	 * Returns the helper class.
	 *
	 * @return object The helper object.
	 */
	public function get_helper() {

		return $this->helpers;
	}

	/**
	 * Create and retrieve an OAuth dialog for Google Sheets.
	 *
	 * @return string the Oauth dialog uri.
	 */
	public function get_auth_url() {

		// Create nonce.
		$nonce = wp_create_nonce( Google_Sheet_Helpers::NONCE );

		// Construct the redirect uri.
		$redirect_uri = add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => 'google-sheet',
				'nonce'       => $nonce,
			),
			admin_url( 'edit.php' )
		);

		$auth_uri = add_query_arg(
			array(
				'action'       => 'authorization_request',
				'scope'        => $this->get_helper()->client_scope,
				'redirect_url' => rawurlencode( $redirect_uri ),
				'nonce'        => $nonce,
				'plugin_ver'   => AUTOMATOR_PLUGIN_VERSION,
			),
			$this->get_helper()->automator_api
		);

		return $auth_uri;
	}

	/**
	 * Create and retrieve a disconnect url for Google Sheet.
	 *
	 * @return string The disconnect uri.
	 */
	public function get_disconnect_uri() {

		return add_query_arg(
			array(
				'action' => 'uo_google_disconnect_user',
				'nonce'  => wp_create_nonce( 'uo-google-user-disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$helper         = $this->get_helper();
		$auth_url       = $this->get_auth_url();
		$disconnect_uri = $this->get_disconnect_uri();

		$connect   = absint( automator_filter_input( 'connect' ) );
		$user_info = $helper->get_user_info();

		try {
			$google_client = $this->helpers->get_google_client();
		} catch ( \Exception $e ) {
			$google_client = '';
		}

		$access_token  = $google_client['access_token'] ?? '';
		$refresh_token = $google_client['refresh_token'] ?? '';

		// The connection must have a Google credentials and must not have any missing scope.
		$this->client = $google_client && ! $helper->has_missing_scope();

		// If the user has still `drive` scope.
		if ( $helper->has_generic_drive_scope() ) {
			// Mock the client as disconnect so it shows disconnected screen if they have old `drive` scope.
			$this->client = $helper->is_connected( false );
		}

		include_once 'view-google-sheet.php';
	}
}

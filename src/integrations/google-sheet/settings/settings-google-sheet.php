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

	public function get_status() {

		$client = false;

		try {
			// The connection must have a Google credentials and must not have any missing scope.
			$client = $this->helpers->get_google_client() && ! $this->helpers->has_missing_scope();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Do nothing
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
		$nonce = wp_create_nonce( 'automator_api_google_authorize' );

		// Construct the redirect uri.
		$redirect_uri = add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => 'google-sheet',
			),
			admin_url( 'edit.php' )
		);

		set_transient( 'automator_api_google_authorize_nonce', $nonce, 3600 );

		// Construct the OAuth uri.
		$auth_uri = add_query_arg(
			array(
				'action'       => 'authorization_request',
				'scope'        => $this->get_helper()->client_scope,
				'redirect_url' => rawurlencode( $redirect_uri ),
				'nonce'        => $nonce,
				'plugin_ver'   => InitializePlugin::PLUGIN_VERSION,
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

		$helper = $this->get_helper();

		$auth_url = $this->get_auth_url();

		$disconnect_uri = $this->get_disconnect_uri();

		$user_info = $helper->get_user_info();

		$connect = absint( automator_filter_input( 'connect' ) );

		$this->client = false;

		try {
			// The connection must have a Google credentials and must not have any missing scope.
			$this->client = $this->helpers->get_google_client() && ! $this->helpers->has_missing_scope();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Do nothing
		}

		include_once 'view-google-sheet.php';

	}

}

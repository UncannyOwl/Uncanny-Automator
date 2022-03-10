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
 * Facebook Settings
 */
class Mailchimp_Settings {

	/**
	 * This trait defines properties and methods shared across all the
	 * settings pages of Premium Integrations
	 */
	use Settings\Premium_Integrations;

	protected $helper = '';
	/**
	 * Creates the settings page
	 */
	public function __construct( $helper ) {

		$this->helper = $helper;

		// Register the tab
		$this->setup_settings();

		// The methods above load even if the tab is not selected
		if ( ! $this->is_current_page_settings() ) {
			return;
		}

	}

	/**
	 * Sets up the properties of the settings page
	 */
	protected function set_properties() {

		$this->set_id( 'mailchimp_api' );

		$this->set_icon( 'mailchimp' );

		$this->set_name( 'Mailchimp' );

		$this->set_status( false !== $this->get_helper()->get_mailchimp_client() ? 'success' : '' );

		$this->set_js( '/mailchimp/settings/assets/script.js' );

		$this->set_css( '/mailchimp/settings/assets/style.css' );

	}

	/**
	 * Returns the helper class.
	 *
	 * @return object The helper object.
	 */
	public function get_helper() {

		return $this->helper;

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		// Set the transient when page is viewed.
		set_transient( 'automator_api_mailchimp_authorize_nonce', wp_create_nonce( 'automator_api_mailchimp_authorize' ), 3600 );

		$client = $this->get_helper()->get_mailchimp_client();

		$auth_uri = $this->get_mailchimp_oauth_uri();

		$connect_code = absint( automator_filter_input( 'connect' ) );

		$disconnect_uri = $this->get_mailchimp_disconnect_uri();

		include_once 'view-mailchimp.php';

	}

	/**
	 * Get the Mailchimp OAuth URI.
	 *
	 * @return string The Mailchimp OAuth URI.
	 */
	public function get_mailchimp_oauth_uri() {

		$action       = '';
		$redirect_url = rawurlencode( admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=mailchimp_api' );

		return add_query_arg(
			array(
				'action'       => 'mailchimp_authorization_request',
				'scope'        => '1',
				'redirect_url' => $redirect_url,
				'nonce'        => wp_create_nonce( 'automator_api_mailchimp_authorize' ),
				'plugin_ver'   => InitializePlugin::PLUGIN_VERSION,
				'api_ver'      => '1.0',
			),
			AUTOMATOR_API_URL . 'v2/mailchimp'
		);
	}

	public function get_mailchimp_disconnect_uri() {
		return add_query_arg(
			array(
				'action' => 'uo_mailchimp_disconnect',
				'nonce'  => wp_create_nonce( 'uo-mailchimp-disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}
}

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
	public function __construct( $helpers ) {

		$this->helpers = $helpers;

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

		try {
			$this->client       = $this->helpers->get_mailchimp_client();
			$this->is_connected = true;
		} catch ( \Exception $e ) {
			$this->client       = array();
			$this->is_connected = false;
		}

		$this->register_option( 'uap_mailchimp_api_url' );

		$this->register_option( 'uap_mailchimp_api_key' );

		$this->register_option( 'uap_mailchimp_enable_webhook' );

		$this->set_id( 'mailchimp_api' );

		$this->set_icon( 'mailchimp' );

		$this->set_name( 'Mailchimp' );

		$this->set_status( $this->is_connected ? 'success' : '' );

		$this->set_js( '/mailchimp/settings/assets/script.js' );

		$this->set_css( '/mailchimp/settings/assets/style.css' );

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		// Set the transient when page is viewed.
		set_transient( 'automator_api_mailchimp_authorize_nonce', wp_create_nonce( 'automator_api_mailchimp_authorize' ), 3600 );

		$connect_code = absint( automator_filter_input( 'connect' ) );

		$connect_uri = $this->helpers->get_connect_uri();

		$disconnect_uri = $this->helpers->get_disconnect_uri();

		$enable_triggers = $this->helpers->is_webhook_enabled() ? 'checked' : '';

		$webhook_url = get_rest_url() . AUTOMATOR_REST_API_END_POINT . $this->helpers->get_webhook_url();

		$kb_link = automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/mailchimp-wordpress-triggers/', 'settings', 'mailchimp-triggers-kb_article' );

		$regenerate_alert = esc_html__( 'Regenerating the URL will prevent Mailchimp triggers from working until the new webhook URL is set in Mailchimp. Continue?', 'uncanny-automator' );

		$regenerate_key_url = add_query_arg(
			array(
				'action' => 'mailchimp-regenerate-webhook-key',
			),
			admin_url( 'admin-ajax.php' )
		);

		include_once 'view-mailchimp.php';

	}


}

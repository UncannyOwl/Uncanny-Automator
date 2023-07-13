<?php
/**
 * Creates the settings page
 *
 * @since   4.2
 * @version 4.2
 * @package Uncanny_Automator
 * @author  UncannyOwl
 */

namespace Uncanny_Automator;

class WhatsApp_Settings extends Settings\Premium_Integration_Settings {

	const PHONE_ID = 'automator_whatsapp_phone_id';

	const ACCESS_TOKEN = 'automator_whatsapp_access_token';

	const BUSINESS_ID = 'automator_whatsapp_business_account_id';

	const KNOWLEDGEBASE_URL = 'https://automatorplugin.com/knowledge-base/whatsapp';

	public function validate_access_token( $sanitize_input, $option_name, $original_input ) {

		$cache_key = $option_name . '_validated';

		// WordPress is calling `add_option` and `update_option` if the option is not yet set in wp_options table.
		// This invokes `sanitize_option_{$option_key}` twice so this method is also called twice as a side effect.
		// Let us check if a run-time cache is set to avoid processing twice.
		if ( Automator()->cache->get( $cache_key ) ) {

			return $sanitize_input;

		}

		// Prevent token validation if there is already a client.
		if ( ! empty( $this->get_helper()->get_client() ) ) {

			return $sanitize_input;

		}

		try {

			$response = $this->get_helper()->verify_token( $sanitize_input );

			update_option( 'automator_whatsapp_client', $response, true );

			$client = $this->get_helper()->get_client();

			/* translators: Settings flash message */
			$heading = sprintf( __( 'Your account "%s" has been connected successfully!', 'uncanny-automator' ), $client['application'] );

			automator_add_settings_error( 'automator_whatsapp_connection_alerts', $heading, '', 'success' );

			// Set the run-time cache.
			Automator()->cache->set( $cache_key, true );

			return $sanitize_input;

		} catch ( \Exception $e ) {

			automator_add_settings_error( 'automator_whatsapp_connection_alerts', __( 'Authentication error', 'uncanny-automator' ), $e->getMessage(), 'error' );

			return false;

		}

	}

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		add_filter( 'sanitize_option_' . self::ACCESS_TOKEN, array( $this, 'validate_access_token' ), 10, 3 );

		$this->set_id( 'whatsapp' );

		$this->set_icon( 'WHATSAPP' );

		$this->set_name( 'WhatsApp' );

		$this->register_option( self::PHONE_ID );

		$this->register_option( self::ACCESS_TOKEN );

		$this->register_option( 'automator_whatsapp_business_account_id' );

	}

	public function get_status() {

		$client = $this->get_helper()->get_client();

		$is_user_connected = ! empty( $client ) && ! $this->get_helper()->has_missing_scopes( $client['scopes'] );

		return $is_user_connected ? 'success' : '';
	}

	/**
	 * Returns the helper class.
	 *
	 * @return object The helper object.
	 */
	public function get_helper() {

		return $this->helpers;

	}

	public function get_settings_url() {

		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => 'whatsapp',
			),
			admin_url( 'edit.php' )
		);

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$is_connected = $this->get_helper()->is_connected();

		$client = $this->get_helper()->get_client();

		$disconnect_url = $this->get_helper()->get_disconnect_url();

		$phone_id = get_option( self::PHONE_ID, '' );

		$access_token = get_option( self::ACCESS_TOKEN, '' );

		$business_id = get_option( self::BUSINESS_ID, '' );

		$alerts = (array) get_settings_errors( 'automator_whatsapp_connection_alerts' );

		$access_token_description = sprintf(
			'%4$s <a href="%2$s#section-access-token" title="%3$s" target="blank">%3$s</a> %1$s ',
			__( 'to learn how to create a permanent access token.', 'uncanny-automator' ),
			automator_utm_parameters( self::KNOWLEDGEBASE_URL, 'premium-integrations', 'whatsapp' ),
			__( 'Click here', 'uncanny-automator' ),
			__( 'You may use the temporary access token found in your WhatsApp product for testing purposes.', 'uncanny-automator' )
		);

		$phone_business_description = sprintf(
			'%1$s <a href="%2$s#section-phone-id" title="%3$s" target="blank">%3$s</a>',
			__( 'Your Phone number ID and WhatsApp Business Account ID can be found in your Meta developer app settings under WhatsApp product.', 'uncanny-automator' ),
			automator_utm_parameters( self::KNOWLEDGEBASE_URL, 'premium-integrations', 'whatsapp' ),
			__( 'Learn more', 'uncanny-automator' )
		);

		$webhook_url = $this->get_helper()->get_webhook_url();

		$verify_token = $this->get_helper()->get_webhook_key();

		$regenerate_alert = esc_html__( 'Regenerating the URL will prevent WhatsApp triggers from working until the new webhook URL is set in your WhatsApp Configuration. Continue?', 'uncanny-automator' );

		$regenerate_key_url = add_query_arg( array( 'action' => 'whatsapp-regenerate-webhook-key' ), admin_url( 'admin-ajax.php' ) );

		include_once 'view-whatsapp.php';

	}

}

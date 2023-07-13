<?php
namespace Uncanny_Automator;

class Zoho_Campaigns_Client {

	const API_ENDPOINT = 'v2/zoho-campaigns';
	const NONCE_KEY    = 'automator_zoho_agent';

	public function get_redacted_token() {

		$creds = automator_get_option( 'zoho_campaigns_credentials', false );

		if ( empty( $creds['access_token'] ) ) {
			return 'N/A';
		}

		return substr( $creds['access_token'], 0, 3 ) . '&hellip;' . substr( $creds['access_token'], strlen( $creds['access_token'] ) - 4, strlen( $creds['access_token'] ) );
	}

	public function get_status() {
		return ! empty( $this->is_connected() ) ? 'success' : '';
	}

	public function is_connected() {
		return ! empty( automator_get_option( 'zoho_campaigns_credentials', false ) );
	}

	public function get_connect_url() {

		return add_query_arg(
			array(
				'action'   => 'authorization',
				'nonce'    => wp_create_nonce( self::NONCE_KEY ),
				'site_url' => get_site_url(),
			),
			AUTOMATOR_API_URL . self::API_ENDPOINT
		);

	}

	public function get_disconnect_url() {
		return add_query_arg(
			array(
				'action' => 'automator-disconnect-zoho-client',
				'nonce'  => wp_create_nonce( self::NONCE_KEY ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	public function get_user() {
		// Zoho Campaigns has no resource owner endpoint.
		return array();
	}

}

<?php

namespace Uncanny_Automator\Integrations\Bitly;

use Uncanny_Automator\App_Integrations\Api_Caller;

class Bitly_Api_Caller extends Api_Caller {

	/**
	 * Set the properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override the default credential request key until migration to vault.
		$this->set_credential_request_key( 'access_token' );
	}

	/**
	 * Check response for errors.
	 *
	 * @param mixed $response
	 * @param array $args
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function check_for_errors( $response, $args = array() ) {
		if ( 201 !== $response['statusCode'] && 200 !== $response['statusCode'] ) {
			$message = isset( $response['data']['message'] )
				? $response['data']['message']
				: esc_html_x( 'Bitly API Error', 'Bitly', 'uncanny-automator' );
			// Invalid tokens return FORBIDDEN message
			// We could utilize this to improve error handling.
			throw new \Exception( esc_html( $message ), absint( $response['statusCode'] ) );
		}
	}
}

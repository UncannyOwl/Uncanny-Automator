<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound

namespace Uncanny_Automator\Integrations\Threads;

use Exception;
use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Threads_App_Helpers
 *
 * @package Uncanny_Automator
 * @property Threads_Api_Caller $api
 */
class Threads_App_Helpers extends App_Helpers {

	/**
	 * Validate credentials format.
	 *
	 * @param array $credentials
	 * @param array $args Optional additional arguments
	 * @return array
	 * @throws Exception
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		if ( empty( $credentials['access_token'] ) || empty( $credentials['user_id'] ) ) {
			throw new Exception( 'Missing or invalid credentials. Please reconnect your account.' );
		}
		return $credentials;
	}

	/**
	 * Get account info for settings display.
	 *
	 * @return array
	 */
	public function get_account_info() {
		try {
			$credentials = $this->get_credentials();
			return array(
				'name' => ! empty( $credentials['user_id'] ) ? sprintf( 'User ID: %s', $credentials['user_id'] ) : '',
				'id'   => $credentials['user_id'] ?? '',
			);
		} catch ( Exception $e ) {
			return array(
				'name' => '',
				'id'   => '',
			);
		}
	}
}

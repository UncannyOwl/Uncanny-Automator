<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Bitly;

use Uncanny_Automator\App_Integrations\App_Helpers;

use Exception;

/**
 * Class App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Bitly_Api_Caller $api
 */
class Bitly_App_Helpers extends App_Helpers {

	/**
	 * @var string[]
	 */
	private $account_details = array(
		'login' => '',
		'email' => '',
		'name'  => '',
	);

	////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set legacy option names for credentials and account info.
		$this->set_credentials_option_name( 'automator_bitly_access_token' );
		$this->set_account_option_name( 'automator_bitly_credentials' );
	}

	/**
	 * Get Account Details.
	 *
	 * @return array
	 */
	public function get_saved_account_details() {

		// No API key set return defaults.
		$access_token = $this->get_credentials();
		if ( empty( $access_token ) ) {
			return array();
		}

		// Get saved account details.
		$account = $this->get_account_info();

		if ( empty( $account['login'] ) ) {
			$account = $this->fetch_account_details();
		}

		return $account;
	}

	/**
	 * @return string[]
	 */
	public function fetch_account_details() {

		// Set defaults.
		$account = $this->account_details;

		// Validate api key.
		$access_token = $this->get_credentials();

		if ( empty( $access_token ) ) {
			return $account;
		}

		// Get account.
		try {
			$response = $this->api->api_request( 'get_user' );
		} catch ( Exception $e ) {
			$error            = $e->getMessage();
			$account['error'] = ! empty( $error ) ? $error : esc_html_x( 'Bitly API Error', 'Bitly', 'uncanny-automator' );

			$this->store_account_info( $account );

			return $account;
		}

		// Success.
		if ( ! empty( $response['data'] ) ) {
			$account['login'] = $response['data']['login'];
			$account['name']  = $response['data']['name'];
			if ( ! empty( $response['data']['emails'] ) ) {
				$emails            = array_shift( $response['data']['emails'] );
				$account['email']  = $emails['email'];
				$account['status'] = 'connected';
			}
		}

		// Check for invalid key.
		if ( empty( $response['data']['login'] ) ) {
			$account['status'] = '';
			$account['error']  = $this->get_invalid_key_message();
		}

		$this->store_account_info( $account );

		return $account;
	}

	/**
	 * Get the invalid key message.
	 *
	 * @return string
	 */
	public function get_invalid_key_message() {
		return esc_html_x( 'Invalid API Key', 'Bitly', 'uncanny-automator' );
	}
}

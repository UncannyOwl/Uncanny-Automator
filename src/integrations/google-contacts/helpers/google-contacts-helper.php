<?php
namespace Uncanny_Automator\Integrations\Google_Contacts;

use Exception;
use Uncanny_Automator\Automator_Helpers_Recipe;
use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Google_Contacts_Helpers
 *
 * @property Google_Contacts_Api_Caller $api
 */
class Google_Contacts_Helpers extends App_Helpers {

	/**
	 * "Account info" transient key.
	 *
	 * @var string RESOURCE_OWNER_KEY
	 */
	const RESOURCE_OWNER_KEY = 'automator_api_google_contacts_resource_owner_transient';


	/////////////////////////////////////////////////////////////
	// Abstract overide methods.
	/////////////////////////////////////////////////////////////

	/**
	 * Get Account Info - Overiding because this integrations saves to transient vs uap_options.
	 *
	 * @return array - Array of account info.
	 */
	public function get_account_info() {

		// Return cached user info if it exists.
		$saved_user_info = get_transient( self::RESOURCE_OWNER_KEY );
		if ( false !== $saved_user_info && ! empty( $saved_user_info['email'] ) ) {
			return $saved_user_info;
		}

		try {

			$user = $this->api->request_resource_owner();
			if ( empty( $user['data'] ) ) {
				throw new Exception( 'No user info found', 404 );
			}

			$user_info = array(
				'name'       => $user['data']['name'] ?? '',
				'avatar_uri' => $user['data']['picture'] ?? '',
				'email'      => $user['data']['email'] ?? '',
			);

			$this->store_account_info( $user_info );

			return $user_info;

		} catch ( Exception $e ) {

			// Clear the connection.
			$this->clear_connection();

			// Customize the error message.
			$error_message = sprintf(
				'An error has occured while fetching the resource owner: (%s) %s',
				absint( $e->getCode() ),
				esc_html( $e->getMessage() )
			);

			throw new Exception( esc_html( $error_message ), absint( $e->getCode() ) );
		}
	}

	/**
	 * Store account info - Overiding because this integrations saves to transient vs uap_options.
	 *
	 * @param array $user_info The user info.
	 *
	 * @return void
	 */
	public function store_account_info( $user_info ) {
		set_transient( self::RESOURCE_OWNER_KEY, $user_info, DAY_IN_SECONDS );
	}

	/**
	 * Delete account info - Overiding because this integrations saves to transient vs uap_options.
	 *
	 * @return void
	 */
	public function delete_account_info() {
		delete_transient( self::RESOURCE_OWNER_KEY );
	}

	/////////////////////////////////////////////////////////////
	// Integration methods.
	/////////////////////////////////////////////////////////////

	/**
	 * Clears the connection data.
	 *
	 * @return true
	 */
	public function clear_connection() {
		$this->delete_credentials();
		$this->delete_account_info();
		return true;
	}

	/**
	 * AJAX handler for fetching contact labels/groups
	 *
	 * @return void
	 */
	public function ajax_fetch_labels() {

		try {
			// Use injected API instance
			$options = $this->api->fetch_contact_groups();

			wp_send_json(
				array(
					'success' => true,
					'options' => $options,
				)
			);

		} catch ( Exception $e ) {

			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);

		}
	}
}

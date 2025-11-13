<?php // phpcs:ignoreFile PHPCompatibility.Operators.NewOperators.t_coalesceFound
// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Bluesky;

use Exception;
use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Bluesky_Helpers
 *
 * @package Uncanny_Automator
 * 
 * @property Bluesky_Api_Caller $api
 */
class Bluesky_App_Helpers extends App_Helpers {

	////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////

	/**
	 * Validate credentials.
	 *
	 * @return array
	 */
	public function validate_credentials( $credentials, $args = array() ) {

		if ( empty( $credentials['vault_signature'] ) ) {
			throw new \Exception( esc_html_x( 'Bluesky is not connected', 'Bluesky', 'uncanny-automator' ) );
		}

		return $credentials;
	}	

	/**
	 * Remove credentials.
	 */
	public function remove_credentials() {
		// Delete account vault.
		try {
			$this->api->api_request( array( 'action'=> 'disconnect' ), null );
		} catch ( Exception $e ) {
			// Do nothing
		}
	}

	/**
	 * Get a credential setting.
	 *
	 * @param string $key
	 * @param string $default
	 * @return string
	 */
	public function get_credential_setting( $key, $default = '' ) {
		try {
			$credentials = $this->get_credentials();
			return $credentials[ $key ] ?? $default;
		} catch ( Exception $e ) {
			return $default;
		}
	}

	/**
	 * Get formatted post record.
	 *
	 * @param string $text
	 * @param array $media
	 * 
	 * @return array
	 * @throws Exception
	 */
	public function get_formatted_post_record( $text, $media = array() ) {
		$record = new Bluesky_Post_Record( $text, $media );
		return $record->get_record();
	}
}
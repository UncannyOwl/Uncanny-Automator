<?php // phpcs:ignoreFile PHPCompatibility.Operators.NewOperators.t_coalesceFound
// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Bluesky;

use Exception;
use WP_Error;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;

/**
 * Class Bluesky_Helpers
 *
 * @package Uncanny_Automator
 */
class Bluesky_Helpers {

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'bluesky';

	/**
	 * Credentials options key.
	 *
	 * @var string
	 */
	const CREDENTIALS = 'automator_bluesky_credentials';

	/**
	 * Bluesky API
	 * 
	 * @return Bluesky_Api
	 */
	public function api() {
		static $api = null;
		if ( null === $api ) {
			$api = new Bluesky_Api( $this );
		}
		return $api;
	}

	/**
	 * Is connected.
	 *
	 * @return bool
	 */
	public function is_connected() {
		try {
			$this->get_credentials();
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Integration status.
	 *
	 * @return string
	 */
	public function integration_status() {
		return $this->is_connected() ? 'success' : '';
	}

	/**
	 * Get credentials.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_credentials() {

		$credentials = automator_get_option( self::CREDENTIALS, array() );

		if ( empty( $credentials['vault_signature'] ) ) {
			throw new Exception( 'Bluesky is not connected' );
		}

		return $credentials;
	}
	
	/**
	 * Save credentials.
	 *
	 * @param array $credentials
	 */
	public function save_credentials( $credentials ) {
		automator_update_option( self::CREDENTIALS, $credentials );
	}

	/**
	 * Remove credentials.
	 */
	public function remove_credentials() {

		// Delete account vault.
		try {
			$this->api()->api_request( array('action'=> 'disconnect' ), null );
		} catch ( Exception $e ) {
			// Do nothing
		}

		// Delete options.
		automator_delete_option( self::CREDENTIALS );
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

	/**
	 * Get settings page URL.
	 *
	 * @return string
	 */
	public function get_settings_page_url() {
		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => $this->settings_tab,
			),
			admin_url( 'edit.php' )
		);
	}

}
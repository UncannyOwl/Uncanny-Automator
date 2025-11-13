<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Brevo;

use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Brevo_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Brevo_Api_Caller $api
 */
class Brevo_App_Helpers extends App_Helpers {

	/**
	 * Default account details.
	 *
	 * @var array
	 */
	private $account_details = array(
		'company' => '',
		'email'   => '',
		'status'  => '',
		'error'   => '',
	);

	/**
	 * The wp_options table key for selecting the integration options.
	 *
	 * @var string
	 */
	const API_KEY_OPTION = 'automator_brevo_api_key';

	/**
	 * The Brevo IP security link.
	 *
	 * @var string
	 */
	const BREVO_IP_SECURITY_LINK = 'https://app.brevo.com/security/authorised_ips';

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override the credentials option name.
		$this->set_credentials_option_name( self::API_KEY_OPTION );
	}

	/**
	 * Get Account Details.
	 *
	 * @return array
	 */
	public function get_saved_account_details() {

		// No API key set return defaults.
		$api_key = $this->get_credentials();
		if ( empty( $api_key ) ) {
			return $this->account_details;
		}

		// Get saved account details from options.
		$account = $this->get_account_info();

		// Legacy check.
		if ( empty( $account ) ) {
			$account = $this->get_account( $api_key );
		}

		return $account;
	}

	/**
	 * Get account - validates the connection, saves and returns the account info
	 *
	 * @param string $api_key The API key.
	 *
	 * @return array
	 */
	private function get_account( $api_key ) {

		// Set defaults.
		$account = $this->account_details;

		// Check for Legacy invalid key.
		if ( 0 === strpos( $api_key, $this->get_invalid_key_message() ) ) {
			$account['error'] = $this->get_invalid_key_message();
			$this->store_account_info( $account );
			return $account;
		}

		// Get account info from API.
		$account = $this->api->get_account_info( $account );

		// Save account info.
		$this->store_account_info( $account );

		return $account;
	}

	/**
	 * Ajax get list options.
	 *
	 * @return json
	 */
	public function ajax_get_list_options() {

		Automator()->utilities->ajax_auth_check();

		$lists = $this->api->get_lists();

		wp_send_json( $lists );

		die();
	}

	/**
	 * Ajax get templates.
	 *
	 * @return json
	 */
	public function ajax_get_templates() {

		Automator()->utilities->ajax_auth_check();

		$templates = $this->api->get_templates();

		wp_send_json( $templates );

		die();
	}

	/**
	 * Get email from parsed.
	 *
	 * @param  array $parsed
	 * @param  string $meta_key
	 * @return string
	 */
	public function get_email_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'Missing email', 'Brevo', 'uncanny-automator' ) );
		}

		$email = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $email || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( esc_html_x( 'Invalid email', 'Brevo', 'uncanny-automator' ) );
		}

		return $email;
	}

	/**
	 * Get list_id from parsed.
	 *
	 * @param  array $parsed
	 * @param  string $meta_key
	 * @return mixed
	 */
	public function get_list_id_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'List ID is required.', 'Brevo', 'uncanny-automator' ) );
		}

		$list_id = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $list_id ) {
			throw new \Exception( esc_html_x( 'List ID is required.', 'Brevo', 'uncanny-automator' ) );
		}

		return $list_id;
	}

	/**
	 * Get the invalid key message.
	 *
	 * @return string
	 */
	public function get_invalid_key_message() {
		return esc_html_x( 'Invalid API Key : ', 'Brevo', 'uncanny-automator' );
	}

	/**
	 * Get the formatted security link with external icon.
	 *
	 * @return string
	 */
	public function get_authorized_ips_link() {
		return sprintf(
			'<a href="%1$s" target="_blank">%2$s <uo-icon id="external-link"></uo-icon></a>',
			esc_url( self::BREVO_IP_SECURITY_LINK ),
			esc_html_x( 'Security → Authorized IPs', 'Brevo', 'uncanny-automator' )
		);
	}
}

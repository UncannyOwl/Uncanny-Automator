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
	 * Sentinel string a recipe author can submit to explicitly clear an attribute on Brevo.
	 *
	 * @var string
	 */
	const DELETE_VALUE = '[DELETE]';

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
	 * Get list options for the remote-data dropdown.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lists( $request ): array {

		$lists = $this->api->get_lists( $request->is_refresh() );

		return $this->remote_data_success( is_array( $lists ) ? $lists : array() );
	}

	/**
	 * Get email template options for the remote-data dropdown.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_templates( $request ): array {

		$templates = $this->api->get_templates( $request->is_refresh() );

		return $this->remote_data_success( is_array( $templates ) ? $templates : array() );
	}

	/**
	 * Get writable contact attributes as transposed-repeater sub-fields.
	 *
	 * Returns the `field_properties.fields` envelope so the recipe builder's
	 * repeater renders one input per Brevo attribute with the correct input
	 * type (text / date / select with the attribute's enumeration). The shape
	 * is built by Brevo_Contact_Attributes_Helper::generate_repeater_fields().
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_contact_attributes( $request ): array {

		$raw    = $this->api->fetch_raw_contact_attributes( $request->is_refresh() );
		$fields = Brevo_Contact_Attributes_Helper::generate_repeater_fields( is_array( $raw ) ? $raw : array(), $this );

		return $this->remote_data_success( array( 'fields' => $fields ), 'field_properties' );
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
	 * Check if a value is the [DELETE] sentinel.
	 *
	 * @param mixed $value Single value or array of values.
	 *
	 * @return bool
	 */
	public function is_delete_value( $value ) {
		if ( is_array( $value ) ) {
			return in_array( self::DELETE_VALUE, $value, true );
		}
		return self::DELETE_VALUE === $value;
	}

	/**
	 * Prepend the "Select option" empty default to a list of select options.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function prepend_empty_option( $options ) {
		return array_merge(
			array(
				array(
					'value' => '',
					'text'  => esc_html_x( 'Select option', 'Brevo', 'uncanny-automator' ),
				),
			),
			$options
		);
	}

	/**
	 * Append the [DELETE] option to a list of select options.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function append_delete_option( $options ) {
		$options[] = array(
			'value' => self::DELETE_VALUE,
			'text'  => esc_html_x( 'Delete value', 'Brevo', 'uncanny-automator' ),
		);
		return $options;
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

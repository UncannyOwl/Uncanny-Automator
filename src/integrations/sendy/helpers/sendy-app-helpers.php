<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Sendy;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Sendy_App_Helpers
 *
 * @package Uncanny_Automator
 * @property Sendy_Api_Caller $api
 */
class Sendy_App_Helpers extends App_Helpers {

	/**
	 * Transient key for lists.
	 *
	 * @var string
	 */
	const LIST_TRANSIENT_KEY = 'automator_sendy_lists';

	/**
	 * Set properties for App Integration framework.
	 */
	public function set_properties() {
		// Map to EXISTING option names from legacy integration.
		$this->set_credentials_option_name( 'automator_sendy_api' );
	}

	/**
	 * Get account info for settings display.
	 *
	 * @return array
	 */
	public function get_account_info() {
		$credentials = $this->get_credentials();
		return array(
			'url'    => $credentials['url'] ?? '',
			'status' => $credentials['status'] ?? false,
		);
	}

	/**
	 * Get a specific Sendy setting.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public function get_sendy_setting( $key ) {
		$settings = $this->get_credentials();
		return $settings[ $key ] ?? '';
	}

	/**
	 * Ajax get list options.
	 *
	 * @return json
	 */
	public function ajax_get_list_options() {

		Automator()->utilities->ajax_auth_check();

		try {
			$lists = $this->api->get_lists( $this->is_ajax_refresh() );
			wp_send_json(
				array(
					'success' => true,
					'options' => $lists,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'message' => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * Get common List field configuration.
	 *
	 * @return array
	 */
	public function get_list_field_config() {
		return array(
			'option_code'           => 'LIST',
			'label'                 => esc_html_x( 'List', 'Sendy', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => false,
			'ajax'                  => array(
				'endpoint' => 'automator_sendy_get_lists',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get common Email field configuration.
	 *
	 * @param string $option_code The option code to use for the email field.
	 *
	 * @return array
	 */
	public function get_email_field_config( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Email', 'Sendy', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);
	}

	/**
	 * Get list from parsed.
	 *
	 * @param array  $parsed
	 * @param string $meta_key
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_list_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'List is required.', 'Sendy', 'uncanny-automator' ) );
		}

		$list_id = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $list_id ) {
			throw new Exception( esc_html_x( 'List is required.', 'Sendy', 'uncanny-automator' ) );
		}

		return $list_id;
	}

	/**
	 * Get email from parsed.
	 *
	 * @param array  $parsed
	 * @param string $meta_key
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_email_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Missing email', 'Sendy', 'uncanny-automator' ) );
		}

		$email = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $email || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception( esc_html_x( 'Invalid email', 'Sendy', 'uncanny-automator' ) );
		}

		return $email;
	}
}

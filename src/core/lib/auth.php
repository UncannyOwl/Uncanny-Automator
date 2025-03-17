<?php
namespace Uncanny_Automator;

/**
 * Singleton Auth class to verify internal requests.
 *
 * @package Uncanny_Automator
 */
class Auth {

	/**
	 * Secret key for token generation.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Constructor to set the secret key.
	 *
	 * @param string $secret_key The secret key for generating tokens.
	 */
	public function __construct( $secret_key = '' ) {

		if ( ! empty( $secret_key ) && is_string( $secret_key ) ) {
			$this->set_secret_key( hash( 'sha256', $secret_key ) );
		}

		if ( empty( $secret_key ) ) {
			// Fallback if no secret key is provided and SALT constants are not defined.
			$this->secret_key = $this->generate_fallback_secret_key();
		}
	}

	/**
	 * Generate a fallback secret key if wp_salt is not available.
	 *
	 * @return string The generated fallback secret key.
	 */
	private function generate_fallback_secret_key() {

		$unique_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$unique_salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : '';

		if ( empty( $unique_key ) && empty( $unique_salt ) ) {
			// Use server-specific data and a random string as a last resort.
			$unique_key  = php_uname(); // Get server information.
			$unique_salt = uniqid( '', true ); // Generate a unique ID.
		}

		return hash( 'sha256', $unique_key . $unique_salt );
	}

	/**
	 * Generate a token based on an action and current timestamp.
	 *
	 * @param string $action Action name for which the token is generated.
	 * @param int    $timestamp Optional. The timestamp to use. Defaults to current time.
	 * @return string The generated token.
	 */
	public function generate_token( $action, $timestamp = null ) {

		$timestamp = $timestamp ? (int) $timestamp : time();
		$data      = $action . '|' . $timestamp . '|' . $this->get_secret_key();
		$token     = hash_hmac( 'sha256', $data, $this->get_secret_key() );

		return base64_encode( $token . '|' . $timestamp );
	}

	/**
	 * Validate the token based on the action and expected timestamp.
	 *
	 * @param string $token The token to validate.
	 * @param string $action The action name associated with the token.
	 * @return bool True if the token is valid, false otherwise.
	 */
	public function validate_token( $token, $action ) {

		$decoded = base64_decode( $token, true );

		if ( false === $decoded || strpos( $decoded, '|' ) === false ) {
			return false;
		}

		list( $token_hash, $timestamp ) = explode( '|', $decoded );

		// Regenerate the token to check its validity.
		$expected_token = $this->generate_token( $action, $timestamp );
		$expected_parts = explode( '|', base64_decode( $expected_token, true ) );

		return hash_equals( $token_hash, $expected_parts[0] );
	}

	/**
	 * Set the secret key.
	 *
	 * @param string $secret_key The new secret key.
	 */
	public function set_secret_key( $secret_key ) {
		$this->secret_key = $secret_key;
	}

	/**
	 * Get the secret key.
	 *
	 * @return string The secret key.
	 */
	public function get_secret_key() {
		return $this->secret_key;
	}

	/**
	 * Prevent cloning of the instance.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing of the instance.
	 *
	 * @return void
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}

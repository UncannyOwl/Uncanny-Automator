<?php
namespace Uncanny_Automator;

/**
 * Singleton Auth class to verify internal requests.
 *
 * @package Uncanny_Automator
 */
class Auth {

	/**
	 * The single instance of the class.
	 *
	 * @var Auth
	 */
	private static $instance = null;

	/**
	 * Secret key for token generation.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->secret_key = $this->generate_fallback_secret_key();
	}

	/**
	 * Main Auth Instance.
	 * Ensures only one instance is loaded or can be loaded.
	 *
	 * @return Auth - Main instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
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
			$unique_key  = php_uname();
			$unique_salt = uniqid( '', true );
		}

		return hash( 'sha256', $unique_key . $unique_salt );
	}

	/**
	 * Generate a token based on an action hash and current timestamp.
	 *
	 * @param string $action_hash Hash of the action data.
	 * @param int    $timestamp Optional. The timestamp to use. Defaults to current time.
	 * @return string The generated token.
	 */
	public function generate_token( $action_hash, $timestamp = null ) {
		$timestamp = $timestamp ? (int) $timestamp : time();
		$data      = $action_hash . '|' . $timestamp . '|' . $this->secret_key;
		$token     = hash_hmac( 'sha256', $data, $this->secret_key );

		return base64_encode( $token . '|' . $timestamp );
	}

	/**
	 * Validate the token based on the action hash and expected timestamp.
	 *
	 * @param string $token The token to validate.
	 * @param string $action_hash Hash of the action data.
	 * @return bool True if the token is valid, false otherwise.
	 */
	public function validate_token( $token, $action_hash ) {
		$decoded = base64_decode( $token, true );

		if ( false === $decoded || strpos( $decoded, '|' ) === false ) {
			return false;
		}

		list( $token_hash, $timestamp ) = explode( '|', $decoded );

		// Regenerate the token to check its validity.
		$expected_token = $this->generate_token( $action_hash, $timestamp );
		$expected_parts = explode( '|', base64_decode( $expected_token, true ) );

		return hash_equals( $token_hash, $expected_parts[0] );
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

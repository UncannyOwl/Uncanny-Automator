<?php
namespace Uncanny_Automator\Services\Email\Attachment;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Validator
 *
 * A class to validate URL and check file constraints.
 */
class Validator {

	/**
	 * The file size limitation.
	 *
	 * @var int
	 */
	protected $file_size_limit = 0;

	/**
	 * URL of the file to be validated.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Whether to skip validation for URLs containing tokens.
	 *
	 * @var bool
	 */
	private $skip_token_validation;

	/**
	 * Constructor to set the URL and token validation flag.
	 *
	 * @param string $url URL of the file to validate.
	 * @param bool $skip_token_validation Whether to skip validation for tokens.
	 */
	public function __construct( $url, $skip_token_validation = true ) {
		$this->url                   = $url;
		$this->skip_token_validation = $skip_token_validation;
		$this->file_size_limit       = self::get_file_size_limit(); // 5mb.
	}

	/**
	 * Validate the file URL.
	 *
	 * @return WP_Error|array WP_Error on failure, or array with 'status_code' and 'message' on success.
	 */
	public function validate() {

		if ( $this->skip_token_validation && $this->contains_tokens() ) {
			return array(
				'status_code' => 200,
				'message'     => _x( 'Validation was skipped because a token was detected. The URL is assumed to be valid.', 'Email Validator', 'uncanny-automator' ),
			);
		}

		if ( ! $this->is_valid_url() ) {
			return new WP_Error( 'invalid_url', _x( 'The URL provided is not valid.', 'Email Validator', 'uncanny-automator' ) );
		}

		if ( automator_resolves_to_private_ip( $this->url ) ) {
			return new WP_Error( 'ssrf_blocked', _x( 'The URL resolves to a private or reserved IP address and cannot be used.', 'Email Validator', 'uncanny-automator' ) );
		}

		$status_code = $this->get_status_code();

		if ( ! $this->is_file_accessible( $status_code ) ) {
			// translators: 1: Status code
			return new WP_Error( 'file_not_accessible', sprintf( _x( 'The file could not be accessed. Please check the URL and try again. Status code: %s.', 'Email Validator', 'uncanny-automator' ), $status_code ), array( 'status_code' => $status_code ) );

		}

		if ( $this->is_file_too_large() ) {

			return new WP_Error(
				'file_too_large',
				sprintf(
					// translators: 1: File size limit in MB
					_x(
						'The file exceeds the maximum allowed size of %d MB.',
						'Email Validator',
						'uncanny-automator'
					),
					self::to_megabytes( $this->file_size_limit )
				),
				$status_code
			);

		}

		return array(
			'status_code' => $status_code,
			'message'     => _x( 'File is valid and accessible.', 'Email Validator', 'uncanny-automator' ),
		);
	}

	/**
	 * Check if the URL is valid.
	 *
	 * @return bool True if the URL is valid, false otherwise.
	 */
	private function is_valid_url() {
		return filter_var( $this->url, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Get the HTTP status code of the file URL.
	 *
	 * @return int HTTP status code.
	 */
	private function get_status_code() {

		return self::remote_get_status_code( $this->url );

	}

	/**
	 * Utility function to fetch the status code.
	 *
	 * @param string $url
	 *
	 * @return int
	 */
	public static function remote_get_status_code( $url ) {

		if ( automator_resolves_to_private_ip( $url ) ) {
			return 0;
		}

		// HEAD + no redirects: a redirect response returns a 3xx code which
		// is treated as inaccessible. This prevents an open-redirect bypass
		// where a public URL redirects to a private/internal address.
		$response = wp_remote_head(
			$url,
			array(
				'redirection' => 0,
				'timeout'     => 10,
			)
		);

		// Return 0 if is wp_error.
		if ( is_wp_error( $response ) ) {
			return 0;
		}

		// Extract headers from the response
		$status_code = wp_remote_retrieve_response_code( $response );

		// Return 0 if status code is empty.
		if ( empty( $status_code ) ) {
			return 0;
		}

		return absint( $status_code );

	}

	/**
	 * Basic static function to return the file size limit.
	 *
	 * @return int
	 */
	public static function get_file_size_limit() {
		return apply_filters( 'automator_email_file_size_limit', 5 * 1024 * 1024 ); //5 mb.
	}

	/**
	 * Check if the file is accessible.
	 *
	 * @param int $status_code HTTP status code.
	 * @return bool True if the file is accessible, false otherwise.
	 */
	private function is_file_accessible( $status_code ) {
		if ( $status_code !== 200 ) {
			// translators: 1: Status code
			$this->log_error( sprintf( _x( 'The file is not accessible. Please check the URL and try again. HTTP status code: %s.', 'Email Validator', 'uncanny-automator' ), $status_code ) );
			return false;
		}
		return true;
	}

	/**
	 * Check if the file exceeds the allowed size limit.
	 *
	 * Uses a HEAD request to read the Content-Length header rather than
	 * downloading the full file, avoiding a redundant outbound request.
	 * If the server does not send Content-Length the check is skipped and
	 * the download itself will impose its own timeout.
	 *
	 * @return bool True if the file is too large, false otherwise.
	 */
	private function is_file_too_large() {

		$response = wp_remote_head(
			$this->url,
			array(
				'timeout'     => 10,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false; // Cannot determine size; allow download to proceed.
		}

		$content_length = wp_remote_retrieve_header( $response, 'content-length' );

		if ( '' === (string) $content_length ) {
			return false; // Server did not send Content-Length; allow download to proceed.
		}

		$limit_size = apply_filters( 'automator_email_file_size_limit', $this->file_size_limit );

		if ( absint( $content_length ) > $limit_size ) {
			$this->log_error(
				sprintf(
					// translators: 1: File size limit in MB, 2: File size in MB
					_x( 'File size exceeds %1$d MB. The file reports: %2$s MB.', 'Email Validator', 'uncanny-automator' ),
					self::to_megabytes( $limit_size ),
					self::to_megabytes( absint( $content_length ) )
				)
			);
			return true;
		}

		return false;
	}

	/**
	 * Converts bytes into megabytes.
	 *
	 * @param int $bytes
	 *
	 * @return int|float
	 */
	public static function to_megabytes( $bytes ) {

		$mb = $bytes / ( 1024 * 1024 );

		return floor( $mb );

	}

	/**
	 * Check if the URL contains tokens (placeholders).
	 *
	 * @return bool True if the URL contains tokens, false otherwise.
	 */
	public function contains_tokens() {
		return preg_match( '/\{\{[^}]*\}\}/', $this->url ) === 1;
	}

	/**
	 * Log error messages.
	 *
	 * @param string $message Error message to log.
	 */
	private function log_error( $message ) {
		automator_log( $message, 'Email attachments error' );
	}

	/**
	 * Set whether to skip validation for tokens.
	 *
	 * @param bool $skip
	 */
	public function set_skip_token_validation( $skip ) {
		$this->skip_token_validation = (bool) $skip;
	}
}

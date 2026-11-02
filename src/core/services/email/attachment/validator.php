<?php
namespace Uncanny_Automator\Services\Email\Attachment;

use Uncanny_Automator\App\Infrastructure\Remote_File\Remote_File_Client;
use Uncanny_Automator\App\Infrastructure\Remote_File\Remote_File_Head;
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
	 * Seconds the HEAD request may take.
	 *
	 * @since 8.0.0
	 *
	 * @var int
	 */
	const HEAD_TIMEOUT = 10;

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
				'message'     => _x( 'Validation was skipped because a token was detected. The URL is assumed to be valid.', 'Email Validator', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text for the run log and the builder, escaped where it is shown.
			);
		}

		if ( ! $this->is_valid_url() ) {
			return new WP_Error( 'invalid_url', _x( 'The URL provided is not valid.', 'Email Validator', 'uncanny-automator' ) ); // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text for the run log and the builder, escaped where it is shown.
		}

		// One HEAD request that follows no redirect, after the address check:
		// the status and the size come from the same answer.
		$head = ( new Remote_File_Client() )->head( $this->url, self::HEAD_TIMEOUT );

		if ( is_wp_error( $head ) && Remote_File_Client::PRIVATE_ADDRESS === $head->get_error_code() ) {
			return new WP_Error( 'ssrf_blocked', _x( 'The URL resolves to a private or reserved IP address and cannot be used.', 'Email Validator', 'uncanny-automator' ) ); // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text for the run log and the builder, escaped where it is shown.
		}

		$status_code = self::status_of( $head );

		if ( ! $this->is_file_accessible( $status_code ) ) {
			// translators: 1: Status code
			return new WP_Error( 'file_not_accessible', sprintf( _x( 'The file could not be accessed. Please check the URL and try again. Status code: %s.', 'Email Validator', 'uncanny-automator' ), $status_code ), array( 'status_code' => $status_code ) ); // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text for the run log and the builder, escaped where it is shown.

		}

		if ( $this->is_file_too_large( $head ) ) {

			return new WP_Error(
				'file_too_large',
				sprintf(
					// translators: 1: File size limit in MB
					_x( // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text for the run log and the builder, escaped where it is shown.
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
			'message'     => _x( 'File is valid and accessible.', 'Email Validator', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text for the run log and the builder, escaped where it is shown.
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
	 * Utility function to fetch the status code.
	 *
	 * One HEAD request that follows no redirect: a redirect answers with its
	 * 3xx code, which is treated as inaccessible, so a public URL cannot hand
	 * the check on to a private or internal address.
	 *
	 * @param string $url
	 *
	 * @return int The status, or 0 when the address is private or the request failed.
	 */
	public static function remote_get_status_code( $url ) {

		return self::status_of( ( new Remote_File_Client() )->head( (string) $url, self::HEAD_TIMEOUT ) );
	}

	/**
	 * The status a HEAD answer carries.
	 *
	 * @since 8.0.0
	 *
	 * @param Remote_File_Head|WP_Error $head The HEAD answer, or why there is none.
	 *
	 * @return int The status, or 0 when there is no answer.
	 */
	private static function status_of( $head ) {

		return $head instanceof Remote_File_Head ? $head->status() : 0;
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
		if ( 200 !== $status_code ) {
			// translators: 1: Status code
			$this->log_error( sprintf( _x( 'The file is not accessible. Please check the URL and try again. HTTP status code: %s.', 'Email Validator', 'uncanny-automator' ), $status_code ) ); // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text for the run log and the builder, escaped where it is shown.
			return false;
		}
		return true;
	}

	/**
	 * Check if the file exceeds the allowed size limit.
	 *
	 * Reads the Content-Length of the HEAD answer the status came from, so the
	 * size costs no request of its own. If the server does not send
	 * Content-Length the check is skipped and the download itself will impose
	 * its own timeout.
	 *
	 * @param Remote_File_Head $head The HEAD answer.
	 *
	 * @return bool True if the file is too large, false otherwise.
	 */
	private function is_file_too_large( Remote_File_Head $head ) {

		$size = $head->size();

		if ( null === $size ) {
			return false; // Server did not send Content-Length; allow download to proceed.
		}

		$limit_size = apply_filters( 'automator_email_file_size_limit', $this->file_size_limit );

		if ( $size > $limit_size ) {
			$this->log_error(
				sprintf(
					// translators: 1: File size limit in MB, 2: File size in MB
					_x( 'File size exceeds %1$d MB. The file reports: %2$s MB.', 'Email Validator', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text for the run log and the builder, escaped where it is shown.
					self::to_megabytes( $limit_size ),
					self::to_megabytes( $size )
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
	 * @return int
	 */
	public static function to_megabytes( $bytes ) {

		$mb = $bytes / ( 1024 * 1024 );

		return (int) floor( $mb );
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

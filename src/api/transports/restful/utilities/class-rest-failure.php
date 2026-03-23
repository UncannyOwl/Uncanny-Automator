<?php
/**
 * REST Failure Response
 *
 * @package Uncanny_Automator
 * @since 7.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Utilities;

use Uncanny_Automator\Api\Transports\Restful\Utilities\Interfaces\Rest_Response_Context;

/**
 * REST Failure Response Class.
 *
 * Represents a failed REST API response.
 *
 * @since 7.0
 */
class Rest_Failure implements Rest_Response_Context {

	/**
	 * Response data.
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Error message.
	 *
	 * @var string
	 */
	private $message = '';

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	private $status_code = 400;

	/**
	 * Error code.
	 *
	 * @var string
	 */
	private $error_code = '';

	/**
	 * Constructor.
	 *
	 * @since 7.0
	 * @param string $message     Error message.
	 * @param int    $status_code HTTP status code (default: 400).
	 * @param string $error_code  Error code identifier.
	 * @param array  $data        Additional error data.
	 */
	public function __construct( string $message, int $status_code = 400, string $error_code = '', array $data = array() ) {
		$this->message     = $message;
		$this->status_code = $status_code;
		$this->error_code  = $error_code;
		$this->data        = $data;
	}

	/**
	 * Check if the execution was successful.
	 *
	 * @since 7.0
	 * @return bool Always returns false for failed responses.
	 */
	public function is_success(): bool {
		return false;
	}

	/**
	 * Check if the execution was a failure.
	 *
	 * @since 7.0
	 * @return bool Always returns true for failed responses.
	 */
	public function is_failure(): bool {
		return true;
	}

	/**
	 * Get the HTTP status code.
	 *
	 * @since 7.0
	 * @return int HTTP status code.
	 */
	public function get_status_code(): int {
		return $this->status_code;
	}

	/**
	 * Get the response data.
	 *
	 * @since 7.0
	 * @return array Response data array.
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Get the response message.
	 *
	 * @since 7.0
	 * @return string Error message.
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Get the error code.
	 *
	 * @since 7.0
	 * @return string Error code identifier.
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}

	/**
	 * Convert response to array format.
	 *
	 * @since 7.0
	 * @return array Array representation of the response.
	 */
	public function to_array(): array {
		$response = array(
			'success' => false,
			'error'   => array(
				'message' => $this->message,
			),
		);

		if ( ! empty( $this->error_code ) ) {
			$response['error']['code'] = $this->error_code;
		}

		if ( ! empty( $this->data ) ) {
			$response['error']['data'] = $this->data;
		}

		return $response;
	}
}

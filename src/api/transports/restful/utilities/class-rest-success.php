<?php
/**
 * REST Success Response
 *
 * @package Uncanny_Automator
 * @since 7.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Utilities;

use Uncanny_Automator\Api\Transports\Restful\Utilities\Interfaces\Rest_Response_Context;

/**
 * REST Success Response Class.
 *
 * Represents a successful REST API response.
 *
 * @since 7.0
 */
class Rest_Success implements Rest_Response_Context {

	/**
	 * Response data.
	 *
	 * @since 7.0
	 * @var array
	 */
	private $data = array();

	/**
	 * Success message.
	 *
	 * @since 7.0
	 * @var string
	 */
	private $message = '';

	/**
	 * HTTP status code.
	 *
	 * @since 7.0
	 * @var int
	 */
	private $status_code = 200;

	/**
	 * Constructor.
	 *
	 * @since 7.0
	 * @param array  $data        Response data.
	 * @param string $message     Success message.
	 * @param int    $status_code HTTP status code (default: 200).
	 */
	public function __construct( array $data = array(), string $message = 'Success', int $status_code = 200 ) {
		$this->data        = $data;
		$this->message     = $message;
		$this->status_code = $status_code;
	}

	/**
	 * Check if the execution was successful.
	 *
	 * @since 7.0
	 * @return bool Always returns true for success responses.
	 */
	public function is_success(): bool {
		return true;
	}

	/**
	 * Check if the execution was a failure.
	 *
	 * @since 7.0
	 * @return bool Always returns false for success responses.
	 */
	public function is_failure(): bool {
		return false;
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
	 * @return string Success message.
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Convert response to array format.
	 *
	 * @since 7.0
	 * @return array Array representation of the response.
	 */
	public function to_array(): array {
		return array(
			'success' => true,
			'data'    => $this->data,
			'message' => $this->message,
		);
	}
}

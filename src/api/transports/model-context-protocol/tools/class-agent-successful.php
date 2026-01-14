<?php
/**
 * Agent Successful Response
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools;

/**
 * Agent Successful Response Class.
 *
 * Represents a successful agent execution result.
 *
 * @since 7.0.0
 */
class Agent_Successful implements Agent_Response_Context {

	/**
	 * Response data.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private $data = array();

	/**
	 * Success message.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $message = '';

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 * @param array  $data    Response data.
	 * @param string $message Success message.
	 */
	public function __construct( array $data = array(), string $message = 'Success' ) {
		$this->data    = $data;
		$this->message = $message;
	}

	/**
	 * Check if the execution was successful.
	 *
	 * @since 7.0.0
	 * @return bool Always returns true for success responses.
	 */
	public function is_success(): bool {
		return true;
	}

	/**
	 * Check if the execution was a failure.
	 *
	 * @since 7.0.0
	 * @return bool Always returns false for success responses.
	 */
	public function is_failure(): bool {
		return false;
	}

	/**
	 * Get the error code.
	 *
	 * @since 7.0.0
	 * @return int Always returns 0 for success responses.
	 */
	public function get_error_code(): int {
		return 0;
	}

	/**
	 * Get the response data.
	 *
	 * @since 7.0.0
	 * @return array Response data array.
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Get the response message.
	 *
	 * @since 7.0.0
	 * @return string Success message.
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Convert response to array format.
	 *
	 * @since 7.0.0
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

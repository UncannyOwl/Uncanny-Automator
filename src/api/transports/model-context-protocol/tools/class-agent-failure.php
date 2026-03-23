<?php
/**
 * Agent Failure Response
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools;

/**
 * Agent Failure Response Class.
 *
 * Represents a failed agent execution result following MCP error code standards.
 *
 * @since 7.0.0
 */
class Agent_Failure implements Agent_Response_Context {

	/**
	 * Response data.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private $data = array();

	/**
	 * Error message.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $message = '';

	/**
	 * MCP error code.
	 *
	 * @since 7.0.0
	 * @var int
	 */
	private $error_code = 0;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 * @param int    $error_code MCP error code (e.g., -32602 for InvalidParams).
	 * @param string $message    Error message.
	 * @param array  $data       Additional error data.
	 */
	public function __construct( int $error_code, string $message, array $data = array() ) {
		$this->error_code = $error_code;
		$this->message    = $message;
		$this->data       = $data;
	}

	/**
	 * Check if the execution was successful.
	 *
	 * @since 7.0.0
	 * @return bool Always returns false for failed responses.
	 */
	public function is_success(): bool {
		return false;
	}
	/**
	 * Is failure.
	 *
	 * @return bool
	 */
	public function is_failure(): bool {
		return true;
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
	 * @return string Error message.
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Get the MCP error code.
	 *
	 * @since 7.0.0
	 * @return int MCP error code.
	 */
	public function get_error_code(): int {
		return $this->error_code;
	}

	/**
	 * Convert response to array format.
	 *
	 * @since 7.0.0
	 * @return array Array representation of the response in MCP format.
	 */
	public function to_array(): array {
		return array(
			'success' => false,
			'error'   => array(
				'code'    => $this->error_code,
				'message' => $this->message,
				'data'    => $this->data,
			),
		);
	}
}

<?php
/**
 * Agent Response Context Interface
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools;

/**
 * Agent Response Context Interface.
 *
 * Defines the contract for agent execution results, providing deterministic
 * success/failure status and structured data for AI systems.
 *
 * @since 7.0.0
 */
interface Agent_Response_Context {

	/**
	 * Check if the execution was successful.
	 *
	 * @since 7.0.0
	 * @return bool True if successful, false otherwise.
	 */
	public function is_success(): bool;

	/**
	 * Check if the execution was a failure.
	 *
	 * @since 7.0.0
	 * @return bool True if failure, false otherwise.
	 */
	public function is_failure(): bool;

	/**
	 * Get the error code.
	 *
	 * @since 7.0.0
	 * @return int Error code.
	 */
	public function get_error_code(): int;

	/**
	 * Get the response data.
	 *
	 * @since 7.0.0
	 * @return array Response data array.
	 */
	public function get_data(): array;

	/**
	 * Get the response message.
	 *
	 * @since 7.0.0
	 * @return string Human-readable message describing the result.
	 */
	public function get_message(): string;

	/**
	 * Convert response to array format.
	 *
	 * @since 7.0.0
	 * @return array Array representation of the response.
	 */
	public function to_array(): array;
}

<?php
/**
 * REST Response Context Interface
 *
 * @package Uncanny_Automator
 * @since 7.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Utilities\Interfaces;

/**
 * REST Response Context Interface.
 *
 * Defines the contract for REST API responses, providing deterministic
 * success/failure status and structured data for consistent API responses.
 *
 * @since 7.0
 */
interface Rest_Response_Context {

	/**
	 * Check if the execution was successful.
	 *
	 * @return bool True if successful, false otherwise.
	 */
	public function is_success(): bool;

	/**
	 * Check if the execution was a failure.
	 *
	 * @return bool True if failure, false otherwise.
	 */
	public function is_failure(): bool;

	/**
	 * Get the HTTP status code.
	 *
	 * @return int HTTP status code.
	 */
	public function get_status_code(): int;

	/**
	 * Get the response data.
	 *
	 * @return array Response data array.
	 */
	public function get_data(): array;

	/**
	 * Get the response message.
	 *
	 * @return string Human-readable message describing the result.
	 */
	public function get_message(): string;

	/**
	 * Convert response to array format.
	 *
	 * @return array Array representation of the response.
	 */
	public function to_array(): array;
}

<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Core\Interfaces;

/**
 * Logger interface for AI operations.
 *
 * Provides logging functionality for debugging and monitoring AI requests.
 * WordPress implementation uses automator_log().
 *
 * @package Uncanny_Automator\Core\Lib\AI\Core\Interfaces
 * @since 5.6
 */
interface Logger_Interface {

	/**
	 * Log error message.
	 *
	 * @param string              $message Log message
	 * @param array<string,mixed> $context Additional data
	 *
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void;

	/**
	 * Log warning message.
	 *
	 * @param string              $message Log message
	 * @param array<string,mixed> $context Additional data
	 *
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void;

	/**
	 * Log info message.
	 *
	 * @param string              $message Log message
	 * @param array<string,mixed> $context Additional data
	 *
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void;

	/**
	 * Log debug message.
	 *
	 * @param string              $message Log message
	 * @param array<string,mixed> $context Additional data
	 *
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void;
}

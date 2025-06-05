<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Adapters\Logger;

use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Logger_Interface;

/**
 * WordPress logging adapter for AI framework.
 *
 * Wraps the WordPress automator_log function to provide clean interface.
 * Logs are written to the AI-specific log file for easier debugging.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Adapters\Logger
 * @since 5.6
 */
final class Logger implements Logger_Interface {

	/** @var string Log subject prefix */
	private $subject = 'Uncanny Automator AI';

	/** @var string Log file name */
	private $file_name = 'ai';

	/**
	 * Log error message.
	 *
	 * @param string              $message Log message
	 * @param array<string,mixed> $context Additional data (unused in current implementation)
	 *
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		automator_log(
			'Critical error: ' . $message,
			$this->subject,
			false,
			$this->file_name
		);
	}

	/**
	 * Log warning message.
	 *
	 * @param string              $message Log message
	 * @param array<string,mixed> $context Additional data (unused in current implementation)
	 *
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		automator_log(
			'Warning: ' . $message,
			$this->subject,
			false,
			$this->file_name
		);
	}

	/**
	 * Log info message.
	 *
	 * @param string              $message Log message
	 * @param array<string,mixed> $context Additional data (unused in current implementation)
	 *
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		automator_log(
			'Info: ' . $message,
			$this->subject,
			false,
			$this->file_name
		);
	}

	/**
	 * Log debug message.
	 *
	 * @param string              $message Log message
	 * @param array<string,mixed> $context Additional data (unused in current implementation)
	 *
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		automator_log(
			'Debug: ' . $message,
			$this->subject,
			false,
			$this->file_name
		);
	}
}

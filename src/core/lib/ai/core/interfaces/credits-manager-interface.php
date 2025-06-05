<?php

declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Core\Interfaces;

/**
 * Interface for credit management systems.
 *
 * Handles credit deduction for AI API usage tracking.
 * WordPress implementation uses Api_Server for credit operations.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Core\Interfaces
 * @since 5.6
 */
interface Credits_Manager_Interface {

	/**
	 * Reduce credits for AI usage.
	 *
	 * Called after successful AI API requests to track usage.
	 * Implementation should handle errors gracefully.
	 *
	 * @return array<string,mixed>
	 */
	public function reduce_credits(): array;
}

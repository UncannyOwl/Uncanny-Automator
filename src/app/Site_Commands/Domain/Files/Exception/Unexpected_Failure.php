<?php
/**
 * An unexpected failure with safe operation context.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Exception;

use RuntimeException;
use Throwable;

/**
 * Safe operation context for the response; the original failure stays available to logging.
 */
final class Unexpected_Failure extends RuntimeException {

	/**
	 * What was happening, in developer-written words.
	 *
	 * @var string
	 */
	private string $operation;

	/**
	 * Wrap the failure.
	 *
	 * @param string    $operation What was happening.
	 * @param Throwable $previous  The original failure.
	 */
	private function __construct( string $operation, Throwable $previous ) {
		$this->operation = $operation;
		parent::__construct( 'Unexpected failure while ' . $operation, 0, $previous );
	}

	/**
	 * What was happening, in developer-written words.
	 *
	 * @return string
	 */
	public function operation(): string {
		return $this->operation;
	}

	/**
	 * Operation must be developer-written text, never a path or host error message.
	 * Expected exceptions must retain their identity so budgets, access denials,
	 * and filesystem conditions still reach their existing specific handlers.
	 *
	 * @param string    $operation What was happening, such as "reading file content".
	 * @param Throwable $failure   The failure caught.
	 * @return Throwable
	 */
	public static function during( string $operation, Throwable $failure ): Throwable {
		if ( $failure instanceof Coder_Exception
			|| $failure instanceof Line_Read_Limit_Exceeded
			|| $failure instanceof Tail_Read_Limit_Exceeded
			|| $failure instanceof self
		) {
			return $failure;
		}

		return new self( $operation, $failure );
	}
}

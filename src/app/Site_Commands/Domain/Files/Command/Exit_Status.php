<?php
/**
 * The exit status a file command reports.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Command;

/**
 * The exit status a command reports. GNU conventions: 0 success, 1 failure
 * or "nothing found", 2 usage or trouble.
 */
final class Exit_Status {

	/**
	 * The status code, 0 to 255.
	 *
	 * @var int
	 */
	private int $code;

	/**
	 * Hold one code.
	 *
	 * @param int $code The status code.
	 */
	private function __construct( int $code ) {
		$this->code = $code;
	}

	/**
	 * Exit status 0.
	 *
	 * @return self
	 */
	public static function success(): self {
		return new self( 0 );
	}

	/**
	 * Exit status 1.
	 *
	 * @return self
	 */
	public static function failure(): self {
		return new self( 1 );
	}

	/**
	 * Exit status 2.
	 *
	 * @return self
	 */
	public static function trouble(): self {
		return new self( 2 );
	}

	/**
	 * Any code, clamped to 0..255.
	 *
	 * @param int $code The status code.
	 * @return self
	 */
	public static function of( int $code ): self {
		return new self( max( 0, min( 255, $code ) ) );
	}

	/**
	 * The status code.
	 *
	 * @return int
	 */
	public function code(): int {
		return $this->code;
	}

	/**
	 * Whether the status is 0.
	 *
	 * @return bool
	 */
	public function is_success(): bool {
		return 0 === $this->code;
	}
}

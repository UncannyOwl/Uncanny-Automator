<?php
/**
 * The stages of one file-command pipeline.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Application\Files;

/**
 * An ordered list of stages, each a command request. The first stage reads
 * the site; every later stage reads the previous stage's output.
 */
final class File_Pipeline_Request {

	public const MAX_STAGES = 8;

	/**
	 * The stages, in order.
	 *
	 * @var File_Command_Request[]
	 */
	private array $stages;

	/**
	 * Use of().
	 *
	 * @param File_Command_Request[] $stages The stages, in order.
	 */
	private function __construct( array $stages ) {
		$this->stages = $stages;
	}

	/**
	 * A pipeline of these stages.
	 *
	 * @param File_Command_Request[] $stages The stages, in order.
	 * @return self
	 *
	 * @throws \InvalidArgumentException When there are no stages or too many.
	 */
	public static function of( array $stages ): self {
		if ( array() === $stages ) {
			throw new \InvalidArgumentException( 'a pipeline needs at least one stage' );
		}
		if ( count( $stages ) > self::MAX_STAGES ) {
			throw new \InvalidArgumentException( sprintf( 'a pipeline has at most %d stages', self::MAX_STAGES ) );
		}

		return new self( array_values( $stages ) );
	}

	/**
	 * The stages, in order.
	 *
	 * @return File_Command_Request[]
	 */
	public function stages(): array {
		return $this->stages;
	}
}

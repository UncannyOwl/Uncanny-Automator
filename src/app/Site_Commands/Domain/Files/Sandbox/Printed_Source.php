<?php
/**
 * A file, or standard input, that a command prints from.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;

/**
 * One file, or standard input, that a command prints from, and how the
 * command refuses it.
 *
 * A file's first bytes are judged before it is opened, but a credential can
 * sit anywhere after them, and head, tail, sed and grep print from any
 * offset. So the ledger holds what a command prints from each source until
 * the sandbox's content policy has read it, with the same signatures that
 * judge a file's first bytes and a pipeline's input. When the printed text
 * holds one, none of it reaches the caller: the command's refusal for a file
 * it may not open is written in its place.
 */
final class Printed_Source {

	/**
	 * The sandbox, whose content policy reads the printed text.
	 *
	 * @var Sandbox
	 */
	private Sandbox $sandbox;

	/**
	 * The operand as the caller wrote it, for the audit.
	 *
	 * @var string
	 */
	private string $requested;

	/**
	 * The command's error lines for a file it may not open.
	 *
	 * @var string
	 */
	private string $refusal;

	/**
	 * The least exit status the command gives when the source is withheld.
	 *
	 * @var Exit_Status
	 */
	private Exit_Status $status;

	/**
	 * Hold the source.
	 *
	 * @param Sandbox     $sandbox   The sandbox, whose content policy reads the printed text.
	 * @param string      $requested The operand as the caller wrote it; empty for standard input the stage runner supplied.
	 * @param string      $refusal   The command's error lines for a file it may not open; empty to write none.
	 * @param Exit_Status $status    The least exit status the command gives when the source is withheld.
	 */
	public function __construct( Sandbox $sandbox, string $requested, string $refusal, Exit_Status $status ) {
		$this->sandbox   = $sandbox;
		$this->requested = $requested;
		$this->refusal   = $refusal;
		$this->status    = $status;
	}

	/**
	 * Whether what the command printed from the source may reach the caller.
	 *
	 * @param string $printed  The text printed from the source, without the prefixes the command added, such as line numbers or file names.
	 * @param bool   $prefixed Whether the command added prefixes to that text.
	 * @return bool
	 */
	public function allows( string $printed, bool $prefixed = false ): bool {
		return $this->sandbox->allows_printed( $printed, $prefixed );
	}

	/**
	 * The operand as the caller wrote it.
	 *
	 * @return string
	 */
	public function requested(): string {
		return $this->requested;
	}

	/**
	 * The command's error lines for a file it may not open.
	 *
	 * @return string
	 */
	public function refusal(): string {
		return $this->refusal;
	}

	/**
	 * The least exit status the command gives when the source is withheld.
	 *
	 * @return Exit_Status
	 */
	public function status(): Exit_Status {
		return $this->status;
	}
}

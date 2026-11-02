<?php
/**
 * A file command's budget was exhausted.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Exception;

/**
 * A budget was exhausted. The ledger has already written the notice and
 * marked the transcript; this unwinds the command.
 */
final class Budget_Exceeded extends Coder_Exception {
}

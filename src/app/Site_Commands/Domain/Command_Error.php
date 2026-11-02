<?php
/**
 * A site command that cannot run as written.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain;

/**
 * Carries an honest, user-facing reason. The message is printed after
 * "Error: ", the way WP-CLI reports a failed command.
 */
final class Command_Error extends \RuntimeException {
}

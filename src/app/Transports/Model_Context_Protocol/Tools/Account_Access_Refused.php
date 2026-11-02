<?php
/**
 * Administrator-level access an AI app tried to give, refused.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\App\Site_Commands\Domain\Refusal;

/**
 * Thrown by Account_Access_Guard before WordPress saves the access. Its
 * message is meant for the AI app and says what was refused and what to do
 * instead.
 */
final class Account_Access_Refused extends \RuntimeException implements Refusal {
}

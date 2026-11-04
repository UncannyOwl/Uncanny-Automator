<?php
/**
 * A change to a setting that belongs to the site owner, refused.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\App\Site_Commands\Domain\Refusal;

/**
 * Thrown by Mcp_Settings_Guard before WordPress updates, adds or deletes an
 * option that AI work may not change. Its message is meant for the AI app.
 */
final class Protected_Setting_Refused extends \RuntimeException implements Refusal {
}

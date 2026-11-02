<?php
/**
 * A refusal raised by the site's own safeguards while a command runs.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain;

/**
 * An exception whose message is written for the caller, such as a setting
 * that belongs to the site owner. A command that meets one reports its
 * message as it is.
 */
interface Refusal extends \Throwable {
}

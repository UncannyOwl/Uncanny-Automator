<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Addons;

use Exception;

/**
 * Raised when the signed addon zip URL cannot be obtained.
 *
 * The code carries the failure class the caller branches on: 400 for a
 * request this site cannot make, 500 for a transport failure, 422 when the
 * store answered but refused.
 */
final class Addon_Download_Exception extends Exception {

}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Settings;

/**
 * Raised when a settings request crosses the supported content-type boundary.
 */
final class InvalidContentTypeSelectionException extends \InvalidArgumentException
{
}

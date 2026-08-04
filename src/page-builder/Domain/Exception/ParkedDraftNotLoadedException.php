<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

/**
 * A hidden human-saved draft must be loaded before another writer edits it.
 */
final class ParkedDraftNotLoadedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The newer parked draft has not been loaded.');
    }
}

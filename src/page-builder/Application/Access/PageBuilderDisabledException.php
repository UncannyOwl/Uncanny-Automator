<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Access;

final class PageBuilderDisabledException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Uncanny Page Builder is disabled. Enable it in Automator settings to create a new Page Builder page.',
        );
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Section;

final class SanitizedSectionSource
{
    /**
     * @param string[] $warnings
     */
    public function __construct(
        private readonly string $html,
        private readonly string $css,
        private readonly array $warnings = [],
    ) {}

    public function html(): string
    {
        return $this->html;
    }

    public function css(): string
    {
        return $this->css;
    }

    /**
     * @return string[]
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Shell;

final class ShellModeContext
{
    public function __construct(
        public readonly ShellMode $mode,
        public readonly bool $hasUncannyHeader,
        public readonly bool $hasUncannyFooter,
        public readonly bool $isExplicit,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'mode'                => $this->mode->value,
            'mode_label'          => $this->mode->label(),
            'has_uncanny_header'  => $this->hasUncannyHeader,
            'has_uncanny_footer'  => $this->hasUncannyFooter,
            'is_explicit'         => $this->isExplicit,
        ];
    }
}

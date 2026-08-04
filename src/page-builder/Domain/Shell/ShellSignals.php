<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Shell;

final class ShellSignals
{
    public function __construct(
        public readonly bool $hasUncannyHeader,
        public readonly bool $hasUncannyFooter,
        public readonly bool $isBlockTheme,
        public readonly string $activeThemeName,
        public readonly ShellProvider $provider = ShellProvider::Theme,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'has_uncanny_header' => $this->hasUncannyHeader,
            'has_uncanny_footer' => $this->hasUncannyFooter,
            'is_block_theme'     => $this->isBlockTheme,
            'active_theme_name'  => $this->activeThemeName,
            'provider'           => $this->provider->value,
            'provider_label'     => $this->provider->label(),
        ];
    }
}

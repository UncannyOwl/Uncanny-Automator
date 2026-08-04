<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Shell;

enum ShellMode: string
{
    case UncannyNative    = 'uncanny_native';
    case ThemeComposition = 'theme_composition';
    case None             = 'none';

    public function label(): string
    {
        return match ($this) {
            self::UncannyNative    => 'Build the full page with Uncanny Page Builder',
            self::ThemeComposition => "Use website's header and footer",
            self::None             => 'Choose how this page should work',
        };
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Shell;

enum ShellProvider: string
{
    case Uncanny         = 'uncanny';
    case Theme           = 'theme';
    case Elementor       = 'elementor';
    case UnknownExternal = 'unknown_external';

    public function label(): string
    {
        return match ($this) {
            self::Uncanny         => 'Uncanny Page Builder',
            self::Theme           => 'Active Theme',
            self::Elementor       => 'Elementor',
            self::UnknownExternal => 'Unknown External',
        };
    }
}

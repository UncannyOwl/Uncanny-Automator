<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Controls;

enum ControlZone: string
{
    case Navigation = 'navigation';
    case History = 'history';
    case Identity = 'identity';
    case Document = 'document';
    case Viewport = 'viewport';
    case Tools = 'tools';

    /** @return string[] */
    public static function orderedValues(): array
    {
        return [
            self::Navigation->value,
            self::History->value,
            self::Identity->value,
            self::Document->value,
            self::Viewport->value,
            self::Tools->value,
        ];
    }
}

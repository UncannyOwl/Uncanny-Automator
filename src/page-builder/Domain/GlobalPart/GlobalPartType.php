<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\GlobalPart;

enum GlobalPartType: string
{
    case Header  = 'header';
    case Footer  = 'footer';
    case Section = 'section';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Section;
    }

    /** @return string[] */
    public static function validValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}

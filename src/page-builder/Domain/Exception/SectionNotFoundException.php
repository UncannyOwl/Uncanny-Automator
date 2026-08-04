<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class SectionNotFoundException extends \RuntimeException
{
    public static function withId(int $id): self
    {
        return new self("Section with ID {$id} not found.");
    }

    public static function withPosition(int $position): self
    {
        return new self("Section at position {$position} not found.");
    }
}

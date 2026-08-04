<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class ShellHtmlTooLargeException extends \RuntimeException
{
    public function __construct(
        private readonly string $field,
        private readonly int $size,
        private readonly int $maxSize,
    ) {
        parent::__construct(sprintf(
            '%s exceeds the %d byte shell HTML analysis limit.',
            $field,
            $maxSize,
        ));
    }

    public function field(): string
    {
        return $this->field;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function maxSize(): int
    {
        return $this->maxSize;
    }
}

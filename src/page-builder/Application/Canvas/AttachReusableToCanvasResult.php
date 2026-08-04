<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Domain\Canvas\Canvas;

final class AttachReusableToCanvasResult
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        private readonly Canvas $canvas,
        private readonly int $reusableId,
        private readonly string $reusableTitle,
        private readonly string $reusableType,
        private readonly int $sectionId,
        private readonly int $position,
        private readonly string $sectionName,
        private readonly string $previewUrl,
        private readonly array $warnings = [],
    ) {}

    public function canvas(): Canvas
    {
        return $this->canvas;
    }

    public function reusableId(): int
    {
        return $this->reusableId;
    }

    public function reusableTitle(): string
    {
        return $this->reusableTitle;
    }

    public function reusableType(): string
    {
        return $this->reusableType;
    }

    public function sectionId(): int
    {
        return $this->sectionId;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function sectionName(): string
    {
        return $this->sectionName;
    }

    public function previewUrl(): string
    {
        return $this->previewUrl;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}

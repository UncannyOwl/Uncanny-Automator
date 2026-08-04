<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Reusable;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

final class Reusable
{
    public function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly GlobalPartType $type,
        private readonly string $status,
        private readonly string $editorUrl,
        private readonly bool $hasSource,
        private readonly ?int $sourceSectionId = null,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function type(): GlobalPartType
    {
        return $this->type;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function editorUrl(): string
    {
        return $this->editorUrl;
    }

    public function hasSource(): bool
    {
        return $this->hasSource;
    }

    public function sourceSectionId(): ?int
    {
        return $this->sourceSectionId;
    }
}

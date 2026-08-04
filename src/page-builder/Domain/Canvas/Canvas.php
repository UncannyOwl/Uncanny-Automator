<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Canvas;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Shell\ShellMode;

final class Canvas
{
    public function __construct(
        private readonly int $id,
        private readonly CanvasKind $kind,
        private readonly string $title,
        private readonly string $status,
        private readonly bool $owned,
        private readonly string $editorUrl,
        private readonly string $previewUrl,
        private readonly ?ShellMode $shellMode = null,
        private readonly ?GlobalPartType $globalPartType = null,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function kind(): CanvasKind
    {
        return $this->kind;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function owned(): bool
    {
        return $this->owned;
    }

    public function editorUrl(): string
    {
        return $this->editorUrl;
    }

    public function previewUrl(): string
    {
        return $this->previewUrl;
    }

    public function shellMode(): ?ShellMode
    {
        return $this->shellMode;
    }

    public function globalPartType(): ?GlobalPartType
    {
        return $this->globalPartType;
    }
}

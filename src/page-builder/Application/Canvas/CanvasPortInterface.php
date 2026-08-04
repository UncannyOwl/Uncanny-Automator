<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Domain\Canvas\Canvas;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Domain\Canvas\CanvasKind;

interface CanvasPortInterface
{
    /**
     * @return list<Canvas>
     */
    public function list(?CanvasKind $kind = null): array;

    public function find(int $canvasId): ?Canvas;

    public function createPage(string $title): Canvas;

    public function createGlobalPart(string $title, GlobalPartType $type): Canvas;

    public function updatePage(
        int $canvasId,
        ?string $title,
        ?ShellMode $shellMode,
    ): Canvas;

    public function updateGlobalPart(int $canvasId, ?string $title): Canvas;

    public function deletePage(int $canvasId, bool $forceDelete): DeleteCanvasResult;

    public function deleteGlobalPart(int $canvasId, bool $forceDelete): DeleteCanvasResult;

    public function attachReusableToPage(int $canvasId, int $reusableId): AttachReusableToCanvasResult;
}

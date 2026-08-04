<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Domain\Canvas\Canvas;
use UncannyPageBuilder\Domain\Canvas\CanvasKind;
use UncannyPageBuilder\Domain\Exception\CanvasNotFoundException;

final class EditCanvasUseCase
{
    public function __construct(private readonly CanvasPortInterface $canvasPort) {}

    public function __invoke(EditCanvasCommand $command): Canvas
    {
        $canvas = $this->canvasPort->find($command->canvasId());
        if (!$canvas instanceof Canvas) {
            throw new CanvasNotFoundException($command->canvasId());
        }

        return match ($canvas->kind()) {
            CanvasKind::Page => $this->canvasPort->updatePage(
                $canvas->id(),
                $command->title(),
                $command->shellMode(),
            ),
            CanvasKind::GlobalPart => $this->updateGlobalPart($canvas->id(), $command),
        };
    }

    private function updateGlobalPart(int $canvasId, EditCanvasCommand $command): Canvas
    {
        if ($command->shellMode() !== null) {
            throw new \InvalidArgumentException('Reusable canvases do not support shell mode updates.');
        }

        return $this->canvasPort->updateGlobalPart($canvasId, $command->title());
    }
}

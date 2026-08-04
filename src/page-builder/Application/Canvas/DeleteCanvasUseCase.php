<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Domain\Canvas\Canvas;
use UncannyPageBuilder\Domain\Canvas\CanvasKind;
use UncannyPageBuilder\Domain\Exception\CanvasNotFoundException;

final class DeleteCanvasUseCase
{
    public function __construct(private readonly CanvasPortInterface $canvasPort) {}

    public function __invoke(DeleteCanvasCommand $command): DeleteCanvasResult
    {
        $canvas = $this->canvasPort->find($command->canvasId());
        if (!$canvas instanceof Canvas) {
            throw new CanvasNotFoundException($command->canvasId());
        }

        return match ($canvas->kind()) {
            CanvasKind::Page => $this->canvasPort->deletePage(
                $canvas->id(),
                $command->forceDelete(),
            ),
            CanvasKind::GlobalPart => $this->canvasPort->deleteGlobalPart(
                $canvas->id(),
                $command->forceDelete(),
            ),
        };
    }
}

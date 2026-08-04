<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Domain\Canvas\CanvasKind;
use UncannyPageBuilder\Domain\Exception\CanvasNotFoundException;

final class AttachReusableToCanvasUseCase
{
    public function __construct(private readonly CanvasPortInterface $canvasPort) {}

    public function __invoke(AttachReusableToCanvasCommand $command): AttachReusableToCanvasResult
    {
        $canvas = $this->canvasPort->find($command->canvasId());
        if ($canvas === null) {
            throw new CanvasNotFoundException($command->canvasId());
        }

        if ($canvas->kind() !== CanvasKind::Page) {
            throw new \InvalidArgumentException('Reusable sections can only be attached to page canvases.');
        }

        return $this->canvasPort->attachReusableToPage(
            $canvas->id(),
            $command->reusableId(),
        );
    }
}

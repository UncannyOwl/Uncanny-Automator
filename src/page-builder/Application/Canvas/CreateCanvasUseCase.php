<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;
use UncannyPageBuilder\Application\Access\PageBuilderDisabledException;
use UncannyPageBuilder\Domain\Canvas\Canvas;
use UncannyPageBuilder\Domain\Canvas\CanvasKind;

final class CreateCanvasUseCase
{
    public function __construct(
        private readonly CanvasPortInterface $canvasPort,
        private readonly PageBuilderAvailabilityInterface $availability,
    ) {}

    public function __invoke(CreateCanvasCommand $command): Canvas
    {
        if ($command->kind() === CanvasKind::Page && !$this->availability->allowsNewPages()) {
            throw new PageBuilderDisabledException();
        }

        return match ($command->kind()) {
            CanvasKind::Page => $this->canvasPort->createPage($command->title()),
            CanvasKind::GlobalPart => $this->canvasPort->createGlobalPart(
                $command->title(),
                $command->globalPartType(),
            ),
        };
    }
}

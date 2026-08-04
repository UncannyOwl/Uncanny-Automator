<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

final class ListCanvasUseCase
{
    public function __construct(private readonly CanvasPortInterface $canvasPort) {}

    /**
     * @return list<\UncannyPageBuilder\Domain\Canvas\Canvas>
     */
    public function __invoke(ListCanvasQuery $query): array
    {
        return $this->canvasPort->list($query->kind());
    }
}

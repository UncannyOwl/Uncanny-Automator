<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

interface WorkingCanvasUrlPortInterface
{
    public function previewUrl(int $canvasId): string;
}

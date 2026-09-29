<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Canvas\WorkingCanvasUrlPortInterface;

final class WordPressWorkingCanvasUrlPort implements WorkingCanvasUrlPortInterface
{
    public function previewUrl(int $canvasId): string
    {
        return AdminCanvasPage::previewUrl($canvasId);
    }
}

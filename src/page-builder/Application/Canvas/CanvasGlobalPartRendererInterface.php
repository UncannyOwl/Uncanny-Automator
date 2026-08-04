<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

interface CanvasGlobalPartRendererInterface
{
    /**
     * Build the browser-safe snapshot used to refresh one canvas shell part.
     *
     * @param array<string, mixed> $part
     * @return array{post_id: int, type: string, html: string, css: string}
     */
    public function renderGlobalPartSnapshot(array $part): array;
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

/**
 * Provides the exact design-token CSS for the current editor surface.
 */
interface WorkingDesignTokenCssRendererInterface
{
    /**
     * Render the CSS that WordPress applies to the specified editor surface.
     *
     * Returns null when an external WordPress callback prevents a safe
     * projection. The saved design change remains valid in that case.
     */
    public function renderForEditor(int $postId): ?string;
}

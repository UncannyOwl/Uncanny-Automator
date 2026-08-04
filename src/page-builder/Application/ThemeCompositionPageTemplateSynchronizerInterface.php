<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

/**
 * Keeps theme composition pages on a template that exposes the content slot.
 */
interface ThemeCompositionPageTemplateSynchronizerInterface
{
    public function needsPreparation(int $pageId): bool;

    public function prepareForThemeComposition(int $pageId): void;
}

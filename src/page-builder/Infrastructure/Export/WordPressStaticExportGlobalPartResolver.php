<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Export;

use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\Export\StaticExportGlobalPartResolverInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartResolverInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Resolves static-export global parts through the same WordPress state as pages.
 */
final class WordPressStaticExportGlobalPartResolver implements StaticExportGlobalPartResolverInterface
{
    public function __construct(
        private readonly PageGlobalPartResolverInterface $pageParts,
        private readonly ShellModeService $shellMode,
    ) {}

    public function resolveForPage(int $pageId, GlobalPartType $type): ?array
    {
        if ($pageId <= 0 || !$this->supportsType($type) || !$this->pageUsesUncannyShell($pageId)) {
            return null;
        }

        return $this->pageParts->resolveForPage($pageId, $type);
    }

    private function supportsType(GlobalPartType $type): bool
    {
        return $type === GlobalPartType::Header || $type === GlobalPartType::Footer;
    }

    private function pageUsesUncannyShell(int $pageId): bool
    {
        return $this->shellMode->resolveForPage($pageId)->mode === ShellMode::UncannyNative;
    }
}

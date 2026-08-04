<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelection;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Applies one page's working shell and global-part choice atomically.
 *
 * These values form one user-visible layout decision. Keeping them under one
 * page-source mutation prevents a failed selection write from leaving only
 * the shell mode committed, and advances the source generation at most once.
 */
final class UpdatePageLayout
{
    public function __construct(
        private readonly PageSourceMutation $pageSource,
        private readonly ShellModeService $shellModes,
        private readonly PageGlobalPartSelectionService $globalParts,
    ) {}

    public function update(int $pageId, ShellMode $mode, mixed $globalPartPolicy): bool
    {
        $generation = $this->pageSource->generation($pageId);
        $modeContext = $this->shellModes->resolveForPage($pageId);
        $selection = $this->selectionFor($mode, $globalPartPolicy);

        $modeChanged = !$modeContext->isExplicit || $modeContext->mode !== $mode;
        $selectionChanged = $selection instanceof PageGlobalPartSelection
            && !$this->globalParts->selectionForPage($pageId)->equals($selection);

        if (!$modeChanged && !$selectionChanged) {
            return false;
        }

        $this->pageSource->runExpected(
            $pageId,
            $generation,
            function () use ($pageId, $mode, $selection): void {
                $this->shellModes->setForPage($pageId, $mode);

                if ($selection instanceof PageGlobalPartSelection) {
                    $this->globalParts->saveForPage($pageId, $selection);
                }
            },
        );

        return true;
    }

    private function selectionFor(ShellMode $mode, mixed $policy): ?PageGlobalPartSelection
    {
        if ($mode !== ShellMode::UncannyNative || !is_string($policy)) {
            return null;
        }

        return match ($policy) {
            'none' => PageGlobalPartSelection::noParts(),
            'default' => PageGlobalPartSelection::siteDefaults(),
            default => null,
        };
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelectionRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PagePublicationState;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshot;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Captures the complete page-owned editable source at one page generation.
 *
 * Reusable source remains in its own aggregate. The snapshot retains only the
 * selected reusable IDs, so restoring a page never rolls shared content back.
 */
final class CapturePageSourceSnapshot implements PageSourceSnapshotCaptureInterface
{
    public function __construct(
        private readonly SectionService $sections,
        private readonly DesignStandardsService $designStandards,
        private readonly PageJavaScriptRuntimeService $javaScript,
        private readonly PageGlobalPartSelectionRepositoryInterface $globalPartSelections,
        private readonly ShellModeService $shellModes,
    ) {}

    public function capture(
        int $pageId,
        string $sourceRevisionHash,
        int $pageGeneration,
        PagePublicationState $state,
        int $createdBy,
        ?ShellMode $shellMode = null,
        ?bool $shellModeExplicit = null,
    ): PageSourceSnapshot {
        $layout = $this->sections->getLayout($pageId);
        $selection = $this->globalPartSelections->loadForPage($pageId);
        $shellContext = $this->shellModes->resolveForPage($pageId);

        return PageSourceSnapshot::create(
            pageId: $pageId,
            sourceRevisionHash: $sourceRevisionHash,
            pageGeneration: $pageGeneration,
            source: [
                'sections' => is_array($layout['sections'] ?? null) ? $layout['sections'] : [],
                'page_design_overrides' => $this->designStandards->loadPageOverrides($pageId)->toArray(),
                'custom_javascript' => $this->javaScript->readForPage($pageId),
                'title' => $state->draftTitle(),
                'slug' => $state->draftSlug(),
                'shell_mode' => ($shellMode ?? $shellContext->mode)->value,
                'shell_mode_explicit' => $shellModeExplicit ?? $shellContext->isExplicit,
                'header_override_id' => $selection->headerOverrideId(),
                'footer_override_id' => $selection->footerOverrideId(),
            ],
            createdBy: $createdBy,
        );
    }
}

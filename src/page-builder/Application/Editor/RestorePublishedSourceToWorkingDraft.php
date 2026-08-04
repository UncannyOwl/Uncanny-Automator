<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editor;

use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\History\OperationHistoryService;
use UncannyPageBuilder\Application\PageGlobalPartSelectionService;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\DesignStandards\PageDesignOverrides;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelection;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshot;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshotRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Replaces an abandoned working draft with the published editable source.
 *
 * Callers run this inside the same PageSourceMutation as the Manual change set,
 * so a later validation or save failure rolls the replacement back as well.
 */
final class RestorePublishedSourceToWorkingDraft
{
    public function __construct(
        private readonly PageStateRepositoryInterface $states,
        private readonly PageSourceSnapshotRepositoryInterface $snapshots,
        private readonly PageSourceMutation $pageSource,
        private readonly SectionService $sections,
        private readonly DesignStandardsService $designStandards,
        private readonly PageJavaScriptRuntimeService $javaScript,
        private readonly ShellModeService $shellModes,
        private readonly PageGlobalPartSelectionService $globalPartSelections,
        private readonly PageDetailsPortInterface $pageDetails,
        private readonly OperationHistoryService $history,
    ) {}

    public function restore(
        int $pageId,
        int $snapshotId,
        int $userId,
        int $expectedWorkingGeneration,
    ): void {
        if ($pageId <= 0 || $snapshotId <= 0 || $userId <= 0 || $expectedWorkingGeneration < 0) {
            throw new \InvalidArgumentException('A valid published source restore identity is required.');
        }

        $this->pageSource->runExpected(
            $pageId,
            $expectedWorkingGeneration,
            function () use ($pageId, $snapshotId, $userId, $expectedWorkingGeneration): void {
                $state = $this->states->findForPage($pageId);
                if ($state?->publishedSourceSnapshotId() !== $snapshotId) {
                    throw new \RuntimeException('The published source changed. Reload the editor and try again.');
                }

                $snapshot = $this->snapshots->findForPage($pageId, $snapshotId);
                if (!$snapshot instanceof PageSourceSnapshot) {
                    throw new \RuntimeException('The published editable source is unavailable.');
                }
                $source = $snapshot->source();

                $this->sections->restoreFromHistory(
                    $pageId,
                    is_array($source['sections'] ?? null) ? $source['sections'] : [],
                );
                $this->designStandards->savePageOverrides(
                    $pageId,
                    PageDesignOverrides::fromArray(
                        is_array($source['page_design_overrides'] ?? null)
                            ? $source['page_design_overrides']
                            : [],
                    ),
                    $expectedWorkingGeneration,
                );
                $this->javaScript->replaceForPage(
                    $pageId,
                    (string) ($source['custom_javascript'] ?? ''),
                    $userId,
                );

                $shellMode = ShellMode::from((string) ($source['shell_mode'] ?? ShellMode::UncannyNative->value));
                if (($source['shell_mode_explicit'] ?? false) === true) {
                    $this->shellModes->setForPage($pageId, $shellMode);
                } else {
                    $this->shellModes->clearPageOverride($pageId);
                }

                $this->globalPartSelections->saveForPage(
                    $pageId,
                    new PageGlobalPartSelection(
                        $this->partId($source['header_override_id'] ?? null),
                        $this->partId($source['footer_override_id'] ?? null),
                    ),
                );
                $this->pageDetails->update(
                    $pageId,
                    (string) ($source['title'] ?? ''),
                    (string) ($source['slug'] ?? ''),
                    $userId,
                );
                $this->history->discardPageHistory($pageId);
            },
        );
    }

    private function partId(mixed $value): ?int
    {
        return is_int($value) && ($value === -1 || $value > 0) ? $value : null;
    }
}

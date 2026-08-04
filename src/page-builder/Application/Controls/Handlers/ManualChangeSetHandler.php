<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Editor\RestorePublishedSourceToWorkingDraft;
use UncannyPageBuilder\Application\History\HistoryOperationRestorer;
use UncannyPageBuilder\Application\History\HistoryRestoreResult;
use UncannyPageBuilder\Application\History\OperationHistoryService;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\PageGlobalPartSelectionService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Application\Settings\ToolSettingsAccess;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\Publishing\DraftResumePolicy;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelection;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Commits one browser-owned Manual change set under one page generation guard.
 *
 * The handler deliberately composes the existing typed design/content handlers.
 * Their nested writes join PageSourceMutation, so validation failure rolls back
 * the whole set and advances the page generation exactly once on success.
 */
final class ManualChangeSetHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly PageSourceMutation $pageSource,
        private readonly RestorePublishedSourceToWorkingDraft $restorePublishedSource,
        private readonly DesignStyleCommitHandler $designStyles,
        private readonly SectionEditableUpdateHandler $editableContent,
        private readonly SectionNodeUpdateHandler $sectionNodes,
        private readonly SectionRewriteSourceHandler $rewriteSectionSource,
        private readonly SectionService $sections,
        private readonly PageDetailsPortInterface $pageDetails,
        private readonly ShellModeService $shellModes,
        private readonly PageGlobalPartSelectionService $globalPartSelections,
        private readonly PageJavaScriptRuntimeService $javaScript,
        private readonly ToolSettingsAccess $toolSettings,
        private readonly OperationHistoryService $history,
        private readonly HistoryOperationRestorer $historyRestorer,
        private readonly PageStateRepositoryInterface $pageStates,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        if ($request->pageId() <= 0 || $request->globalPartId() > 0) {
            throw new \InvalidArgumentException('Manual page changes require a page canvas.');
        }

        $payload = is_array($request->value()) ? $request->value() : $request->extra();
        $base = is_array($payload['base'] ?? null) ? $payload['base'] : [];
        $expectedGeneration = (int) ($base['working_generation'] ?? -1);
        $loadedSource = trim((string) ($base['loaded_source'] ?? 'working'));
        $snapshotId = (int) ($base['snapshot_id'] ?? 0);
        $designChanges = is_array($payload['design_changes'] ?? null)
            ? array_values(array_filter($payload['design_changes'], 'is_array'))
            : [];
        $contentChanges = is_array($payload['content_changes'] ?? null)
            ? array_values(array_filter($payload['content_changes'], 'is_array'))
            : [];
        $hasSectionLayout = array_key_exists('sections', $payload);
        $sectionLayout = $hasSectionLayout && is_array($payload['sections'])
            ? array_values(array_filter($payload['sections'], 'is_array'))
            : [];
        $temporarySectionIds = $hasSectionLayout
            ? $this->temporarySectionIdsByPosition($sectionLayout)
            : [];
        $hasPageDetails = array_key_exists('page_details', $payload);
        $pageDetails = $hasPageDetails && is_array($payload['page_details'])
            ? $payload['page_details']
            : [];
        $hasPageLayout = array_key_exists('page_layout', $payload);
        $pageLayout = $hasPageLayout && is_array($payload['page_layout'])
            ? $payload['page_layout']
            : [];
        $hasCustomJavaScript = array_key_exists('custom_javascript', $payload);
        $customJavaScript = $hasCustomJavaScript && is_string($payload['custom_javascript'])
            ? $payload['custom_javascript']
            : '';
        $hasHistoryTransition = array_key_exists('history_transition', $payload);
        $historyTransition = $hasHistoryTransition && is_array($payload['history_transition'])
            ? $payload['history_transition']
            : [];
        $historyOperationId = filter_var(
            $historyTransition['operation_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        $historyDirection = is_string($historyTransition['direction'] ?? null)
            ? trim($historyTransition['direction'])
            : '';
        $historyBaseGeneration = filter_var(
            $historyTransition['base_generation'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]],
        );
        $resumePolicy = DraftResumePolicy::tryFrom(
            trim((string) ($payload['draft_resume_policy'] ?? 'active')),
        );

        if (
            $expectedGeneration < 0
            || !in_array($loadedSource, ['working', 'published'], true)
            || !$resumePolicy instanceof DraftResumePolicy
        ) {
            throw new \InvalidArgumentException('Manual changes require a valid editor source identity.');
        }
        if ($loadedSource === 'published' && $snapshotId <= 0) {
            throw new \InvalidArgumentException('Published Manual changes require a source snapshot.');
        }
        if ($hasSectionLayout && !is_array($payload['sections'])) {
            throw new \InvalidArgumentException('Manual section layout must be an array.');
        }
        if ($hasSectionLayout) {
            $sectionLayout = $this->normalizeManualSectionLayout($sectionLayout);
        }
        if (
            $hasPageDetails
            && (
                !is_array($payload['page_details'])
                || !is_string($pageDetails['title'] ?? null)
                || !is_string($pageDetails['slug'] ?? null)
            )
        ) {
            throw new \InvalidArgumentException('Manual page details require title and slug strings.');
        }
        $layoutMode = $hasPageLayout && is_string($pageLayout['mode'] ?? null)
            ? ShellMode::tryFrom($pageLayout['mode'])
            : null;
        if (
            $hasPageLayout
            && (
                !is_array($payload['page_layout'])
                || !$layoutMode instanceof ShellMode
                || !is_bool($pageLayout['mode_explicit'] ?? null)
                || !$this->validPartOverride($pageLayout['header_override_id'] ?? null)
                || !$this->validPartOverride($pageLayout['footer_override_id'] ?? null)
            )
        ) {
            throw new \InvalidArgumentException('Manual page layout is invalid.');
        }
        if ($hasCustomJavaScript && !is_string($payload['custom_javascript'])) {
            throw new \InvalidArgumentException('Manual page JavaScript must be a string.');
        }
        if (
            $hasCustomJavaScript
            && (
                !$request->context()->canEditCustomJavaScript()
                || !$this->toolSettings->pageCustomJavaScriptEnabled()
            )
        ) {
            throw new \InvalidArgumentException('Custom JavaScript editing is not available.');
        }
        if ($hasCustomJavaScript && strlen($customJavaScript) > PageJavaScriptRuntimeService::MAX_SOURCE_BYTES) {
            throw new \InvalidArgumentException(sprintf(
                'Custom JavaScript source exceeds the %d byte limit.',
                PageJavaScriptRuntimeService::MAX_SOURCE_BYTES,
            ));
        }
        if (
            $hasHistoryTransition
            && (
                !is_array($payload['history_transition'])
                || $loadedSource !== 'working'
                || $historyOperationId === false
                || !in_array($historyDirection, ['undo', 'redo'], true)
                || $historyBaseGeneration === false
                || $historyBaseGeneration !== $expectedGeneration
            )
        ) {
            throw new \InvalidArgumentException('Manual history requires its visible working-source identity.');
        }
        $hasOtherChanges = $designChanges !== []
            || $contentChanges !== []
            || $hasSectionLayout
            || $hasPageDetails
            || $hasPageLayout
            || $hasCustomJavaScript;
        if (
            !$hasOtherChanges
            && !$hasHistoryTransition
        ) {
            throw new \InvalidArgumentException('At least one Manual change is required.');
        }
        $this->assertPageOwnedChanges($designChanges, $contentChanges);

        $commit = function () use (
            $request,
            $loadedSource,
            $snapshotId,
            $expectedGeneration,
            $designChanges,
            $contentChanges,
            $hasSectionLayout,
            $sectionLayout,
            $temporarySectionIds,
            $hasPageDetails,
            $pageDetails,
            $hasPageLayout,
            $pageLayout,
            $layoutMode,
            $hasCustomJavaScript,
            $customJavaScript,
            $hasHistoryTransition,
            $historyOperationId,
            $historyDirection,
            $hasOtherChanges,
            $resumePolicy,
        ): array {
                $historyResult = null;
            if ($hasHistoryTransition) {
                $transition = $this->history->applyPreviewedPageTransition(
                    pageId: $request->pageId(),
                    direction: $historyDirection,
                    operationId: (int) $historyOperationId,
                    expectedGeneration: $expectedGeneration,
                    restore: fn($entry): HistoryRestoreResult => $this->historyRestorer->restore(
                        $entry,
                        $historyDirection === 'undo',
                        $request->userId(),
                    ),
                );
                $historyResult = [
                    'operation_id' => $transition['entry']->id(),
                    'direction' => $historyDirection,
                    'operation' => $transition['entry']->operation(),
                ];

                if ($hasOtherChanges) {
                    $this->history->discardRedoablePageBranch($request->pageId());
                }
            }

            if ($loadedSource === 'published') {
                $this->restorePublishedSource->restore(
                    pageId: $request->pageId(),
                    snapshotId: $snapshotId,
                    userId: $request->userId(),
                    expectedWorkingGeneration: $expectedGeneration,
                );
            }

                $sectionResult = null;
                $appliedDesignChanges = $designChanges;
                $appliedContentChanges = $contentChanges;
            if ($hasSectionLayout) {
                $sectionResult = $this->sections->restoreFromHistory(
                    $request->pageId(),
                    $sectionLayout,
                );
                $savedSections = is_array($sectionResult['sections'] ?? null)
                    ? array_values(array_filter($sectionResult['sections'], 'is_array'))
                    : [];
                [$appliedDesignChanges, $appliedContentChanges] = $this->remapTemporarySectionChanges(
                    $appliedDesignChanges,
                    $appliedContentChanges,
                    $this->mapTemporarySectionIds($temporarySectionIds, $savedSections),
                );
                [$appliedDesignChanges, $appliedContentChanges] = $this->changesForRetainedSections(
                    $appliedDesignChanges,
                    $appliedContentChanges,
                    $savedSections,
                );
            } else {
                [$appliedDesignChanges, $appliedContentChanges] = $this->remapTemporarySectionChanges(
                    $appliedDesignChanges,
                    $appliedContentChanges,
                    [],
                );
            }

                $pageDetailsResult = null;
            if ($hasPageDetails) {
                $pageDetailsResult = $this->pageDetails->update(
                    $request->pageId(),
                    $pageDetails['title'],
                    $pageDetails['slug'],
                    max(0, $request->userId()),
                )->toArray();
            }

                $pageLayoutResult = null;
            if ($hasPageLayout && $layoutMode instanceof ShellMode) {
                if ($pageLayout['mode_explicit']) {
                    $this->shellModes->setForPage($request->pageId(), $layoutMode);
                } else {
                    $this->shellModes->clearPageOverride($request->pageId());
                }
                $this->globalPartSelections->saveForPage(
                    $request->pageId(),
                    new PageGlobalPartSelection(
                        $this->partOverride($pageLayout['header_override_id']),
                        $this->partOverride($pageLayout['footer_override_id']),
                    ),
                );
                $pageLayoutResult = [
                    'mode' => $layoutMode->value,
                    'mode_explicit' => $pageLayout['mode_explicit'],
                    'header_override_id' => $pageLayout['header_override_id'],
                    'footer_override_id' => $pageLayout['footer_override_id'],
                ];
            }

                $javaScriptResult = null;
            if ($hasCustomJavaScript) {
                $javaScriptResult = $this->javaScript->replaceForPage(
                    $request->pageId(),
                    $customJavaScript,
                    max(0, $request->userId()),
                );
            }

                $designResult = null;
            if ($appliedDesignChanges !== []) {
                $designResult = $this->designStyles->__invoke(new ControlInvokeRequest(
                    controlId: 'design.style.commit',
                    context: $request->context(),
                    value: ['changes' => $appliedDesignChanges],
                ))->toArray();
                $designData = is_array($designResult['data'] ?? null) ? $designResult['data'] : [];
                if (($designData['status'] ?? null) === 'error') {
                    throw new \RuntimeException(
                        (string) ($designData['message'] ?? 'The Manual design changes could not be saved.'),
                    );
                }
            }

                $contentResults = [];
            foreach ($appliedContentChanges as $change) {
                $commandId = trim((string) ($change['command_id'] ?? ''));
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $handler = match ($commandId) {
                    'section.editable.update' => $this->editableContent,
                    'section.node.update' => $this->sectionNodes,
                    'section.rewrite_source' => $this->rewriteSectionSource,
                    default => throw new \InvalidArgumentException('Unsupported Manual content change.'),
                };
                    $contentResults[] = $handler->__invoke(new ControlInvokeRequest(
                        controlId: $commandId,
                        context: $request->context(),
                        value: $value,
                    ))->toArray();
            }

                $this->pageStates->saveDraftResumePolicy(
                    $request->pageId(),
                    $resumePolicy,
                );

                return [
                    'history' => $historyResult,
                    'sections' => $sectionResult,
                    'page_details' => $pageDetailsResult,
                    'page_layout' => $pageLayoutResult,
                    'custom_javascript' => $javaScriptResult,
                    'design' => $designResult,
                    'content' => $contentResults,
                ];
        };
        $result = $hasHistoryTransition
            ? $this->pageSource->runHistoryExpected(
                $request->pageId(),
                $expectedGeneration,
                $commit,
            )
            : $this->pageSource->runExpected(
                $request->pageId(),
                $expectedGeneration,
                $commit,
            );

        // PageSourceMutation owns the compare-and-swap and advances exactly
        // once. Do not perform an unlocked read after commit: an Agent may
        // legitimately advance the page again before this response is built.
        $nextGeneration = $expectedGeneration + 1;

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Manual changes saved',
            data: [
                'working_generation' => $nextGeneration,
                'changes' => $result,
            ],
        );
    }

    /**
     * Global and reusable source have independent lifecycles and cannot join a
     * page-source rollback boundary.
     *
     * @param array<int, array<string, mixed>> $designChanges
     * @param array<int, array<string, mixed>> $contentChanges
     */
    private function assertPageOwnedChanges(array $designChanges, array $contentChanges): void
    {
        foreach ($designChanges as $change) {
            if (
                ($change['scope'] ?? null) === 'global'
                || (($change['owner']['kind'] ?? null) === 'global_part')
            ) {
                throw new \InvalidArgumentException('Save reusable and sitewide design changes separately.');
            }
        }
        foreach ($contentChanges as $change) {
            $value = is_array($change['value'] ?? null) ? $change['value'] : [];
            if (($value['owner']['kind'] ?? null) === 'global_part') {
                throw new \InvalidArgumentException('Save reusable content changes separately.');
            }
        }
    }

    /**
     * A later structural delete supersedes earlier pending edits inside the
     * deleted section. Surviving section edits still run after the aggregate
     * replacement so the final source contains both operations.
     *
     * @param array<int, array<string, mixed>> $designChanges
     * @param array<int, array<string, mixed>> $contentChanges
     * @param array<int, array<string, mixed>>|null $sectionLayout
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function changesForRetainedSections(
        array $designChanges,
        array $contentChanges,
        ?array $sectionLayout,
    ): array {
        if ($sectionLayout === null) {
            return [$designChanges, $contentChanges];
        }

        $retained = [];
        foreach ($sectionLayout as $section) {
            $sectionId = (int) ($section['id'] ?? 0);
            if ($sectionId > 0) {
                $retained[$sectionId] = true;
            }
        }

        $designChanges = array_values(array_filter(
            $designChanges,
            static function (array $change) use ($retained): bool {
                $owner = is_array($change['owner'] ?? null) ? $change['owner'] : [];
                $target = is_array($change['target'] ?? null) ? $change['target'] : [];
                $targetOwner = is_array($target['owner'] ?? null) ? $target['owner'] : [];
                $sectionId = (int) (
                    $owner['section_id']
                    ?? $targetOwner['section_id']
                    ?? $target['section_id']
                    ?? 0
                );

                return $sectionId <= 0 || isset($retained[$sectionId]);
            },
        ));
        $contentChanges = array_values(array_filter(
            $contentChanges,
            static function (array $change) use ($retained): bool {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $owner = is_array($value['owner'] ?? null) ? $value['owner'] : [];
                $sectionId = (int) ($owner['section_id'] ?? $value['section_id'] ?? 0);

                return $sectionId <= 0 || isset($retained[$sectionId]);
            },
        ));

        return [$designChanges, $contentChanges];
    }

    private function validPartOverride(mixed $value): bool
    {
        return $value === null || (is_int($value) && ($value === -1 || $value > 0));
    }

    private function partOverride(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @return array<int, int> Browser-only section ID indexed by layout position.
     */
    private function temporarySectionIdsByPosition(array $sections): array
    {
        $temporaryIds = [];
        $seen = [];
        foreach ($sections as $position => $section) {
            $id = is_int($section['id'] ?? null) ? $section['id'] : 0;
            if ($id >= 0) {
                continue;
            }
            if (isset($seen[$id])) {
                throw new \InvalidArgumentException('Manual section layout contains duplicate temporary IDs.');
            }
            $seen[$id] = true;
            $temporaryIds[$position] = $id;
        }

        return $temporaryIds;
    }

    /**
     * Pair browser-only IDs with IDs assigned by the guarded aggregate save.
     *
     * @param array<int, int> $temporaryIds
     * @param array<int, array<string, mixed>> $savedSections
     * @return array<int, int>
     */
    private function mapTemporarySectionIds(array $temporaryIds, array $savedSections): array
    {
        $mapped = [];
        foreach ($temporaryIds as $position => $temporaryId) {
            $savedId = (int) ($savedSections[$position]['id'] ?? 0);
            if ($savedId <= 0) {
                throw new \RuntimeException('A new Manual section did not receive a durable ID.');
            }
            $mapped[$temporaryId] = $savedId;
        }

        return $mapped;
    }

    /**
     * Pending edits may target a newly inserted section before it has a database
     * ID. Rewrite only the typed section-owner fields after the aggregate save.
     *
     * @param array<int, array<string, mixed>> $designChanges
     * @param array<int, array<string, mixed>> $contentChanges
     * @param array<int, int> $temporaryIdMap
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function remapTemporarySectionChanges(
        array $designChanges,
        array $contentChanges,
        array $temporaryIdMap,
    ): array {
        foreach ($designChanges as &$change) {
            $this->remapSectionIdField($change, 'section_id', $temporaryIdMap);
            if (is_array($change['owner'] ?? null)) {
                $this->remapSectionOwner($change['owner'], $temporaryIdMap);
            }
            if (is_array($change['target'] ?? null)) {
                $this->remapSectionIdField($change['target'], 'section_id', $temporaryIdMap);
                if (is_array($change['target']['owner'] ?? null)) {
                    $this->remapSectionOwner($change['target']['owner'], $temporaryIdMap);
                }
            }
        }
        unset($change);

        foreach ($contentChanges as &$change) {
            if (!is_array($change['value'] ?? null)) {
                continue;
            }
            $this->remapSectionIdField($change['value'], 'section_id', $temporaryIdMap);
            if (is_array($change['value']['owner'] ?? null)) {
                $this->remapSectionOwner($change['value']['owner'], $temporaryIdMap);
            }
        }
        unset($change);

        return [$designChanges, $contentChanges];
    }

    /**
     * @param array<string, mixed> $owner
     * @param array<int, int> $temporaryIdMap
     */
    private function remapSectionOwner(array &$owner, array $temporaryIdMap): void
    {
        if (($owner['kind'] ?? null) !== 'section') {
            return;
        }
        $this->remapSectionIdField($owner, 'section_id', $temporaryIdMap);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<int, int> $temporaryIdMap
     */
    private function remapSectionIdField(array &$record, string $field, array $temporaryIdMap): void
    {
        if (!array_key_exists($field, $record)) {
            return;
        }
        $sectionId = filter_var($record[$field], FILTER_VALIDATE_INT);
        if ($sectionId === false || $sectionId >= 0) {
            return;
        }
        if (!isset($temporaryIdMap[$sectionId])) {
            throw new \InvalidArgumentException('A Manual change references an unknown temporary section.');
        }
        $record[$field] = $temporaryIdMap[$sectionId];
    }

    /**
     * Negative IDs exist only in the browser so newly staged sections can be
     * painted and targeted before persistence. Convert them to new aggregate
     * members and reject duplicate durable IDs before SectionService writes.
     *
     * @param array<int, array<string, mixed>> $sections
     * @return array<int, array<string, mixed>>
     */
    private function normalizeManualSectionLayout(array $sections): array
    {
        $durableIds = [];
        foreach ($sections as $index => &$section) {
            $id = is_int($section['id'] ?? null) ? $section['id'] : null;
            if ($id !== null && $id > 0) {
                if (isset($durableIds[$id])) {
                    throw new \InvalidArgumentException('Manual section layout contains duplicate section IDs.');
                }
                $durableIds[$id] = true;
            } else {
                $section['id'] = null;
            }
            $section['position'] = $index;
        }
        unset($section);

        return $sections;
    }
}

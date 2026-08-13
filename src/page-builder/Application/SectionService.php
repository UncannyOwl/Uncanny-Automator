<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Application\History\OperationHistoryService;
use UncannyPageBuilder\Application\History\SectionHistoryRestorerInterface;
use UncannyPageBuilder\Domain\Compiler\CssContractWarningDetector;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshQueueInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\Section\SectionPostCommitFailureReporterInterface;
use UncannyPageBuilder\Application\Section\SectionSourceSanitizerInterface;
use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\Exception\BindingContractUpdateException;
use UncannyPageBuilder\Domain\Exception\BindingTargetNotFoundException;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;
use UncannyPageBuilder\Domain\Exception\HistorySnapshotConflictException;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Section\BindingTargetReference;
use UncannyPageBuilder\Domain\Section\CopiedSectionIdentityRemapper;
use UncannyPageBuilder\Domain\Section\HtmlCssProcessor;
use UncannyPageBuilder\Domain\Section\LucideIconValidator;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionCollection;
use UncannyPageBuilder\Domain\Section\SectionContent;
use UncannyPageBuilder\Domain\Section\SectionEditProposal;
use UncannyPageBuilder\Domain\Section\SectionEditResult;
use UncannyPageBuilder\Domain\Section\SectionManifestExtractorInterface;
use UncannyPageBuilder\Application\DesignStyles\SectionSourceWriter;
use UncannyPageBuilder\Domain\Section\SectionEventDispatcherInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

final class SectionService implements SectionSourceWriter, SectionHistoryRestorerInterface
{
    public function __construct(
        private readonly SectionRepositoryInterface $repository,
        private readonly ShadowCompiler $compiler,
        private readonly BindingContractReplacementService $bindingContractReplacementService,
        private readonly SectionManifestExtractorInterface $manifestExtractor,
        private readonly SectionSourceSanitizerInterface $sourceSanitizer,
        private readonly HtmlCssProcessor $htmlCssProcessor,
        private readonly ?SectionEventDispatcherInterface $events = null,
        private readonly ?OperationHistoryService $history = null,
        private readonly ?WorkingCanvasRefresherInterface $workingCanvas = null,
        private readonly ?LucideIconValidator $lucideIconValidator = null,
        private readonly ?WorkingCanvasRefreshQueueInterface $workingCanvasRefreshQueue = null,
        private readonly ?SectionPostCommitFailureReporterInterface $postCommitFailureReporter = null,
    ) {}

    /**
     * Append or edit a section on a page.
     *
     * @param int         $pageId
     * @param string      $sectionName
     * @param array       $content  ['html' => string, 'css' => string]
     * @param string|null $action   'edit_section' or null (append)
     * @param int|null    $sectionId  Required when action is 'edit_section'
     *
     * @return array{page_id: int, sections: int, preview: string, warnings: string[]}
     *
     * @throws PageNotFoundException
     * @throws \UncannyPageBuilder\Domain\Exception\SectionNotFoundException
     */
    public function create(
        int $pageId,
        string $sectionName,
        array $content,
        ?string $action = null,
        ?int $sectionId = null,
    ): array {
        if (!$this->repository->pageExists($pageId)) {
            throw new PageNotFoundException($pageId);
        }

        $sections = $this->repository->findByPageId($pageId);
        $before = $sections->toArray();

        $warnings = [];

        if ($action === 'edit_section' && $sectionId !== null) {
            $existing = $sections->getById($sectionId);
            $sectionContent = $this->sanitizeExistingContent(
                SectionContent::fromSourceUpdate($content, $existing->content()),
                $existing->content(),
                $warnings,
            );
            $newSection = Section::create(
                $pageId,
                $existing->position(),
                $sectionName ?: $existing->name(),
                $sectionContent,
            );

            $newSection->assignId($sectionId);
            $sections->replaceById($sectionId, $newSection);
        } else {
            $sectionContent = CopiedSectionIdentityRemapper::remapCollisions(
                SectionContent::fromArray($content),
                $sections,
                $pageId . ':' . $sections->count(),
            );
            $sectionContent = $this->sanitizeContent($sectionContent, $warnings);
            $newSection = Section::create(
                $pageId,
                $sections->count(),
                $sectionName ?: 'Section ' . ($sections->count() + 1),
                $sectionContent,
            );

            $sections->append($newSection);
        }

        // Compile and persist atomically
        $compiled = $this->compiler->compile($sections);
        $this->persistPageOperation(
            $pageId,
            $action === 'edit_section' ? 'section.update' : 'section.create',
            $action === 'edit_section' ? 'Updated section' : 'Created section',
            $before,
            $sections,
            fn(): mixed => $this->repository->save($pageId, $sections, $compiled),
        );
        $this->repository->markAsEnginePage($pageId);
        $this->refreshWorkingCanvas($pageId);

        $savedId = $newSection->id() ?? 0;
        $this->dispatchSectionSaved($pageId, $savedId, $action === 'edit_section' ? 'edited' : 'created');

        return [
            'page_id'  => $pageId,
            'sections' => $sections->count(),
            'preview'  => $this->repository->getPermalink($pageId),
            'warnings' => $warnings,
        ];
    }

    /**
     * Persist a section source update against an already-loaded page collection.
     *
     * Element-level Design Lens writes resolve their target from one in-memory
     * snapshot. Saving that same collection avoids recomputing the patch against
     * a different page load.
     *
     * @param array{html: string, css: string, element_styles?: array<string, mixed>} $content
     * @return array{section_id: int, warnings: string[]}
     */
    public function replaceLoadedSectionSource(
        int $pageId,
        SectionCollection $sections,
        int $sectionId,
        string $sectionName,
        array $content,
        bool $requireExactCss = false,
        bool $normalizePatchedHtml = false,
    ): array {
        if ($normalizePatchedHtml && array_key_exists('html', $content)) {
            $content['html'] = $this->htmlCssProcessor->normalizePatchedHtml((string) $content['html']);
        }

        $result = $this->replaceLoadedSectionSources($pageId, $sections, [[
            'section_id'   => $sectionId,
            'section_name' => $sectionName,
            'content'      => $content,
        ]], $requireExactCss);

        return [
            'section_id' => $sectionId,
            'warnings'   => $result['warnings'] ?? [],
        ];
    }

    /**
     * Persist multiple section source updates against one already-loaded page
     * collection. This is the section-element design stack path: all selected
     * targets mutate one in-memory page draft, then the page sections are saved
     * once.
     *
     * @param array<int, array{
     *     section_id: int,
     *     section_name: string,
     *     content: array{html: string, css: string, element_styles?: array<string, mixed>}
     * }> $updates
     * @return array{warnings: string[]}
     */
    public function replaceLoadedSectionSources(
        int $pageId,
        SectionCollection $sections,
        array $updates,
        bool $requireExactCss = false,
    ): array {
        if (!$this->repository->pageExists($pageId)) {
            throw new PageNotFoundException($pageId);
        }
        if ($updates === []) {
            throw new \InvalidArgumentException('At least one section update is required.');
        }

        $before = $sections->toArray();

        $warnings = [];
        $savedSectionIds = [];
        foreach ($updates as $update) {
            $sectionId = (int) ($update['section_id'] ?? 0);
            $sectionName = (string) ($update['section_name'] ?? '');
            $content = is_array($update['content'] ?? null) ? $update['content'] : [];

            $existing = $sections->getById($sectionId);

            $requestedContent = SectionContent::fromSourceUpdate($content, $existing->content());
            $sectionContent = $this->sanitizeExistingContent(
                $requestedContent,
                $existing->content(),
                $warnings,
                $requireExactCss,
            );
            $newSection = Section::create(
                $pageId,
                $existing->position(),
                $sectionName ?: $existing->name(),
                $sectionContent,
            );
            $newSection->assignId($sectionId);

            $sections->replaceById($sectionId, $newSection);
            $savedSectionIds[] = $sectionId;
        }

        $compiled = $this->compiler->compile($sections);
        $this->persistPageOperation(
            $pageId,
            'section.update',
            'Updated section styles',
            $before,
            $sections,
            fn(): mixed => $this->repository->save($pageId, $sections, $compiled),
        );
        $this->repository->markAsEnginePage($pageId);
        $this->refreshWorkingCanvas($pageId);

        foreach ($savedSectionIds as $savedSectionId) {
            $this->dispatchSectionSaved($pageId, (int) $savedSectionId, 'edited');
        }

        return [
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate a section source update without persisting it.
     *
     * This mirrors replaceLoadedSectionSource through page ownership,
     * sanitization, section replacement, and compilation. It
     * deliberately stops before save/history/artifact/event side effects.
     *
     * @param array{html: string, css: string, element_styles?: array<string, mixed>} $content
     * @return array{section_id: int, html: string, css: string, warnings: string[]}
     */
    public function previewLoadedSectionSource(
        int $pageId,
        SectionCollection $sections,
        int $sectionId,
        string $sectionName,
        array $content,
        bool $requireExactCss = false,
    ): array {
        if (!$this->repository->pageExists($pageId)) {
            throw new PageNotFoundException($pageId);
        }

        $existing = $sections->getById($sectionId);

        $warnings = [];
        $requestedContent = SectionContent::fromSourceUpdate($content, $existing->content());
        $sectionContent = $this->sanitizeExistingContent(
            $requestedContent,
            $existing->content(),
            $warnings,
            $requireExactCss,
        );
        $newSection = Section::create(
            $pageId,
            $existing->position(),
            $sectionName ?: $existing->name(),
            $sectionContent,
        );
        $newSection->assignId($sectionId);

        // Preview must not consume the generation-bearing collection that a
        // subsequent save uses for its compare-and-swap boundary.
        $previewSections = SectionCollection::fromArray(
            $sections->toArray(),
            $pageId,
            $sections->generation(),
        );
        $previewSections->replaceById($sectionId, $newSection);
        $this->compiler->compile($previewSections);

        return [
            'section_id' => $sectionId,
            'html'       => $sectionContent->html(),
            'css'        => $sectionContent->css(),
            'warnings'   => $warnings,
        ];
    }

    /**
     * Update a single section's HTML (Magic Bridge inline edits).
     *
     * @return array{section_id: int, html: string, warnings: string[]}
     * @throws PageNotFoundException
     * @throws \UncannyPageBuilder\Domain\Exception\SectionNotFoundException
     */
    public function patchHtml(int $pageId, int $sectionId, string $html): array
    {
        if (!$this->repository->pageExists($pageId)) {
            throw new PageNotFoundException($pageId);
        }

        $sections = $this->repository->findByPageId($pageId);
        $before = $sections->toArray();
        $section  = $sections->getById($sectionId);

        $warnings = [];
        $content = $this->sanitizeExistingContent(
            $section->content()->withHtml($this->htmlCssProcessor->normalizePatchedHtml($html)),
            $section->content(),
            $warnings,
        );
        $normalizedHtml = $content->html();
        $section->replaceContent($content);


        $compiled = $this->compiler->compile($sections);
        $this->persistPageOperation(
            $pageId,
            'section.patch_html',
            'Updated editable content',
            $before,
            $sections,
            fn(): mixed => $this->repository->save($pageId, $sections, $compiled),
        );
        $this->refreshWorkingCanvas($pageId);

        $this->dispatchSectionSaved($pageId, $sectionId, 'edited');

        return [
            'section_id' => $sectionId,
            'html'       => $normalizedHtml,
            'warnings'   => $warnings,
        ];
    }

    /**
     * Bulk replace all sections from a direct restore request.
     *
     * @param array $rawSections Raw array from request body.
     * @return array{sections: array, compiled_css: string, warnings: string[]}
     * @throws PageNotFoundException
     */
    public function restore(int $pageId, array $rawSections): array
    {
        return $this->restoreInternal($pageId, $rawSections, true);
    }

    /**
     * Save one browser-owned Manual layout inside its aggregate transaction.
     *
     * @param array $rawSections Raw array from the Manual change set.
     * @return array{sections: array, compiled_css: string, warnings: string[]}
     */
    public function saveManualLayout(int $pageId, array $rawSections): array
    {
        return $this->restoreInternal($pageId, $rawSections, true, null, false);
    }

    /**
     * Restore a history snapshot without recording another operation.
     *
     * @param array $rawSections Raw section snapshot payload.
     * @return array{sections: array, compiled_css: string, warnings: string[]}
     */
    public function restoreFromHistory(int $pageId, array $rawSections, ?array $expectedCurrentSections = null): array
    {
        $currentSections = null;
        if ($expectedCurrentSections !== null) {
            /*
             * Validate and restore against one generation-bearing aggregate.
             * Reloading after validation would rebase the history payload onto a
             * concurrent edit and let replaceAll() accept that newer generation.
             */
            $currentSections = $this->repository->findByPageId($pageId);
            $this->assertHistorySnapshotIsCurrent($currentSections, $expectedCurrentSections);
        }

        return $this->restoreInternal($pageId, $rawSections, false, $currentSections, false);
    }

    /**
     * @param array $rawSections Raw section snapshot payload.
     * @return array{sections: array, compiled_css: string, warnings: string[]}
     */
    private function restoreInternal(
        int $pageId,
        array $rawSections,
        bool $recordHistory,
        ?SectionCollection $currentSections = null,
        bool $completeAfterCommit = true,
    ): array {
        if (!$this->repository->pageExists($pageId)) {
            throw new PageNotFoundException($pageId);
        }

        // A restore is a full aggregate replacement. Carry the generation from
        // the exact snapshot it replaces so a concurrent save is rejected by
        // the repository before any row is overwritten or deleted.
        $currentSections ??= $this->repository->findByPageId($pageId);

        $before = [];
        if ($recordHistory) {
            $this->assertRestoreIdsBelongToCurrentPage($rawSections, $currentSections);
            $before = $currentSections->toArray();
        }

        $sections = SectionCollection::fromArray(
            $rawSections,
            $pageId,
            $currentSections->generation(),
        );
        $warnings = [];
        foreach ($sections->all() as $s) {
            $s->replaceContent($this->sanitizeContent($s->content(), $warnings));
        }
        $compiled = $this->compiler->compile($sections);
        if ($recordHistory) {
            $this->persistPageOperation(
                $pageId,
                'section.restore',
                'Restored layout',
                $before,
                $sections,
                fn(): mixed => $this->repository->replaceAll($pageId, $sections, $compiled),
            );
        } else {
            $this->repository->replaceAll($pageId, $sections, $compiled);
        }

        if ($completeAfterCommit) {
            $this->completeHistoryRestore($pageId, $sections->toArray());
        }

        return [
            'sections'     => $sections->toArray(),
            'compiled_css' => $compiled->minifiedCss(),
            'warnings'     => array_values(array_unique($warnings)),
        ];
    }

    /**
     * Public bulk restore payloads are caller-controlled. They may update
     * existing page sections by ID or create new sections with no ID, but they
     * must not resurrect deleted/foreign rows through replaceAll().
     *
     * @param array<int, array<string, mixed>> $rawSections
     */
    private function assertRestoreIdsBelongToCurrentPage(array $rawSections, SectionCollection $currentSections): void
    {
        $currentIds = [];
        foreach ($currentSections->all() as $section) {
            $id = $section->id();
            if ($id !== null && $id > 0) {
                $currentIds[$id] = true;
            }
        }

        foreach ($rawSections as $rawSection) {
            $id = (int) ($rawSection['id'] ?? 0);
            if ($id > 0 && !isset($currentIds[$id])) {
                throw SectionNotFoundException::withId($id);
            }
        }
    }

    /**
     * History entries are page transitions, not free-form replace requests.
     * Undo/redo may only restore a snapshot when the visible page still matches
     * the operation state it is meant to transition away from.
     *
     * @param array<int, array<string, mixed>> $expectedSections
     */
    private function assertHistorySnapshotIsCurrent(
        SectionCollection $currentSnapshot,
        array $expectedSections,
    ): void {
        $currentSections = $currentSnapshot->toArray();
        $expectedSections = array_values($expectedSections);

        if (count($currentSections) !== count($expectedSections)) {
            throw new HistorySnapshotConflictException();
        }

        foreach ($expectedSections as $index => $expected) {
            $current = $currentSections[$index] ?? [];
            if (
                (int) ($current['id'] ?? 0) !== (int) ($expected['id'] ?? 0)
                || (string) ($current['name'] ?? '') !== (string) ($expected['name'] ?? '')
                || (string) json_encode($current['content'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    !== (string) json_encode($expected['content'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ) {
                throw new HistorySnapshotConflictException();
            }
        }
    }

    /**
     * Reorder sections on a page by supplying an ordered list of section IDs.
     *
     * Every section ID that exists on the page must appear in $sectionIds.
     * The compiled output is regenerated so rendering reflects the new order.
     *
     * @param int   $pageId
     * @param int[] $sectionIds Desired order, first to last.
     *
     * @return array{page_id: int, sections: array}
     *
     * @throws PageNotFoundException
     * @throws \UncannyPageBuilder\Domain\Exception\SectionNotFoundException
     */
    public function reorder(
        int $pageId,
        array $sectionIds,
    ): array {
        if (!$this->repository->pageExists($pageId)) {
            throw new PageNotFoundException($pageId);
        }

        $collection = $this->repository->findByPageId($pageId);
        $before = $collection->toArray();

        $collection->reorderByIds($sectionIds);

        $compiled = $this->compiler->compile($collection);
        $this->persistPageOperation(
            $pageId,
            'section.reorder',
            'Reordered sections',
            $before,
            $collection,
            fn(): mixed => $this->repository->save($pageId, $collection, $compiled),
        );
        $this->refreshWorkingCanvas($pageId);

        $this->dispatchSectionsReordered($pageId);

        $sections = [];
        foreach ($collection->all() as $section) {
            $sections[] = [
                'id'       => $section->id(),
                'position' => $section->position(),
                'name'     => $section->name(),
            ];
        }

        return [
            'page_id' => $pageId,
            'sections' => $sections,
        ];
    }

    /**
     * Delete a section from a page.
     *
     * @return array{page_id: int, sections: int, preview: string}
     *
     * @throws PageNotFoundException
     * @throws \UncannyPageBuilder\Domain\Exception\SectionNotFoundException
     */
    public function delete(int $pageId, int $sectionId): array
    {
        if (!$this->repository->pageExists($pageId)) {
            throw new PageNotFoundException($pageId);
        }

        $sections = $this->repository->findByPageId($pageId);
        $before = $sections->toArray();
        $sections->getById($sectionId);

        $sections->removeById($sectionId);

        $compiled = $this->compiler->compile($sections);

        $this->persistPageOperation(
            $pageId,
            'section.delete',
            'Deleted section',
            $before,
            $sections,
            fn(): mixed => $this->repository->save($pageId, $sections, $compiled),
        );
        $this->refreshWorkingCanvas($pageId);

        $this->dispatchSectionDeleted($pageId, $sectionId);

        return [
            'page_id'  => $pageId,
            'sections' => $sections->count(),
            'preview'  => $this->repository->getPermalink($pageId),
        ];
    }

    /**
     * Validate and apply a structured edit proposal.
     *
     * @throws \UncannyPageBuilder\Domain\Exception\SectionNotFoundException
     * @throws EditableUpdateException
     */
    public function applyProposal(SectionEditProposal $proposal): SectionEditResult
    {
        $sections = $this->repository->findByPageId($proposal->pageId());
        $existing = $sections->getById($proposal->sectionId());

        if ($proposal->isNoOp()) {
            return new SectionEditResult(
                sectionId: $existing->id(),
                pageId: $existing->pageId(),
                position: $existing->position(),
                name: $existing->name(),
                content: $existing->content(),
                manifest: $this->manifestExtractor->extract($existing),
            );
        }

        $content = $this->resolveProposalContent($existing, $proposal);
        $name = $proposal->isReplaceSource() ? ($proposal->name() ?: $existing->name()) : $existing->name();

        return $this->persistSectionChange(
            $sections,
            $existing,
            $content,
            $name,
            requireExactCss: !$proposal->isReplaceSource(),
        );
    }

    private function resolveProposalContent(Section $existing, SectionEditProposal $proposal): SectionContent
    {
        if ($proposal->isReplaceSource()) {
            return new SectionContent($proposal->html(), $proposal->css(), $existing->content()->elementStyles());
        }

        if ($proposal->isPatchSource()) {
            return new SectionContent(
                $this->applyPatchList($existing->content()->html(), $proposal->htmlPatches(), 'HTML'),
                $this->applyPatchList($existing->content()->css(), $proposal->cssPatches(), 'CSS'),
                $existing->content()->elementStyles(),
            );
        }

        if ($proposal->isRewriteEditable()) {
            $patched = $this->htmlCssProcessor->applyRewriteEditable(
                $existing->content()->html(),
                $existing->content()->css(),
                $proposal,
                $existing->id(),
            );
            return new SectionContent($patched['html'], $patched['css'], $existing->content()->elementStyles());
        }

        if ($proposal->isReplaceBindingContract()) {
            $patchedHtml = $this->bindingContractReplacementService->replace(
                $existing,
                $proposal->bindingId() ?? '',
                $proposal->expectedContractHash() ?? '',
                $proposal->replacementTemplateHtml() ?? '',
            );
            return new SectionContent($patchedHtml, $existing->content()->css(), $existing->content()->elementStyles());
        }

        // update_editables
        $patchedHtml = $this->htmlCssProcessor->applyEditableUpdates(
            $existing->content()->html(),
            $proposal->editableUpdates(),
        );
        return new SectionContent($patchedHtml, $existing->content()->css(), $existing->content()->elementStyles());
    }

    private function applyPatchList(string $subject, array $patches, string $label): string
    {
        foreach ($patches as $patch) {
            $replaced = str_replace($patch['old'], $patch['new'], $subject, $count);
            if ($count === 0) {
                throw new \InvalidArgumentException(
                    "{$label} patch failed: 'old' string not found. Ensure exact match including whitespace."
                );
            }
            if ($count > 1) {
                throw new \InvalidArgumentException(
                    "{$label} patch failed: 'old' string matched {$count} times. Provide a more specific string."
                );
            }
            $subject = $replaced;
        }
        return $subject;
    }

    /**
     * @param string[] $warnings
     */
    private function sanitizeContent(SectionContent $content, array &$warnings = []): SectionContent
    {
        $source = $this->sourceSanitizer->sanitize($content->html(), $content->css());
        $html = $source->html();
        $sanitized = new SectionContent(
            $html,
            $source->css(),
            $content->elementStyles()->pruneMissingElementIds($this->elementIdsInHtml($html), $html),
        );

        $warnings = array_values(array_unique([
            ...$warnings,
            ...$source->warnings(),
            ...$this->lucideWarningsForHtml($sanitized->html()),
            ...CssContractWarningDetector::warningsForCss($sanitized->css()),
        ]));

        return $sanitized;
    }

    /**
     * Sanitize an edit to an existing source without allowing an untouched or
     * surgically edited stylesheet to be rewritten as a side effect.
     *
     * @param string[] $warnings
     */
    private function sanitizeExistingContent(
        SectionContent $requested,
        SectionContent $existing,
        array &$warnings = [],
        bool $requireExactCss = false,
    ): SectionContent {
        $sanitized = $this->sanitizeContent($requested, $warnings);
        if (
            ($requireExactCss && $sanitized->toArray() !== $requested->toArray())
            || ($requested->css() === $existing->css() && $sanitized->css() !== $requested->css())
        ) {
            throw new CssRuleIntegrityException();
        }

        return $sanitized;
    }

    /**
     * Lucide name checks are advisory for agent writes. A single misspelled icon
     * should not discard a large generated section that otherwise compiles.
     *
     * @return string[]
     */
    private function lucideWarningsForHtml(string $html): array
    {
        return $this->lucideIconValidator?->warningsForHtml($html) ?? [];
    }

    private function persistSectionChange(
        SectionCollection $sections,
        Section $existing,
        SectionContent $content,
        string $name,
        bool $requireExactCss = false,
    ): SectionEditResult {
        $before = $sections->toArray();
        $warnings = [];
        $content = $this->sanitizeExistingContent(
            $content,
            $existing->content(),
            $warnings,
            $requireExactCss,
        );
        $newSection = Section::create($existing->pageId(), $existing->position(), $name, $content);
        $newSection->assignId($existing->id());

        $sections->replaceById($existing->id(), $newSection);
        $manifest = $this->manifestExtractor->extract($newSection);
        $compiled = $this->compiler->compile($sections);
        $this->persistPageOperation(
            $existing->pageId(),
            'section.apply_proposal',
            'Applied section proposal',
            $before,
            $sections,
            fn(): mixed => $this->repository->save($existing->pageId(), $sections, $compiled),
        );
        $this->refreshWorkingCanvas($existing->pageId());

        $this->dispatchSectionSaved($existing->pageId(), $existing->id(), 'proposal_applied');

        return new SectionEditResult(
            sectionId: $existing->id(),
            pageId: $existing->pageId(),
            position: $existing->position(),
            name: $newSection->name(),
            content: $content,
            manifest: $manifest,
            warnings: $warnings,
        );
    }

    /**
     * @return array<string, true>
     */
    private function elementIdsInHtml(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return [];
        }

        $ids = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $id = trim($element->getAttribute('id'));
            if ($id !== '') {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    /**
     * Replace exactly one binding contract inside an existing page section.
     *
     * @throws BindingTargetNotFoundException
     * @throws BindingContractUpdateException
     */
    public function replaceBindingContract(
        int $sectionId,
        string $bindingId,
        string $expectedContractHash,
        string $replacementTemplateHtml,
    ): BindingTargetUpdateResult {
        try {
            $existing = $this->repository->findById($sectionId);
        } catch (\Throwable) {
            throw new BindingTargetNotFoundException(BindingTargetReference::forSection($sectionId)->token());
        }

        $sections = $this->repository->findByPageId($existing->pageId());
        $current = $sections->getById($sectionId);

        $before = $sections->toArray();
        $patchedHtml = $this->bindingContractReplacementService->replace(
            $current,
            $bindingId,
            $expectedContractHash,
            $replacementTemplateHtml,
        );

        $bindingWriteWarnings = [];
        $updatedSection = Section::create(
            $current->pageId(),
            $current->position(),
            $current->name(),
            $this->sanitizeExistingContent(
                new SectionContent($patchedHtml, $current->content()->css(), $current->content()->elementStyles()),
                $current->content(),
                $bindingWriteWarnings,
            ),
        );
        $updatedSection->assignId($sectionId);

        $sections->replaceById($sectionId, $updatedSection);
        $compiled = $this->compiler->compile($sections);
        $this->persistPageOperation(
            $current->pageId(),
            'section.replace_binding_contract',
            'Updated binding contract',
            $before,
            $sections,
            fn(): mixed => $this->repository->save($current->pageId(), $sections, $compiled),
        );
        $this->refreshWorkingCanvas($current->pageId());

        $this->dispatchSectionSaved($current->pageId(), $sectionId, 'edited');

        return new BindingTargetUpdateResult(
            targetId: BindingTargetReference::forSection($sectionId)->token(),
            targetLabel: $updatedSection->name(),
            bindingId: $bindingId,
            warnings: $bindingWriteWarnings,
        );
    }

    public function isPageOwned(int $pageId): bool
    {
        return $this->repository->isOwnedPage($pageId);
    }

    /**
     * @return array{page_id: int, sections: array, compiled_css: string}
     */
    public function getLayout(int $pageId): array
    {
        $sections = $this->repository->findByPageId($pageId);
        $compiled = $this->compiler->compile($sections);

        return [
            'page_id'  => $pageId,
            'sections' => $sections->toArray(),
            'compiled_css' => $compiled->minifiedCss(),
        ];
    }

    /**
     * Return all sections for a page as domain entities.
     *
     * @return \UncannyPageBuilder\Domain\Section\Section[]
     */
    public function findAllSections(int $pageId): array
    {
        return $this->loadSections($pageId)->all();
    }

    /**
     * Load one generation-bearing page snapshot for a target-specific edit.
     *
     * The caller must pass this exact collection to replaceLoadedSectionSource
     * so repository compare-and-swap can reject a concurrent write.
     */
    public function loadSections(int $pageId): SectionCollection
    {
        return $this->repository->findByPageId($pageId);
    }

    /**
     * Find a single section by page ID and section ID, or null if not found.
     */
    public function findSection(int $pageId, int $sectionId): ?\UncannyPageBuilder\Domain\Section\Section
    {
        $collection = $this->loadSections($pageId);
        foreach ($collection->all() as $section) {
            if ($section->id() === $sectionId) {
                return $section;
            }
        }
        return null;
    }

    public function findSectionById(int $sectionId): ?\UncannyPageBuilder\Domain\Section\Section
    {
        try {
            return $this->repository->findById($sectionId);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build a map of section ID => editable capabilities for all sections on a page.
     *
     * @return array<int, array<int, array{key: string, type: string, supports_inline_update: bool, supports_ai_rewrite: bool}>>
     */
    public function buildEditableCapabilitiesMap(int $pageId): array
    {
        return $this->editableCapabilitiesMap($this->findAllSections($pageId));
    }

    /**
     * Build capabilities from the exact source selected for the editor.
     *
     * @param array<int, array<string, mixed>> $rawSections
     * @return array<int, array<int, array{key: string, type: string, supports_inline_update: bool, supports_ai_rewrite: bool}>>
     */
    public function buildEditableCapabilitiesMapForSource(int $pageId, array $rawSections): array
    {
        return $this->editableCapabilitiesMap(
            SectionCollection::fromArray($rawSections, $pageId, 0)->all(),
        );
    }

    /**
     * @param \UncannyPageBuilder\Domain\Section\Section[] $sections
     * @return array<int, array<int, array{key: string, type: string, supports_inline_update: bool, supports_ai_rewrite: bool}>>
     */
    private function editableCapabilitiesMap(array $sections): array
    {
        $map = [];

        foreach ($sections as $section) {
            $manifest = $this->manifestExtractor->extract($section);
            $caps = [];
            foreach ($manifest->editables() as $entry) {
                $caps[] = [
                    'key'                    => $entry->key(),
                    'type'                   => $entry->type(),
                    'supports_inline_update' => $entry->supportsInlineUpdate(),
                    'supports_ai_rewrite'    => $entry->supportsAiRewrite(),
                ];
            }
            $map[$section->id()] = $caps;
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $before
     */
    private function persistPageOperation(
        int $pageId,
        string $operation,
        string $label,
        array $before,
        SectionCollection $after,
        callable $write,
    ): mixed {
        if (!$this->history instanceof OperationHistoryService) {
            return $write();
        }

        return $this->history->recordPageMutation(
            pageId: $pageId,
            expectedGeneration: $after->generation(),
            actorUserId: $this->currentUserId(),
            operation: $operation,
            label: $label,
            beforePayload: $before,
            afterPayload: $after->toArray(),
            write: $write,
            persistedAfterPayload: fn(): array => $after->toArray(),
        );
    }

    /**
     * Complete editor-only effects after the source/history transaction.
     *
     * @param array<int, array<string, mixed>> $sections
     */
    public function completeHistoryRestore(int $pageId, array $sections): void
    {
        $this->refreshWorkingCanvas($pageId);

        foreach ($sections as $section) {
            $this->dispatchSectionSaved($pageId, (int) ($section['id'] ?? 0), 'restored');
        }
    }

    private function currentUserId(): int
    {
        if (!\function_exists('get_current_user_id')) {
            return 0;
        }

        return max(0, (int) \get_current_user_id());
    }

    private function refreshWorkingCanvas(int $pageId): void
    {
        if (!$this->workingCanvas instanceof WorkingCanvasRefresherInterface) {
            return;
        }

        try {
            $this->workingCanvas->refresh($pageId);
        } catch (\Throwable $failure) {
            $this->reportPostCommitFailure($pageId, 'working_canvas.refresh', $failure);
            $this->enqueueWorkingCanvasRefresh($pageId);
        }
    }

    /**
     * Hook listeners are external post-commit observers. A broken listener
     * must not make a completed section write look failed to the caller.
     */
    private function dispatchSectionSaved(int $pageId, int $sectionId, string $action): void
    {
        if (!$this->events instanceof SectionEventDispatcherInterface) {
            return;
        }

        try {
            $this->events->sectionSaved($pageId, $sectionId, $action);
        } catch (\Throwable $failure) {
            $this->reportPostCommitFailure($pageId, 'event.section_saved', $failure);
        }
    }

    private function dispatchSectionDeleted(int $pageId, int $sectionId): void
    {
        if (!$this->events instanceof SectionEventDispatcherInterface) {
            return;
        }

        try {
            $this->events->sectionDeleted($pageId, $sectionId);
        } catch (\Throwable $failure) {
            $this->reportPostCommitFailure($pageId, 'event.section_deleted', $failure);
        }
    }

    private function dispatchSectionsReordered(int $pageId): void
    {
        if (!$this->events instanceof SectionEventDispatcherInterface) {
            return;
        }

        try {
            $this->events->sectionsReordered($pageId);
        } catch (\Throwable $failure) {
            $this->reportPostCommitFailure($pageId, 'event.sections_reordered', $failure);
        }
    }

    /**
     * Derived working CSS is retryable because durable section source is
     * already committed. This queue can refresh editor state but never publish.
     */
    private function enqueueWorkingCanvasRefresh(int $pageId): void
    {
        if (!$this->workingCanvasRefreshQueue instanceof WorkingCanvasRefreshQueueInterface) {
            return;
        }

        try {
            $this->workingCanvasRefreshQueue->enqueuePages([$pageId]);
        } catch (\Throwable $failure) {
            $this->reportPostCommitFailure($pageId, 'working_canvas.enqueue', $failure);
        }
    }

    /**
     * Observability is best-effort at this boundary. A logging backend must
     * never turn a successful source commit into an API failure.
     */
    private function reportPostCommitFailure(int $pageId, string $step, \Throwable $failure): void
    {
        if (!$this->postCommitFailureReporter instanceof SectionPostCommitFailureReporterInterface) {
            return;
        }

        try {
            $this->postCommitFailureReporter->report($pageId, $step, $failure);
        } catch (\Throwable) {
            // The canonical source write has already committed; there is no
            // safe rollback or caller retry at this point.
        }
    }
}

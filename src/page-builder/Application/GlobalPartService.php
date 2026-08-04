<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;
use UncannyPageBuilder\Domain\Compiler\CssContractWarningDetector;
use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\Exception\BindingTargetNotFoundException;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartCreationCleanupInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartSnapshotRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartSourceUpdateRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\BindingTargetReference;
use UncannyPageBuilder\Domain\Section\LucideIconValidator;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionCollection;
use UncannyPageBuilder\Domain\Section\SectionContent;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Infrastructure\Section\SiteLogoImageNormalizer;
use UncannyPageBuilder\Infrastructure\WordPress\CssSanitizationGate;
use UncannyPageBuilder\Infrastructure\WordPress\HtmlSanitizationGate;

final class GlobalPartService
{
    public function __construct(
        private readonly GlobalPartRepositoryInterface $repository,
        private readonly ShadowCompiler $compiler,
        private readonly BindingContractReplacementService $bindingContractReplacementService,
        private readonly ?SectionRepositoryInterface $sectionRepository = null,
        private readonly ?WorkingCanvasRefreshScheduler $workingCanvasRefreshes = null,
        private readonly ?LucideIconValidator $lucideIconValidator = null,
    ) {}

    /**
     * @param string $title
     * @param array  $sectionData  Raw section array with content.html/css
     * @param string $type         'header', 'footer', or 'section'
     */
    /**
     * @return array{id: int, title: string, type: string, warnings: string[]}
     */
    public function create(string $title, array $sectionData, string $type): array
    {
        $gpType = GlobalPartType::fromString($type);
        $postId = $this->repository->createPost($title, $gpType);

        try {
            return $this->persistSectionData($postId, $title, $sectionData, $gpType);
        } catch (\Throwable $failure) {
            $this->rethrowAfterCreationCleanup($postId, $failure);
        }
    }

    /**
     * @return array{id: int, title: string, type: string, warnings: string[]}
     */
    public function createFromSectionId(int $sectionId, string $title, GlobalPartType $type): array
    {
        if ($this->sectionRepository === null) {
            throw new \RuntimeException('Section repository is required to create a reusable from a stored section.');
        }

        $section = $this->sectionRepository->findById($sectionId);
        $postId = $this->repository->createPost($title, $type);

        try {
            $createdPart = $this->repository->findById($postId);

            return $this->persistSection(
                $postId,
                $title,
                $section,
                $type,
                generation: (int) ($createdPart['generation'] ?? 0),
            );
        } catch (\Throwable $failure) {
            $this->rethrowAfterCreationCleanup($postId, $failure);
        }
    }

    /**
     * Resolve the canonical source content and title from a global part.
     *
     * @return array{content: string, title: string}|null
     */
    public function resolveSourceContent(int $globalPartId): ?array
    {
        $part = $this->repository->findById($globalPartId);
        if ($part === null || empty($part['sections'])) {
            return null;
        }
        $source = $part['sections'][0];
        $content = $source['content'] ?? null;
        if ($content === null) {
            return null;
        }
        return [
            'content' => $content,
            'title'   => $part['title'] ?? '',
        ];
    }

    /**
     * Hydrate the canonical source unit of a stored global part.
     *
     * A global part is one reusable source aggregate; the lowest-position
     * stored row is its canonical source. Legacy top-level html/css shapes are
     * normalized here. Multi-row sources remain readable and their noncanonical
     * rows are preserved by source writes.
     */
    public function sourceSection(int $globalPartId): ?Section
    {
        $part = $this->repository->findById($globalPartId);
        if ($part === null) {
            return null;
        }

        return $this->sourceSectionFromSnapshot($globalPartId, $part);
    }

    /**
     * Hydrate source from the same generation-bearing snapshot that will be
     * passed to replaceLoadedSource after a target-specific edit.
     *
     * @param array<string, mixed> $part
     */
    public function sourceSectionFromSnapshot(int $globalPartId, array $part): ?Section
    {
        if ((int) ($part['post_id'] ?? 0) !== $globalPartId) {
            return null;
        }

        return $this->hydrateSource($globalPartId, $this->normalizeLegacySectionData($part));
    }

    /**
     * Create a new global part, or replace an existing one with the same type and title.
     *
     * Re-imports should update the reusable they created earlier without
     * clobbering an unrelated header/footer just because it sorts first.
     */
    /**
     * @return array{id: int, title: string, type: string, warnings: string[]}
     */
    public function createOrReplace(string $title, array $sectionData, string $type): array
    {
        $gpType = GlobalPartType::fromString($type);
        $existing = $this->findByTypeAndTitle($gpType, $title);

        if ($existing !== null) {
            return $this->persistSectionData($existing['post_id'], $title, $sectionData, $gpType);
        }

        return $this->create($title, $sectionData, $type);
    }

    /**
     * Replace one known global part. Agent/default-part updates must not fall
     * back to the first global part of the same type.
     *
     * @return array{id: int, title: string, type: string, warnings: string[]}
     */
    public function replaceExisting(int $globalPartId, string $title, array $sectionData, GlobalPartType $type): array
    {
        $existing = $this->repository->findById($globalPartId);
        if ($existing === null || ($existing['type'] ?? null) !== $type->value) {
            throw new \RuntimeException('Global part not found.');
        }

        return $this->persistSectionData(
            $globalPartId,
            $title,
            $sectionData,
            $type,
            existingPart: $existing,
        );
    }

    /**
     * Replace one known global-part source and return the refreshed source row.
     *
     * @return array{id: int, title: string, type: string, section_id: int, html: string, css: string}
     */
    public function replaceExistingSource(
        int $globalPartId,
        string $title,
        array $sectionData,
        GlobalPartType $type,
        bool $requireExactCss = false,
    ): array {
        $existing = $this->repository->findById($globalPartId);
        if ($existing === null || ($existing['type'] ?? null) !== $type->value) {
            throw new \RuntimeException('Global part not found.');
        }

        return $this->replaceLoadedSource(
            $globalPartId,
            $existing,
            $title,
            $sectionData,
            $type,
            $requireExactCss,
        );
    }

    /**
     * Persist against the exact global-part snapshot used to prepare an edit.
     * Its generation remains the repository compare-and-swap boundary, so a
     * concurrent human write cannot be accepted and then overwritten.
     *
     * @param array<string, mixed> $existing
     * @return array{id: int, title: string, type: string, section_id: int, html: string, css: string}
     */
    public function replaceLoadedSource(
        int $globalPartId,
        array $existing,
        string $title,
        array $sectionData,
        GlobalPartType $type,
        bool $requireExactCss = false,
    ): array {
        if ((int) ($existing['post_id'] ?? 0) !== $globalPartId || ($existing['type'] ?? null) !== $type->value) {
            throw new \RuntimeException('Global part not found.');
        }

        $section = $this->hydrateSource($globalPartId, $this->normalizeLegacySectionData($existing));
        if (!$section instanceof Section) {
            throw new \RuntimeException('Global part has no source section.');
        }

        return $this->persistSectionData(
            $globalPartId,
            $title,
            $sectionData,
            $type,
            includeSource: true,
            requireExactCss: $requireExactCss,
            existingPart: $existing,
        );
    }

    public function findById(int $globalPartId): ?array
    {
        return $this->repository->findById($globalPartId);
    }

    /**
     * Build a rendered layout payload for a reusable canvas.
     *
     * @return array{page_id: int, sections: array<int, array<string, mixed>>, compiled_css: string}|null
     */
    public function getLayout(int $globalPartId): ?array
    {
        $part = $this->repository->findById($globalPartId);
        if ($part === null) {
            return null;
        }

        $normalized = $this->normalizeLegacySectionData($part);
        $sections = [];

        foreach (($normalized['sections'] ?? []) as $position => $section) {
            if (!is_array($section)) {
                continue;
            }

            $sections[] = Section::fromStoredArray(
                $section,
                $globalPartId,
                (int) ($section['position'] ?? $position),
            );
        }

        $collection = new SectionCollection($sections);
        $compiled = $this->compiler->compile($collection);

        return [
            'page_id'      => 0,
            'sections'     => $collection->toArray(),
            'compiled_css' => $compiled->minifiedCss(),
        ];
    }

    private function findByTypeAndTitle(GlobalPartType $type, string $title): ?array
    {
        foreach ($this->repository->findAllByType($type) as $part) {
            if (($part['title'] ?? '') === $title) {
                return $part;
            }
        }

        return null;
    }

    /**
     * @return array{id: int, title: string, type: string, warnings: string[], section_id?: int, html?: string, css?: string}
     */
    private function persistSectionData(
        int $postId,
        string $title,
        array $sectionData,
        GlobalPartType $gpType,
        bool $includeSource = false,
        bool $requireExactCss = false,
        ?array $existingPart = null,
    ): array {
        $existingPart ??= $this->repository->findById($postId);
        $existingSource = is_array($existingPart)
            ? $this->hydrateSource($postId, $this->normalizeLegacySectionData($existingPart))
            : null;
        $rawContent = $existingSource instanceof Section
            ? SectionContent::fromSourceUpdate($sectionData['content'] ?? [], $existingSource->content())
            : SectionContent::fromArray($sectionData['content'] ?? []);
        $preserveExistingCss = $existingSource instanceof Section
            && $rawContent->css() === $existingSource->content()->css();
        $source = Section::create(
            $postId,
            $existingSource instanceof Section ? $existingSource->position() : 0,
            $sectionData['name'] ?? $title,
            $rawContent,
        );
        if ($existingSource instanceof Section && $existingSource->id() !== null) {
            $source->assignId($existingSource->id());
        }

        return $this->persistSection(
            $postId,
            $title,
            $source,
            $gpType,
            $includeSource,
            (int) ($existingPart['generation'] ?? 0),
            $requireExactCss,
            $preserveExistingCss,
            $existingPart,
        );
    }

    /**
     * @return array{id: int, title: string, type: string, warnings: string[]}
     */
    private function persistSection(
        int $postId,
        string $title,
        Section $source,
        GlobalPartType $gpType,
        bool $includeSource = false,
        int $generation = 0,
        bool $requireExactCss = false,
        bool $preserveExistingCss = false,
        ?array $existingPart = null,
    ): array {
        $warnings = [];
        $requestedContent = $source->content();
        $sanitizedContent = $this->sanitizeContent($requestedContent, $warnings);
        if (
            ($requireExactCss && $sanitizedContent->toArray() !== $requestedContent->toArray())
            || ($preserveExistingCss && $sanitizedContent->css() !== $requestedContent->css())
        ) {
            throw new CssRuleIntegrityException();
        }

        $section = Section::create(
            $postId,
            $source->position(),
            $source->name() !== '' ? $source->name() : $title,
            $sanitizedContent,
        );
        if ($source->id() !== null) {
            $section->assignId($source->id());
        }

        $collection = $this->collectionWithUpdatedSource(
            $postId,
            $section,
            $existingPart,
            $generation,
        );
        $compiled = $this->compiler->compile($collection);
        $sourceRows = $this->sourceRowsForWrite($existingPart);
        if (
            $sourceRows !== []
            && $section->id() !== null
            && $this->repository instanceof GlobalPartSourceUpdateRepositoryInterface
        ) {
            $this->repository->saveSource($postId, $section, $compiled, $generation);
        } elseif (count($sourceRows) > 1) {
            throw CssRuleIntegrityException::unpreservableGlobalPartSourceRows();
        } elseif ($this->repository instanceof GlobalPartSnapshotRepositoryInterface) {
            $this->repository->saveSnapshot($postId, $collection, $compiled);
        } else {
            $this->repository->saveSections($postId, $collection);
            $this->repository->saveCompiled($postId, $compiled);
        }
        $this->workingCanvasRefreshes?->enqueueAll();

        $result = [
            'id'       => $postId,
            'title'    => $title,
            'type'     => $gpType->value,
            'warnings' => $warnings,
        ];
        if ($includeSource) {
            $result['section_id'] = $section->id() ?? 0;
            $result['html'] = $section->content()->html();
            $result['css'] = $section->content()->css();
        }

        return $result;
    }

    /**
     * Replace exactly one binding contract inside a stored global part.
     *
     * @throws BindingTargetNotFoundException
     */
    public function replaceBindingContract(
        int $globalPartId,
        string $bindingId,
        string $expectedContractHash,
        string $replacementTemplateHtml,
    ): BindingTargetUpdateResult {
        $part = $this->repository->findById($globalPartId);
        if ($part === null) {
            throw new BindingTargetNotFoundException(BindingTargetReference::forGlobalPart($globalPartId)->token());
        }

        $section = $this->hydrateSource($globalPartId, $part);
        if (!$section instanceof Section) {
            throw new BindingTargetNotFoundException(BindingTargetReference::forGlobalPart($globalPartId)->token());
        }

        $patchedHtml = $this->bindingContractReplacementService->replace(
            $section,
            $bindingId,
            $expectedContractHash,
            $replacementTemplateHtml,
        );

        $updatedSection = Section::create(
            $globalPartId,
            $section->position(),
            $section->name(),
            new SectionContent($patchedHtml, $section->content()->css(), $section->content()->elementStyles()),
        );
        if ($section->id() !== null) {
            $updatedSection->assignId($section->id());
        }

        $persisted = $this->persistSection(
            $globalPartId,
            (string) ($part['title'] ?? 'Global Part'),
            $updatedSection,
            GlobalPartType::fromString((string) ($part['type'] ?? 'section')),
            generation: (int) ($part['generation'] ?? 0),
            preserveExistingCss: true,
            existingPart: $part,
        );

        return new BindingTargetUpdateResult(
            targetId: BindingTargetReference::forGlobalPart($globalPartId)->token(),
            targetLabel: (string) ($part['title'] ?? 'Global Part'),
            bindingId: $bindingId,
            targetRole: is_string($part['type'] ?? null) ? $part['type'] : null,
            warnings: $persisted['warnings'],
        );
    }

    /**
     * @param string[] $warnings
     */
    private function sanitizeContent(SectionContent $content, array &$warnings = []): SectionContent
    {
        $logoRewrites = 0;
        $html = HtmlSanitizationGate::filter(SiteLogoImageNormalizer::normalize($content->html(), $logoRewrites));
        $sanitized = new SectionContent(
            $html,
            CssSanitizationGate::filter($content->css()),
            $content->elementStyles()->pruneMissingElementIds($this->elementIdsInHtml($html), $html),
        );

        $warnings = array_values(array_unique([
            ...$warnings,
            ...($logoRewrites > 0 ? [SiteLogoImageNormalizer::REWRITE_WARNING] : []),
            ...($this->lucideIconValidator?->warningsForHtml($sanitized->html()) ?? []),
            ...CssContractWarningDetector::warningsForCss($sanitized->css()),
        ]));

        return $sanitized;
    }

    /**
     * Build the complete source view used by the compiler while replacing only
     * the canonical row. Hidden legacy rows are never sanitized or rewritten.
     *
     * @param array<string, mixed>|null $existingPart
     */
    private function collectionWithUpdatedSource(
        int $globalPartId,
        Section $updatedSource,
        ?array $existingPart,
        int $generation,
    ): SectionCollection {
        $sourceRows = $this->sourceRowsForWrite($existingPart);
        if ($sourceRows === []) {
            return SectionCollection::fromPersisted([$updatedSource], $generation);
        }

        $normalized = $this->normalizeLegacySectionData(['sections' => $sourceRows]);
        $normalizedRows = $normalized['sections'] ?? null;
        if (!is_array($normalizedRows) || count($normalizedRows) !== count($sourceRows)) {
            throw CssRuleIntegrityException::unpreservableGlobalPartSourceRows();
        }

        $sections = [$updatedSource];
        foreach (array_slice($normalizedRows, 1) as $position => $row) {
            if (!is_array($row)) {
                throw CssRuleIntegrityException::unpreservableGlobalPartSourceRows();
            }

            $sections[] = Section::fromStoredArray(
                $row,
                $globalPartId,
                (int) ($row['position'] ?? $position + 1),
            );
        }

        return SectionCollection::fromPersisted($sections, $generation);
    }

    /**
     * Return stored rows only when every row can be accounted for. A malformed
     * shape fails closed before any canonical source persistence begins.
     *
     * @param array<string, mixed>|null $part
     * @return list<array<string, mixed>>
     */
    private function sourceRowsForWrite(?array $part): array
    {
        if ($part === null || !array_key_exists('sections', $part)) {
            return [];
        }

        $rows = $part['sections'];
        if (!is_array($rows)) {
            throw CssRuleIntegrityException::unpreservableGlobalPartSourceRows();
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw CssRuleIntegrityException::unpreservableGlobalPartSourceRows();
            }
        }

        return array_values($rows);
    }

    /**
     * A global part is not created until its canonical source snapshot is
     * durable. Production repositories expose an explicit compensation port;
     * lightweight read-only adapters retain the original failure unchanged.
     */
    private function rethrowAfterCreationCleanup(int $globalPartId, \Throwable $failure): never
    {
        if (!$this->repository instanceof GlobalPartCreationCleanupInterface) {
            throw $failure;
        }

        try {
            $this->repository->removeCreatedGlobalPart($globalPartId);
        } catch (\Throwable $cleanupFailure) {
            throw new \RuntimeException(
                "Global part creation failed, and its incomplete post could not be removed: {$cleanupFailure->getMessage()}",
                0,
                $failure,
            );
        }

        throw $failure;
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
     * Hydrate the canonical source unit from a hydrated global-part array.
     *
     * @param array{sections?: array<int, array{id?: int, position?: int, name?: string, content?: array{html?: string, css?: string}}>} $globalPart
     */
    private function hydrateSource(int $globalPartId, array $globalPart): ?Section
    {
        $sections = $globalPart['sections'] ?? [];
        if ($sections === []) {
            return null;
        }

        $sectionData = $sections[0];
        $section = Section::fromStoredArray(
            $sectionData,
            $globalPartId,
            (int) ($sectionData['position'] ?? 0),
        );

        return $section;
    }

    /**
     * Older tests and import paths may store a global-part section as
     * top-level html/css instead of content.html/content.css.
     *
     * @param array<string, mixed> $globalPart
     * @return array<string, mixed>
     */
    private function normalizeLegacySectionData(array $globalPart): array
    {
        $sections = $globalPart['sections'] ?? [];
        if (!is_array($sections) || $sections === []) {
            return $globalPart;
        }

        $normalized = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            if (!isset($section['content']) && (isset($section['html']) || isset($section['css']))) {
                $section['content'] = [
                    'html' => (string) ($section['html'] ?? ''),
                    'css'  => (string) ($section['css'] ?? ''),
                ];
            }
            $normalized[] = $section;
        }

        $globalPart['sections'] = $normalized;

        return $globalPart;
    }

    /**
     * List all global parts of a given type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByType(GlobalPartType $type): array
    {
        return $this->repository->findAllByType($type);
    }
}

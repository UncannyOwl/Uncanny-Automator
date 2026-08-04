<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\DesignStandards\PageDesignOverrides;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\SourcePackage\PageSourcePackage;

/**
 * Imports and exports Page Builder-owned page source packages.
 */
final class PageSourcePackageService
{
    public function __construct(
        private readonly SectionService $sections,
        private readonly DesignStandardsService $designStandards,
        private readonly PageJavaScriptRuntimeService $javaScriptRuntime,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
        private readonly ?WorkingCanvasRefresherInterface $workingCanvas = null,
    ) {}

    /**
     * Build an importable source package for a Page Builder page.
     *
     * @return array<string, mixed>
     */
    public function exportPage(int $pageId): array
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }

        $generation = $this->sourceGenerations->pageGeneration($pageId);
        $layout = $this->sections->getLayout($pageId);
        $designOverrides = $this->designStandards->loadPageOverrides($pageId)->toArray();
        $customJavaScript = $this->javaScriptRuntime->readForPage($pageId);
        $currentGeneration = $this->sourceGenerations->pageGeneration($pageId);
        if ($currentGeneration !== $generation) {
            throw new StaleSourceGenerationException('page', $generation, $currentGeneration);
        }

        $package = PageSourcePackage::fromPage(
            pageId: $pageId,
            sections: is_array($layout['sections'] ?? null) ? $layout['sections'] : [],
            designOverrides: $designOverrides,
            customJavaScript: $customJavaScript,
            exportedAt: gmdate('c'),
        );

        return $package->toArray();
    }

    /**
     * Validate a page package before callers create a new WordPress page.
     *
     * @param array<string, mixed> $payload
     */
    public function validatePage(array $payload): PageSourcePackage
    {
        return PageSourcePackage::fromImportPayload($payload);
    }

    /**
     * Populate a newly-created Page Builder page from a validated source package.
     *
     * This method intentionally has no "replace existing page" path. Admin page
     * imports must create the page first, then call this method with that new ID
     * so an uploaded package cannot clobber the page the user is currently on.
     *
     * @param array<string, mixed> $payload
     * @return array{sections: array<int, array<string, mixed>>, compiled_css: string, warnings: string[]}
     */
    public function importIntoNewPage(int $pageId, array $payload, int $savedBy): array
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }

        $package = $this->validatePage($payload);
        $layout = $this->sections->restore($pageId, $package->sectionsForRestore());
        $designOverrides = $package->designOverrides();
        $customJavaScript = $package->customJavaScript();
        $warnings = is_array($layout['warnings'] ?? null) ? $layout['warnings'] : [];
        $designChanged = false;

        if ($designOverrides !== []) {
            $result = $this->designStandards->savePageOverrides($pageId, PageDesignOverrides::fromArray($designOverrides));
            $rejected = [...$result->rejectedKeys()['tokens'], ...$result->rejectedKeys()['typography']];
            $locked = [...$result->lockedKeys()['tokens'], ...$result->lockedKeys()['typography']];
            if ($rejected !== []) {
                $warnings[] = sprintf(
                    '%d design override(s) were not available on this site and were skipped.',
                    count($rejected),
                );
            }
            if ($locked !== []) {
                $warnings[] = sprintf(
                    '%d design override(s) are locked by this site and were skipped.',
                    count($locked),
                );
            }
            $designChanged = $result->appliedKeys()['tokens'] !== []
                || $result->appliedKeys()['typography'] !== [];
        }

        if ($customJavaScript !== '') {
            $this->javaScriptRuntime->replaceForPage($pageId, $customJavaScript, $savedBy);
        }

        // Section restore refreshes compiled CSS before page overrides are
        // applied. Refresh once more only when those overrides changed it.
        if ($designChanged && $this->workingCanvas instanceof WorkingCanvasRefresherInterface) {
            try {
                $this->workingCanvas->refresh($pageId);
            } catch (\Throwable) {
                $warnings[] = 'The imported source was saved, but its canvas preview is queued for a later refresh.';
            }
        }

        $layout['warnings'] = array_values(array_unique($warnings));

        return $layout;
    }
}

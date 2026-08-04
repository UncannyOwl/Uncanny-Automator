<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;

/**
 * Coordinates the portable archive without leaking ZIP or WordPress media APIs
 * into the page-source application contract.
 */
final class PageSourceArchiveService
{
    public function __construct(
        private readonly PageSourcePackageService $pageSources,
        private readonly PageSourceImageCollectorInterface $images,
        private readonly PageSourceArchiveWriterInterface $archives,
        private readonly PageSourceImageImporterInterface $imageImporter,
        private readonly PageSourceImageUrlRewriter $urlRewriter,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
    ) {}

    public function exportPage(int $pageId): PageSourceArchiveArtifact
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }

        $generation = $this->sourceGenerations->pageGeneration($pageId);
        $payload = $this->pageSources->exportPage($pageId);
        $collection = $this->images->collect($payload);
        $currentGeneration = $this->sourceGenerations->pageGeneration($pageId);
        if ($currentGeneration !== $generation) {
            throw new StaleSourceGenerationException('page', $generation, $currentGeneration);
        }

        return $this->archives->write(
            $pageId,
            $payload,
            $collection->images(),
            $collection->warnings(),
        );
    }

    public function prepareImport(int $pageId, UploadedPageSource $upload): PageSourcePreparedImport
    {
        $result = $this->imageImporter->import($pageId, $upload->images());

        return new PageSourcePreparedImport(
            $this->urlRewriter->rewrite($upload->payload(), $result->urlMap()),
            $result->attachmentIds(),
            array_values(array_unique([...$upload->warnings(), ...$result->warnings()])),
            count($upload->images()),
        );
    }

    /** @param list<int> $attachmentIds */
    public function cleanupImportedImages(array $attachmentIds): void
    {
        $this->imageImporter->delete($attachmentIds);
    }
}

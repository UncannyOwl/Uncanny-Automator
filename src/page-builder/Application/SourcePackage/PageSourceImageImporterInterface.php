<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

interface PageSourceImageImporterInterface
{
    /**
     * @param list<PageSourceImage> $images
     */
    public function import(int $pageId, array $images): PageSourceImageImportResult;

    /**
     * Compensation for a page import that failed after attachments were made.
     *
     * @param list<int> $attachmentIds
     */
    public function delete(array $attachmentIds): void;
}

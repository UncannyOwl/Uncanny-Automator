<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Domain\Section\SectionCollection;

/**
 * Narrow seam over SectionService for editing an existing section.
 *
 * The element committer resolves selected targets against one loaded section
 * collection, then asks this writer to persist that same collection through the
 * canonical persistence path (compile + history).
 */
interface SectionSourceWriter
{
    /**
     * @param array{html: string, css: string, element_styles?: array<string, mixed>} $content
     * @return array{section_id: int, warnings?: string[]}
     */
    public function replaceLoadedSectionSource(
        int $pageId,
        SectionCollection $sections,
        int $sectionId,
        string $sectionName,
        array $content,
    ): array;

    /**
     * @param array<int, array{
     *     section_id: int,
     *     section_name: string,
     *     content: array{html: string, css: string, element_styles?: array<string, mixed>}
     * }> $updates
     * @return array{warnings?: string[]}
     */
    public function replaceLoadedSectionSources(
        int $pageId,
        SectionCollection $sections,
        array $updates,
    ): array;
}

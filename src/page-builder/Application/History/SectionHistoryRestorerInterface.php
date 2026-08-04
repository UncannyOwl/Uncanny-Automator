<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\History;

interface SectionHistoryRestorerInterface
{
    /**
     * @param array<int, array<string, mixed>> $rawSections
     * @param array<int, array<string, mixed>>|null $expectedCurrentSections
     * @return array{sections: array<int, array<string, mixed>>, compiled_css: string}
     */
    public function restoreFromHistory(
        int $pageId,
        array $rawSections,
        ?array $expectedCurrentSections = null,
    ): array;
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

interface SectionEventDispatcherInterface
{
    public function sectionSaved(int $pageId, int $sectionId, string $action): void;

    public function sectionDeleted(int $pageId, int $sectionId): void;

    public function sectionsReordered(int $pageId): void;
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Immutable result returned after a successful proposal apply.
 */
final class SectionEditResult
{
    /** @param string[] $warnings Advisory sanitization notes (e.g. logo rewritten to binding). */
    public function __construct(
        private readonly int $sectionId,
        private readonly int $pageId,
        private readonly int $position,
        private readonly string $name,
        private readonly SectionContent $content,
        private readonly SectionManifest $manifest,
        private readonly array $warnings = [],
    ) {}

    /** @return string[] */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'section_id'       => $this->sectionId,
            'page_id'          => $this->pageId,
            'position'         => $this->position,
            'name'             => $this->name,
            'content'          => $this->content->toArray(),
            'manifest'         => $this->manifest->toArray(),
            'warnings'         => $this->warnings,
        ];
    }
}

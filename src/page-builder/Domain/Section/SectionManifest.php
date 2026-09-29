<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Immutable manifest describing the stored structure of an existing section.
 */
final class SectionManifest
{
    /**
     * @param array<string, mixed> $root
     * @param array<int, array<string, mixed>> $dynamicRegions
     * @param array<string, mixed> $constraints
     */
    public function __construct(
        private readonly ?int $sectionId,
        private readonly int $pageId,
        private readonly array $root,
        private readonly array $dynamicRegions,
        private readonly array $constraints,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version'   => '1.0',
            'section_id'       => $this->sectionId,
            'page_id'          => $this->pageId,
            'root'             => $this->root,
            'dynamic_regions'  => $this->dynamicRegions,
            'constraints'      => $this->constraints,
        ];
    }
}

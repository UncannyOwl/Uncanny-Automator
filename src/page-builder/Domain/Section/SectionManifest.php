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
     * @param EditableManifestEntry[] $editables
     * @param array<int, array<string, mixed>> $dynamicRegions
     * @param array<string, mixed> $constraints
     */
    public function __construct(
        private readonly ?int $sectionId,
        private readonly int $pageId,
        private readonly array $root,
        private readonly array $editables,
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
            'editables'        => array_map(
                static fn(EditableManifestEntry $e): array => $e->toArray(),
                $this->editables,
            ),
            'dynamic_regions'  => $this->dynamicRegions,
            'constraints'      => $this->constraints,
        ];
    }

    /**
     * Find an editable entry by its key, or null if not found.
     */
    public function findEditable(string $key): ?EditableManifestEntry
    {
        foreach ($this->editables as $entry) {
            if ($entry->key() === $key) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return EditableManifestEntry[]
     */
    public function editables(): array
    {
        return $this->editables;
    }
}

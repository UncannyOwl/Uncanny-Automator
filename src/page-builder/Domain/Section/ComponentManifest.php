<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Full component manifest for a section.
 *
 * Wraps the existing SectionManifest (unchanged) and adds:
 * - component_category (derived, not persisted in Phase 7)
 * - supported_binding_schemas (platform vocabulary snapshot)
 *
 * Immutable value object.
 */
final class ComponentManifest
{
    public function __construct(
        private readonly SectionManifest $manifest,
        private readonly ComponentCategory $category,
    ) {}

    public function manifest(): SectionManifest { return $this->manifest; }
    public function category(): ComponentCategory { return $this->category; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $base = $this->manifest->toArray();

        return array_merge($base, [
            'component_category'        => $this->category->value,
            'supported_binding_schemas' => [BindingSchema::toArray()],
        ]);
    }
}

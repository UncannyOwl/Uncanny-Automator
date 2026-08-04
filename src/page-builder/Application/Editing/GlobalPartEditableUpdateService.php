<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editing;

use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\Section;

/**
 * Applies one legacy inline editable update to a global part source.
 */
final class GlobalPartEditableUpdateService
{
    private const ALLOWED_TYPES = ['text', 'textarea', 'image', 'link', 'bg-image'];
    private readonly EditableHtmlMutator $mutator;

    public function __construct(
        private readonly GlobalPartService $globalParts,
        ?EditableHtmlMutator $mutator = null,
    ) {
        $this->mutator = $mutator ?? new EditableHtmlMutator();
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function update(
        int $globalPartId,
        string $editableKey,
        string $editableType,
        array $values,
    ): array {
        if ($globalPartId <= 0) {
            throw new \InvalidArgumentException('global_part_id is required.');
        }
        if (trim($editableKey) === '') {
            throw new \InvalidArgumentException('editable_key is required.');
        }
        if (!in_array($editableType, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('editable_type is invalid.');
        }

        $part = $this->globalParts->findById($globalPartId);
        $section = $part !== null
            ? $this->globalParts->sourceSectionFromSnapshot($globalPartId, $part)
            : null;
        if ($part === null || !$section instanceof Section) {
            throw new \RuntimeException('Global part not found.');
        }

        $patchedHtml = $this->mutator->apply(
            $section->content()->html(),
            trim($editableKey),
            $editableType,
            $values,
        );

        $saved = $this->globalParts->replaceLoadedSource(
            globalPartId: $globalPartId,
            existing: $part,
            title: (string) ($part['title'] ?? ''),
            sectionData: [
                'name'    => $section->name(),
                'content' => [
                    'html'           => $patchedHtml,
                    'css'            => $section->content()->css(),
                    'element_styles' => $section->content()->elementStyles()->toArray(),
                ],
            ],
            type: GlobalPartType::fromString((string) ($part['type'] ?? 'section')),
        );

        return [
            'section_id'            => (int) ($saved['section_id'] ?? 0),
            'global_part_id'       => $globalPartId,
            'global_part'          => [
                'part_id' => $globalPartId,
            ],
        ];
    }
}

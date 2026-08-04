<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editing;

use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\Exception\EditableUpdateException;

final class EditableUpdateService
{
    private const ALLOWED_TYPES = ['text', 'textarea', 'image', 'link', 'bg-image'];
    private readonly EditableHtmlMutator $mutator;

    public function __construct(
        private readonly SectionService $sections,
        ?EditableHtmlMutator $mutator = null,
    ) {
        $this->mutator = $mutator ?? new EditableHtmlMutator();
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function update(
        int $pageId,
        int $sectionId,
        string $editableKey,
        string $editableType,
        array $values,
    ): array {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }
        if ($sectionId <= 0) {
            throw new \InvalidArgumentException('section_id is required.');
        }
        if (trim($editableKey) === '') {
            throw new \InvalidArgumentException('editable_key is required.');
        }
        if (!in_array($editableType, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('editable_type is invalid.');
        }

        $loadedSections = $this->sections->loadSections($pageId);
        $section = $loadedSections->getById($sectionId);

        $patchedHtml = $this->mutator->apply(
            $section->content()->html(),
            trim($editableKey),
            $editableType,
            $values,
        );

        $this->sections->replaceLoadedSectionSource(
            pageId: $pageId,
            sections: $loadedSections,
            sectionId: $sectionId,
            sectionName: $section->name(),
            content: [
                'html' => $patchedHtml,
                'css' => $section->content()->css(),
                'element_styles' => $section->content()->elementStyles()->toArray(),
            ],
            normalizePatchedHtml: true,
        );

        $updated = $loadedSections->getById($sectionId);

        return [
            'section_id'       => $sectionId,
            'page_id'          => $pageId,
            'section'          => [
                'id'               => $sectionId,
                'name'             => $updated->name(),
                'position'         => $updated->position(),
            ],
        ];
    }
}

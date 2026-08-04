<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editing;

use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\Section;

/**
 * Applies generated-node content changes to a global part source.
 */
final class GlobalPartNodeUpdateService
{
    private readonly SectionNodeHtmlMutator $mutator;

    public function __construct(
        private readonly GlobalPartService $globalParts,
        ?SectionNodeHtmlMutator $mutator = null,
    ) {
        $this->mutator = $mutator ?? new SectionNodeHtmlMutator();
    }

    /**
     * @param array<string, mixed> $target
     * @param array<int, array<string, mixed>> $changes
     * @return array<string, mixed>
     */
    public function update(
        int $globalPartId,
        array $target,
        array $changes,
    ): array {
        if ($globalPartId <= 0) {
            throw new \InvalidArgumentException('global_part_id is required.');
        }
        if ($changes === []) {
            throw new \InvalidArgumentException('At least one node change is required.');
        }

        $part = $this->globalParts->findById($globalPartId);
        $section = $part !== null
            ? $this->globalParts->sourceSectionFromSnapshot($globalPartId, $part)
            : null;
        if ($part === null || !$section instanceof Section) {
            throw new \RuntimeException('Global part not found.');
        }

        $oldHtml = $section->content()->html();
        $oldCss = $section->content()->css();
        $result = $this->mutator->apply($section, $target, $changes);

        $saved = $this->globalParts->replaceLoadedSource(
            globalPartId: $globalPartId,
            existing: $part,
            title: (string) ($part['title'] ?? ''),
            sectionData: [
                'name'    => $section->name(),
                'content' => [
                    'html'           => $result['html'],
                    'css'            => $oldCss,
                    'element_styles' => $section->content()->elementStyles()->toArray(),
                ],
            ],
            type: GlobalPartType::fromString((string) ($part['type'] ?? 'section')),
        );

        return [
            'section_id'            => (int) ($saved['section_id'] ?? 0),
            'global_part_id'       => $globalPartId,
            'selector'             => $result['selector'],
            'promoted'             => $result['promoted'],
            'old_html'             => $oldHtml,
            'new_html'             => (string) ($saved['html'] ?? $result['html']),
            'old_css'              => $oldCss,
            'new_css'              => (string) ($saved['css'] ?? $oldCss),
            'global_part'          => [
                'part_id' => $globalPartId,
            ],
        ];
    }
}

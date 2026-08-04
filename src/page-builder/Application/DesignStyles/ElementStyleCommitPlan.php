<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Domain\Section\SectionCollection;

/**
 * Prepared section-owned element style write.
 */
final class ElementStyleCommitPlan
{
    /**
     * @param array<int, array{
     *     section_id: int,
     *     section_name: string,
     *     content: array{html: string, css: string, element_styles?: array<string, mixed>}
     * }> $sectionUpdates
     * @param array<int, array<string, mixed>> $applied
     * @param array<int, array<string, mixed>> $targets
     */
    public function __construct(
        private readonly int $pageId,
        private readonly SectionCollection $sections,
        private readonly array $sectionUpdates,
        private readonly array $applied,
        private readonly array $targets,
        private readonly bool $promoted,
    ) {}

    public function pageId(): int { return $this->pageId; }
    public function sections(): SectionCollection { return $this->sections; }
    /**
     * @return array<int, array{
     *     section_id: int,
     *     section_name: string,
     *     content: array{html: string, css: string, element_styles?: array<string, mixed>}
     * }>
     */
    public function sectionUpdates(): array { return $this->sectionUpdates; }
    public function sectionId(): int { return (int) ($this->firstUpdate()['section_id'] ?? 0); }
    public function sectionName(): string { return (string) ($this->firstUpdate()['section_name'] ?? ''); }
    public function html(): string { return (string) (($this->firstUpdate()['content'] ?? [])['html'] ?? ''); }
    public function css(): string { return (string) (($this->firstUpdate()['content'] ?? [])['css'] ?? ''); }
    /** @return array<string, mixed> */
    public function elementStyles(): array
    {
        $content = $this->firstUpdate()['content'] ?? [];

        return is_array($content['element_styles'] ?? null) ? $content['element_styles'] : [];
    }
    /** @return array<int, array<string, mixed>> */
    public function applied(): array { return $this->applied; }
    /** @return array<int, array<string, mixed>> */
    public function targets(): array { return $this->targets; }
    public function promoted(): bool { return $this->promoted; }

    /** @return array<string, mixed> */
    private function firstUpdate(): array
    {
        $first = $this->sectionUpdates[0] ?? [];

        return is_array($first) ? $first : [];
    }
}

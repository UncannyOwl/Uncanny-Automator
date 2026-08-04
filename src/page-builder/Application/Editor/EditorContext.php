<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editor;

final class EditorContext
{
    private function __construct(
        public readonly string $surface,
        public readonly string $scope,
        public readonly int $pageId,
        public readonly int $globalPartId,
    ) {}

    public static function forPage(int $pageId, string $surface = 'canvas'): self
    {
        return new self($surface, 'page', $pageId, 0);
    }

    public static function forGlobalPart(int $globalPartId, string $surface = 'canvas'): self
    {
        return new self($surface, 'global_part', 0, $globalPartId);
    }

    /** @return array{surface: string, scope: string, page_id: int, global_part_id: int} */
    public function toArray(): array
    {
        return [
            'surface'        => $this->surface,
            'scope'          => $this->scope,
            'page_id'        => $this->pageId,
            'global_part_id' => $this->globalPartId,
        ];
    }
}

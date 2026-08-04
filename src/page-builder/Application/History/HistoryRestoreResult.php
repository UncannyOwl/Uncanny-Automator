<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\History;

use UncannyPageBuilder\Application\Controls\PageDetails;

final class HistoryRestoreResult
{
    /**
     * @param array{sections: array<int, array<string, mixed>>, compiled_css: string}|null $sectionLayout
     */
    private function __construct(
        private readonly ?array $sectionLayout,
        private readonly ?PageDetails $pageDetails,
    ) {}

    /** @param array{sections: array<int, array<string, mixed>>, compiled_css: string} $layout */
    public static function sections(array $layout): self
    {
        return new self($layout, null);
    }

    public static function pageDetails(PageDetails $details): self
    {
        return new self(null, $details);
    }

    /** @return array{sections: array<int, array<string, mixed>>, compiled_css: string}|null */
    public function sectionLayout(): ?array
    {
        return $this->sectionLayout;
    }

    public function pageDetailsValue(): ?PageDetails
    {
        return $this->pageDetails;
    }
}

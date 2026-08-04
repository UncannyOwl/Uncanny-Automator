<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

/**
 * The page identity that one static artifact is being built to publish.
 *
 * WordPress still contains the previous public title and slug while a draft is
 * compiled. Passing the future identity explicitly prevents static bindings
 * from accidentally freezing those older public values into the artifact.
 */
final class StaticExportPageIdentity
{
    public function __construct(
        private readonly int $pageId,
        private readonly string $title,
        private readonly string $permalink,
    ) {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('Static export page identity requires a positive page ID.');
        }
        if (trim($title) === '') {
            throw new \InvalidArgumentException('Static export page identity requires a title.');
        }
        if (trim($permalink) === '') {
            throw new \InvalidArgumentException('Static export page identity requires a future permalink.');
        }
    }

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function permalink(): string
    {
        return $this->permalink;
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\ContentType;

/**
 * Host boundary for discovering content types and their display facts.
 */
interface ContentTypeCatalogInterface
{
    /**
     * @return list<ContentType>
     */
    public function contentTypes(): array;
}

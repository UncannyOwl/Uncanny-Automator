<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

/** Source adapter used by element commits to migrate legacy inline styles. */
interface InlineStylePatcherInterface
{
    /**
     * @param string[] $properties
     */
    public function removeFromElement(string $html, string $elementId, array $properties): InlineStylePatch;
}

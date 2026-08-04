<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

interface SectionManifestExtractorInterface
{
    /**
     * Derive a deterministic manifest from a persisted section source.
     */
    public function extract(Section $section): SectionManifest;
}

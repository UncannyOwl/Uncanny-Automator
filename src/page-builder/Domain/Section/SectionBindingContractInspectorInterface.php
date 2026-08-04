<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

interface SectionBindingContractInspectorInterface
{
    /**
     * Derive deterministic binding contracts from a persisted section source.
     *
     * @return SectionBindingContract[]
     */
    public function inspect(Section $section): array;
}

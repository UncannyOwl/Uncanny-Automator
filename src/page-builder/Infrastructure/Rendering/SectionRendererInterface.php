<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

/**
 * Runtime contract for declaration-backed dynamic section renderers.
 */
interface SectionRendererInterface
{
    /**
     * @param array<string, mixed> $args
     */
    public function render(string $cardTemplate, array $args): string;
}

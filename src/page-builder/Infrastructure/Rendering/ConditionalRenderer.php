<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

/**
 * Marker renderer for structural conditional bindings.
 *
 * Conditional wrappers are resolved by DynamicRenderer before normal content
 * binding replacement. This class gives declarations an honest runtime owner
 * without routing conditional behavior through WpSingleValueRenderer.
 */
final class ConditionalRenderer implements SectionRendererInterface
{
    /**
     * @param array<string, mixed> $args
     */
    public function render(string $cardTemplate, array $args): string
    {
        return $cardTemplate;
    }
}

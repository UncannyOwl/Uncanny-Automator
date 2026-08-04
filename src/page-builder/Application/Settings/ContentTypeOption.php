<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Settings;

/**
 * Read model for one selectable Page Builder content type.
 */
final class ContentTypeOption
{
    public function __construct(
        private readonly string $slug,
        private readonly string $label,
        private readonly bool $enabled,
    ) {}

    public function slug(): string
    {
        return $this->slug;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }
}

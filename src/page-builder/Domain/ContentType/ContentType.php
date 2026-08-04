<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\ContentType;

/**
 * Page Builder-relevant facts about one host content type.
 *
 * The domain receives these facts through a port. It does not know how a host
 * such as WordPress discovers or represents content types.
 */
final class ContentType
{
    public function __construct(
        private readonly string $slug,
        private readonly string $label,
        private readonly bool $public,
        private readonly bool $showsUi,
        private readonly bool $supportsEditor,
    ) {
        if (
            $slug === ''
            || preg_match('/^[a-z0-9_-]+$/', $slug) !== 1
        ) {
            throw new \InvalidArgumentException('Content type slug is invalid.');
        }

        if (trim($label) === '') {
            throw new \InvalidArgumentException('Content type label is required.');
        }
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function showsUi(): bool
    {
        return $this->showsUi;
    }

    public function supportsEditor(): bool
    {
        return $this->supportsEditor;
    }
}

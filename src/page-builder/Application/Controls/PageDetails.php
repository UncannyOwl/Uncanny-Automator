<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

/**
 * Page Builder working identity plus a non-authoritative URL presentation.
 */
final class PageDetails
{
    public function __construct(
        private readonly int $pageId,
        private readonly string $title,
        private readonly string $slug,
        private readonly string $permalink,
        private readonly string $permalinkPrefix,
        private readonly string $permalinkSuffix,
        private readonly string $previewUrl,
        private readonly bool $permalinkIsLive = false,
    ) {}

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function permalink(): string
    {
        return $this->permalink;
    }

    public function permalinkPrefix(): string
    {
        return $this->permalinkPrefix;
    }

    public function permalinkSuffix(): string
    {
        return $this->permalinkSuffix;
    }

    public function previewUrl(): string
    {
        return $this->previewUrl;
    }

    public function permalinkIsLive(): bool
    {
        return $this->permalinkIsLive;
    }

    /** @return array{title: string, slug: string, permalink: string, permalink_prefix: string, permalink_suffix: string, preview_url: string, permalink_is_live: bool} */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'permalink' => $this->permalink,
            'permalink_prefix' => $this->permalinkPrefix,
            'permalink_suffix' => $this->permalinkSuffix,
            'preview_url' => $this->previewUrl,
            'permalink_is_live' => $this->permalinkIsLive,
        ];
    }
}

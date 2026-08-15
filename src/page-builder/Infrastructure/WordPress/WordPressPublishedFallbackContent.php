<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Verified split between the WordPress-owned prefix and one fallback suffix.
 */
final class WordPressPublishedFallbackContent
{
    public function __construct(
        private readonly string $originalContent,
        private readonly int $formatVersion,
        private readonly int $artifactId,
        private readonly string $artifactHash,
        private readonly string $fallbackHash,
        private readonly ShellMode $shellMode,
        private readonly string $suffixHash,
    ) {}

    public function originalContent(): string
    {
        return $this->originalContent;
    }

    public function formatVersion(): int
    {
        return $this->formatVersion;
    }

    public function artifactId(): int
    {
        return $this->artifactId;
    }

    public function artifactHash(): string
    {
        return $this->artifactHash;
    }

    public function fallbackHash(): string
    {
        return $this->fallbackHash;
    }

    public function shellMode(): ShellMode
    {
        return $this->shellMode;
    }

    public function suffixHash(): string
    {
        return $this->suffixHash;
    }
}

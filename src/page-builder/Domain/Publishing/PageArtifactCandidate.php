<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Publishing;

use UncannyPageBuilder\Domain\Concurrency\SourceGenerationSnapshot;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Complete, validated output built from one coherent working-source snapshot.
 *
 * The candidate is not public and has no storage identity. Only the explicit
 * publication transaction may turn it into a PublishedPageArtifact.
 */
final class PageArtifactCandidate
{
    /**
     * @param array<string, mixed> $assetsManifest
     * @param array<string, mixed> $dependencies
     * @param array<int, array<string, mixed>> $staticSafetyReport
     */
    public function __construct(
        private readonly int $pageId,
        private readonly string $sourceRevisionHash,
        private readonly int $pageSectionCount,
        private readonly string $title,
        private readonly string $slug,
        private readonly ShellMode $shellMode,
        private readonly string $html,
        private readonly string $css,
        private readonly string $customJavaScript,
        private readonly array $assetsManifest,
        private readonly array $dependencies,
        private readonly array $staticSafetyReport,
        private readonly int $createdBy,
        private readonly ?PageSourceSnapshot $sourceSnapshot = null,
    ) {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('Page artifact candidate requires a positive page ID.');
        }
        if ($sourceRevisionHash !== trim($sourceRevisionHash) || $sourceRevisionHash === '' || strlen($sourceRevisionHash) > 128) {
            throw new \InvalidArgumentException('Page artifact candidate source revision hash is invalid.');
        }
        if ($pageSectionCount < 0) {
            throw new \InvalidArgumentException('Page artifact candidate section count must not be negative.');
        }
        if ($title !== trim($title)) {
            throw new \InvalidArgumentException('Page artifact candidate title is invalid.');
        }
        if ($slug !== trim($slug) || $slug === '' || $this->textLength($slug) > 200) {
            throw new \InvalidArgumentException('Page artifact candidate slug is invalid.');
        }
        if ($shellMode === ShellMode::None) {
            throw new \InvalidArgumentException('Page artifact candidates require a resolved shell mode.');
        }
        if (!$this->isAssociativeArray($assetsManifest)) {
            throw new \InvalidArgumentException('Page artifact candidate asset manifest must be an associative array.');
        }
        if (!$this->isAssociativeArray($dependencies)) {
            throw new \InvalidArgumentException('Page artifact candidate dependencies must be an associative array.');
        }
        if ($staticSafetyReport !== [] && !$this->isListOfArrays($staticSafetyReport)) {
            throw new \InvalidArgumentException('Page artifact candidate static-safety report must be a list of records.');
        }
        foreach ($staticSafetyReport as $record) {
            if (($record['status'] ?? '') === 'failed') {
                throw new \InvalidArgumentException('Unsafe output cannot become a page artifact candidate.');
            }
        }
        if ($createdBy <= 0) {
            throw new \InvalidArgumentException('Page artifact candidates require a human creator.');
        }

        $snapshot = SourceGenerationSnapshot::fromDependencies($dependencies);
        if (!$snapshot instanceof SourceGenerationSnapshot || $snapshot->pageId() !== $pageId) {
            throw new \InvalidArgumentException('Page artifact candidate source generations are missing or invalid.');
        }
        if ($sourceSnapshot instanceof PageSourceSnapshot) {
            if (
                $sourceSnapshot->id() !== null
                || $sourceSnapshot->pageId() !== $pageId
                || $sourceSnapshot->sourceRevisionHash() !== $sourceRevisionHash
                || $sourceSnapshot->pageGeneration() !== $snapshot->pageGeneration()
                || $sourceSnapshot->createdBy() !== $createdBy
            ) {
                throw new \InvalidArgumentException('Page artifact candidate source snapshot does not match its output.');
            }
        }
    }

    // Section: Captured publication source

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function sourceRevisionHash(): string
    {
        return $this->sourceRevisionHash;
    }

    public function pageSectionCount(): int
    {
        return $this->pageSectionCount;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function shellMode(): ShellMode
    {
        return $this->shellMode;
    }

    public function html(): string
    {
        return $this->html;
    }

    public function css(): string
    {
        return $this->css;
    }

    public function customJavaScript(): string
    {
        return $this->customJavaScript;
    }

    /** @return array<string, mixed> */
    public function assetsManifest(): array
    {
        return $this->assetsManifest;
    }

    /** @return array<string, mixed> */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    /** @return array<int, array<string, mixed>> */
    public function staticSafetyReport(): array
    {
        return $this->staticSafetyReport;
    }

    public function createdBy(): int
    {
        return $this->createdBy;
    }

    public function sourceSnapshot(): ?PageSourceSnapshot
    {
        return $this->sourceSnapshot;
    }

    public function sourceGenerations(): SourceGenerationSnapshot
    {
        $snapshot = SourceGenerationSnapshot::fromDependencies($this->dependencies);
        if (!$snapshot instanceof SourceGenerationSnapshot) {
            throw new \LogicException('Validated artifact candidate lost its source generation snapshot.');
        }

        return $snapshot;
    }

    // Section: Immutable publication conversion

    public function publish(
        string $finalSlug,
        \DateTimeImmutable $createdAt,
        ?int $sourceSnapshotId = null,
    ): PublishedPageArtifact {
        if ($finalSlug !== $this->slug) {
            throw new \InvalidArgumentException('The final slug must match the approved artifact candidate.');
        }

        return PublishedPageArtifact::create(
            pageId: $this->pageId,
            sourceRevisionHash: $this->sourceRevisionHash,
            pageSectionCount: $this->pageSectionCount,
            title: $this->title,
            slug: $finalSlug,
            shellMode: $this->shellMode,
            html: $this->html,
            css: $this->css,
            customJavaScript: $this->customJavaScript,
            assetsManifest: $this->assetsManifest,
            dependencies: $this->dependencies,
            createdBy: $this->createdBy,
            createdAt: $createdAt,
            staticSafetyReport: $this->staticSafetyReport,
            sourceSnapshotId: $sourceSnapshotId,
        );
    }

    // Section: Domain validation helpers

    /** @param array<mixed> $value */
    private function isAssociativeArray(array $value): bool
    {
        return $value === [] || !array_is_list($value);
    }

    /** @param array<mixed> $value */
    private function isListOfArrays(array $value): bool
    {
        if (!array_is_list($value)) {
            return false;
        }

        foreach ($value as $record) {
            if (!is_array($record)) {
                return false;
            }
        }

        return true;
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

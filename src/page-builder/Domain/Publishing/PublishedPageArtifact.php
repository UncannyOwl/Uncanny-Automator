<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Publishing;

use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * One immutable Page Builder publication, including a valid empty page.
 *
 * This is compiled output, not editable source. It deliberately contains no
 * "latest" or projection status; public selection belongs to page state.
 */
final class PublishedPageArtifact
{
    public const ARTIFACT_VERSION = 1;

    /**
     * @param array<string, mixed> $dependencies
     * @param array<string, mixed> $assetsManifest
     * @param array<int, array<string, mixed>> $staticSafetyReport
     */
    private function __construct(
        private readonly ?int $id,
        private readonly int $pageId,
        private readonly int $artifactVersion,
        private readonly ?int $sourceSnapshotId,
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
        private readonly PageArtifactStaticSafetyStatus $staticSafetyStatus,
        private readonly array $staticSafetyReport,
        private readonly int $createdBy,
        private readonly ?\DateTimeImmutable $createdAt,
    ) {
        if ($id !== null && $id <= 0) {
            throw new \InvalidArgumentException('Published artifact ID must be positive.');
        }
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('Published artifact requires a positive page ID.');
        }
        if ($artifactVersion <= 0) {
            throw new \InvalidArgumentException('Published artifact version must be positive.');
        }
        if ($sourceSnapshotId !== null && $sourceSnapshotId <= 0) {
            throw new \InvalidArgumentException('Published artifact source snapshot ID must be positive.');
        }
        if ($sourceRevisionHash !== trim($sourceRevisionHash) || $sourceRevisionHash === '' || strlen($sourceRevisionHash) > 128) {
            throw new \InvalidArgumentException('Published artifact source revision hash is invalid.');
        }
        if ($pageSectionCount < 0) {
            throw new \InvalidArgumentException('Published artifact section count must not be negative.');
        }
        if ($slug !== trim($slug) || $slug === '' || $this->textLength($slug) > 200) {
            throw new \InvalidArgumentException('Published artifact slug is invalid.');
        }
        if ($shellMode === ShellMode::None) {
            throw new \InvalidArgumentException('Published artifacts require a resolved shell mode.');
        }
        if (!$this->isAssociativeArray($assetsManifest)) {
            throw new \InvalidArgumentException('Published artifact asset manifest must be an associative array.');
        }
        if (!$this->isAssociativeArray($dependencies)) {
            throw new \InvalidArgumentException('Published artifact dependencies must be an associative array.');
        }
        if ($staticSafetyStatus !== PageArtifactStaticSafetyStatus::Safe) {
            throw new \InvalidArgumentException('Only statically safe output can become a published artifact.');
        }
        if ($staticSafetyReport !== [] && !$this->isListOfArrays($staticSafetyReport)) {
            throw new \InvalidArgumentException('Published artifact static-safety report must be a list of records.');
        }
        if ($createdBy <= 0) {
            throw new \InvalidArgumentException('Published artifact requires a human creator.');
        }
    }

    /**
     * @param array<string, mixed> $dependencies
     * @param array<string, mixed> $assetsManifest
     * @param array<int, array<string, mixed>> $staticSafetyReport
     */
    public static function create(
        int $pageId,
        string $sourceRevisionHash,
        int $pageSectionCount,
        string $title,
        string $slug,
        ShellMode $shellMode,
        string $html,
        string $css,
        string $customJavaScript,
        array $assetsManifest,
        array $dependencies,
        int $createdBy,
        ?\DateTimeImmutable $createdAt = null,
        array $staticSafetyReport = [],
        ?int $sourceSnapshotId = null,
    ): self {
        return new self(
            id: null,
            pageId: $pageId,
            artifactVersion: self::ARTIFACT_VERSION,
            sourceSnapshotId: $sourceSnapshotId,
            sourceRevisionHash: $sourceRevisionHash,
            pageSectionCount: $pageSectionCount,
            title: $title,
            slug: $slug,
            shellMode: $shellMode,
            html: $html,
            css: $css,
            customJavaScript: $customJavaScript,
            assetsManifest: $assetsManifest,
            dependencies: $dependencies,
            staticSafetyStatus: PageArtifactStaticSafetyStatus::Safe,
            staticSafetyReport: $staticSafetyReport,
            createdBy: $createdBy,
            createdAt: $createdAt,
        );
    }

    /**
     * @param array<string, mixed> $dependencies
     * @param array<string, mixed> $assetsManifest
     * @param array<int, array<string, mixed>> $staticSafetyReport
     */
    public static function hydrate(
        int $id,
        int $pageId,
        int $artifactVersion,
        string $sourceRevisionHash,
        string $storedContentHash,
        string $storedDependencyHash,
        int $pageSectionCount,
        string $title,
        string $slug,
        ShellMode $shellMode,
        string $html,
        string $css,
        string $customJavaScript,
        array $assetsManifest,
        array $dependencies,
        PageArtifactStaticSafetyStatus|string $staticSafetyStatus,
        array $staticSafetyReport,
        int $createdBy,
        \DateTimeImmutable $createdAt,
        ?int $sourceSnapshotId = null,
    ): self {
        $artifact = new self(
            id: $id,
            pageId: $pageId,
            artifactVersion: $artifactVersion,
            sourceSnapshotId: $sourceSnapshotId,
            sourceRevisionHash: $sourceRevisionHash,
            pageSectionCount: $pageSectionCount,
            title: $title,
            slug: $slug,
            shellMode: $shellMode,
            html: $html,
            css: $css,
            customJavaScript: $customJavaScript,
            assetsManifest: $assetsManifest,
            dependencies: $dependencies,
            staticSafetyStatus: is_string($staticSafetyStatus)
                ? PageArtifactStaticSafetyStatus::fromStorage($staticSafetyStatus)
                : $staticSafetyStatus,
            staticSafetyReport: $staticSafetyReport,
            createdBy: $createdBy,
            createdAt: $createdAt,
        );

        $artifact->assertStoredHash('content', $storedContentHash, $artifact->contentHash());
        $artifact->assertStoredHash('dependency', $storedDependencyHash, $artifact->dependencyHash());

        return $artifact;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function artifactVersion(): int
    {
        return $this->artifactVersion;
    }

    public function sourceSnapshotId(): ?int
    {
        return $this->sourceSnapshotId;
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

    public function staticSafetyStatus(): PageArtifactStaticSafetyStatus
    {
        return $this->staticSafetyStatus;
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

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Link a legacy immutable artifact to its newly captured editable source.
     *
     * The link participates in the artifact content hash, so callers must
     * persist the returned hash in the same transaction as the snapshot ID.
     */
    public function withSourceSnapshotId(int $sourceSnapshotId): self
    {
        if ($sourceSnapshotId <= 0) {
            throw new \InvalidArgumentException('Published artifact source snapshot ID must be positive.');
        }
        if ($this->sourceSnapshotId !== null) {
            throw new \LogicException('Published artifact already has an editable source snapshot.');
        }

        return new self(
            id: $this->id,
            pageId: $this->pageId,
            artifactVersion: $this->artifactVersion,
            sourceSnapshotId: $sourceSnapshotId,
            sourceRevisionHash: $this->sourceRevisionHash,
            pageSectionCount: $this->pageSectionCount,
            title: $this->title,
            slug: $this->slug,
            shellMode: $this->shellMode,
            html: $this->html,
            css: $this->css,
            customJavaScript: $this->customJavaScript,
            assetsManifest: $this->assetsManifest,
            dependencies: $this->dependencies,
            staticSafetyStatus: $this->staticSafetyStatus,
            staticSafetyReport: $this->staticSafetyReport,
            createdBy: $this->createdBy,
            createdAt: $this->createdAt,
        );
    }

    public function dependencyHash(): string
    {
        return hash('sha256', $this->encodeNormalized($this->dependencies));
    }

    public function contentHash(): string
    {
        $payload = [
            'page_id' => $this->pageId,
            'artifact_version' => $this->artifactVersion,
            'source_revision_hash' => $this->sourceRevisionHash,
            'page_section_count' => $this->pageSectionCount,
            'title' => $this->title,
            'slug' => $this->slug,
            'shell_mode' => $this->shellMode->value,
            'html' => $this->html,
            'css' => $this->css,
            'custom_javascript' => $this->customJavaScript,
            'assets_manifest' => $this->assetsManifest,
            'dependency_hash' => $this->dependencyHash(),
            'static_safety_status' => $this->staticSafetyStatus->value,
            'static_safety_report' => $this->staticSafetyReport,
        ];

        // Legacy artifacts predate editable snapshots. Omitting the absent key
        // preserves their stored integrity hash until lazy migration links a
        // snapshot and persists the corresponding new hash atomically.
        if ($this->sourceSnapshotId !== null) {
            $payload['source_snapshot_id'] = $this->sourceSnapshotId;
        }

        return hash('sha256', $this->encodeNormalized($payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->pageId,
            'artifact_version' => $this->artifactVersion,
            'source_snapshot_id' => $this->sourceSnapshotId,
            'source_revision_hash' => $this->sourceRevisionHash,
            'content_hash' => $this->contentHash(),
            'dependency_hash' => $this->dependencyHash(),
            'dependencies' => $this->dependencies,
            'page_section_count' => $this->pageSectionCount,
            'title' => $this->title,
            'slug' => $this->slug,
            'shell_mode' => $this->shellMode->value,
            'html' => $this->html,
            'css' => $this->css,
            'custom_javascript' => $this->customJavaScript,
            'assets_manifest' => $this->assetsManifest,
            'static_safety_status' => $this->staticSafetyStatus->value,
            'static_safety_report' => $this->staticSafetyReport,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assertStoredHash(string $name, string $storedHash, string $calculatedHash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $storedHash) !== 1 || !hash_equals(strtolower($storedHash), $calculatedHash)) {
            throw new \UnexpectedValueException("Published artifact {$name} hash does not match its stored data.");
        }
    }

    /** @param array<string, mixed> $value */
    private function encodeNormalized(array $value): string
    {
        $this->sortRecursively($value);

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function sortRecursively(mixed &$value): void
    {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as &$child) {
            $this->sortRecursively($child);
        }
        unset($child);

        if (!array_is_list($value)) {
            ksort($value);
        }
    }

    private function isAssociativeArray(array $value): bool
    {
        return $value === [] || !array_is_list($value);
    }

    private function isListOfArrays(array $records): bool
    {
        $index = 0;
        foreach ($records as $key => $record) {
            if ($key !== $index || !is_array($record)) {
                return false;
            }
            $index++;
        }

        return true;
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

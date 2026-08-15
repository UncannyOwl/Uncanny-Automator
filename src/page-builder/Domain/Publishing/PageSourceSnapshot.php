<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Publishing;

/**
 * Immutable, editable page-owned source captured by one publication.
 *
 * Reusable/global-part source is deliberately excluded. A page snapshot keeps
 * only the selected reusable IDs so opening an older page revision cannot roll
 * shared content backwards.
 */
final class PageSourceSnapshot
{
    public const SNAPSHOT_VERSION = 1;

    private readonly string $sourceContentHash;

    /**
     * @param array{
     *     sections: array<int, array<string, mixed>>,
     *     page_design_overrides: array<string, mixed>,
     *     custom_javascript: string,
     *     title: string,
     *     slug: string,
     *     shell_mode: string,
     *     shell_mode_explicit: bool,
     *     header_override_id: int|null,
     *     footer_override_id: int|null
     * } $source
     */
    private function __construct(
        private readonly ?int $id,
        private readonly int $pageId,
        private readonly int $snapshotVersion,
        private readonly string $sourceRevisionHash,
        private readonly int $pageGeneration,
        private readonly array $source,
        private readonly int $createdBy,
        private readonly ?\DateTimeImmutable $createdAt,
        ?string $storedSourceContentHash = null,
    ) {
        if ($id !== null && $id <= 0) {
            throw new \InvalidArgumentException('Page source snapshot ID must be positive.');
        }
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('Page source snapshot requires a positive page ID.');
        }
        if ($snapshotVersion <= 0) {
            throw new \InvalidArgumentException('Page source snapshot version must be positive.');
        }
        if (
            $sourceRevisionHash !== trim($sourceRevisionHash)
            || $sourceRevisionHash === ''
            || strlen($sourceRevisionHash) > 128
        ) {
            throw new \InvalidArgumentException('Page source snapshot revision hash is invalid.');
        }
        if ($pageGeneration < 0) {
            throw new \InvalidArgumentException('Page source snapshot generation must not be negative.');
        }
        if ($createdBy <= 0) {
            throw new \InvalidArgumentException('Page source snapshots require a human creator.');
        }

        $this->assertSource($source);
        $computedSourceContentHash = self::hashSource($source);
        if (
            $storedSourceContentHash !== null
            && $storedSourceContentHash !== ''
            && !hash_equals($storedSourceContentHash, $computedSourceContentHash)
        ) {
            throw new \RuntimeException('Stored page source snapshot content hash does not match its source.');
        }
        $this->sourceContentHash = $computedSourceContentHash;
    }

    /** @param array<string, mixed> $source */
    public static function create(
        int $pageId,
        string $sourceRevisionHash,
        int $pageGeneration,
        array $source,
        int $createdBy,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            id: null,
            pageId: $pageId,
            snapshotVersion: self::SNAPSHOT_VERSION,
            sourceRevisionHash: $sourceRevisionHash,
            pageGeneration: $pageGeneration,
            source: $source,
            createdBy: $createdBy,
            createdAt: $createdAt,
        );
    }

    /** @param array<string, mixed> $source */
    public static function hydrate(
        int $id,
        int $pageId,
        int $snapshotVersion,
        string $sourceRevisionHash,
        int $pageGeneration,
        array $source,
        int $createdBy,
        \DateTimeImmutable $createdAt,
        ?string $storedSourceContentHash = null,
    ): self {
        return new self(
            id: $id,
            pageId: $pageId,
            snapshotVersion: $snapshotVersion,
            sourceRevisionHash: $sourceRevisionHash,
            pageGeneration: $pageGeneration,
            source: $source,
            createdBy: $createdBy,
            createdAt: $createdAt,
            storedSourceContentHash: $storedSourceContentHash,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function snapshotVersion(): int
    {
        return $this->snapshotVersion;
    }

    public function sourceRevisionHash(): string
    {
        return $this->sourceRevisionHash;
    }

    public function pageGeneration(): int
    {
        return $this->pageGeneration;
    }

    public function sourceContentHash(): string
    {
        return $this->sourceContentHash;
    }

    /** @return array<string, mixed> */
    public function source(): array
    {
        return $this->source;
    }

    public function createdBy(): int
    {
        return $this->createdBy;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @param array<string, mixed> $source */
    private function assertSource(array $source): void
    {
        if (!is_array($source['sections'] ?? null) || !array_is_list($source['sections'])) {
            throw new \InvalidArgumentException('Page source snapshot sections must be a list.');
        }
        if (
            !is_array($source['page_design_overrides'] ?? null)
            || (($source['page_design_overrides'] ?? []) !== [] && array_is_list($source['page_design_overrides']))
        ) {
            throw new \InvalidArgumentException('Page source snapshot design overrides must be an object.');
        }

        foreach (['custom_javascript', 'title', 'slug', 'shell_mode'] as $key) {
            if (!is_string($source[$key] ?? null)) {
                throw new \InvalidArgumentException(sprintf('Page source snapshot %s is invalid.', $key));
            }
        }
        if (!is_bool($source['shell_mode_explicit'] ?? null)) {
            throw new \InvalidArgumentException('Page source snapshot shell-mode identity is invalid.');
        }

        foreach (['header_override_id', 'footer_override_id'] as $key) {
            $value = $source[$key] ?? null;
            if ($value !== null && (!is_int($value) || ($value !== -1 && $value <= 0))) {
                throw new \InvalidArgumentException(sprintf('Page source snapshot %s is invalid.', $key));
            }
        }
    }

    /** @param array<string, mixed> $source */
    private static function hashSource(array $source): string
    {
        return hash('sha256', self::encodeJson(
            self::canonicalize($source),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }

    private static function encodeJson(mixed $value, int $flags = 0): string|false
    {
        // Exact JSON bytes are part of the domain hash; wp_json_encode() may repair invalid UTF-8 and change the digest.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This deterministic language operation is not a WordPress capability.
        return json_encode($value, $flags);
    }
}

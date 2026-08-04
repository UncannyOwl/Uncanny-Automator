<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Publishing;

/**
 * Mutable page-owned draft details plus the one explicit public artifact pointer.
 *
 * Editable sections and their generation remain in their existing stores. This
 * state owns only the values that cannot safely use public WordPress columns
 * while they are drafts, together with publication audit data.
 */
final class PagePublicationState
{
    private function __construct(
        private readonly int $pageId,
        private readonly string $draftTitle,
        private readonly string $draftSlug,
        private readonly ?int $publishedArtifactId,
        private readonly ?int $publishedSourceSnapshotId,
        private readonly DraftResumePolicy $draftResumePolicy,
        private readonly int $updatedBy,
        private readonly \DateTimeImmutable $updatedAt,
        private readonly ?int $publishedBy,
        private readonly ?\DateTimeImmutable $publishedAt,
    ) {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('Page publication state requires a positive page ID.');
        }
        if ($this->textLength($draftSlug) > 200) {
            throw new \InvalidArgumentException('Draft page slug must not exceed 200 characters.');
        }
        if ($updatedBy < 0) {
            throw new \InvalidArgumentException('Draft update actor must not be negative.');
        }

        if ($publishedArtifactId === null) {
            if ($publishedBy !== null || $publishedAt !== null) {
                throw new \InvalidArgumentException('An unpublished page cannot contain publication audit data.');
            }
            if ($publishedSourceSnapshotId !== null) {
                throw new \InvalidArgumentException('An unpublished page cannot select a published source snapshot.');
            }

            return;
        }

        if ($publishedArtifactId <= 0) {
            throw new \InvalidArgumentException('Published artifact ID must be positive.');
        }
        if ($publishedBy === null || $publishedBy <= 0 || $publishedAt === null) {
            throw new \InvalidArgumentException('A published artifact pointer requires a human actor and timestamp.');
        }
        if ($publishedSourceSnapshotId !== null && $publishedSourceSnapshotId <= 0) {
            throw new \InvalidArgumentException('Published source snapshot ID must be positive.');
        }
    }

    public static function unpublished(
        int $pageId,
        string $draftTitle,
        string $draftSlug,
        int $updatedBy,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            pageId: $pageId,
            draftTitle: $draftTitle,
            draftSlug: $draftSlug,
            publishedArtifactId: null,
            publishedSourceSnapshotId: null,
            draftResumePolicy: DraftResumePolicy::Active,
            updatedBy: $updatedBy,
            updatedAt: $updatedAt,
            publishedBy: null,
            publishedAt: null,
        );
    }

    public static function hydrate(
        int $pageId,
        string $draftTitle,
        string $draftSlug,
        ?int $publishedArtifactId,
        int $updatedBy,
        \DateTimeImmutable $updatedAt,
        ?int $publishedBy,
        ?\DateTimeImmutable $publishedAt,
        ?int $publishedSourceSnapshotId = null,
        DraftResumePolicy|string $draftResumePolicy = DraftResumePolicy::Active,
    ): self {
        return new self(
            pageId: $pageId,
            draftTitle: $draftTitle,
            draftSlug: $draftSlug,
            publishedArtifactId: $publishedArtifactId,
            publishedSourceSnapshotId: $publishedSourceSnapshotId,
            draftResumePolicy: is_string($draftResumePolicy)
                ? DraftResumePolicy::fromStorage($draftResumePolicy)
                : $draftResumePolicy,
            updatedBy: $updatedBy,
            updatedAt: $updatedAt,
            publishedBy: $publishedBy,
            publishedAt: $publishedAt,
        );
    }

    public function withDraftDetails(
        string $title,
        string $slug,
        int $updatedBy,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            pageId: $this->pageId,
            draftTitle: $title,
            draftSlug: $slug,
            publishedArtifactId: $this->publishedArtifactId,
            publishedSourceSnapshotId: $this->publishedSourceSnapshotId,
            draftResumePolicy: $this->draftResumePolicy,
            updatedBy: $updatedBy,
            updatedAt: $updatedAt,
            publishedBy: $this->publishedBy,
            publishedAt: $this->publishedAt,
        );
    }

    public function withPublishedArtifact(
        int $artifactId,
        int $publishedBy,
        \DateTimeImmutable $publishedAt,
        ?int $sourceSnapshotId = null,
    ): self {
        return new self(
            pageId: $this->pageId,
            draftTitle: $this->draftTitle,
            draftSlug: $this->draftSlug,
            publishedArtifactId: $artifactId,
            publishedSourceSnapshotId: $sourceSnapshotId,
            draftResumePolicy: DraftResumePolicy::Active,
            updatedBy: $this->updatedBy,
            updatedAt: $this->updatedAt,
            publishedBy: $publishedBy,
            publishedAt: $publishedAt,
        );
    }

    /**
     * End public handover without deleting draft details or immutable artifacts.
     */
    public function withoutPublishedArtifact(): self
    {
        return new self(
            pageId: $this->pageId,
            draftTitle: $this->draftTitle,
            draftSlug: $this->draftSlug,
            publishedArtifactId: null,
            publishedSourceSnapshotId: null,
            draftResumePolicy: $this->draftResumePolicy,
            updatedBy: $this->updatedBy,
            updatedAt: $this->updatedAt,
            publishedBy: null,
            publishedAt: null,
        );
    }

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function draftTitle(): string
    {
        return $this->draftTitle;
    }

    public function draftSlug(): string
    {
        return $this->draftSlug;
    }

    public function publishedArtifactId(): ?int
    {
        return $this->publishedArtifactId;
    }

    public function publishedSourceSnapshotId(): ?int
    {
        return $this->publishedSourceSnapshotId;
    }

    public function draftResumePolicy(): DraftResumePolicy
    {
        return $this->draftResumePolicy;
    }

    public function withDraftResumePolicy(DraftResumePolicy $policy): self
    {
        return new self(
            pageId: $this->pageId,
            draftTitle: $this->draftTitle,
            draftSlug: $this->draftSlug,
            publishedArtifactId: $this->publishedArtifactId,
            publishedSourceSnapshotId: $this->publishedSourceSnapshotId,
            draftResumePolicy: $policy,
            updatedBy: $this->updatedBy,
            updatedAt: $this->updatedAt,
            publishedBy: $this->publishedBy,
            publishedAt: $this->publishedAt,
        );
    }

    public function updatedBy(): int
    {
        return $this->updatedBy;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function publishedBy(): ?int
    {
        return $this->publishedBy;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isPublished(): bool
    {
        return $this->publishedArtifactId !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'page_id' => $this->pageId,
            'draft_title' => $this->draftTitle,
            'draft_slug' => $this->draftSlug,
            'published_artifact_id' => $this->publishedArtifactId,
            'published_source_snapshot_id' => $this->publishedSourceSnapshotId,
            'draft_resume_policy' => $this->draftResumePolicy->value,
            'updated_by' => $this->updatedBy,
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
            'published_by' => $this->publishedBy,
            'published_at' => $this->publishedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

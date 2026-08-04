<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editor;

use UncannyPageBuilder\Domain\Publishing\DraftResumePolicy;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshot;

/**
 * The durable page source selected for one fresh editor load.
 */
final class EditorPageSourceSelection
{
    public function __construct(
        private readonly string $loadedSource,
        private readonly int $workingGeneration,
        private readonly ?PageSourceSnapshot $publishedSnapshot,
        private readonly DraftResumePolicy $resumePolicy,
        private readonly bool $workingDraftNewer,
    ) {
        if (!in_array($loadedSource, ['working', 'published'], true)) {
            throw new \InvalidArgumentException('Editor source must be working or published.');
        }
        if ($workingGeneration < 0) {
            throw new \InvalidArgumentException('Working generation must not be negative.');
        }
        if ($loadedSource === 'published' && !$publishedSnapshot instanceof PageSourceSnapshot) {
            throw new \InvalidArgumentException('Published editor source requires a snapshot.');
        }
    }

    public function loadedSource(): string
    {
        return $this->loadedSource;
    }

    public function workingGeneration(): int
    {
        return $this->workingGeneration;
    }

    public function publishedSnapshot(): ?PageSourceSnapshot
    {
        return $this->publishedSnapshot;
    }

    public function resumePolicy(): DraftResumePolicy
    {
        return $this->resumePolicy;
    }

    public function workingDraftNewer(): bool
    {
        return $this->workingDraftNewer;
    }

    public function shouldOfferParkedDraft(): bool
    {
        return $this->loadedSource === 'published'
            && $this->resumePolicy === DraftResumePolicy::Parked
            && $this->workingDraftNewer;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'loaded_source' => $this->loadedSource,
            'working_generation' => $this->workingGeneration,
            'loaded_working_generation' => $this->loadedSource === 'working'
                ? $this->workingGeneration
                : null,
            'loaded_snapshot_id' => $this->loadedSource === 'published'
                ? $this->publishedSnapshot?->id()
                : null,
            'published_snapshot_id' => $this->publishedSnapshot?->id(),
            'working_draft_newer' => $this->workingDraftNewer,
            'draft_resume_policy' => $this->resumePolicy->value,
            'offer_parked_draft' => $this->shouldOfferParkedDraft(),
        ];
    }
}

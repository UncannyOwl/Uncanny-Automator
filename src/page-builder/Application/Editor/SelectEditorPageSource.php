<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editor;

use UncannyPageBuilder\Application\Publishing\PageDraftStatusPortInterface;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Publishing\DraftResumePolicy;
use UncannyPageBuilder\Domain\Publishing\PagePublicationState;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshot;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshotRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifactRepositoryInterface;

/**
 * Draft-status pages always reopen their working source.
 *
 * A published page selects its immutable published source only when a newer
 * human-saved draft is parked. Agent-created working drafts remain active.
 */
final class SelectEditorPageSource
{
    public function __construct(
        private readonly PageStateRepositoryInterface $states,
        private readonly PageSourceSnapshotRepositoryInterface $snapshots,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
        private readonly PageDraftStatusPortInterface $visibility,
        private readonly ?PublishedPageArtifactRepositoryInterface $artifacts = null,
    ) {}

    public function forPage(int $pageId): EditorPageSourceSelection
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id must be positive.');
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $selection = $this->select($pageId);
            $endingGeneration = $this->sourceGenerations->pageGeneration($pageId);
            $endingStatus = $this->visibility->currentStatus($pageId);
            $endingState = $this->states->findForPage($pageId);

            if (
                $selection['working_generation'] === $endingGeneration
                && $selection['status'] === $endingStatus
                && $selection['state_identity'] === $this->stateIdentity($endingState)
            ) {
                return $selection['source'];
            }
        }

        throw new \RuntimeException(
            'The page source changed while the editor revision was being selected.',
        );
    }

    /**
     * @return array{
     *     source: EditorPageSourceSelection,
     *     working_generation: int,
     *     status: string,
     *     state_identity: string
     * }
     */
    private function select(int $pageId): array
    {
        $workingGeneration = $this->sourceGenerations->pageGeneration($pageId);
        $status = $this->visibility->currentStatus($pageId);
        $isPublished = $status === 'publish';
        $state = $this->states->findForPage($pageId);
        $snapshotId = $state?->publishedSourceSnapshotId();
        $snapshot = $snapshotId !== null
            ? $this->snapshots->findForPage($pageId, $snapshotId)
            : null;
        if (
            $isPublished
            && $snapshot instanceof PageSourceSnapshot
            && $this->artifacts instanceof PublishedPageArtifactRepositoryInterface
        ) {
            $artifactId = $state?->publishedArtifactId();
            $artifact = is_int($artifactId)
                ? $this->artifacts->findForPage($pageId, $artifactId)
                : null;
            if (
                $artifact === null
                || $artifact->sourceSnapshotId() !== $snapshot->id()
                || !hash_equals($artifact->sourceRevisionHash(), $snapshot->sourceRevisionHash())
            ) {
                throw new \RuntimeException(
                    'The published artifact and editable source snapshot do not share one source identity.',
                );
            }
        }
        if (
            $isPublished
            && $snapshot instanceof PageSourceSnapshot
            && $workingGeneration < $snapshot->pageGeneration()
        ) {
            throw new \RuntimeException(
                'The working page generation is older than its published source snapshot.',
            );
        }
        $workingNewer = $snapshot instanceof PageSourceSnapshot
            && $workingGeneration > $snapshot->pageGeneration();
        $policy = $state?->draftResumePolicy() ?? DraftResumePolicy::Active;
        $loadPublished = $isPublished
            && $snapshot instanceof PageSourceSnapshot
            && $workingNewer
            && $policy === DraftResumePolicy::Parked;

        return [
            'source' => new EditorPageSourceSelection(
                loadedSource: $loadPublished ? 'published' : 'working',
                workingGeneration: $workingGeneration,
                publishedSnapshot: $snapshot,
                resumePolicy: $policy,
                workingDraftNewer: $workingNewer,
            ),
            'working_generation' => $workingGeneration,
            'status' => $status,
            'state_identity' => $this->stateIdentity($state),
        ];
    }

    private function stateIdentity(?PagePublicationState $state): string
    {
        if ($state === null) {
            return 'missing';
        }

        return implode(':', [
            (string) ($state->publishedArtifactId() ?? 0),
            (string) ($state->publishedSourceSnapshotId() ?? 0),
            $state->draftResumePolicy()->value,
        ]);
    }
}

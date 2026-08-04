<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Application\Editor\PublishedSourceSnapshotMigrationInterface;
use UncannyPageBuilder\Application\Publishing\PageSourceSnapshotCaptureInterface;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationSnapshot;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Publishing\PagePublicationState;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshotRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifactRepositoryInterface;

/**
 * Backfills a legacy published-source pointer without guessing from HTML.
 *
 * Migration is allowed only when the current page generation still equals the
 * generation captured by the selected public artifact. The guarded transaction
 * rechecks that identity while locking page state, then links the new immutable
 * snapshot to both the artifact and page pointer.
 */
final class LazyPublishedSourceSnapshotMigrator implements PublishedSourceSnapshotMigrationInterface
{
    public function __construct(
        private readonly PageStateRepositoryInterface $states,
        private readonly PublishedPageArtifactRepositoryInterface $artifacts,
        private readonly PageSourceSnapshotRepositoryInterface $snapshots,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
        private readonly PageSourceSnapshotCaptureInterface $sourceCapture,
    ) {}

    public function migrateIfSafe(int $pageId): bool
    {
        if ($pageId <= 0) {
            return false;
        }

        $state = $this->states->findForPage($pageId);
        if (
            !$state instanceof PagePublicationState
            || $state->publishedSourceSnapshotId() !== null
            || $state->publishedArtifactId() === null
        ) {
            return false;
        }

        $artifact = $this->artifacts->findForPage($pageId, $state->publishedArtifactId());
        if ($artifact === null || $artifact->sourceSnapshotId() !== null) {
            return false;
        }

        $artifactGenerations = SourceGenerationSnapshot::fromDependencies($artifact->dependencies());
        $currentPageGeneration = $this->sourceGenerations->pageGeneration($pageId);
        $currentGlobalGeneration = $this->sourceGenerations->globalGeneration();
        if (
            !$artifactGenerations instanceof SourceGenerationSnapshot
            || $artifactGenerations->pageId() !== $pageId
            || $artifactGenerations->pageGeneration() !== $currentPageGeneration
            || $artifactGenerations->globalGeneration() !== $currentGlobalGeneration
        ) {
            // Existing page or shared source diverged. Current shell defaults
            // may no longer describe the selected legacy artifact, so keep the
            // working source active until a human publishes a new snapshot.
            return false;
        }

        $snapshot = $this->sourceCapture->capture(
            pageId: $pageId,
            sourceRevisionHash: $artifact->sourceRevisionHash(),
            pageGeneration: $artifactGenerations->pageGeneration(),
            state: $state,
            createdBy: $state->publishedBy() ?? 0,
            shellMode: $artifact->shellMode(),
        );
        $guard = new SourceGenerationSnapshot(
            pageId: $pageId,
            pageGeneration: $artifactGenerations->pageGeneration(),
            globalGeneration: $artifactGenerations->globalGeneration(),
        );

        try {
            return (bool) $this->sourceGenerations->publishIfCurrent(
                $guard,
                function () use ($pageId, $state, $artifact, $snapshot): bool {
                    $lockedState = $this->states->findForPage($pageId);
                    if (
                        !$lockedState instanceof PagePublicationState
                        || $lockedState->publishedArtifactId() !== $state->publishedArtifactId()
                        || $lockedState->publishedSourceSnapshotId() !== null
                    ) {
                        return false;
                    }

                    $lockedArtifact = $this->artifacts->findForPage($pageId, $artifact->id() ?? 0);
                    if ($lockedArtifact === null || $lockedArtifact->sourceSnapshotId() !== null) {
                        return false;
                    }

                    $stored = $this->snapshots->insert($snapshot);
                    if ($stored->id() === null) {
                        throw new \RuntimeException('Legacy editable source migration did not create a snapshot.');
                    }
                    $linkedArtifact = $lockedArtifact->withSourceSnapshotId($stored->id());

                    global $wpdb;
                    $artifactUpdated = $wpdb->update(
                        SchemaManager::pageArtifactsTableName(),
                        [
                            'source_snapshot_id' => $stored->id(),
                            'content_hash' => $linkedArtifact->contentHash(),
                        ],
                        ['id' => $lockedArtifact->id(), 'page_id' => $pageId],
                        ['%d', '%s'],
                        ['%d', '%d'],
                    );
                    $stateUpdated = $wpdb->update(
                        SchemaManager::pageStateTableName(),
                        [
                            'published_source_snapshot_id' => $stored->id(),
                            'draft_resume_policy' => 'active',
                        ],
                        ['page_id' => $pageId, 'published_artifact_id' => $lockedArtifact->id()],
                        ['%d', '%s'],
                        ['%d', '%d'],
                    );
                    if ($artifactUpdated !== 1 || $stateUpdated !== 1) {
                        throw new \RuntimeException('Legacy editable source pointers could not be linked atomically.');
                    }

                    return true;
                },
            );
        } catch (StaleSourceGenerationException) {
            // Another legitimate source writer won the race. Leave the legacy
            // draft active and retry the safe migration on a later editor load.
            return false;
        }
    }
}

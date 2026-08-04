<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

use UncannyPageBuilder\Application\History\HistoryOperationRestorer;
use UncannyPageBuilder\Application\History\OperationHistoryService;
use UncannyPageBuilder\Application\History\PageDetailsHistoryRestorerInterface;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\HistorySnapshotConflictException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Publishing\PagePublicationState;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;

/**
 * Owns the draft title and slug lifecycle for Page Builder pages.
 *
 * WordPress supplies the one-time adoption values and URL shape. After that,
 * the Page Builder state row is authoritative for editor and Agent reads.
 */
final class PageDetailsService implements PageDetailsPortInterface, PageDetailsHistoryRestorerInterface
{
    /** @var \Closure(): \DateTimeImmutable */
    private readonly \Closure $now;

    /**
     * @param (\Closure(): \DateTimeImmutable)|null $now
     */
    public function __construct(
        private readonly PageStateRepositoryInterface $states,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
        private readonly PageDetailsProjectionInterface $projection,
        ?\Closure $now = null,
        private readonly ?OperationHistoryService $history = null,
    ) {
        $this->now = $now ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable(
            'now',
            new \DateTimeZone('UTC'),
        );
    }

    public function find(int $pageId): ?PageDetails
    {
        if ($pageId <= 0) {
            return null;
        }

        $state = $this->states->findForPage($pageId);
        if (!$state instanceof PagePublicationState) {
            return null;
        }

        return $this->project($state);
    }

    public function initialize(int $pageId, int $updatedBy): PageDetails
    {
        $this->assertWriteContext($pageId, $updatedBy);

        $state = $this->states->findForPage($pageId);
        if (!$state instanceof PagePublicationState) {
            $publicDetails = $this->projection->readPublicPage($pageId);
            if (!$publicDetails instanceof PageDetails) {
                throw new \RuntimeException('Page details could not be initialized because the WordPress page was not found.');
            }

            $state = $this->states->initialize(PagePublicationState::unpublished(
                pageId: $pageId,
                draftTitle: $publicDetails->title(),
                draftSlug: $publicDetails->slug(),
                updatedBy: $updatedBy,
                updatedAt: $this->currentTime(),
            ));
        }

        return $this->requireProjection($state);
    }

    public function update(int $pageId, string $title, string $slug, int $updatedBy): PageDetails
    {
        $this->assertWriteContext($pageId, $updatedBy);

        $projected = $this->projection->projectDraft($pageId, $title, $slug);
        if (!$projected instanceof PageDetails) {
            throw new \RuntimeException('Page details could not be saved because the WordPress page was not found.');
        }

        [$state, $generation] = $this->coherentState($pageId);
        if (!$state instanceof PagePublicationState) {
            throw new \RuntimeException('Page details must be initialized when Page Builder adopts the page.');
        }

        if (
            $state->draftTitle() === $projected->title()
            && $state->draftSlug() === $projected->slug()
        ) {
            return $projected;
        }

        $nextState = $state->withDraftDetails(
            title: $projected->title(),
            slug: $projected->slug(),
            updatedBy: $updatedBy,
            updatedAt: $this->currentTime(),
        );
        $write = fn(): PagePublicationState => $this->states->saveDraftDetails($nextState, $generation);
        $saved = $this->history instanceof OperationHistoryService
            ? $this->history->recordPageMutation(
                pageId: $pageId,
                expectedGeneration: $generation,
                actorUserId: $updatedBy,
                operation: HistoryOperationRestorer::PAGE_DETAILS_CHANGED,
                label: 'Updated page details',
                beforePayload: [[
                    'title' => $state->draftTitle(),
                    'slug' => $state->draftSlug(),
                ]],
                afterPayload: [[
                    'title' => $projected->title(),
                    'slug' => $projected->slug(),
                ]],
                write: $write,
            )
            : $write();
        if (!$saved instanceof PagePublicationState) {
            throw new \RuntimeException('Draft page details did not return their saved state.');
        }

        return $this->requireProjection($saved);
    }

    /**
     * Restore one typed history payload inside OperationHistoryService's page
     * transaction without recording a second operation.
     *
     * @param array{title: string, slug: string} $target
     * @param array{title: string, slug: string} $expectedCurrent
     */
    public function restoreFromHistory(
        int $pageId,
        array $target,
        array $expectedCurrent,
        int $updatedBy,
    ): PageDetails {
        $this->assertWriteContext($pageId, $updatedBy);

        $projected = $this->projection->projectDraft($pageId, $target['title'], $target['slug']);
        if (!$projected instanceof PageDetails) {
            throw new \RuntimeException('History page details could not be projected.');
        }

        [$state, $generation] = $this->coherentState($pageId);
        if (!$state instanceof PagePublicationState) {
            throw new \RuntimeException('Page details must exist before history can restore them.');
        }
        if (
            $state->draftTitle() !== $expectedCurrent['title']
            || $state->draftSlug() !== $expectedCurrent['slug']
        ) {
            throw new HistorySnapshotConflictException();
        }

        $saved = $this->states->saveDraftDetails(
            $state->withDraftDetails(
                title: $projected->title(),
                slug: $projected->slug(),
                updatedBy: $updatedBy,
                updatedAt: $this->currentTime(),
            ),
            $generation,
        );

        return $this->requireProjection($saved);
    }

    /**
     * Read the state between matching generation values so a concurrent title,
     * section, design, shell, or runtime save cannot supply a mixed snapshot.
     *
     * @return array{0: PagePublicationState|null, 1: int}
     */
    private function coherentState(int $pageId): array
    {
        $before = 0;
        $after = 0;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $before = $this->sourceGenerations->pageGeneration($pageId);
            $state = $this->states->findForPage($pageId);
            $after = $this->sourceGenerations->pageGeneration($pageId);

            if ($before === $after) {
                return [$state, $before];
            }
        }

        throw new StaleSourceGenerationException('page', $before, $after);
    }

    private function project(PagePublicationState $state): ?PageDetails
    {
        return $this->projection->projectDraft(
            $state->pageId(),
            $state->draftTitle(),
            $state->draftSlug(),
        );
    }

    private function requireProjection(PagePublicationState $state): PageDetails
    {
        $details = $this->project($state);
        if (!$details instanceof PageDetails) {
            throw new \RuntimeException('The saved page details could not be projected.');
        }

        return $details;
    }

    private function assertWriteContext(int $pageId, int $updatedBy): void
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id must be positive.');
        }
        if ($updatedBy < 0) {
            throw new \InvalidArgumentException('The page-details actor must not be negative.');
        }
    }

    private function currentTime(): \DateTimeImmutable
    {
        $now = $this->now;
        $time = $now();
        if (!$time instanceof \DateTimeImmutable) {
            throw new \LogicException('The page-details clock must return DateTimeImmutable.');
        }

        return $time;
    }
}

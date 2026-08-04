<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Publishing\DraftResumePolicy;
use UncannyPageBuilder\Domain\Publishing\PagePublicationState;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;

/**
 * InnoDB persistence for draft page details and the public artifact pointer.
 */
final class DatabasePageStateRepository implements PageStateRepositoryInterface
{
    /** @var array<string, true> */
    private array $transactionalTables = [];

    public function __construct(
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
        private readonly ?PageSourceMutation $pageSource = null,
    ) {}

    public function findForPage(int $pageId): ?PagePublicationState
    {
        if ($pageId <= 0) {
            return null;
        }

        $this->ensureSchema();
        $row = $this->findRow($pageId, false);

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function initialize(PagePublicationState $state): PagePublicationState
    {
        if ($state->isPublished()) {
            throw new \InvalidArgumentException('Page state initialization must start unpublished.');
        }

        $this->ensureSchema();
        $this->assertTransactionalTables([SchemaManager::pageStateTableName()]);

        return $this->transaction(function () use ($state): PagePublicationState {
            $stored = $this->findRow($state->pageId(), true);
            if ($stored !== null) {
                return $this->hydrate($stored);
            }

            $this->insertUnpublished($state);

            return $this->requireState($state->pageId());
        });
    }

    public function saveDraftDetails(
        PagePublicationState $state,
        int $expectedGeneration,
    ): PagePublicationState {
        if ($expectedGeneration < 0) {
            throw new \InvalidArgumentException('A valid page generation is required.');
        }

        $this->ensureSchema();
        $this->assertTransactionalTables([SchemaManager::pageStateTableName()]);

        return $this->commitPage(
            $state->pageId(),
            $expectedGeneration,
            function () use ($state): PagePublicationState {
                $stored = $this->findRow($state->pageId(), true);
                if ($stored === null) {
                    throw new \RuntimeException('Page state must be initialized before draft details are saved.');
                }

                global $wpdb;
                $updated = $wpdb->update(
                    SchemaManager::pageStateTableName(),
                    [
                        'draft_title' => $state->draftTitle(),
                        'draft_slug' => $state->draftSlug(),
                        'updated_by' => $state->updatedBy(),
                        'updated_at' => $state->updatedAt()->format('Y-m-d H:i:s'),
                    ],
                    ['page_id' => $state->pageId()],
                    ['%s', '%s', '%d', '%s'],
                    ['%d'],
                );
                if ($updated === false) {
                    throw new \RuntimeException('Failed to save draft page details.');
                }

                return $this->requireState($state->pageId());
            },
        );
    }

    public function deleteForPage(int $pageId): int
    {
        if ($pageId <= 0) {
            return 0;
        }

        $this->ensureSchema();

        global $wpdb;
        $deleted = $wpdb->delete(SchemaManager::pageStateTableName(), ['page_id' => $pageId], ['%d']);
        if ($deleted === false) {
            throw new \RuntimeException('Failed to delete Page Builder page state.');
        }

        return (int) $deleted;
    }

    public function saveDraftResumePolicy(
        int $pageId,
        DraftResumePolicy $policy,
    ): PagePublicationState {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id must be positive.');
        }

        $this->ensureSchema();

        global $wpdb;
        $updated = $wpdb->update(
            SchemaManager::pageStateTableName(),
            ['draft_resume_policy' => $policy->value],
            ['page_id' => $pageId],
            ['%s'],
            ['%d'],
        );
        if ($updated === false) {
            throw new \RuntimeException('Failed to save the working-draft resume policy.');
        }

        return $this->requireState($pageId);
    }

    // Section: Row-level integrity

    private function insertUnpublished(PagePublicationState $state): void
    {
        global $wpdb;

        $inserted = $wpdb->insert(
            SchemaManager::pageStateTableName(),
            [
                'page_id' => $state->pageId(),
                'draft_title' => $state->draftTitle(),
                'draft_slug' => $state->draftSlug(),
                'published_artifact_id' => null,
                'published_source_snapshot_id' => null,
                'draft_resume_policy' => $state->draftResumePolicy()->value,
                'updated_by' => $state->updatedBy(),
                'updated_at' => $state->updatedAt()->format('Y-m-d H:i:s'),
                'published_by' => null,
                'published_at' => null,
            ],
            ['%d', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s'],
        );
        if ($inserted === false) {
            throw new \RuntimeException('Failed to initialize Page Builder page state.');
        }
    }

    private function requireState(int $pageId): PagePublicationState
    {
        $row = $this->findRow($pageId, false);
        if ($row === null) {
            throw new \RuntimeException('Failed to reload Page Builder page state.');
        }

        return $this->hydrate($row);
    }

    private function findRow(int $pageId, bool $forUpdate): ?object
    {
        global $wpdb;

        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . SchemaManager::pageStateTableName() . ' WHERE page_id = %d LIMIT 1' . $lock,
            $pageId,
        ));

        return is_object($row) ? $row : null;
    }

    private function hydrate(object $row): PagePublicationState
    {
        $storedArtifactId = $row->published_artifact_id ?? null;
        $publishedArtifactId = $storedArtifactId !== null ? (int) $storedArtifactId : null;
        $publishedBy = $row->published_by ?? null;
        $publishedAt = $row->published_at ?? null;
        $storedSourceSnapshotId = $row->published_source_snapshot_id ?? null;
        $publishedSourceSnapshotId = $storedSourceSnapshotId !== null
            ? (int) $storedSourceSnapshotId
            : null;

        /*
         * A handover releases the artifact and source snapshot as one public
         * identity. Tolerate rows written before both pointers were cleared
         * together without weakening the domain invariant or mutating storage.
         */
        if ($publishedArtifactId === null) {
            $publishedSourceSnapshotId = null;
        }

        return PagePublicationState::hydrate(
            pageId: (int) $row->page_id,
            draftTitle: (string) $row->draft_title,
            draftSlug: (string) $row->draft_slug,
            publishedArtifactId: $publishedArtifactId,
            updatedBy: (int) $row->updated_by,
            updatedAt: new \DateTimeImmutable((string) $row->updated_at),
            publishedBy: $publishedBy !== null ? (int) $publishedBy : null,
            publishedAt: $publishedAt !== null ? new \DateTimeImmutable((string) $publishedAt) : null,
            publishedSourceSnapshotId: $publishedSourceSnapshotId,
            draftResumePolicy: DraftResumePolicy::fromStorage($row->draft_resume_policy ?? null),
        );
    }

    /** @param callable(): PagePublicationState $operation */
    private function transaction(callable $operation): PagePublicationState
    {
        global $wpdb;

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new \RuntimeException('Failed to start a page-state transaction.');
        }

        try {
            $result = $operation();
            if ($wpdb->query('COMMIT') === false) {
                throw new \RuntimeException('Failed to commit a page-state transaction.');
            }

            return $result;
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    /** @param string[] $tables */
    private function assertTransactionalTables(array $tables): void
    {
        global $wpdb;

        foreach ($tables as $table) {
            if (isset($this->transactionalTables[$table])) {
                continue;
            }

            $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
            $engine = is_object($status) && isset($status->Engine) ? (string) $status->Engine : '';
            if (strcasecmp($engine, 'InnoDB') !== 0) {
                throw new SourceTransactionsUnavailableException($table, $engine !== '' ? $engine : 'unknown');
            }

            $this->transactionalTables[$table] = true;
        }
    }

    private function ensureSchema(): void
    {
        if (function_exists('get_option') && defined('ABSPATH')) {
            SchemaManager::ensureSchema();
        }
    }

    /**
     * @param callable(): mixed $write
     */
    private function commitPage(int $pageId, int $expectedGeneration, callable $write): mixed
    {
        if ($this->pageSource instanceof PageSourceMutation) {
            return $this->pageSource->runExpected($pageId, $expectedGeneration, $write);
        }

        return $this->generationStore()->commitPage($pageId, $expectedGeneration, $write);
    }

    private function generationStore(): SourceGenerationStoreInterface
    {
        return $this->sourceGenerations ?? new WordPressSourceGenerationStore();
    }
}

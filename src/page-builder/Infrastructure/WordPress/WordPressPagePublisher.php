<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Publishing\PagePublicationFailed;
use UncannyPageBuilder\Application\Publishing\PageDeactivationFallbackAssetResolverInterface;
use UncannyPageBuilder\Application\Publishing\PagePublisherInterface;
use UncannyPageBuilder\Application\Publishing\PublishPageResult;
use UncannyPageBuilder\Application\Rendering\PublishedPageAssets;
use UncannyPageBuilder\Application\Rendering\PublishedPageRuntimeUnavailable;
use UncannyPageBuilder\Application\ThemeCompositionPageTemplateSynchronizerInterface;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Publishing\PageArtifactCandidate;
use UncannyPageBuilder\Domain\Publishing\PageDeactivationFallback;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshot;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshotRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifact;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifactRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Infrastructure\Persistence\SchemaManager;
use UncannyPageBuilder\Infrastructure\Persistence\WpPageOwnershipRepository;

/**
 * Atomically commits one human-approved Page Builder publication to WordPress.
 */
final class WordPressPagePublisher implements PagePublisherInterface
{
    private const PUBLIC_SLUG_STATUS = 'publish';

    /** @var \Closure(string, int, string, string, int): string */
    private readonly \Closure $slugResolver;

    /** @var \Closure(): \DateTimeImmutable */
    private readonly \Closure $now;

    /** @var \Closure(): string */
    private readonly \Closure $gmtNow;

    /** @var \Closure(int): void */
    private readonly \Closure $cacheCleaner;

    private readonly ?PageDeactivationFallbackAssetResolverInterface $fallbackAssets;

    private readonly WordPressPublishedFallbackComposer $fallbackComposer;

    private readonly WpOriginalPageContentStore $originalContent;

    private readonly WordPressPublishedFallbackParser $fallbackParser;

    /**
     * @param (\Closure(string, int, string, string, int): string)|null $slugResolver
     * @param (\Closure(): \DateTimeImmutable)|null $now
     * @param (\Closure(): string)|null $gmtNow
     * @param (\Closure(int): void)|null $cacheCleaner
     */
    public function __construct(
        private readonly PublishedPageArtifactRepositoryInterface $artifacts,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
        ?\Closure $slugResolver = null,
        ?\Closure $now = null,
        ?\Closure $gmtNow = null,
        ?\Closure $cacheCleaner = null,
        private readonly ?ThemeCompositionPageTemplateSynchronizerInterface $themeTemplates = null,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
        private readonly ?PageSourceSnapshotRepositoryInterface $sourceSnapshots = null,
        ?PageDeactivationFallbackAssetResolverInterface $fallbackAssets = null,
        ?WordPressPublishedFallbackComposer $fallbackComposer = null,
        ?WpOriginalPageContentStore $originalContent = null,
        ?WordPressPublishedFallbackParser $fallbackParser = null,
    ) {
        $this->slugResolver = $slugResolver ?? fn (
            string $slug,
            int $pageId,
            string $status,
            string $postType,
            int $parentId,
        ): string => $this->wordpressUniqueSlug($slug, $pageId, $status, $postType, $parentId);
        $this->now = $now ?? fn (): \DateTimeImmutable => $this->wordpressCurrentTime();
        $this->gmtNow = $gmtNow ?? fn (): string => $this->wordpressCurrentGmtTime();
        $this->cacheCleaner = $cacheCleaner ?? function (int $pageId): void {
            $this->cleanWordPressPageCache($pageId);
        };
        $this->fallbackAssets = $fallbackAssets;
        $this->fallbackComposer = $fallbackComposer ?? new WordPressPublishedFallbackComposer();
        $this->originalContent = $originalContent ?? new WpOriginalPageContentStore();
        $this->fallbackParser = $fallbackParser ?? new WordPressPublishedFallbackParser();
    }

    public function publish(PageArtifactCandidate $candidate): PublishPageResult
    {
        $this->ensureSchema();

        /*
         * Asset resolution reads and hashes release files. Complete it before
         * publication acquires database locks so an unavailable release asset
         * cannot prolong or partially enter the public-state transaction.
         */
        try {
            if (!$this->fallbackAssets instanceof PageDeactivationFallbackAssetResolverInterface) {
                throw new \RuntimeException('Page deactivation fallback asset resolution is unavailable.');
            }
            $resolvedFallbackAssets = $this->fallbackAssets->resolveFallback($candidate->deactivationFallback());
        } catch (PagePublicationFailed $exception) {
            throw $exception;
        } catch (PublishedPageRuntimeUnavailable $exception) {
            throw PagePublicationFailed::publicStateCommitFailed(
                $exception,
                $exception->reasonCode(),
            );
        } catch (\Throwable $exception) {
            throw PagePublicationFailed::publicStateCommitFailed($exception);
        }

        try {
            $result = $this->sourceGenerations->publishIfCurrent(
                $candidate->sourceGenerations(),
                fn (): PublishPageResult => $this->commit($candidate, $resolvedFallbackAssets),
            );
        } catch (StaleSourceGenerationException | PagePublicationFailed $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw PagePublicationFailed::publicStateCommitFailed($exception);
        }

        if (!$result instanceof PublishPageResult) {
            throw PagePublicationFailed::publicStateCommitFailed(
                new \RuntimeException('The publication transaction returned an invalid result.'),
            );
        }

        /*
         * Controlled SQL keeps artifact insertion, WordPress public fields,
         * and the pointer in one rollback boundary. Cache invalidation happens
         * only after commit; re-entrant WordPress save hooks never run while
         * Page Builder publication locks are held.
         */
        try {
            $this->cleanPageCache($candidate->pageId());
        } catch (\Throwable) {
            $result = $result->withWarning(
                'cache_invalidation_failed',
                'The page was published, but WordPress cache invalidation failed.',
            );
        }

        try {
            $this->artifacts->pruneForPage($candidate->pageId());
        } catch (\Throwable) {
            $result = $result->withWarning(
                'artifact_retention_failed',
                'The page was published, but old artifact cleanup failed.',
            );
        }

        try {
            $this->sourceSnapshots?->pruneForPage($candidate->pageId());
        } catch (\Throwable) {
            $result = $result->withWarning(
                'source_snapshot_retention_failed',
                'The page was published, but old editable snapshot cleanup failed.',
            );
        }

        return $result;
    }

    // Section: Atomic publication transaction

    private function commit(PageArtifactCandidate $candidate, PublishedPageAssets $resolvedFallbackAssets): PublishPageResult
    {
        $state = $this->pageState($candidate->pageId());
        if (
            (string) ($state->draft_title ?? '') !== $candidate->title()
            || (string) ($state->draft_slug ?? '') !== $candidate->slug()
        ) {
            throw PagePublicationFailed::staleSource('page_details');
        }

        $page = $this->page($candidate->pageId());
        $postType = (string) ($page->post_type ?? '');
        $previousStatus = (string) ($page->post_status ?? '');
        if (!$this->supportsPostType->isSupported($postType) || $previousStatus === 'trash') {
            throw new \RuntimeException('The WordPress page cannot be published in its current state.');
        }

        /*
         * Authorization is checked once before the artifact build and again
         * here under the same page lock used by Return to WordPress. The
         * second check closes the gap where ownership can change while an
         * expensive immutable artifact is being compiled.
         */
        if (!$this->pageBuilderOwnsPage($candidate->pageId())) {
            throw PagePublicationFailed::notAuthorized();
        }
        $originalContent = $this->originalContent->originalContentForPublication($candidate->pageId());
        $this->validateExistingFallback(
            (string) ($page->post_content ?? ''),
            $candidate->pageId(),
            isset($state->published_artifact_id) ? (int) $state->published_artifact_id : 0,
        );

        $resolvedSlug = $this->resolveUniqueSlug(
            $candidate->slug(),
            $candidate->pageId(),
            $postType,
            (int) ($page->post_parent ?? 0),
        );
        if ($resolvedSlug !== $candidate->slug()) {
            throw PagePublicationFailed::slugConflict($candidate->slug(), $resolvedSlug);
        }

        $publishedAt = $this->currentTime();
        $sourceSnapshotId = $this->storeSourceSnapshot($candidate, $publishedAt);
        $artifact = $candidate->publish($resolvedSlug, $publishedAt, $sourceSnapshotId);

        /*
         * Theme template metadata is visitor-facing public state. Prepare it
         * only inside the guarded human publication transaction, never when a
         * working layout selection is saved.
         */
        if ($candidate->shellMode() === ShellMode::ThemeComposition) {
            $this->themeTemplates?->prepareForThemeComposition($candidate->pageId());
        }

        try {
            $storedArtifact = $this->artifacts->insert($artifact);
        } catch (\Throwable $exception) {
            throw PagePublicationFailed::artifactPersistFailed($exception);
        }
        if ($storedArtifact->id() === null || $storedArtifact->pageId() !== $candidate->pageId()) {
            throw PagePublicationFailed::artifactPersistFailed(
                new \RuntimeException('The immutable artifact repository returned an invalid identity.'),
            );
        }

        $composedContent = $this->fallbackComposer->compose(
            (string) ($page->post_content ?? ''),
            $originalContent,
            $storedArtifact,
            $candidate->deactivationFallback(),
            $resolvedFallbackAssets,
        );

        $this->updatePublicPage($candidate, $publishedAt, $composedContent);
        $this->verifyPublicContent($candidate->pageId(), $composedContent);
        $this->movePointer($candidate, $storedArtifact, $sourceSnapshotId, $publishedAt);

        $publishedArtifactId = $state->published_artifact_id ?? null;

        return new PublishPageResult(
            artifact: $storedArtifact,
            previousStatus: $previousStatus,
            publicStatus: self::PUBLIC_SLUG_STATUS,
            firstPublication: $publishedArtifactId === null || (int) $publishedArtifactId <= 0,
        );
    }

    // Section: Locked source reads

    private function pageState(int $pageId): object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . SchemaManager::pageStateTableName() . ' WHERE page_id = %d LIMIT 1',
            $pageId,
        ));
        if (!is_object($row)) {
            throw new \RuntimeException('Page publication state is missing.');
        }

        return $row;
    }

    private function page(int $pageId): object
    {
        global $wpdb;

        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT ID, post_type, post_status, post_parent, post_content FROM {$postsTable} WHERE ID = %d LIMIT 1",
            $pageId,
        ));
        if (!is_object($row)) {
            throw new \RuntimeException('The WordPress page is missing.');
        }

        return $row;
    }

    private function pageBuilderOwnsPage(int $pageId): bool
    {
        global $wpdb;

        $postmetaTable = isset($wpdb->postmeta)
            ? (string) $wpdb->postmeta
            : (string) $wpdb->prefix . 'postmeta';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT meta_id FROM {$postmetaTable}
             WHERE post_id = %d AND meta_key = %s AND meta_value = '1'
             LIMIT 1 FOR UPDATE",
            $pageId,
            WpPageOwnershipRepository::META_OWNED,
        ));

        return is_object($row);
    }

    private function resolveUniqueSlug(
        string $requestedSlug,
        int $pageId,
        string $postType,
        int $parentId,
    ): string {
        $resolver = $this->slugResolver;
        $resolved = $resolver($requestedSlug, $pageId, self::PUBLIC_SLUG_STATUS, $postType, $parentId);
        if (!is_string($resolved) || trim($resolved) === '') {
            throw new \RuntimeException('WordPress could not resolve the final page slug.');
        }

        return $resolved;
    }

    private function validateExistingFallback(
        string $postContent,
        int $pageId,
        int $publishedArtifactId,
    ): void {
        if ($this->fallbackParser->isLegacyArtifact($postContent)) {
            return;
        }

        $fallback = $this->fallbackParser->parse($postContent);
        if (!$fallback instanceof WordPressPublishedFallbackContent) {
            return;
        }
        if ($publishedArtifactId <= 0) {
            throw new InvalidPublishedFallbackContent(
                'The Page Builder fallback has no matching public artifact pointer.',
            );
        }

        $artifact = $this->artifacts->findForPage($pageId, $publishedArtifactId);
        $fallbackHash = $artifact instanceof PublishedPageArtifact
            ? ($artifact->dependencies()[PageDeactivationFallback::DEPENDENCY_HASH_KEY] ?? null)
            : null;
        if (
            !$artifact instanceof PublishedPageArtifact
            || $fallback->artifactId() !== $publishedArtifactId
            || !hash_equals($artifact->contentHash(), $fallback->artifactHash())
            || !is_string($fallbackHash)
            || !hash_equals($fallbackHash, $fallback->fallbackHash())
            || $artifact->shellMode() !== $fallback->shellMode()
        ) {
            throw new InvalidPublishedFallbackContent(
                'The Page Builder fallback does not match the exact public artifact pointer.',
            );
        }
    }

    // Section: Public field and pointer writes

    private function updatePublicPage(
        PageArtifactCandidate $candidate,
        \DateTimeImmutable $publishedAt,
        string $postContent,
    ): void {
        global $wpdb;

        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $updated = $wpdb->update(
            $postsTable,
            [
                'post_title' => $candidate->title(),
                'post_name' => $candidate->slug(),
                'post_status' => self::PUBLIC_SLUG_STATUS,
                'post_content' => $postContent,
                'post_modified' => $publishedAt->format('Y-m-d H:i:s'),
                'post_modified_gmt' => $this->currentGmtTime(),
            ],
            ['ID' => $candidate->pageId()],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d'],
        );
        if ($updated === false) {
            throw new \RuntimeException('Failed to update WordPress public page fields.');
        }
    }

    private function verifyPublicContent(int $pageId, string $expectedContent): void
    {
        global $wpdb;

        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $actualContent = $wpdb->get_var($wpdb->prepare(
            "SELECT post_content FROM {$postsTable} WHERE ID = %d LIMIT 1",
            $pageId,
        ));

        if (!is_string($actualContent) || $actualContent !== $expectedContent) {
            throw new \RuntimeException('The WordPress page body update could not be verified byte-for-byte.');
        }
    }

    private function movePointer(
        PageArtifactCandidate $candidate,
        PublishedPageArtifact $artifact,
        ?int $sourceSnapshotId,
        \DateTimeImmutable $publishedAt,
    ): void {
        global $wpdb;

        $updated = $wpdb->update(
            SchemaManager::pageStateTableName(),
            [
                'published_artifact_id' => $artifact->id(),
                'published_source_snapshot_id' => $sourceSnapshotId,
                'draft_resume_policy' => 'active',
                'published_by' => $candidate->createdBy(),
                'published_at' => $publishedAt->format('Y-m-d H:i:s'),
            ],
            ['page_id' => $candidate->pageId()],
            ['%d', '%d', '%s', '%d', '%s'],
            ['%d'],
        );
        if ($updated === false) {
            throw new \RuntimeException('Failed to move the published artifact pointer.');
        }
    }

    private function storeSourceSnapshot(
        PageArtifactCandidate $candidate,
        \DateTimeImmutable $publishedAt,
    ): ?int {
        $snapshot = $candidate->sourceSnapshot();
        if (!$snapshot instanceof PageSourceSnapshot) {
            if ($this->sourceSnapshots instanceof PageSourceSnapshotRepositoryInterface) {
                throw new \RuntimeException('The publication candidate is missing its editable source snapshot.');
            }

            // Compatibility for isolated legacy callers and older stored
            // artifacts. Production publication always wires the repository.
            return null;
        }
        if (!$this->sourceSnapshots instanceof PageSourceSnapshotRepositoryInterface) {
            throw new \RuntimeException('Editable page source snapshot storage is unavailable.');
        }

        $stored = $this->sourceSnapshots->insert(PageSourceSnapshot::create(
            pageId: $snapshot->pageId(),
            sourceRevisionHash: $snapshot->sourceRevisionHash(),
            pageGeneration: $snapshot->pageGeneration(),
            source: $snapshot->source(),
            createdBy: $snapshot->createdBy(),
            createdAt: $publishedAt,
        ));
        if ($stored->id() === null || $stored->pageId() !== $candidate->pageId()) {
            throw new \RuntimeException('The editable source snapshot repository returned an invalid identity.');
        }

        return $stored->id();
    }

    // Section: Post-commit WordPress adapters

    private function currentTime(): \DateTimeImmutable
    {
        $now = $this->now;
        $value = $now();
        if (!$value instanceof \DateTimeImmutable) {
            throw new \RuntimeException('WordPress publication time is unavailable.');
        }

        return $value;
    }

    private function currentGmtTime(): string
    {
        $now = $this->gmtNow;
        $value = $now();

        return is_string($value) && $value !== '' ? $value : gmdate('Y-m-d H:i:s');
    }

    private function cleanPageCache(int $pageId): void
    {
        $cleaner = $this->cacheCleaner;
        $cleaner($pageId);
    }

    private function wordpressUniqueSlug(
        string $slug,
        int $pageId,
        string $status,
        string $postType,
        int $parentId,
    ): string {
        $function = $this->wordpressFunction('wp_unique_post_slug');
        if ($function === null) {
            throw new \RuntimeException('WordPress slug resolution is unavailable.');
        }

        $resolved = $function($slug, $pageId, $status, $postType, $parentId);

        return is_string($resolved) ? $resolved : '';
    }

    private function wordpressCurrentTime(): \DateTimeImmutable
    {
        $function = $this->wordpressFunction('current_time');
        $value = $function !== null ? $function('mysql') : gmdate('Y-m-d H:i:s');
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException('WordPress publication time is unavailable.');
        }

        return new \DateTimeImmutable($value);
    }

    private function wordpressCurrentGmtTime(): string
    {
        $function = $this->wordpressFunction('current_time');
        $value = $function !== null ? $function('mysql', true) : gmdate('Y-m-d H:i:s');

        return is_string($value) && $value !== '' ? $value : gmdate('Y-m-d H:i:s');
    }

    private function cleanWordPressPageCache(int $pageId): void
    {
        $function = $this->wordpressFunction('clean_post_cache');
        if ($function !== null) {
            $function($pageId);
        }
    }

    private function wordpressFunction(string $name): ?\Closure
    {
        $namespaced = __NAMESPACE__ . '\\' . $name;
        if (function_exists($namespaced)) {
            return \Closure::fromCallable($namespaced);
        }
        if (function_exists($name)) {
            return \Closure::fromCallable($name);
        }

        return null;
    }

    private function ensureSchema(): void
    {
        if (function_exists('get_option') && defined('ABSPATH')) {
            /*
             * Schema repair may execute DDL, which implicitly commits MySQL
             * transactions. Complete it before acquiring publication locks.
             */
            SchemaManager::ensureSchema();
        }
    }
}

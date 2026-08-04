<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\Reusable\DeleteReusableResult;
use UncannyPageBuilder\Application\Reusable\ReusablePortInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Reusable\Reusable;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Infrastructure\Persistence\SchemaManager;

final class WordPressReusablePort implements ReusablePortInterface
{
    private const POST_TYPE = 'upb_global_part';
    private const TYPE_META_KEY = '_upb_global_part_type';

    public function __construct(
        private readonly GlobalPartRepositoryInterface $globalPartRepository,
        private readonly GlobalPartService $globalPartService,
        private readonly SectionRepositoryInterface $sectionRepository,
        private readonly GlobalPartDefaultsService $globalPartDefaults,
        private readonly GlobalSourceMutation $globalSource,
    ) {}

    // ── Reusable lookup ─────────────────────────────────────────────────────

    public function list(?GlobalPartType $type = null): array
    {
        // Agent-facing reusable management follows the canonical reusable
        // source lane: published internal artifacts only.
        $query = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'DESC',
            'no_found_rows' => true,
        ];

        if ($type === GlobalPartType::Header || $type === GlobalPartType::Footer) {
            $query['meta_key'] = self::TYPE_META_KEY;
            $query['meta_value'] = $type->value;
        } elseif ($type === GlobalPartType::Section) {
            $query['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => self::TYPE_META_KEY,
                    'value' => GlobalPartType::Section->value,
                ],
                [
                    'key' => self::TYPE_META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => self::TYPE_META_KEY,
                    'value' => '',
                ],
            ];
        }

        $posts = get_posts($query);
        if (!is_array($posts)) {
            return [];
        }

        $reusables = [];
        foreach ($posts as $post) {
            if (!$post instanceof \WP_Post || $post->post_status === 'trash') {
                continue;
            }

            $reusable = $this->mapReusable($post);
            if ($type instanceof GlobalPartType && $reusable->type() !== $type) {
                continue;
            }

            $reusables[] = $reusable;
        }

        return $reusables;
    }

    public function find(int $reusableId): ?Reusable
    {
        $post = get_post($reusableId);
        if (!$post instanceof \WP_Post || $post->post_type !== self::POST_TYPE || $post->post_status !== 'publish') {
            return null;
        }

        return $this->mapReusable($post);
    }

    // ── Reusable create ─────────────────────────────────────────────────────

    public function create(string $title, GlobalPartType $type): Reusable
    {
        $requestedTitle = trim($title);
        $resolvedTitle = $this->resolvedTitle($requestedTitle, _x('Untitled reusable', 'Page Builder', 'uncanny-automator'));
        $reusableId = $this->globalPartRepository->createPost($resolvedTitle, $type);

        try {
            if ($requestedTitle === '') {
                $this->assignUntitledTitleWithId($reusableId);
            }

            $reusable = $this->find($reusableId);
            if (!$reusable instanceof Reusable) {
                throw new \RuntimeException('Created reusable could not be loaded.');
            }

            return $reusable;
        } catch (\Throwable $failure) {
            $this->rethrowAfterCreatedPostCleanup($reusableId, $failure);
        }
    }

    public function convertSection(
        int $sectionId,
        string $title,
        GlobalPartType $type,
    ): Reusable {
        $resolvedTitle = $this->resolvedConvertedTitle($sectionId, $title);
        $result = $this->globalPartService->createFromSectionId($sectionId, $resolvedTitle, $type);
        $reusableId = (int) ($result['id'] ?? 0);

        if ($reusableId <= 0) {
            throw new \RuntimeException('Converted reusable was created without a valid ID.');
        }

        try {
            $reusable = $this->find($reusableId);
            if (!$reusable instanceof Reusable) {
                throw new \RuntimeException('Converted reusable could not be loaded.');
            }

            return $reusable;
        } catch (\Throwable $failure) {
            $this->rethrowAfterCreatedPostCleanup($reusableId, $failure);
        }
    }

    // ── Reusable update ─────────────────────────────────────────────────────

    public function update(int $reusableId, ?string $title, ?GlobalPartType $type): Reusable
    {
        $this->currentPost($reusableId);

        if ($title === null && $type === null) {
            throw new \InvalidArgumentException('Provide at least one reusable property to update.');
        }

        $this->globalSource->run(function () use ($reusableId, $title, $type): void {
            if ($title !== null) {
                $this->updatePostTitle(
                    $reusableId,
                    $this->resolvedTitle($title, $this->untitledTitleWithId($reusableId)),
                );
            }

            if ($type instanceof GlobalPartType) {
                $this->updateMetaExact($reusableId, self::TYPE_META_KEY, $type->value);
                $this->clearConflictingDefaults($reusableId, $type);
            }
        });

        clean_post_cache($reusableId);
        $reloaded = get_post($reusableId);
        if (!$reloaded instanceof \WP_Post) {
            throw new \RuntimeException('Updated reusable could not be loaded.');
        }

        return $this->mapReusable($reloaded);
    }

    // ── Reusable delete ─────────────────────────────────────────────────────

    public function delete(int $reusableId, bool $forceDelete): DeleteReusableResult
    {
        $reusable = $this->find($reusableId);
        if (!$reusable instanceof Reusable) {
            throw new \RuntimeException('Reusable could not be loaded for deletion.');
        }

        // WordPress lifecycle hooks must run outside Page Builder's source
        // transaction. GlobalPartDeletionCleanup opens the guarded transaction
        // only for Page Builder-owned cleanup after the core transition.
        $deleted = $forceDelete ? wp_delete_post($reusableId, true) : wp_trash_post($reusableId);
        if ($deleted === false || $deleted instanceof \WP_Error) {
            throw new \RuntimeException('Could not delete the reusable.');
        }

        return new DeleteReusableResult($reusable, $forceDelete);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function currentPost(int $reusableId): \WP_Post
    {
        $post = get_post($reusableId);
        if (!$post instanceof \WP_Post || $post->post_type !== self::POST_TYPE || $post->post_status !== 'publish') {
            throw new \RuntimeException('Reusable not found.');
        }

        return $post;
    }

    private function updateMetaExact(int $postId, string $metaKey, string $value): void
    {
        $updated = update_post_meta($postId, $metaKey, $value);
        if ($updated === false && !$this->metaMatchesDatabase($postId, $metaKey, $value)) {
            throw new \RuntimeException('Reusable metadata update failed.');
        }
    }

    private function metaMatchesDatabase(int $postId, string $metaKey, string $value): bool
    {
        global $wpdb;

        $postmetaTable = isset($wpdb->postmeta) ? (string) $wpdb->postmeta : (string) $wpdb->prefix . 'postmeta';
        $stored = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$postmetaTable}
             WHERE post_id = %d AND meta_key = %s
             ORDER BY meta_id DESC LIMIT 1",
            $postId,
            $metaKey,
        ));

        return is_string($stored) && hash_equals($value, $stored);
    }

    private function mapReusable(\WP_Post $post): Reusable
    {
        $reusableId = (int) $post->ID;
        $type = GlobalPartType::fromString((string) get_post_meta($reusableId, self::TYPE_META_KEY, true));
        $source = $this->loadSourceSummary($reusableId);
        $status = (string) $post->post_status;

        return new Reusable(
            id: $reusableId,
            title: $this->resolvedTitle((string) $post->post_title, _x('Untitled reusable', 'Page Builder', 'uncanny-automator')),
            type: $type,
            status: $status !== '' ? $status : 'publish',
            editorUrl: $this->editorUrl($reusableId, $status),
            hasSource: $source !== null,
            sourceSectionId: $source['section_id'] ?? null,
        );
    }

    /**
     * A reusable can exist before it has any canonical source row. Keep that
     * bootstrap state explicit so the agent can choose create_section once.
     *
     * @return array{section_id: int}|null
     */
    private function loadSourceSummary(int $reusableId): ?array
    {
        global $wpdb;
        $table = SchemaManager::globalSectionsTableName();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE global_part_id = %d ORDER BY position ASC LIMIT 1",
                $reusableId,
            )
        );

        if (!is_object($row)) {
            return null;
        }

        return [
            'section_id' => (int) ($row->id ?? 0),
        ];
    }

    private function resolvedConvertedTitle(int $sectionId, string $title): string
    {
        $resolvedTitle = trim($title);
        if ($resolvedTitle !== '') {
            return $resolvedTitle;
        }

        $section = $this->sectionRepository->findById($sectionId);
        $sectionName = trim($section->name());

        return $sectionName !== '' ? $sectionName : _x('Untitled reusable', 'Page Builder', 'uncanny-automator');
    }

    private function assignUntitledTitleWithId(int $reusableId): void
    {
        $this->globalSource->run(
            fn (): mixed => $this->updatePostTitle($reusableId, $this->untitledTitleWithId($reusableId)),
        );
        clean_post_cache($reusableId);
    }

    /**
     * Reusable creation is not complete until its canonical WordPress post can
     * be reloaded. Remove the just-created post on any later failure so a tool
     * retry cannot accumulate invisible global parts.
     */
    private function rethrowAfterCreatedPostCleanup(int $reusableId, \Throwable $failure): never
    {
        try {
            $canDeletePost = function_exists(__NAMESPACE__ . '\\wp_delete_post') || function_exists('wp_delete_post');
            $deleted = $canDeletePost ? wp_delete_post($reusableId, true) : false;
        } catch (\Throwable $cleanupFailure) {
            throw new \RuntimeException(
                "Reusable creation failed, and its WordPress post could not be removed: {$cleanupFailure->getMessage()}",
                0,
                $failure,
            );
        }

        if ($deleted === false || $deleted instanceof \WP_Error) {
            throw new \RuntimeException(
                'Reusable creation failed, and its WordPress post could not be removed.',
                0,
                $failure,
            );
        }

        throw $failure;
    }

    private function untitledTitleWithId(int $reusableId): string
    {
        return sprintf(
            /* translators: %d: The reusable post ID used in the fallback untitled title. */
            _x('Untitled reusable #%d', 'Page Builder', 'uncanny-automator'),
            $reusableId,
        );
    }

    /**
     * Keep the title and generation in one database transaction without firing
     * third-party save_post hooks inside that transaction.
     */
    private function updatePostTitle(int $reusableId, string $title): void
    {
        global $wpdb;
        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $updated = $wpdb->update(
            $postsTable,
            ['post_title' => $title],
            ['ID' => $reusableId],
            ['%s'],
            ['%d'],
        );
        if ($updated === false) {
            throw new \RuntimeException('Reusable title update failed.');
        }
    }

    private function resolvedTitle(string $title, string $fallback): string
    {
        $resolvedTitle = trim($title);

        return $resolvedTitle !== '' ? $resolvedTitle : $fallback;
    }

    private function editorUrl(int $reusableId, string $status): string
    {
        if ($status === 'publish') {
            return AdminCanvasEditorWindowedGlobalPartPage::editorUrl($reusableId);
        }

        return admin_url('post.php?post=' . $reusableId . '&action=edit');
    }

    /**
     * Default header/footer assignments are type-specific. If a reusable stops
     * being that type, clear the stale default pointer at the same boundary.
     */
    private function clearConflictingDefaults(int $reusableId, GlobalPartType $newType): void
    {
        foreach ([GlobalPartType::Header, GlobalPartType::Footer] as $candidateType) {
            if ($candidateType === $newType) {
                continue;
            }

            if ($this->globalPartDefaults->getDefaultId($candidateType) === $reusableId) {
                $this->globalPartDefaults->setDefaultId($candidateType, null);
            }
        }
    }
}

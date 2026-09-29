<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Canvas\AttachReusableToCanvasResult;
use UncannyPageBuilder\Application\Canvas\CanvasPortInterface;
use UncannyPageBuilder\Application\Canvas\DeleteCanvasResult;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Controls\PageTitleUpdatePortInterface;
use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\Canvas\Canvas;
use UncannyPageBuilder\Domain\Canvas\CanvasCreationUncertainException;
use UncannyPageBuilder\Domain\Canvas\CanvasKind;
use UncannyPageBuilder\Domain\Exception\CanvasNotFoundException;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Exception\ReusableNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Infrastructure\Persistence\WordPressWriteVerificationException;

final class WordPressCanvasPort implements CanvasPortInterface
{
    private const WORKING_CANVAS_REFRESH_WARNING = 'The page layout was saved, but the working canvas could not be refreshed.';
    private const GLOBAL_PART_POST_TYPE = 'upb_global_part';
    private const GLOBAL_PART_TYPE_META = '_upb_global_part_type';
    private const CREATION_MARKER_PREFIX = 'uncanny-page-builder:create:';

    public function __construct(
        private readonly SectionRepositoryInterface $sectionRepository,
        private readonly GlobalPartRepositoryInterface $globalPartRepository,
        private readonly SectionService $sectionService,
        private readonly GlobalPartService $globalPartService,
        private readonly ShellModeService $shellModeService,
        private readonly PageDetailsPortInterface&PageTitleUpdatePortInterface $pageDetails,
        private readonly GlobalSourceMutation $globalSource,
        private readonly ?WorkingCanvasRefresherInterface $workingCanvas = null,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    // ── Canvas lookup ────────────────────────────────────────────────────────

    public function list(?CanvasKind $kind = null): array
    {
        $canvases = [];

        if ($kind === null || $kind === CanvasKind::Page) {
            $supportedPostTypes = $this->supportedPostTypes();
            if ($supportedPostTypes !== []) {
                $pageIds = get_posts([
                    'post_type' => $supportedPostTypes,
                    'post_status' => 'any',
                    'fields' => 'ids',
                    'posts_per_page' => -1,
                    'orderby' => 'ID',
                    'order' => 'DESC',
                    'meta_key' => '_uncanny_page_builder_owned',
                    'meta_value' => '1',
                    'no_found_rows' => true,
                ]);

                if (is_array($pageIds)) {
                    foreach ($pageIds as $pageId) {
                        $post = get_post((int) $pageId);
                        if (
                            !is_object($post)
                            || !$this->supportsPostType->isSupported((string) ($post->post_type ?? ''))
                        ) {
                            continue;
                        }

                        $canvas = $this->find((int) $pageId);
                        if ($canvas instanceof Canvas) {
                            $canvases[] = $canvas;
                        }
                    }
                }
            }
        }

        if ($kind === null || $kind === CanvasKind::GlobalPart) {
            $canvasIds = get_posts([
                'post_type' => self::GLOBAL_PART_POST_TYPE,
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'DESC',
                'no_found_rows' => true,
            ]);

            if (is_array($canvasIds)) {
                foreach ($canvasIds as $canvasId) {
                    $canvas = $this->find((int) $canvasId);
                    if ($canvas instanceof Canvas) {
                        $canvases[] = $canvas;
                    }
                }
            }
        }

        return $canvases;
    }

    public function find(int $canvasId): ?Canvas
    {
        $post = get_post($canvasId);
        if (!is_object($post)) {
            return null;
        }

        if ($this->isTrashedPost($post)) {
            return null;
        }

        $postType = (string) ($post->post_type ?? '');
        $kind = $postType === self::GLOBAL_PART_POST_TYPE
            ? CanvasKind::GlobalPart
            : ($this->supportsPostType->isSupported($postType) ? CanvasKind::Page : null);
        if ($kind === null) {
            return null;
        }

        if ($kind === CanvasKind::Page && !$this->sectionRepository->isOwnedPage($canvasId)) {
            return null;
        }

        if ($kind === CanvasKind::GlobalPart && !$this->isPublishedPost($post)) {
            return null;
        }

        return $this->mapCanvas($post, $kind);
    }

    // ── Canvas create ────────────────────────────────────────────────────────

    public function createPage(string $title): Canvas
    {
        $resolvedTitle = trim($title);
        $isUntitled = $resolvedTitle === '';
        $initialTitle = $isUntitled
            ? _x('Untitled page', 'Page Builder', 'uncanny-automator')
            : $resolvedTitle;
        $initialSlug = $isUntitled ? '' : sanitize_title($initialTitle);
        $creationMarker = self::CREATION_MARKER_PREFIX . bin2hex(random_bytes(16));

        $post = [
            'post_type'   => 'page',
            'post_title'  => WordPressSlashing::slash($initialTitle),
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'post_content_filtered' => $creationMarker,
        ];

        /*
         * A non-empty post_name makes WordPress reserve the URL during the
         * insert through wp_unique_post_slug(). Without it, draft insertion
         * can leave Page Builder holding a title-derived slug that collides
         * only when a human later publishes the page.
         */
        if ($initialSlug !== '') {
            $post['post_name'] = $initialSlug;
        }

        try {
            $pageId = wp_insert_post($post, true);
        } catch (\Throwable $failure) {
            $recoveredPageId = $this->pageIdForCreationMarker($creationMarker);
            if ($recoveredPageId > 0) {
                $this->rethrowAfterCreatedPostCleanup($recoveredPageId, $failure, 'page');
            }

            throw $failure;
        }

        if ($pageId instanceof \WP_Error || (int) $pageId <= 0) {
            throw new \RuntimeException(
                $pageId instanceof \WP_Error
                    ? $pageId->get_error_message()
                    : 'WordPress did not return a page ID.',
            );
        }

        $pageId = (int) $pageId;

        try {
            if ($isUntitled) {
                $numberedTitle = sprintf(
                    /* translators: %d: The draft page ID used in the fallback untitled title. */
                    _x('Untitled page #%d', 'Page Builder', 'uncanny-automator'),
                    $pageId,
                );
                $result = wp_update_post([
                    'ID' => $pageId,
                    'post_title' => $numberedTitle,
                    'post_name' => sanitize_title($numberedTitle),
                ], true);
                if ($result instanceof \WP_Error || (int) $result <= 0) {
                    throw new \RuntimeException(
                        $result instanceof \WP_Error
                            ? $result->get_error_message()
                            : 'Created page title could not be saved.',
                    );
                }
            }

            $this->sectionRepository->markAsOwned($pageId);
            $this->shellModeService->setForPage($pageId, ShellMode::None);
            $this->pageDetails->initialize($pageId, max(0, (int) get_current_user_id()));

            $canvas = $this->find($pageId);
            if (!$canvas instanceof Canvas) {
                throw new \RuntimeException('Created page canvas could not be loaded.');
            }
            $this->clearCreationMarker($pageId, $creationMarker);

            return $canvas;
        } catch (\Throwable $failure) {
            $this->rethrowAfterCreatedPostCleanup($pageId, $failure, 'page');
        }
    }

    public function createGlobalPart(string $title, GlobalPartType $type): Canvas
    {
        $resolvedTitle = trim($title);
        if ($resolvedTitle === '') {
            $resolvedTitle = _x('Untitled reusable', 'Page Builder', 'uncanny-automator');
        }

        $canvasId = $this->globalPartRepository->createPost($resolvedTitle, $type);

        try {
            $canvas = $this->find($canvasId);
            if (!$canvas instanceof Canvas) {
                throw new \RuntimeException('Created reusable canvas could not be loaded.');
            }

            return $canvas;
        } catch (\Throwable $failure) {
            $this->rethrowAfterCreatedPostCleanup($canvasId, $failure, 'reusable');
        }
    }

    // ── Canvas update ────────────────────────────────────────────────────────

    public function updatePage(
        int $canvasId,
        ?string $title,
        ?ShellMode $shellMode,
    ): Canvas {
        $current = $this->mapCanvas($this->currentPagePost($canvasId), CanvasKind::Page);

        if ($title !== null && $shellMode instanceof ShellMode) {
            throw new \InvalidArgumentException('Update the draft title and layout in separate requests.');
        }

        if ($title === null && $shellMode === null) {
            throw new \InvalidArgumentException('Provide at least one canvas property to update.');
        }

        $committedTitle = $current->title();
        $committedPreviewUrl = $current->previewUrl();
        $warnings = [];
        if ($title !== null) {
            $details = $this->pageDetails->updateTitle(
                $canvasId,
                $title,
                max(0, (int) get_current_user_id()),
            );
            $committedTitle = $details->title();
            $committedPreviewUrl = $details->previewUrl();
        }

        if ($shellMode instanceof ShellMode) {
            $this->shellModeService->setForPage($canvasId, $shellMode);
            try {
                $this->refreshWorkingCanvas($canvasId);
            } catch (\Throwable) {
                // The shell mode is already committed. A derived refresh
                // failure cannot turn that known write into a retry.
                $warnings[] = self::WORKING_CANVAS_REFRESH_WARNING;
            }
        }

        return $this->canvasWithCommittedValues(
            $current,
            $committedTitle,
            $committedPreviewUrl,
            $shellMode ?? $current->shellMode(),
            $warnings,
        );
    }

    public function updateGlobalPart(int $canvasId, ?string $title): Canvas
    {
        if ($title === null) {
            throw new \InvalidArgumentException('Provide at least one canvas property to update.');
        }

        $current = $this->mapCanvas(
            $this->currentGlobalPartPost($canvasId),
            CanvasKind::GlobalPart,
        );

        $resolvedTitle = trim($title);
        if ($resolvedTitle === '') {
            $resolvedTitle = _x('Untitled reusable', 'Page Builder', 'uncanny-automator');
        }

        $this->globalSource->run(fn (): mixed => $this->updateGlobalPartTitle($canvasId, $resolvedTitle));
        clean_post_cache($canvasId);

        return $this->canvasWithCommittedValues(
            $current,
            $resolvedTitle,
            $current->previewUrl(),
            $current->shellMode(),
        );
    }

    // ── Canvas delete ────────────────────────────────────────────────────────

    public function deletePage(int $canvasId, bool $forceDelete): DeleteCanvasResult
    {
        $this->currentPagePost($canvasId);

        $canvas = $this->find($canvasId);
        if (!$canvas instanceof Canvas) {
            throw new CanvasNotFoundException($canvasId);
        }

        $deleted = $forceDelete ? wp_delete_post($canvasId, true) : wp_trash_post($canvasId);
        if ($deleted === false || $deleted instanceof \WP_Error) {
            throw new \RuntimeException('Could not delete the page canvas.');
        }
        $this->assertPostDeletionState($canvasId, $forceDelete, 'page canvas');

        return new DeleteCanvasResult($canvas, $forceDelete);
    }

    public function deleteGlobalPart(int $canvasId, bool $forceDelete): DeleteCanvasResult
    {
        $this->currentGlobalPartPost($canvasId);

        $canvas = $this->find($canvasId);
        if (!$canvas instanceof Canvas) {
            throw new CanvasNotFoundException($canvasId);
        }

        // Keep WordPress and third-party lifecycle hooks outside the guarded
        // source transaction. The cleanup listener owns the generation change.
        $deleted = $forceDelete ? wp_delete_post($canvasId, true) : wp_trash_post($canvasId);
        if ($deleted === false || $deleted instanceof \WP_Error) {
            throw new \RuntimeException('Could not delete the reusable canvas.');
        }
        $this->assertPostDeletionState($canvasId, $forceDelete, 'reusable canvas');

        return new DeleteCanvasResult($canvas, $forceDelete);
    }

    // ── Canvas reusable attach ──────────────────────────────────────────────

    public function attachReusableToPage(int $canvasId, int $reusableId): AttachReusableToCanvasResult
    {
        $this->currentPagePost($canvasId);

        $canvas = $this->find($canvasId);
        if (!$canvas instanceof Canvas || $canvas->kind() !== CanvasKind::Page) {
            throw new CanvasNotFoundException($canvasId);
        }

        $reusable = $this->globalPartRepository->findById($reusableId);
        if ($reusable === null) {
            throw new ReusableNotFoundException($reusableId);
        }

        if (GlobalPartType::fromString((string) ($reusable['type'] ?? '')) !== GlobalPartType::Section) {
            throw new \InvalidArgumentException('Only reusable sections can be attached to page canvases.');
        }

        $source = $this->globalPartService->sourceSectionFromSnapshot($reusableId, $reusable);
        if (!$source instanceof Section) {
            throw new \InvalidArgumentException('Reusable has no source content.');
        }

        try {
            $result = $this->sectionService->create(
                pageId: $canvasId,
                sectionName: sanitize_text_field((string) ($reusable['title'] ?? '')),
                content: $source->content()->toArray(),
                sourceRootId: $source->id(),
            );
        } catch (PageNotFoundException | SectionValidationException $e) {
            throw $e;
        }

        $sectionId = (int) ($result['section_id'] ?? 0);
        if ($sectionId <= 0) {
            throw new \RuntimeException('Attached reusable section could not be loaded.');
        }

        return new AttachReusableToCanvasResult(
            canvas: $canvas,
            reusableId: $reusableId,
            reusableTitle: trim((string) ($reusable['title'] ?? '')) !== '' ? (string) $reusable['title'] : _x('Untitled reusable', 'Page Builder', 'uncanny-automator'),
            reusableType: (string) ($reusable['type'] ?? GlobalPartType::Section->value),
            sectionId: $sectionId,
            position: (int) ($result['position'] ?? 0),
            sectionName: (string) ($result['name'] ?? ''),
            editorUrl: $canvas->editorUrl(),
            previewUrl: $canvas->previewUrl(),
            warnings: array_values(array_map('strval', (array) ($result['warnings'] ?? []))),
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Resolve queryable names from the WordPress registry without exposing the
     * use case's private support-list implementation.
     *
     * @return list<string>
     */
    private function supportedPostTypes(): array
    {
        $function = __NAMESPACE__ . '\\get_post_types';
        if (function_exists('get_post_types')) {
            $registered = \get_post_types([], 'names');
        } elseif (function_exists($function)) {
            $registered = $function([], 'names');
        } else {
            return [];
        }

        if (!is_array($registered)) {
            return [];
        }

        $supported = [];
        foreach ($registered as $postType) {
            if (is_string($postType) && $this->supportsPostType->isSupported($postType)) {
                $supported[] = $postType;
            }
        }

        return array_values(array_unique($supported));
    }

    private function currentPagePost(int $canvasId): object
    {
        $post = get_post($canvasId);
        if (!is_object($post)) {
            throw new CanvasNotFoundException($canvasId);
        }

        if ($this->isTrashedPost($post)) {
            throw new CanvasNotFoundException($canvasId);
        }

        if (
            !$this->supportsPostType->isSupported((string) ($post->post_type ?? ''))
            || !$this->sectionRepository->isOwnedPage($canvasId)
        ) {
            throw new CanvasNotFoundException($canvasId);
        }

        return $post;
    }

    private function currentGlobalPartPost(int $canvasId): object
    {
        $post = get_post($canvasId);
        if (!is_object($post)) {
            throw new CanvasNotFoundException($canvasId);
        }

        if ($this->isTrashedPost($post)) {
            throw new CanvasNotFoundException($canvasId);
        }

        if ((string) ($post->post_type ?? '') !== self::GLOBAL_PART_POST_TYPE || !$this->isPublishedPost($post)) {
            throw new CanvasNotFoundException($canvasId);
        }

        return $post;
    }

    private function isTrashedPost(object $post): bool
    {
        return trim((string) ($post->post_status ?? '')) === 'trash';
    }

    private function isPublishedPost(object $post): bool
    {
        return trim((string) ($post->post_status ?? '')) === 'publish';
    }

    private function pageIdForCreationMarker(string $marker): int
    {
        global $wpdb;
        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $query = $wpdb->prepare(
            "SELECT ID FROM {$postsTable} WHERE post_content_filtered = %s AND post_type = %s ORDER BY ID DESC LIMIT 2",
            $marker,
            'page',
        );
        $ids = $wpdb->get_col($query);
        if (!is_array($ids) || count($ids) !== 1) {
            return 0;
        }

        return max(0, (int) $ids[0]);
    }

    private function clearCreationMarker(int $postId, string $marker): void
    {
        global $wpdb;
        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $updated = $wpdb->update(
            $postsTable,
            ['post_content_filtered' => ''],
            ['ID' => $postId, 'post_content_filtered' => $marker],
            ['%s'],
            ['%d', '%s'],
        );
        if ($updated === false) {
            throw new \RuntimeException('Created page recovery marker could not be cleared.');
        }

        clean_post_cache($postId);
    }

    private function assertPostDeletionState(int $postId, bool $forceDelete, string $label): void
    {
        clean_post_cache($postId);
        $remaining = get_post($postId);

        if ($forceDelete && is_object($remaining)) {
            throw new WordPressWriteVerificationException(
                "WordPress returned from deleting the {$label}, but post {$postId} still exists.",
            );
        }
        if (!$forceDelete && (!is_object($remaining) || !$this->isTrashedPost($remaining))) {
            throw new WordPressWriteVerificationException(
                "WordPress returned from trashing the {$label}, but post {$postId} is not in Trash.",
            );
        }
    }

    /**
     * A WordPress post is only one step of canvas creation. If Page Builder
     * initialization fails after that insert, remove the post before exposing
     * the error so a retry cannot accumulate empty pages or reusables.
     */
    private function rethrowAfterCreatedPostCleanup(int $postId, \Throwable $failure, string $kind): never
    {
        try {
            $canDeletePost = function_exists(__NAMESPACE__ . '\\wp_delete_post') || function_exists('wp_delete_post');
            $delete = static fn (): mixed => $canDeletePost
                ? wp_delete_post($postId, true)
                : false;
            $deleted = $delete();
            if ($deleted !== false && !$deleted instanceof \WP_Error) {
                clean_post_cache($postId);
                if (is_object(get_post($postId))) {
                    throw new \RuntimeException('The incomplete WordPress post still exists after cleanup.');
                }
            }
        } catch (\Throwable $cleanupFailure) {
            throw new CanvasCreationUncertainException(
                $postId,
                $kind,
                $failure,
                $cleanupFailure,
            );
        }

        if ($deleted === false || $deleted instanceof \WP_Error) {
            throw new CanvasCreationUncertainException(
                $postId,
                $kind,
                $failure,
                new \RuntimeException('The incomplete WordPress post could not be removed.'),
            );
        }

        throw $failure;
    }

    private function updateGlobalPartTitle(int $canvasId, string $title): void
    {
        global $wpdb;
        $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
        $updated = $wpdb->update(
            $postsTable,
            ['post_title' => $title],
            ['ID' => $canvasId],
            ['%s'],
            ['%d'],
        );
        if ($updated === false) {
            throw new \RuntimeException('Reusable title update failed.');
        }
    }

    private function refreshWorkingCanvas(int $pageId): void
    {
        if (!$this->workingCanvas instanceof WorkingCanvasRefresherInterface) {
            return;
        }

        $this->workingCanvas->refresh($pageId);
    }

    private function canvasWithCommittedValues(
        Canvas $current,
        string $title,
        string $previewUrl,
        ?ShellMode $shellMode,
        array $warnings = [],
    ): Canvas {
        return new Canvas(
            id: $current->id(),
            kind: $current->kind(),
            title: $title,
            status: $current->status(),
            owned: $current->owned(),
            editorUrl: $current->editorUrl(),
            previewUrl: $previewUrl,
            shellMode: $shellMode,
            globalPartType: $current->globalPartType(),
            warnings: $warnings,
        );
    }

    private function mapCanvas(object $post, CanvasKind $kind): Canvas
    {
        $canvasId = (int) ($post->ID ?? 0);
        $status = (string) ($post->post_status ?? '');
        $title = (string) ($post->post_title ?? '');

        if ($kind === CanvasKind::Page) {
            $shellMode = $this->shellModeService->resolveForPage($canvasId)->mode;
            $draftDetails = $this->pageDetails->find($canvasId);
            $draftTitle = $draftDetails?->title();

            return new Canvas(
                id: $canvasId,
                kind: $kind,
                title: is_string($draftTitle) && $draftTitle !== ''
                    ? $draftTitle
                    : ($title !== '' ? $title : sprintf(
                        /* translators: %d: The page ID used in the fallback untitled title. */
                        _x('Untitled page #%d', 'Page Builder', 'uncanny-automator'),
                        $canvasId,
                    )),
                status: $status !== '' ? $status : 'draft',
                owned: $this->sectionRepository->isOwnedPage($canvasId),
                editorUrl: AdminCanvasEditorWindowedPage::editorUrl($canvasId),
                previewUrl: $draftDetails?->previewUrl() ?? (string) get_permalink($canvasId),
                shellMode: $shellMode,
            );
        }

        $type = GlobalPartType::fromString((string) get_post_meta($canvasId, self::GLOBAL_PART_TYPE_META, true));

        return new Canvas(
            id: $canvasId,
            kind: $kind,
            title: $title !== '' ? $title : _x('Untitled reusable', 'Page Builder', 'uncanny-automator'),
            status: $status !== '' ? $status : 'publish',
            owned: true,
            editorUrl: AdminCanvasEditorWindowedGlobalPartPage::editorUrl($canvasId),
            previewUrl: '',
            globalPartType: $type,
        );
    }
}

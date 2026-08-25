<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Canvas\AttachReusableToCanvasResult;
use UncannyPageBuilder\Application\Canvas\CanvasPortInterface;
use UncannyPageBuilder\Application\Canvas\DeleteCanvasResult;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\Canvas\Canvas;
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

final class WordPressCanvasPort implements CanvasPortInterface
{
    private const GLOBAL_PART_POST_TYPE = 'upb_global_part';
    private const GLOBAL_PART_TYPE_META = '_upb_global_part_type';

    public function __construct(
        private readonly SectionRepositoryInterface $sectionRepository,
        private readonly GlobalPartRepositoryInterface $globalPartRepository,
        private readonly SectionService $sectionService,
        private readonly GlobalPartService $globalPartService,
        private readonly ShellModeService $shellModeService,
        private readonly PageDetailsPortInterface $pageDetails,
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

        $post = [
            'post_type'   => 'page',
            'post_title'  => $initialTitle,
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
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

        $pageId = wp_insert_post($post, true);

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
        $this->currentPagePost($canvasId);

        if ($title !== null && $shellMode instanceof ShellMode) {
            throw new \InvalidArgumentException('Update the draft title and layout in separate requests.');
        }

        if ($title === null && $shellMode === null) {
            throw new \InvalidArgumentException('Provide at least one canvas property to update.');
        }

        if ($title !== null) {
            $current = $this->pageDetails->find($canvasId);
            if ($current === null) {
                throw new \RuntimeException('Draft page details are unavailable.');
            }

            $this->pageDetails->update(
                $canvasId,
                $title,
                $current->slug(),
                max(0, (int) get_current_user_id()),
            );
        }

        if ($shellMode instanceof ShellMode) {
            $this->shellModeService->setForPage($canvasId, $shellMode);
            $this->refreshWorkingCanvas($canvasId);
        }

        $canvas = $this->find($canvasId);
        if (!$canvas instanceof Canvas) {
            throw new CanvasNotFoundException($canvasId);
        }

        return $canvas;
    }

    public function updateGlobalPart(int $canvasId, ?string $title): Canvas
    {
        if ($title === null) {
            throw new \InvalidArgumentException('Provide at least one canvas property to update.');
        }

        $this->currentGlobalPartPost($canvasId);

        $resolvedTitle = trim($title);
        if ($resolvedTitle === '') {
            $resolvedTitle = _x('Untitled reusable', 'Page Builder', 'uncanny-automator');
        }

        $this->globalSource->run(fn (): mixed => $this->updateGlobalPartTitle($canvasId, $resolvedTitle));
        clean_post_cache($canvasId);

        $canvas = $this->find($canvasId);
        if (!$canvas instanceof Canvas) {
            throw new CanvasNotFoundException($canvasId);
        }

        return $canvas;
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

        $resolved = $this->globalPartService->resolveSourceContent($reusableId);
        if ($resolved === null) {
            throw new \InvalidArgumentException('Reusable has no source content.');
        }

        try {
            $result = $this->sectionService->create(
                pageId: $canvasId,
                sectionName: sanitize_text_field((string) ($resolved['title'] ?? '')),
                content: $resolved['content'],
                sourceRootId: $resolved['section_id'],
            );
        } catch (PageNotFoundException | SectionValidationException $e) {
            throw $e;
        }

        $inserted = $this->lastPageSection($canvasId);
        if (!$inserted instanceof Section) {
            throw new \RuntimeException('Attached reusable section could not be loaded.');
        }

        return new AttachReusableToCanvasResult(
            canvas: $canvas,
            reusableId: $reusableId,
            reusableTitle: trim((string) ($reusable['title'] ?? '')) !== '' ? (string) $reusable['title'] : _x('Untitled reusable', 'Page Builder', 'uncanny-automator'),
            reusableType: (string) ($reusable['type'] ?? GlobalPartType::Section->value),
            sectionId: $inserted->id() ?? 0,
            position: $inserted->position(),
            sectionName: $inserted->name(),
            previewUrl: (string) ($result['preview'] ?? ''),
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
        } catch (\Throwable $cleanupFailure) {
            throw new \RuntimeException(
                "Created {$kind} initialization failed, and its WordPress post could not be removed: {$cleanupFailure->getMessage()}",
                0,
                $failure,
            );
        }

        if ($deleted === false || $deleted instanceof \WP_Error) {
            throw new \RuntimeException(
                "Created {$kind} initialization failed, and its WordPress post could not be removed.",
                0,
                $failure,
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

    private function lastPageSection(int $pageId): ?Section
    {
        $sections = $this->sectionRepository->findByPageId($pageId)->all();
        if ($sections === []) {
            return null;
        }

        $last = end($sections);

        return $last instanceof Section ? $last : null;
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

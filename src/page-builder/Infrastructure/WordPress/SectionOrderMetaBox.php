<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Controls\ControlContext;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\Handlers\ManualChangeSetHandler;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Admin metabox for drag-and-drop section reordering on Engine-owned pages.
 *
 * Reordering is browser-local until the native WordPress Update action submits
 * the form. The save then joins the same guarded Manual draft lane as canvas
 * section changes.
 */
final class SectionOrderMetaBox
{
    private const META_BOX_ID = 'upb_section_order';
    private const NONCE_KEY = 'upb_section_order_nonce';
    private const NONCE_ACTION = 'upb_save_section_order';
    private const ORDER_FIELD = 'upb_section_order_ids';
    private const CHANGED_FIELD = 'upb_section_order_changed';
    private const GENERATION_FIELD = 'upb_section_order_generation';
    private const NOTICE_TRANSIENT_PREFIX = 'upb_section_order_notice_';

    public function __construct(
        private readonly SectionRepositoryInterface $repository,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly ?ManualChangeSetHandler $manualChanges = null,
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
        private readonly ?NativePageSave $nativePageSave = null,
    ) {}

    public function register(\WP_Post $post): void
    {
        if (
            !$this->repository->isOwnedPage($post->ID)
            || !$this->allowedCapabilities->currentUserHasAllowedCapability()
        ) {
            return;
        }

        add_meta_box(
            self::META_BOX_ID,
            _x('Section order', 'Page Builder', 'uncanny-automator'),
            [$this, 'render'],
            $post->post_type,
            'side',
            'default',
        );
    }

    public function render($post = null): void
    {
        if (!$post instanceof \WP_Post) {
            return;
        }

        try {
            $pageId = $post->ID;
            $sections = $this->repository->findByPageId($pageId);
            $workingGeneration = $this->sourceGenerations?->pageGeneration($pageId)
                ?? $sections->generation();

            include __DIR__ . '/../../Presentation/Pages/section-order.php';

            if ($sections->count() > 1) {
                include __DIR__ . '/../../Presentation/Pages/section-order-script.php';
            }
        } catch (\Throwable $failure) {
            // Render nothing further; a metabox failure must not fail the
            // complete WordPress edit screen.
            error_log('[Uncanny Page Builder] Section order metabox render failed (' . $failure::class . ')');
        }
    }

    public function save(int $postId, \WP_Post $post): void
    {
        if (
            !$this->repository->isOwnedPage($postId)
            || !$this->allowedCapabilities->currentUserHasAllowedCapability()
            || !$this->manualChanges instanceof ManualChangeSetHandler
            || !$this->sourceGenerations instanceof SourceGenerationStoreInterface
        ) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($postId)) {
            return;
        }
        if (
            ($_POST[self::CHANGED_FIELD] ?? '') !== '1'
            || !isset($_POST[self::NONCE_KEY])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST[self::NONCE_KEY])),
                self::NONCE_ACTION . '_' . $postId,
            )
        ) {
            return;
        }

        $expectedGeneration = filter_var(
            wp_unslash($_POST[self::GENERATION_FIELD] ?? null),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]],
        );
        $orderedIds = $this->orderedIds(wp_unslash($_POST[self::ORDER_FIELD] ?? ''));
        if ($expectedGeneration === false || $orderedIds === []) {
            $message = _x('Section order was not saved because the submitted draft identity was invalid.', 'Page Builder', 'uncanny-automator');
            if ($this->nativePageSave instanceof NativePageSave) {
                $this->nativePageSave->reject($postId, $message);
            } else {
                $this->recordSaveNotice($postId, $message);
            }
            return;
        }

        $save = function () use ($postId, $expectedGeneration, $orderedIds): void {
            $sections = $this->repository->findByPageId($postId);
            $sections->reorderByIds($orderedIds);
            ($this->manualChanges)(new ControlInvokeRequest(
                controlId: 'page.manual_changes.commit',
                context: ControlContext::forPage(
                    $postId,
                    max(0, (int) get_current_user_id()),
                    [
                        'can_edit' => true,
                        'can_manage' => true,
                        'can_upload' => false,
                        'can_publish' => false,
                    ],
                ),
                value: [
                    'base' => [
                        'loaded_source' => 'working',
                        'working_generation' => $expectedGeneration,
                        'snapshot_id' => null,
                    ],
                    'design_changes' => [],
                    'content_changes' => [],
                    'sections' => $sections->toArray(),
                    'draft_resume_policy' => 'parked',
                ],
            ));
        };

        if ($this->nativePageSave instanceof NativePageSave) {
            $formGeneration = $this->nativePageSave->postedGeneration();
            if ($formGeneration === null || $formGeneration !== $expectedGeneration) {
                $this->nativePageSave->reject(
                    $postId,
                    _x('Section order was not saved because the submitted draft identity was invalid.', 'Page Builder', 'uncanny-automator'),
                );
                return;
            }
            $this->nativePageSave->stage(
                $postId,
                $expectedGeneration,
                function () use ($postId, $save): void {
                    $save();
                    PostEditNotice::forget(self::NOTICE_TRANSIENT_PREFIX, $postId);
                },
            );
            return;
        }

        try {
            $save();
            PostEditNotice::forget(self::NOTICE_TRANSIENT_PREFIX, $postId);
        } catch (\Throwable) {
            $this->recordSaveNotice(
                $postId,
                _x(
                    'Section order was not saved because this page changed in another request. Reload the page and reorder again.',
                    'Page Builder',
                    'uncanny-automator',
                ),
            );
        }
    }

    public function renderSaveNotice(): void
    {
        $postId = absint(sanitize_text_field(wp_unslash($_GET['post'] ?? '0')));
        if ($postId <= 0) {
            return;
        }

        $message = PostEditNotice::read(self::NOTICE_TRANSIENT_PREFIX, $postId);
        if (!is_string($message) || $message === '') {
            return;
        }

        PostEditNotice::forget(self::NOTICE_TRANSIENT_PREFIX, $postId);

        PostEditNotice::render($message);
    }

    /** @return int[] */
    private function orderedIds(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $value) {
            $value = trim($value);
            if (!preg_match('/^[1-9]\d*$/', $value)) {
                return [];
            }
            $id = (int) $value;
            if (isset($ids[$id])) {
                return [];
            }
            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    private function recordSaveNotice(int $postId, string $message): void
    {
        PostEditNotice::remember(self::NOTICE_TRANSIENT_PREFIX, $postId, $message);
    }

    public static function nonceKey(): string
    {
        return self::NONCE_KEY;
    }

    public static function nonceActionForPage(int $postId): string
    {
        return self::NONCE_ACTION . '_' . $postId;
    }

    public static function orderField(): string
    {
        return self::ORDER_FIELD;
    }

    public static function changedField(): string
    {
        return self::CHANGED_FIELD;
    }

    public static function generationField(): string
    {
        return self::GENERATION_FIELD;
    }
}

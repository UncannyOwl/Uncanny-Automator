<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;

final class GlobalPartMetaBox
{
    private const META_KEY     = '_upb_global_part_type';
    private const NONCE_KEY    = '_uncanny_gpt_nonce';
    private const NONCE_ACTION = 'upb_global_part_type_nonce';
    private const NOTICE_TRANSIENT_PREFIX = 'upb_global_part_save_notice_';

    private bool $saving = false;

    public function __construct(
        private readonly ?GlobalPartRepositoryInterface $globalPartRepo,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly GlobalSourceMutation $globalSource,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    public function register(): void
    {
        add_meta_box(
            'upb_global_part_type',
            _x('Part type', 'Page Builder', 'uncanny-automator'),
            [$this, 'render'],
            'upb_global_part',
            'side',
            'high'
        );

        if ($this->globalPartRepo !== null) {
            add_meta_box(
                'upb_global_part_preview',
                _x('Content preview', 'Page Builder', 'uncanny-automator'),
                [$this, 'renderPreview'],
                'upb_global_part',
                'normal',
                'default'
            );
        }
    }

    public function render($post = null): void
    {
        if (!$post instanceof \WP_Post) {
            return;
        }

        try {
            $current = GlobalPartType::fromString(
                (string) get_post_meta($post->ID, self::META_KEY, true)
            );
            $nonceAction = self::NONCE_ACTION;
            $nonceKey = self::NONCE_KEY;

            include __DIR__ . '/../../Presentation/GlobalParts/type-selector.php';
        } catch (\Throwable $failure) {
            // Render nothing further; a metabox failure must not fail the
            // complete WordPress edit screen.
            error_log('[Uncanny Page Builder] Reusable part type metabox render failed (' . $failure::class . ')');
        }
    }

    public function renderPreview($post = null): void
    {
        if (!$post instanceof \WP_Post) {
            return;
        }

        if ($this->globalPartRepo === null) {
            return;
        }

        try {
            try {
                $part = $this->globalPartRepo->findById($post->ID);
            } catch (\Throwable $failure) {
                // Meta-box rendering is part of the shared WordPress edit screen.
                // A repository failure must not make the complete screen fatal.
                error_log(sprintf(
                    '[Uncanny Page Builder] Reusable part preview failed for post %d (%s).',
                    $post->ID,
                    $failure::class,
                ));
                echo '<p class="notice notice-error inline">';
                echo esc_html_x(
                    'Page Builder could not load the reusable part preview.',
                    'Page Builder',
                    'uncanny-automator',
                );
                echo '</p>';
                return;
            }

            if ($part === null || empty($part['sections'])) {
                echo '<p style="color: #787c82; font-style: italic;">';
                echo esc_html_x('No sections yet.', 'Page Builder', 'uncanny-automator');
                echo '</p>';
                return;
            }

            // Preview contract: use the real hidden canvas route in preview mode so
            // the reusable part renders through the same document and asset path as
            // production canvas rendering instead of a hand-built srcdoc clone.
            $body = '';

            foreach ($part['sections'] as $section) {
                $html = $section['content']['html'] ?? '';
                if ($html === '') {
                    continue;
                }
                $body .= $html;
            }

            if ($body === '') {
                $body = '';
                $previewUrl = '';
                include __DIR__ . '/../../Presentation/GlobalParts/preview.php';
                return;
            }

            $previewUrl = add_query_arg('upb_preview', '1', AdminCanvasPage::editorUrl($post->ID));

            include __DIR__ . '/../../Presentation/GlobalParts/preview.php';
        } catch (\Throwable $failure) {
            // Render nothing further; a metabox failure must not fail the
            // complete WordPress edit screen.
            error_log('[Uncanny Page Builder] Reusable part preview render failed (' . $failure::class . ')');
        }
    }

    public function save($postId = null, $post = null): void
    {
        $postId = WordPressPostId::fromMixed($postId);
        if ($postId === null || !$post instanceof \WP_Post) {
            return;
        }

        if ($this->saving) {
            return;
        }

        if (
            !isset($_POST[self::NONCE_KEY])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST[self::NONCE_KEY])),
                self::NONCE_ACTION
            )
        ) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            return;
        }

        $type = GlobalPartType::fromString(
            sanitize_text_field(wp_unslash($_POST['upb_global_part_type'] ?? 'section'))
        );
        $postedTitle = array_key_exists('post_title', $_POST)
            ? trim(sanitize_text_field(wp_unslash($_POST['post_title'])))
            : null;

        $this->saving = true;
        try {
            $this->globalSource->run(function () use ($postId, $type, $postedTitle): void {
                if ($postedTitle !== null) {
                    $title = $postedTitle !== ''
                        ? $postedTitle
                        : sprintf(
                            /* translators: %d: Reusable part ID. */
                            _x('Untitled reusable %d', 'Page Builder', 'uncanny-automator'),
                            $postId,
                        );
                    global $wpdb;
                    $postsTable = isset($wpdb->posts) ? (string) $wpdb->posts : (string) $wpdb->prefix . 'posts';
                    $updated = $wpdb->update(
                        $postsTable,
                        ['post_title' => $title],
                        ['ID' => $postId],
                        ['%s'],
                        ['%d'],
                    );
                    if ($updated === false) {
                        throw new \RuntimeException('Reusable title update failed.');
                    }
                }

                $this->updateMetaExact($postId, $type->value);
            });
        } catch (\Throwable $failure) {
            // save_post is a shared WordPress boundary. A failed Page Builder
            // transaction must not terminate the remaining save callbacks or
            // make WordPress return a fatal-error response.
            $this->reportSaveFailure($postId, $failure);
            $this->recordSaveNotice($postId);
            return;
        } finally {
            $this->saving = false;
        }

        PostEditNotice::forget(self::NOTICE_TRANSIENT_PREFIX, $postId);
        clean_post_cache($postId);
    }

    private function reportSaveFailure(int $postId, \Throwable $failure): void
    {
        try {
            if ($this->failureReporter instanceof FailureReporterInterface) {
                $this->failureReporter->report('reusable part', $postId, 'global_part.settings.save', $failure);
                return;
            }
        } catch (\Throwable) {
            // A reporting failure cannot escape this WordPress hook boundary.
        }

        error_log(sprintf(
            '[Uncanny Page Builder] Reusable part save failed for post %d (%s).',
            $postId,
            $failure::class,
        ));
    }

    public function renderSaveNotice(): void
    {
        $postId = WordPressPostId::fromMixed($_GET['post'] ?? null);
        if ($postId === null) {
            return;
        }

        if (PostEditNotice::read(self::NOTICE_TRANSIENT_PREFIX, $postId) !== 'save_failed') {
            return;
        }

        PostEditNotice::forget(self::NOTICE_TRANSIENT_PREFIX, $postId);

        PostEditNotice::render(
            _x(
                'Page Builder could not save the reusable part settings. Review the current title and part type before you try again.',
                'Page Builder',
                'uncanny-automator',
            ),
        );
    }

    /**
     * WordPress writes the title before save_post. Preserve the stored value
     * until save() can commit title and type under one global-source guard.
     * Page Builder ports already run inside that guard and pass through.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     * @return array<string, mixed>
     */
    public function protectPostData($data = null, $postarr = null): array
    {
        $data = is_array($data) ? $data : [];
        $postarr = is_array($postarr) ? $postarr : [];

        if (($data['post_type'] ?? '') !== 'upb_global_part' || $this->globalSource->isRunning()) {
            return $data;
        }

        $postId = (int) ($postarr['ID'] ?? 0);
        if ($postId <= 0) {
            return $data;
        }

        $stored = get_post($postId);
        if ($stored instanceof \WP_Post) {
            $data['post_title'] = (string) $stored->post_title;
        }

        return $data;
    }

    private function updateMetaExact(int $postId, string $value): void
    {
        $updated = update_post_meta($postId, self::META_KEY, $value);
        if ($updated === false && !$this->metaMatchesDatabase($postId, $value)) {
            throw new \RuntimeException('Reusable type update failed.');
        }
    }

    private function metaMatchesDatabase(int $postId, string $value): bool
    {
        global $wpdb;

        $postmetaTable = isset($wpdb->postmeta) ? (string) $wpdb->postmeta : (string) $wpdb->prefix . 'postmeta';
        $stored = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$postmetaTable}
             WHERE post_id = %d AND meta_key = %s
             ORDER BY meta_id DESC LIMIT 1",
            $postId,
            self::META_KEY,
        ));

        return is_string($stored) && hash_equals($value, $stored);
    }

    private function recordSaveNotice(int $postId): void
    {
        try {
            PostEditNotice::remember(self::NOTICE_TRANSIENT_PREFIX, $postId, 'save_failed');
        } catch (\Throwable $failure) {
            // A diagnostic failure must not replace the original save failure
            // or terminate the shared save_post request.
            error_log(sprintf(
                '[Uncanny Page Builder] Reusable part save notice failed for post %d (%s).',
                $postId,
                $failure::class,
            ));
        }
    }
}

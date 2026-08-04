<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\PageGlobalPartSelectionService;
use UncannyPageBuilder\Domain\Exception\ParkedDraftNotLoadedException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelection;
use UncannyPageBuilder\Domain\Publishing\DraftResumePolicy;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

final class GlobalPartSelectorMetaBox
{
    private const META_BOX_ID = 'upb_global_part_selector';
    private const NONCE_KEY = 'upb_gp_selector_nonce';
    private const NONCE_ACTION = 'upb_save_page_global_parts';
    private const NOTICE_TRANSIENT_PREFIX = 'upb_page_global_parts_notice_';
    public function __construct(
        private readonly SectionRepositoryInterface $sectionRepo,
        private readonly GlobalPartDefaultsService $gpDefaults,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly PageGlobalPartSelectionService $pageSelections,
        private readonly ?PageSourceMutation $pageSource = null,
        private readonly ?PageStateRepositoryInterface $pageStates = null,
        private readonly ?SelectEditorPageSource $pageSources = null,
        private readonly ?NativePageSave $nativePageSave = null,
    ) {}

    public function register(\WP_Post $post): void
    {
        if (
            !$this->sectionRepo->isOwnedPage($post->ID)
            || !$this->allowedCapabilities->currentUserHasAllowedCapability()
        ) {
            return;
        }

        add_action('admin_notices', [$this, 'renderSaveNotice']);

        add_meta_box(
            self::META_BOX_ID,
            _x('Header and footer', 'Page Builder', 'uncanny-automator'),
            [$this, 'render'],
            $post->post_type,
            'side',
            'default',
        );
    }

    public function render(\WP_Post $post): void
    {
        $pageId = $post->ID;
        $headers = $this->gpDefaults->listByType(GlobalPartType::Header);
        $footers = $this->gpDefaults->listByType(GlobalPartType::Footer);

        $selection = $this->pageSelections->selectionForPage($pageId);
        $currentHeader = $selection->headerOverrideId() ?? 0;
        $currentFooter = $selection->footerOverrideId() ?? 0;

        $defaultHeaderId = $this->gpDefaults->getDefaultId(GlobalPartType::Header);
        $defaultFooterId = $this->gpDefaults->getDefaultId(GlobalPartType::Footer);

        wp_nonce_field(self::nonceActionForPage($pageId), self::nonceKey());
        ?>
        <p>
            <label for="upb_page_header"><strong><?php echo esc_html_x('Header', 'Page Builder', 'uncanny-automator'); ?></strong></label><br>
            <select id="upb_page_header" name="upb_page_header_id" style="width:100%;margin-top:4px">
                <option value="0"><?php
                    echo esc_html(sprintf(
                        /* translators: %s: the default header label in parentheses when set, or an empty string when unset. */
                        _x('Site default%s', 'Page Builder', 'uncanny-automator'),
                        $defaultHeaderId ? ' (' . get_the_title($defaultHeaderId) . ')' : ''
                    ));
                                    ?></option>
                <option value="-1" <?php selected($currentHeader, -1); ?>><?php echo esc_html_x('None (no header)', 'Page Builder', 'uncanny-automator'); ?></option>
                <?php foreach ($headers as $h) : ?>
                    <option value="<?php echo esc_attr($h['post_id']); ?>" <?php selected($currentHeader, $h['post_id']); ?>>
                        <?php echo esc_html($h['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="upb_page_footer"><strong><?php echo esc_html_x('Footer', 'Page Builder', 'uncanny-automator'); ?></strong></label><br>
            <select id="upb_page_footer" name="upb_page_footer_id" style="width:100%;margin-top:4px">
                <option value="0"><?php
                    echo esc_html(sprintf(
                        /* translators: %s: the default footer label in parentheses when set, or an empty string when unset. */
                        _x('Site default%s', 'Page Builder', 'uncanny-automator'),
                        $defaultFooterId ? ' (' . get_the_title($defaultFooterId) . ')' : ''
                    ));
                                    ?></option>
                <option value="-1" <?php selected($currentFooter, -1); ?>><?php echo esc_html_x('None (no footer)', 'Page Builder', 'uncanny-automator'); ?></option>
                <?php foreach ($footers as $f) : ?>
                    <option value="<?php echo esc_attr($f['post_id']); ?>" <?php selected($currentFooter, $f['post_id']); ?>>
                        <?php echo esc_html($f['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description" style="margin-top:8px"><?php echo esc_html_x('Choose which header and footer to use on this page, or keep the site default.', 'Page Builder', 'uncanny-automator'); ?></p>
        <?php
    }

    public function save(int $postId, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST[self::nonceKey()])) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::nonceKey()])), self::nonceActionForPage($postId))) {
            return;
        }
        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            return;
        }

        $header = $this->parseSubmittedSelection($_POST['upb_page_header_id'] ?? null, GlobalPartType::Header);
        $footer = $this->parseSubmittedSelection($_POST['upb_page_footer_id'] ?? null, GlobalPartType::Footer);
        if (!$header['valid'] || !$footer['valid']) {
            if ($this->nativePageSave instanceof NativePageSave) {
                $this->nativePageSave->reject(
                    $postId,
                    _x('Page Builder settings were not saved because a header or footer selection was invalid.', 'Page Builder', 'uncanny-automator'),
                );
            } else {
                $this->recordInvalidSelectionNotice($postId);
            }
            return;
        }

        $selection = new PageGlobalPartSelection($header['value'], $footer['value']);
        if ($this->pageSelections->selectionForPage($postId)->equals($selection)) {
            delete_transient($this->noticeTransientKey($postId));
            return;
        }

        try {
            $save = fn() => $this->pageSelections->saveForPage(
                $postId,
                $selection,
            );
            if ($this->nativePageSave instanceof NativePageSave) {
                $expectedGeneration = $this->nativePageSave->postedGeneration();
                if ($expectedGeneration === null) {
                    $this->nativePageSave->reject(
                        $postId,
                        _x('Header and footer selections were not saved because the page draft identity is missing.', 'Page Builder', 'uncanny-automator'),
                    );
                    return;
                }
                $this->nativePageSave->stage(
                    $postId,
                    $expectedGeneration,
                    function () use ($postId, $save): void {
                        $save();
                        delete_transient($this->noticeTransientKey($postId));
                    },
                );
                return;
            }

            delete_transient($this->noticeTransientKey($postId));
            if (
                $this->pageSource instanceof PageSourceMutation
                && $this->pageStates instanceof PageStateRepositoryInterface
            ) {
                $this->pageSource->runAsHumanSave(
                    $postId,
                    $save,
                    function () use ($postId): void {
                        $this->pageStates?->saveDraftResumePolicy(
                            $postId,
                            DraftResumePolicy::Parked,
                        );
                    },
                    fn() => $this->assertVisibleSourceCanBeSaved($postId),
                );
            } else {
                $save();
            }
        } catch (ParkedDraftNotLoadedException) {
            $this->recordSaveNotice(
                $postId,
                _x(
                    'Header and footer selections were not saved. Load the newer saved draft in the Page Builder editor first.',
                    'Page Builder',
                    'uncanny-automator',
                ),
            );
        } catch (StaleSourceGenerationException) {
            $this->recordSaveNotice(
                $postId,
                _x('Header and footer selections were not saved because this page changed in another request. Reload the page and choose again.', 'Page Builder', 'uncanny-automator'),
            );
        }
    }

    public function renderSaveNotice(): void
    {
        $postId = absint(sanitize_text_field(wp_unslash($_GET['post'] ?? '0')));
        if ($postId <= 0) {
            return;
        }

        $notice = get_transient($this->noticeTransientKey($postId));
        if (!is_array($notice)) {
            return;
        }

        delete_transient($this->noticeTransientKey($postId));

        $message = is_string($notice['message'] ?? null) ? $notice['message'] : '';
        if ($message === '') {
            return;
        }

        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    public static function nonceKey(): string
    {
        return self::NONCE_KEY;
    }

    public static function nonceActionForPage(int $pageId): string
    {
        return self::NONCE_ACTION . '_' . $pageId;
    }

    /**
     * Parse a submitted metabox selection into one explicit write operation.
     *
     * Site default, none, and assignable part IDs are the only accepted values.
     * Missing or malformed fields reject the whole submission so page header and
     * footer overrides cannot drift apart on one save.
     *
     * @return array{valid: bool, value: ?int}
     */
    private function parseSubmittedSelection(mixed $value, GlobalPartType $type): array
    {
        if ($value === null || !is_scalar($value) || !preg_match('/^-?\d+$/', (string) $value)) {
            return ['valid' => false, 'value' => null];
        }

        $selection = (int) $value;

        if ($selection === 0) {
            return ['valid' => true, 'value' => null];
        }

        if ($selection === -1) {
            return ['valid' => true, 'value' => -1];
        }

        if ($selection > 0 && $this->gpDefaults->isAssignablePartId($type, $selection)) {
            return ['valid' => true, 'value' => $selection];
        }

        return ['valid' => false, 'value' => null];
    }

    private function recordInvalidSelectionNotice(int $postId): void
    {
        $this->recordSaveNotice(
            $postId,
            _x(
                'Header and footer selections were not saved because one submitted selection is invalid or no longer available. Reload the page and choose again.',
                'Page Builder',
                'uncanny-automator',
            ),
        );
    }

    private function recordSaveNotice(int $postId, string $message): void
    {
        set_transient($this->noticeTransientKey($postId), ['message' => $message], 60);
    }

    private function noticeTransientKey(int $postId): string
    {
        return self::NOTICE_TRANSIENT_PREFIX . $postId . '_' . (int) get_current_user_id();
    }

    private function assertVisibleSourceCanBeSaved(int $postId): void
    {
        if (
            $this->pageSources instanceof SelectEditorPageSource
            && $this->pageSources->forPage($postId)->loadedSource() !== 'working'
        ) {
            throw new ParkedDraftNotLoadedException();
        }
    }
}

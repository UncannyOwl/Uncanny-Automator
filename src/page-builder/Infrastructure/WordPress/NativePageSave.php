<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Domain\Exception\ParkedDraftNotLoadedException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Publishing\DraftResumePolicy;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;

/**
 * Commits one native WordPress Update form as one guarded page-source change.
 *
 * Several Page Builder metaboxes share the same form. They stage their typed
 * writes here during save_post; the final editor callback commits the whole
 * ordered batch against the generation rendered with that form.
 */
final class NativePageSave
{
    private const NOTICE_TRANSIENT_PREFIX = 'upb_native_page_save_notice_';

    /** @var array<int, list<\Closure>> */
    private array $changesByPage = [];

    /** @var array<int, int> */
    private array $generationByPage = [];

    /** @var array<int, string> */
    private array $rejectionByPage = [];

    public function __construct(
        private readonly PageSourceMutation $pageSource,
        private readonly PageStateRepositoryInterface $pageStates,
        private readonly SelectEditorPageSource $pageSources,
    ) {}

    /** @param callable(): mixed $change */
    public function stage(int $pageId, int $expectedGeneration, callable $change): void
    {
        if ($pageId <= 0 || $expectedGeneration < 0) {
            $this->reject($pageId, _x('The page draft identity is invalid. Reload the page and try again.', 'Page Builder', 'uncanny-automator'));
            return;
        }

        $existingGeneration = $this->generationByPage[$pageId] ?? null;
        if ($existingGeneration !== null && $existingGeneration !== $expectedGeneration) {
            $this->reject($pageId, _x('The Page Builder fields came from different drafts. Reload the page and try again.', 'Page Builder', 'uncanny-automator'));
            return;
        }

        $this->generationByPage[$pageId] = $expectedGeneration;
        $this->changesByPage[$pageId][] = \Closure::fromCallable($change);
    }

    public function reject(int $pageId, string $message): void
    {
        if ($pageId > 0 && !isset($this->rejectionByPage[$pageId])) {
            $this->rejectionByPage[$pageId] = $message;
        }
    }

    public function commit(int $pageId): bool
    {
        $changes = $this->changesByPage[$pageId] ?? [];
        $expectedGeneration = $this->generationByPage[$pageId] ?? null;
        $rejection = $this->rejectionByPage[$pageId] ?? null;

        try {
            if (is_string($rejection) && $rejection !== '') {
                $this->recordNotice($pageId, $rejection);
                return false;
            }
            if ($changes === []) {
                return true;
            }
            if (!is_int($expectedGeneration) || $expectedGeneration < 0) {
                $this->recordNotice(
                    $pageId,
                    _x('The page draft identity is missing. Reload the page and try again.', 'Page Builder', 'uncanny-automator'),
                );
                return false;
            }

            $this->pageSource->runAsHumanSave(
                $pageId,
                fn(): mixed => $this->pageSource->runExpected(
                    $pageId,
                    $expectedGeneration,
                    static function () use ($changes): void {
                        foreach ($changes as $change) {
                            $change();
                        }
                    },
                ),
                function () use ($pageId): void {
                    $this->pageStates->saveDraftResumePolicy(
                        $pageId,
                        DraftResumePolicy::Parked,
                    );
                },
                function () use ($pageId): void {
                    if ($this->pageSources->forPage($pageId)->loadedSource() !== 'working') {
                        throw new ParkedDraftNotLoadedException();
                    }
                },
            );
            delete_transient($this->noticeTransientKey($pageId));

            return true;
        } catch (ParkedDraftNotLoadedException) {
            $this->recordNotice(
                $pageId,
                _x('Page Builder settings were not saved. Open the Page Builder editor and load the newer saved draft first.', 'Page Builder', 'uncanny-automator'),
            );
        } catch (StaleSourceGenerationException) {
            $this->recordNotice(
                $pageId,
                _x('Page Builder settings were not saved because this page changed in another request. Reload the page and try again.', 'Page Builder', 'uncanny-automator'),
            );
        } catch (\Throwable) {
            $this->recordNotice(
                $pageId,
                _x('Page Builder settings could not be saved together. No partial Page Builder change was kept.', 'Page Builder', 'uncanny-automator'),
            );
        } finally {
            unset(
                $this->changesByPage[$pageId],
                $this->generationByPage[$pageId],
                $this->rejectionByPage[$pageId],
            );
        }

        return false;
    }

    public function postedGeneration(): ?int
    {
        $generation = filter_var(
            wp_unslash($_POST[PageEditorMetaBoxes::SOURCE_GENERATION_FIELD] ?? null),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]],
        );

        return $generation === false ? null : $generation;
    }

    public function renderNotice(): void
    {
        $postId = absint(sanitize_text_field(wp_unslash($_GET['post'] ?? '0')));
        if ($postId <= 0) {
            return;
        }

        $message = get_transient($this->noticeTransientKey($postId));
        if (!is_string($message) || $message === '') {
            return;
        }

        delete_transient($this->noticeTransientKey($postId));
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private function recordNotice(int $pageId, string $message): void
    {
        set_transient($this->noticeTransientKey($pageId), $message, 60);
    }

    private function noticeTransientKey(int $pageId): string
    {
        return self::NOTICE_TRANSIENT_PREFIX . $pageId . '_' . (int) get_current_user_id();
    }
}

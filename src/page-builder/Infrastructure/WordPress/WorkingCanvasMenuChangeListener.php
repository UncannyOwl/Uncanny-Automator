<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;
use UncannyPageBuilder\Infrastructure\Persistence\SourceTransactionsUnavailableException;

/**
 * Advances shared source and refreshes working canvases after menu changes.
 *
 * A published artifact keeps the menu snapshot it captured. Advancing the
 * global generation makes the working draft visibly dirty and prevents an
 * older publication candidate from committing after WordPress changes a menu.
 */
final class WorkingCanvasMenuChangeListener
{
    private const RETRY_OPTION = 'uncanny_page_builder_pending_menu_refresh';
    private const STEP_ADVANCE = 'advance_global_source';
    private const STEP_REFRESH = 'queue_working_canvas_refresh';
    public function __construct(
        private readonly GlobalSourceMutation $globalSource,
        private readonly WorkingCanvasRefreshScheduler $refreshScheduler,
    ) {}

    public function register(): void
    {
        add_action('wp_update_nav_menu', [$this, 'menuChanged'], 10, 1);
        add_action('wp_delete_nav_menu', [$this, 'menuChanged'], 10, 1);
        add_action('admin_init', [$this, 'retryPendingChange']);
    }

    public function menuChanged($menuId = null): void
    {
        if (WordPressPostId::fromMixed($menuId) === null) {
            return;
        }

        try {
            $this->rememberPendingStep(self::STEP_ADVANCE);
        } catch (\Throwable $failure) {
            // The observer can still finish during this request when WordPress
            // cannot store the retry marker.
            $this->reportFailure('record_retry_step', $failure);
        }

        $this->advanceGlobalSourceAndQueue();
    }

    public function retryPendingChange(): void
    {
        try {
            $step = get_option(self::RETRY_OPTION, '');
        } catch (\Throwable $failure) {
            $this->reportFailure('read_retry_step', $failure);
            return;
        }

        if ($step === self::STEP_ADVANCE) {
            $this->advanceGlobalSourceAndQueue();
            return;
        }

        if ($step === self::STEP_REFRESH) {
            $this->queueWorkingCanvasRefreshes();
        }
    }

    private function advanceGlobalSourceAndQueue(): void
    {
        try {
            $this->globalSource->run(static fn(): mixed => null);
        } catch (SourceTransactionsUnavailableException $exception) {
            unset($exception);

            // The next request reports this database failure through the host
            // Site Health check. This hook must not make the menu save fatal.
            return;
        } catch (\Throwable $failure) {
            // WordPress runs this callback after it saves the menu. Other
            // plugins can run callbacks in the same request. Page Builder
            // cannot safely reverse the save, so this observer must not
            // terminate the shared request.
            $this->reportFailure(self::STEP_ADVANCE, $failure);
            return;
        }

        try {
            $this->rememberPendingStep(self::STEP_REFRESH);
        } catch (\Throwable $failure) {
            // The generation is committed. If this marker stays at the prior
            // step, a later retry can advance it once more before it queues the
            // idempotent refresh. That is safer than losing the recovery signal.
            $this->reportFailure('record_retry_step', $failure);
        }

        $this->queueWorkingCanvasRefreshes();
    }

    private function queueWorkingCanvasRefreshes(): void
    {
        try {
            $this->refreshScheduler->enqueueAll();
        } catch (\Throwable $failure) {
            // A queue failure happens after the menu and shared source writes.
            // WordPress must continue the shared request. A later input change
            // or manual refresh can queue the derived working canvases again.
            $this->reportFailure(self::STEP_REFRESH, $failure);
            try {
                $this->rememberPendingStep(self::STEP_REFRESH);
            } catch (\Throwable $retryFailure) {
                $this->reportFailure('record_retry_step', $retryFailure);
            }
            return;
        }

        try {
            $deleted = delete_option(self::RETRY_OPTION);
            if ($deleted === false && get_option(self::RETRY_OPTION, false) !== false) {
                throw new \RuntimeException('Failed to clear the menu refresh retry step.');
            }
        } catch (\Throwable $failure) {
            // The derived refresh is already queued. Leaving the marker causes
            // only an idempotent retry on the next admin request.
            $this->reportFailure('clear_retry_step', $failure);
        }
    }

    private function rememberPendingStep(string $step): void
    {
        $updated = update_option(self::RETRY_OPTION, $step, false);
        if ($updated === false && get_option(self::RETRY_OPTION, '') !== $step) {
            throw new \RuntimeException('Failed to store the menu refresh retry step.');
        }
    }

    private function reportFailure(string $step, \Throwable $failure): void
    {
        error_log(sprintf(
            '[Uncanny Page Builder] Menu change post-save step "%s" failed (%s).',
            $step,
            $failure::class,
        ));
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;

/**
 * Cron worker for derived working canvas refreshes with bounded retries.
 *
 * A completed refresh can update editor CSS but cannot create an immutable
 * artifact or move a public pointer. Failures are retried with the existing
 * bounded queue policy.
 */
final class WpCronWorkingCanvasRefreshRunner
{
    private const BATCH_SIZE = 25;
    private const RECOVERY_DELAY_SECONDS = 60;

    public function __construct(
        private readonly WpCronWorkingCanvasRefreshQueue $queue,
        private readonly WorkingCanvasRefresherInterface $workingCanvas,
    ) {
    }

    public function register(): void
    {
        add_action(WpCronWorkingCanvasRefreshQueue::HOOK, [$this, 'run']);
    }

    public function run(): void
    {
        $this->runBatch(self::BATCH_SIZE);
    }

    /**
     * @return array{processed: int, completed: int, requeued: int, terminal: int}
     */
    public function runBatch(int $limit): array
    {
        $result = [
            'processed' => 0,
            'completed' => 0,
            'requeued' => 0,
            'terminal' => 0,
        ];

        try {
            $jobs = $this->queue->claimBatch($limit);
        } catch (\Throwable $failure) {
            // WP-Cron is a public WordPress boundary. A queue backend failure
            // must not terminate the cron request or block unrelated events.
            $this->reportBoundaryFailure('queue_claim', $failure);
            $this->scheduleRecovery();

            return $result;
        }

        foreach ($jobs as $job) {
            $pageId = $job['page_id'];
            $attempts = $job['attempts'];
            $claimToken = $job['claim_token'];
            $result['processed']++;

            try {
                $this->workingCanvas->refresh($pageId);
                $this->queue->complete($pageId, $claimToken);
                $result['completed']++;
                continue;
            } catch (\Throwable $e) {
                try {
                    $requeued = $this->retryOrGiveUp(
                        $pageId,
                        $attempts,
                        $claimToken,
                        'working_canvas_refresh_exception',
                        'Working canvas refresh failed (' . $e::class . ').',
                    );
                } catch (\Throwable $queueFailure) {
                    // Do not report a retry or terminal result when the queue
                    // did not record either state. The claim lease expires and
                    // a recovery event can claim the job again.
                    $this->reportBoundaryFailure('queue_recovery', $queueFailure, $pageId);
                    $this->scheduleRecovery();
                    continue;
                }
            }

            $result[$requeued ? 'requeued' : 'terminal']++;
        }

        return $result;
    }

    private function retryOrGiveUp(
        int $pageId,
        int $attempts,
        string $claimToken,
        string $errorCode,
        string $errorMessage,
    ): bool {
        $requeued = $this->queue->releaseForRetry(
            $pageId,
            $attempts,
            $claimToken,
            $errorCode,
            $errorMessage,
        );

        if (!function_exists('error_log')) {
            return $requeued;
        }

        if ($requeued) {
            error_log(sprintf(
                'Uncanny Page Builder working canvas refresh failed for page %d (attempt %d of %d, %s): %s',
                $pageId,
                $attempts + 1,
                WpCronWorkingCanvasRefreshQueue::MAX_ATTEMPTS,
                $errorCode,
                $errorMessage,
            ));

            return true;
        }

        error_log(sprintf(
            'Uncanny Page Builder working canvas refresh for page %d reached the attempt cap (%d, %s) and will not retry until the page is queued again: %s',
            $pageId,
            WpCronWorkingCanvasRefreshQueue::MAX_ATTEMPTS,
            $errorCode,
            $errorMessage,
        ));

        return false;
    }

    private function scheduleRecovery(): void
    {
        try {
            if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_single_event')) {
                return;
            }

            $timestamp = time() + self::RECOVERY_DELAY_SECONDS;
            $scheduled = wp_next_scheduled(WpCronWorkingCanvasRefreshQueue::HOOK);
            if ($scheduled !== false && $scheduled <= $timestamp) {
                return;
            }

            if (!wp_schedule_single_event($timestamp, WpCronWorkingCanvasRefreshQueue::HOOK)) {
                throw new \RuntimeException('WordPress did not schedule the working canvas recovery event.');
            }
        } catch (\Throwable $failure) {
            $this->reportBoundaryFailure('schedule_recovery', $failure);
        }
    }

    private function reportBoundaryFailure(string $step, \Throwable $failure, int $pageId = 0): void
    {
        $pageContext = $pageId > 0 ? sprintf(' for page %d', $pageId) : '';

        error_log(sprintf(
            '[Uncanny Page Builder] Working canvas cron step "%s" failed%s (%s).',
            $step,
            $pageContext,
            $failure::class,
        ));
    }
}

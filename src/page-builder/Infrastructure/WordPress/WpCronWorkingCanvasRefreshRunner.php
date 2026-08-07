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

        foreach ($this->queue->claimBatch($limit) as $job) {
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
                $requeued = $this->retryOrGiveUp(
                    $pageId,
                    $attempts,
                    $claimToken,
                    'working_canvas_refresh_exception',
                    $e->getMessage(),
                );
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
}

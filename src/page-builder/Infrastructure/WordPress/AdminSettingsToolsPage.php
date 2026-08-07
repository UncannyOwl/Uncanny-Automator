<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

final class AdminSettingsToolsPage
{
    public function __construct(
        private readonly WorkingCanvasAdminActions $workingCanvasAdminActions,
        private readonly ?WpCronWorkingCanvasRefreshQueue $refreshQueue = null,
    ) {
    }

    // Section: Tools maintenance content
    public function render(): void
    {
        $notice = $this->resultNotice();
        $queueStatus = $this->queueStatus();
        $workingCanvasActionUrl = $this->workingCanvasAdminActions->actionUrl();

        include __DIR__ . '/../../Presentation/Settings/tools-section.php';
    }

    private function resultNotice(): ?array
    {
        $result = is_string($_GET[WorkingCanvasAdminActions::RESULT_QUERY_ARG] ?? null)
            ? (string) $_GET[WorkingCanvasAdminActions::RESULT_QUERY_ARG]
            : '';

        if ($result !== WorkingCanvasAdminActions::RESULT_REFRESHED_ALL) {
            return null;
        }

        $count = $this->queryCount(WorkingCanvasAdminActions::COUNT_QUERY_ARG);
        $processed = $this->queryCount(WorkingCanvasAdminActions::PROCESSED_QUERY_ARG);
        $pending = $this->queryCount(WorkingCanvasAdminActions::PENDING_QUERY_ARG);
        $failed = $this->queryCount(WorkingCanvasAdminActions::FAILED_QUERY_ARG);
        if ($count === 0 && $processed === 0 && $pending === 0) {
            return [
                'class' => 'notice notice-info',
                'message' => _x('No Page Builder working previews needed to be refreshed.', 'Page Builder', 'uncanny-automator'),
            ];
        }

        $total = max($count, $processed + $pending);

        if ($processed > 0 && $pending > 0) {
            return [
                'class' => 'notice notice-success',
                'message' => sprintf(
                    /* translators: 1: total pages in the rebuild, 2: pages rebuilt immediately, 3: pages still pending. */
                    _x('Started refreshing %1$d Page Builder working previews. Refreshed %2$d now; %3$d remain queued in the background.', 'Page Builder', 'uncanny-automator'),
                    $total,
                    $processed,
                    $pending,
                ),
            ];
        }

        if ($processed > 0) {
            return [
                'class' => $failed > 0 ? 'notice notice-warning' : 'notice notice-success',
                'message' => sprintf(
                    /* translators: 1: total pages in the rebuild, 2: pages rebuilt immediately. */
                    _x('Refreshed %2$d of %1$d Page Builder working previews.', 'Page Builder', 'uncanny-automator'),
                    $total,
                    $processed,
                ),
            ];
        }

        return [
            'class' => 'notice notice-warning',
            'message' => sprintf(
                /* translators: 1: total pages in the rebuild, 2: pages still pending. */
                _x('Queued %1$d Page Builder working previews to be refreshed. %2$d remain pending; check that WordPress cron is running if this number does not go down.', 'Page Builder', 'uncanny-automator'),
                $total,
                $pending,
            ),
        ];
    }

    /**
     * @return array{pending: int, terminal_failures: array<int, array{page_id: int, attempts: int, next_run_at: int, claimed_at: int|null, last_error_code: string, last_error_message: string, terminal_at: int|null}>}
     */
    private function queueStatus(): array
    {
        return [
            'pending' => $this->refreshQueue?->pendingCount() ?? 0,
            'terminal_failures' => $this->refreshQueue?->terminalFailures() ?? [],
        ];
    }

    private function queryCount(string $key): int
    {
        return max(0, (int) ($_GET[$key] ?? 0));
    }
}

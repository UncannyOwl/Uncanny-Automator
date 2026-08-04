<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshQueueInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshQueueCleanupInterface;

/**
 * Option-backed working-canvas refresh queue with bounded retries and visible
 * terminal failures.
 *
 * Each entry carries page_id, attempts, next_run_at, claimed_at,
 * claim_token, last_error_code, last_error_message, and terminal_at. Entries
 * with terminal_at set are never claimed again; a fresh enqueuePages() call
 * resets them for another refresh cycle.
 *
 * WordPress options do not provide a compare-and-swap API. Every queue mutation
 * therefore runs behind a short-lived option mutex created with add_option(),
 * whose unique option key makes acquisition atomic at the database boundary.
 * Per-claim tokens separately prevent a worker whose lease expired from
 * completing or retrying a newer enqueue of the same page.
 */
final class WpCronWorkingCanvasRefreshQueue implements
    WorkingCanvasRefreshQueueInterface,
    WorkingCanvasRefreshQueueCleanupInterface
{
    public const HOOK = 'uncanny_page_builder_refresh_working_canvases';
    public const MAX_ATTEMPTS = 5;
    private const OPTION_KEY = 'uncanny_page_builder_working_canvas_refresh_queue';
    private const LOCK_OPTION_KEY = 'uncanny_page_builder_working_canvas_refresh_queue_lock';
    private const BASE_RETRY_DELAY_SECONDS = 60;
    private const MAX_RETRY_DELAY_SECONDS = 3600;
    private const MAX_TERMINAL_ENTRIES = 100;
    private const MAX_ERROR_MESSAGE_LENGTH = 500;
    private const CLAIM_LEASE_SECONDS = 900;
    private const LOCK_LEASE_SECONDS = 10;
    private const LOCK_WAIT_MICROSECONDS = 20_000;
    private const LOCK_WAIT_ATTEMPTS = 100;

    public function enqueuePages(array $pageIds): void
    {
        if (!$this->canMutate()) {
            return;
        }

        $pageIds = array_filter(array_map('intval', $pageIds), static fn(int $pageId): bool => $pageId > 0);
        if ($pageIds === []) {
            return;
        }

        $now = time();
        $this->mutateEntries(function (array $entries) use ($pageIds, $now): array {
            foreach ($pageIds as $pageId) {
                $entries[$pageId] = $this->freshEntry($pageId, $now);
            }

            return $entries;
        });
        $this->schedule($now + self::BASE_RETRY_DELAY_SECONDS);
    }

    /**
     * Claims due, non-terminal jobs behind a visibility timeout. Successful
     * refreshes must call complete(); failures go back through releaseForRetry().
     *
     * @return array<int, array{page_id: int, attempts: int, claim_token: string}>
     */
    public function claimBatch(int $limit): array
    {
        if (!$this->canMutate()) {
            return [];
        }

        $now = time();
        $limit = max(1, min(100, $limit));
        $claimed = [];
        $entries = $this->mutateEntries(function (array $entries) use ($limit, $now, &$claimed): array {
            foreach ($entries as $pageId => $entry) {
                if (count($claimed) >= $limit) {
                    break;
                }
                if (!$this->isClaimable($entry, $now)) {
                    continue;
                }

                $claimToken = $this->newToken();
                $claimed[] = [
                    'page_id' => $pageId,
                    'attempts' => $entry['attempts'],
                    'claim_token' => $claimToken,
                ];
                $entries[$pageId]['claimed_at'] = $now;
                $entries[$pageId]['claim_token'] = $claimToken;
            }

            return $entries;
        });

        $nextRun = $this->earliestPendingRun($entries);
        if ($nextRun !== null) {
            $this->schedule(max($nextRun, $now + self::BASE_RETRY_DELAY_SECONDS));
        }

        return $claimed;
    }

    public function complete(int $pageId, string $claimToken): void
    {
        if ($pageId <= 0 || $claimToken === '' || !$this->canMutate()) {
            return;
        }

        $this->mutateEntries(static function (array $entries) use ($pageId, $claimToken): array {
            if (($entries[$pageId]['claim_token'] ?? null) === $claimToken) {
                unset($entries[$pageId]);
            }

            return $entries;
        });
    }

    public function removePage(int $pageId): void
    {
        if ($pageId <= 0 || !$this->canMutate()) {
            return;
        }

        $this->mutateEntries(static function (array $entries) use ($pageId): array {
            unset($entries[$pageId]);

            return $entries;
        });
    }

    /**
     * Permanently removes stored terminal failures without touching active work.
     */
    public function clearTerminalFailures(): int
    {
        if (!$this->canMutate()) {
            return 0;
        }

        $cleared = 0;
        $this->mutateEntries(static function (array $entries) use (&$cleared): array {
            foreach ($entries as $pageId => $entry) {
                if ($entry['terminal_at'] === null) {
                    continue;
                }

                unset($entries[$pageId]);
                $cleared++;
            }

            return $entries;
        });

        return $cleared;
    }

    /**
     * Records a failed attempt for a claimed job. Returns true when the job
     * was requeued with backoff, false when it hit the attempt cap and was
     * stored as a terminal failure.
     */
    public function releaseForRetry(
        int $pageId,
        int $attempts,
        string $claimToken,
        string $errorCode,
        string $errorMessage,
    ): bool {
        if ($pageId <= 0 || $claimToken === '' || !$this->canMutate()) {
            return false;
        }

        $now = time();
        $attempts = max(0, $attempts) + 1;
        $requeued = false;
        $nextRunAt = null;

        $this->mutateEntries(function (array $entries) use (
            $pageId,
            $attempts,
            $claimToken,
            $errorCode,
            $errorMessage,
            $now,
            &$requeued,
            &$nextRunAt,
        ): array {
            $current = $entries[$pageId] ?? null;
            if (!is_array($current)) {
                return $entries;
            }

            // A fresh enqueue or a reclaimed lease owns this page now. The old
            // worker must not overwrite that newer job's state.
            if (($current['claim_token'] ?? null) !== $claimToken) {
                $requeued = $current['terminal_at'] === null;

                return $entries;
            }

            $entry = $this->freshEntry($pageId, $now);
            $entry['attempts'] = $attempts;
            $entry['last_error_code'] = $errorCode;
            $entry['last_error_message'] = substr($errorMessage, 0, self::MAX_ERROR_MESSAGE_LENGTH);

            if ($attempts >= self::MAX_ATTEMPTS) {
                $entry['next_run_at'] = 0;
                $entry['terminal_at'] = $now;
                $entries[$pageId] = $entry;

                return $entries;
            }

            $delay = min(self::MAX_RETRY_DELAY_SECONDS, self::BASE_RETRY_DELAY_SECONDS * (2 ** ($attempts - 1)));
            $entry['next_run_at'] = $now + $delay;
            $nextRunAt = $entry['next_run_at'];
            $requeued = true;
            $entries[$pageId] = $entry;

            return $entries;
        });

        if ($nextRunAt !== null) {
            $this->schedule($nextRunAt);
        }

        return $requeued;
    }

    /**
     * Jobs that exhausted their retries and wait for a fresh enqueue.
     *
     * @return array<int, array{page_id: int, attempts: int, next_run_at: int, claimed_at: int|null, claim_token: string|null, last_error_code: string, last_error_message: string, terminal_at: int|null}>
     */
    public function terminalFailures(): array
    {
        if (!function_exists('get_option')) {
            return [];
        }

        return array_values(array_filter(
            $this->loadEntries(),
            static fn(array $entry): bool => $entry['terminal_at'] !== null,
        ));
    }

    public function pendingCount(): int
    {
        if (!function_exists('get_option')) {
            return 0;
        }

        return count(array_filter(
            $this->loadEntries(),
            static fn(array $entry): bool => $entry['terminal_at'] === null,
        ));
    }

    /**
     * @return array<int, array{page_id: int, attempts: int, next_run_at: int, claimed_at: int|null, claim_token: string|null, last_error_code: string, last_error_message: string, terminal_at: int|null}>
     */
    private function loadEntries(): array
    {
        $raw = get_option(self::OPTION_KEY, []);
        if (!is_array($raw)) {
            return [];
        }

        $entries = [];
        foreach ($raw as $item) {
            if (is_int($item) || is_string($item)) {
                $pageId = (int) $item;
                if ($pageId > 0) {
                    $entries[$pageId] = $this->freshEntry($pageId, 0);
                }
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $pageId = (int) ($item['page_id'] ?? 0);
            if ($pageId <= 0) {
                continue;
            }

            $terminalAt = (int) ($item['terminal_at'] ?? 0);
            $claimedAt = (int) ($item['claimed_at'] ?? 0);
            $entries[$pageId] = [
                'page_id' => $pageId,
                'attempts' => max(0, (int) ($item['attempts'] ?? 0)),
                'next_run_at' => max(0, (int) ($item['next_run_at'] ?? 0)),
                'claimed_at' => $claimedAt > 0 ? $claimedAt : null,
                'claim_token' => is_string($item['claim_token'] ?? null) && $item['claim_token'] !== ''
                    ? $item['claim_token']
                    : null,
                'last_error_code' => (string) ($item['last_error_code'] ?? ''),
                'last_error_message' => (string) ($item['last_error_message'] ?? ''),
                'terminal_at' => $terminalAt > 0 ? $terminalAt : null,
            ];
        }

        return $entries;
    }

    /**
     * @param array<int, array{page_id: int, attempts: int, next_run_at: int, claimed_at: int|null, claim_token: string|null, last_error_code: string, last_error_message: string, terminal_at: int|null}> $entries
     */
    private function storeEntries(array $entries): void
    {
        $terminal = array_filter($entries, static fn(array $entry): bool => $entry['terminal_at'] !== null);
        if (count($terminal) > self::MAX_TERMINAL_ENTRIES) {
            uasort($terminal, static fn(array $a, array $b): int => ($b['terminal_at'] ?? 0) <=> ($a['terminal_at'] ?? 0));
            foreach (array_slice(array_keys($terminal), self::MAX_TERMINAL_ENTRIES) as $pageId) {
                unset($entries[$pageId]);
            }
        }

        ksort($entries);
        update_option(self::OPTION_KEY, array_values($entries), false);
    }

    /**
     * @param array<int, array{page_id: int, attempts: int, next_run_at: int, claimed_at: int|null, claim_token: string|null, last_error_code: string, last_error_message: string, terminal_at: int|null}> $entries
     */
    private function earliestPendingRun(array $entries): ?int
    {
        $earliest = null;
        foreach ($entries as $entry) {
            if ($entry['terminal_at'] !== null) {
                continue;
            }

            $nextRun = $entry['claimed_at'] === null
                ? $entry['next_run_at']
                : $entry['claimed_at'] + self::CLAIM_LEASE_SECONDS;

            if ($earliest === null || $nextRun < $earliest) {
                $earliest = $nextRun;
            }
        }

        return $earliest;
    }

    /**
     * @param array{page_id: int, attempts: int, next_run_at: int, claimed_at: int|null, claim_token: string|null, last_error_code: string, last_error_message: string, terminal_at: int|null} $entry
     */
    private function isClaimable(array $entry, int $now): bool
    {
        if ($entry['terminal_at'] !== null || $entry['next_run_at'] > $now) {
            return false;
        }

        return $entry['claimed_at'] === null
            || $entry['claimed_at'] + self::CLAIM_LEASE_SECONDS <= $now;
    }

    /**
     * @return array{page_id: int, attempts: int, next_run_at: int, claimed_at: int|null, claim_token: string|null, last_error_code: string, last_error_message: string, terminal_at: int|null}
     */
    private function freshEntry(int $pageId, int $now): array
    {
        return [
            'page_id' => $pageId,
            'attempts' => 0,
            'next_run_at' => $now,
            'claimed_at' => null,
            'claim_token' => null,
            'last_error_code' => '',
            'last_error_message' => '',
            'terminal_at' => null,
        ];
    }

    /**
     * Serialize one read-modify-write cycle so concurrent WordPress requests
     * cannot save snapshots over each other.
     *
     * @template T
     * @param callable(array<int, array{page_id: int, attempts: int, next_run_at: int, claimed_at: int|null, claim_token: string|null, last_error_code: string, last_error_message: string, terminal_at: int|null}>): array<int, array{page_id: int, attempts: int, next_run_at: int, claimed_at: int|null, claim_token: string|null, last_error_code: string, last_error_message: string, terminal_at: int|null}> $mutation
     * @return array<int, array{page_id: int, attempts: int, next_run_at: int, claimed_at: int|null, claim_token: string|null, last_error_code: string, last_error_message: string, terminal_at: int|null}>
     */
    private function mutateEntries(callable $mutation): array
    {
        // Lightweight unit/compatibility environments may expose only the
        // historical get/update option pair. WordPress itself always provides
        // add_option/delete_option, so production mutations take the mutex path.
        if (!$this->canLock()) {
            $entries = $mutation($this->loadEntries());
            $this->storeEntries($entries);

            return $entries;
        }

        $lockToken = $this->acquireLock();

        try {
            $entries = $mutation($this->loadEntries());
            $this->storeEntries($entries);

            return $entries;
        } finally {
            $this->releaseLock($lockToken);
        }
    }

    private function acquireLock(): string
    {
        $lockToken = $this->newToken();

        for ($attempt = 0; $attempt < self::LOCK_WAIT_ATTEMPTS; ++$attempt) {
            $now = time();
            if (
                add_option(
                    self::LOCK_OPTION_KEY,
                    ['token' => $lockToken, 'expires_at' => $now + self::LOCK_LEASE_SECONDS],
                    '',
                    false,
                )
            ) {
                return $lockToken;
            }

            $current = get_option(self::LOCK_OPTION_KEY, null);
            if (is_array($current) && (int) ($current['expires_at'] ?? 0) <= $now) {
                delete_option(self::LOCK_OPTION_KEY);
                continue;
            }

            usleep(self::LOCK_WAIT_MICROSECONDS);
        }

        throw new \RuntimeException('Could not acquire the Page Builder working-canvas refresh queue lock.');
    }

    private function releaseLock(string $lockToken): void
    {
        $current = get_option(self::LOCK_OPTION_KEY, null);
        if (is_array($current) && hash_equals((string) ($current['token'] ?? ''), $lockToken)) {
            delete_option(self::LOCK_OPTION_KEY);
        }
    }

    private function canMutate(): bool
    {
        return function_exists('get_option')
            && function_exists('update_option');
    }

    private function canLock(): bool
    {
        return function_exists('add_option')
            && function_exists('delete_option');
    }

    private function newToken(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return (string) wp_generate_uuid4();
        }

        return bin2hex(random_bytes(16));
    }

    private function schedule(int $timestamp): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_single_event')) {
            return;
        }

        $timestamp = max(time() + self::BASE_RETRY_DELAY_SECONDS, $timestamp);

        $existing = wp_next_scheduled(self::HOOK);
        if ($existing !== false) {
            // A backed-off retry may have scheduled the next run far out;
            // fresh due jobs must be able to pull the event earlier.
            if ((int) $existing <= $timestamp || !function_exists('wp_unschedule_event')) {
                return;
            }

            wp_unschedule_event((int) $existing, self::HOOK);
        }

        wp_schedule_single_event($timestamp, self::HOOK);
    }
}

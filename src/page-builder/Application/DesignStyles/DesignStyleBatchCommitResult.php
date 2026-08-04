<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

/**
 * Normalized outcome for a design stack batch commit.
 */
final class DesignStyleBatchCommitResult
{
    /**
     * @param array<int, array<string, mixed>> $groups
     * @param array<int, array<string, mixed>> $applied
     * @param array<int, array<string, mixed>> $rejected
     * @param array<int, array{section_id?: int}> $globalPartSources
     * @param array<string, mixed> $refreshed
     */
    private function __construct(
        private readonly string $status,
        private readonly string $message,
        private readonly array $groups,
        private readonly array $applied,
        private readonly array $rejected,
        private readonly array $globalPartSources,
        private readonly array $refreshed,
    ) {}

    /**
     * @param DesignStyleCommitResult[] $results
     */
    public static function success(array $results): self
    {
        return self::fromGroupResults('success', 'Design changes saved.', self::wrapResults($results));
    }

    /**
     * @param DesignStyleCommitResult[] $results
     */
    public static function error(string $message, array $results = []): self
    {
        return self::fromGroupResults('error', $message, self::wrapResults($results));
    }

    /**
     * @param array<int, array{result: DesignStyleCommitResult, change_ids?: string[]}> $groups
     */
    public static function successGroups(array $groups): self
    {
        return self::fromGroupResults('success', 'Design changes saved.', $groups);
    }

    /**
     * @param array<int, array{result: DesignStyleCommitResult, change_ids?: string[]}> $groups
     */
    public static function errorGroups(string $message, array $groups = []): self
    {
        return self::fromGroupResults('error', $message, $groups);
    }

    /**
     * @param DesignStyleCommitResult[] $results
     * @return array<int, array{result: DesignStyleCommitResult, change_ids: string[]}>
     */
    private static function wrapResults(array $results): array
    {
        return array_map(
            static fn(DesignStyleCommitResult $result): array => ['result' => $result, 'change_ids' => []],
            $results,
        );
    }

    /**
     * @param array<int, array{result: DesignStyleCommitResult, change_ids?: string[]}> $resultGroups
     */
    private static function fromGroupResults(string $status, string $message, array $resultGroups): self
    {
        $groups = [];
        $applied = [];
        $rejected = [];
        $globalPartSources = [];
        $refreshed = [];

        foreach ($resultGroups as $resultGroup) {
            $result = $resultGroup['result'];
            $changeIds = array_values(array_filter(
                array_map(
                    static fn(mixed $id): string => is_scalar($id) ? trim((string) $id) : '',
                    $resultGroup['change_ids'] ?? [],
                ),
                static fn(string $id): bool => $id !== '',
            ));
            $payload = $result->toArray();
            $groups[] = [
                'status'     => $payload['status'] ?? '',
                'scope'      => $payload['scope'] ?? '',
                'message'    => $payload['message'] ?? '',
                'change_ids' => $changeIds,
                'refreshed'  => $payload['refreshed'] ?? new \stdClass(),
            ];
            foreach (is_array($payload['applied'] ?? null) ? $payload['applied'] : [] as $entry) {
                if (is_array($entry)) {
                    $applied[] = ['scope' => (string) ($payload['scope'] ?? '')] + $entry;
                }
            }
            foreach (is_array($payload['rejected'] ?? null) ? $payload['rejected'] : [] as $entry) {
                if (is_array($entry)) {
                    $rejected[] = ['scope' => (string) ($payload['scope'] ?? '')] + $entry;
                }
            }

            $groupRefreshed = is_array($payload['refreshed'] ?? null) ? $payload['refreshed'] : [];
            $refreshed = array_replace_recursive($refreshed, $groupRefreshed);

            $globalPart = is_array($groupRefreshed['global_part'] ?? null) ? $groupRefreshed['global_part'] : [];
            $partId = (int) ($globalPart['part_id'] ?? ($globalPart['global_part_id'] ?? 0));
            if ($partId > 0) {
                $globalPartSources[$partId] = [
                    'section_id' => (int) ($globalPart['section_id'] ?? 0),
                ];
            }
        }

        return new self(
            status: $status,
            message: $message,
            groups: $groups,
            applied: $applied,
            rejected: $rejected,
            globalPartSources: $globalPartSources,
            refreshed: $refreshed,
        );
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status'              => $this->status,
            'message'             => $this->message,
            'groups'              => $this->groups,
            'applied'             => $this->applied,
            'rejected'            => $this->rejected,
            'global_part_sources' => $this->globalPartSources,
            'refreshed'           => $this->refreshed === [] ? new \stdClass() : $this->refreshed,
        ];
    }
}

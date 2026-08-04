<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Domain\DesignStyles\DesignWriteScope;

/**
 * Normalized outcome of a design style commit.
 *
 * status is one of success | error | notice. applied lists the {property, value}
 * pairs that landed; rejected lists {property, reason} pairs that did not. The
 * optional refreshed payload carries fresh source-of-truth data for the client
 * result applier (global profile, page overrides, or section/canvas data).
 */
final class DesignStyleCommitResult
{
    /**
     * @param array<int, array{property: string, value: string}> $applied
     * @param array<int, array{property: string, reason: string}> $rejected
     * @param array<string, mixed> $refreshed
     */
    private function __construct(
        private readonly string $status,
        private readonly DesignWriteScope $scope,
        private readonly string $message,
        private readonly array $applied,
        private readonly array $rejected,
        private readonly array $refreshed,
    ) {}

    /**
     * @param array<int, array{property: string, value: string}> $applied
     * @param array<string, mixed> $refreshed
     */
    public static function success(
        DesignWriteScope $scope,
        string $message,
        array $applied,
        array $refreshed = [],
    ): self {
        return new self('success', $scope, $message, $applied, [], $refreshed);
    }

    /**
     * @param array<int, array{property: string, reason: string}> $rejected
     */
    public static function error(DesignWriteScope $scope, string $message, array $rejected = []): self
    {
        return new self('error', $scope, $message, [], $rejected, []);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function scope(): DesignWriteScope
    {
        return $this->scope;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * Normalized envelope shared by every scope (see plan's error contract).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status'    => $this->status,
            'scope'     => $this->scope->value,
            'message'   => $this->message,
            'applied'   => $this->applied,
            'rejected'  => $this->rejected,
            'refreshed' => $this->refreshed === [] ? new \stdClass() : $this->refreshed,
        ];
    }
}

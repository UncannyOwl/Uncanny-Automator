<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Observability;

/** Records an operational failure without exposing request or source payloads. */
interface FailureReporterInterface
{
    /**
     * $step is a stable support code. Keep it free of request data so support
     * can search logs and the codebase without exposing the failure message.
     */
    public function report(string $scope, int $ownerId, string $step, \Throwable $failure): void;
}

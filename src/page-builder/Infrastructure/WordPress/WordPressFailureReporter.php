<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Observability\FailureReporterInterface;

final class WordPressFailureReporter implements FailureReporterInterface
{
    public function report(string $scope, int $ownerId, string $step, \Throwable $failure): void
    {
        \error_log(sprintf(
            '[Uncanny Page Builder] %s %d step "%s" failed (%s).',
            $scope,
            $ownerId,
            $step,
            $failure::class,
        ));
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Section;

/**
 * Reports a failed secondary step after section source was committed.
 *
 * Implementations must not be required for source persistence to succeed.
 * This port keeps logging and monitoring details outside the application
 * service while making partial post-commit failures observable.
 */
interface SectionPostCommitFailureReporterInterface
{
    public function report(int $pageId, string $step, \Throwable $failure): void;
}

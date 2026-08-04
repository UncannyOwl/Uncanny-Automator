<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Section\SectionPostCommitFailureReporterInterface;

/**
 * Sends post-commit section failures to the site's configured PHP error log.
 */
final class WordPressSectionPostCommitFailureReporter implements SectionPostCommitFailureReporterInterface
{
    private const MAX_MESSAGE_LENGTH = 500;

    public function report(int $pageId, string $step, \Throwable $failure): void
    {
        $message = str_replace(["\r", "\n"], ' ', $failure->getMessage());

        \error_log(sprintf(
            '[Uncanny Page Builder] Section source for page %d was saved, but post-commit step "%s" failed (%s): %s',
            $pageId,
            $step,
            $failure::class,
            substr($message, 0, self::MAX_MESSAGE_LENGTH),
        ));
    }
}

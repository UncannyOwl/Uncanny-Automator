<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Writes a Page Builder diagnostic line to the PHP error log. It writes on
 * every site, debug mode or not: these lines record failures that support
 * needs to see on production sites.
 */
final class DiagnosticLog
{
    public static function write(string $message): void
    {
        error_log($message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Page Builder failure diagnostics; the one place Page Builder writes to the PHP error log.
    }
}

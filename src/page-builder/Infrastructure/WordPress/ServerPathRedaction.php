<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Removes server paths and URLs from a system message before an
 * authenticated caller receives it.
 */
final class ServerPathRedaction
{
    /**
     * @param array<string, string> $knownPaths absolute roots mapped to the label that replaces them
     */
    public static function redact(string $message, array $knownPaths = []): string
    {
        // Replace whole known roots first so filenames and relative asset paths remain useful.
        $paths = $knownPaths;
        uksort($paths, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        $message = strtr($message, $paths);

        // open_basedir errors can also contain server paths outside the asset directories.
        // Keep the operating-system explanation, but omit unrelated absolute paths and URLs.
        $message = preg_replace('~[a-z][a-z0-9+.-]*://[^\s<>"\']+~i', '[url]', $message) ?? 'unknown';
        $message = preg_replace(
            '~(?<![A-Za-z0-9_\]])(?:[A-Za-z]:[\\\\/]|\\\\\\\\|/)[^<>"\'(),;:\r\n]+~',
            '[server-path]',
            $message,
        ) ?? 'unknown';
        $message = preg_replace('/[\x00-\x1F\x7F]/', ' ', $message) ?? 'unknown';
        $message = trim(substr($message, 0, 1024));

        // Filesystem messages can contain filenames with non-UTF-8 bytes.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Use PHP's explicit invalid-byte replacement for filesystem messages.
        return json_decode(json_encode($message !== '' ? $message : 'unknown', JSON_INVALID_UTF8_SUBSTITUTE), true);
    }
}

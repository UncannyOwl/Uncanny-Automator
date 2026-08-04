<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveArtifact;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveArtifactStoreInterface;

final class WordPressPageSourceArchiveArtifactStore implements PageSourceArchiveArtifactStoreInterface
{
    public const CLEANUP_ACTION = 'uncanny_page_builder_delete_expired_page_archive';

    private const TRANSIENT_PREFIX = 'uncanny_page_builder_archive_';
    private const LOCK_PREFIX = 'uncanny_page_builder_archive_lock_';
    private const DIRECTORY_NAME = 'uncanny-page-builder-archives';
    private const ARCHIVE_PATTERN = '/^[a-f0-9]{64}\.zip$/';
    private const TTL_SECONDS = 300;

    public function __construct(
        private readonly ?string $directory = null,
    ) {}

    public function register(): void
    {
        add_action(self::CLEANUP_ACTION, [$this, 'deleteExpired']);
    }

    public function store(int $pageId, PageSourceArchiveArtifact $artifact): string
    {
        if ($pageId <= 0 || !is_file($artifact->path()) || !is_readable($artifact->path())) {
            throw new \InvalidArgumentException('A readable page archive is required.');
        }

        $sourcePath = $artifact->path();
        $storedPath = null;
        try {
            $directory = $this->prepareDirectory();
            $this->sweepStaleArtifacts($directory);

            $token = bin2hex(random_bytes(32));
            $archiveName = bin2hex(random_bytes(32)) . '.zip';
            $candidatePath = $directory . DIRECTORY_SEPARATOR . $archiveName;
            if (!@rename($sourcePath, $candidatePath)) {
                throw new \RuntimeException('Could not move the page archive into protected storage.');
            }
            $storedPath = $candidatePath;

            $key = self::key($token);
            $stored = set_transient($key, [
                'page_id' => $pageId,
                'archive_name' => $archiveName,
                'filename' => $artifact->filename(),
            ], self::TTL_SECONDS);
            if ($stored !== true) {
                throw new \RuntimeException('Could not prepare the page archive download.');
            }

            $scheduled = wp_schedule_single_event(
                time() + self::TTL_SECONDS,
                self::CLEANUP_ACTION,
                [$archiveName],
            );
            if ($scheduled !== true) {
                error_log('[Uncanny Page Builder] Page archive cleanup schedule failed.');
            }
        } catch (\Throwable $e) {
            if (is_string($storedPath)) {
                $this->deleteFile($storedPath);
            } else {
                $this->deleteFile($sourcePath);
            }
            throw $e;
        }

        return $token;
    }

    public function take(int $pageId, string $token): ?PageSourceArchiveArtifact
    {
        if ($pageId <= 0 || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $lockKey = self::lockKey($token);
        if (!$this->acquireLock($lockKey)) {
            return null;
        }

        try {
            $key = self::key($token);
            $stored = get_transient($key);
            if (
                !is_array($stored)
                || (int) ($stored['page_id'] ?? 0) !== $pageId
                || !is_string($stored['archive_name'] ?? null)
                || !preg_match(self::ARCHIVE_PATTERN, $stored['archive_name'])
                || !is_string($stored['filename'] ?? null)
            ) {
                return null;
            }

            delete_transient($key);
            wp_clear_scheduled_hook(self::CLEANUP_ACTION, [$stored['archive_name']]);

            return new PageSourceArchiveArtifact(
                $this->archiveDirectory() . DIRECTORY_SEPARATOR . $stored['archive_name'],
                $stored['filename'],
            );
        } finally {
            delete_option($lockKey);
        }
    }

    public function deleteExpired(string $archiveName): void
    {
        if (!preg_match(self::ARCHIVE_PATTERN, $archiveName)) {
            return;
        }

        $this->deleteFile($this->archiveDirectory() . DIRECTORY_SEPARATOR . $archiveName);
    }

    public function delete(PageSourceArchiveArtifact $artifact): void
    {
        $archiveName = basename($artifact->path());
        if (!preg_match(self::ARCHIVE_PATTERN, $archiveName)) {
            return;
        }

        $artifactDirectory = realpath(dirname($artifact->path()));
        $storageDirectory = realpath($this->archiveDirectory());
        if (!is_string($artifactDirectory) || !is_string($storageDirectory)) {
            return;
        }

        $sameDirectory = PHP_OS_FAMILY === 'Windows'
            ? strcasecmp($artifactDirectory, $storageDirectory) === 0
            : $artifactDirectory === $storageDirectory;
        if ($sameDirectory) {
            $this->deleteExpired($archiveName);
        }
    }

    private function prepareDirectory(): string
    {
        $directory = $this->archiveDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Could not create protected page archive storage.');
        }

        @chmod($directory, 0700);
        $this->assertDirectoryIsProtected($directory);
        $this->writeProtectionFile($directory . DIRECTORY_SEPARATOR . 'index.php', "<?php\n// Silence is golden.\n");
        $this->writeProtectionFile($directory . DIRECTORY_SEPARATOR . '.htaccess', "Deny from all\nRequire all denied\n");
        $this->writeProtectionFile(
            $directory . DIRECTORY_SEPARATOR . 'web.config',
            "<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
        );

        return $directory;
    }

    private function assertDirectoryIsProtected(string $directory): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return;
        }

        clearstatcache(true, $directory);
        $stat = @stat($directory);
        if (!is_array($stat) || !is_int($stat['mode'] ?? null) || ($stat['mode'] & 0077) !== 0) {
            throw new \RuntimeException('Could not protect page archive storage.');
        }

        $hasEffectiveUserId = function_exists(__NAMESPACE__ . '\\posix_geteuid')
            || function_exists('posix_geteuid');
        if ($hasEffectiveUserId && (int) ($stat['uid'] ?? -1) !== posix_geteuid()) {
            throw new \RuntimeException('Could not protect page archive storage.');
        }
    }

    private function archiveDirectory(): string
    {
        if (is_string($this->directory) && $this->directory !== '') {
            return rtrim($this->directory, '/\\');
        }

        $tempDirectory = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();

        return rtrim($tempDirectory, '/\\') . DIRECTORY_SEPARATOR . self::DIRECTORY_NAME;
    }

    private function sweepStaleArtifacts(?string $directory = null): void
    {
        $directory ??= $this->archiveDirectory();
        if (!is_dir($directory)) {
            return;
        }

        $entries = @scandir($directory);
        if (!is_array($entries)) {
            return;
        }

        $oldestAllowed = time() - self::TTL_SECONDS;
        foreach ($entries as $entry) {
            if (!preg_match(self::ARCHIVE_PATTERN, $entry)) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            $modifiedAt = @filemtime($path);
            if (is_int($modifiedAt) && $modifiedAt <= $oldestAllowed) {
                $this->deleteFile($path);
            }
        }
    }

    private function writeProtectionFile(string $path, string $contents): void
    {
        if (is_file($path)) {
            return;
        }

        $handle = @fopen($path, 'x');
        if ($handle === false) {
            if (!is_file($path)) {
                throw new \RuntimeException('Could not protect page archive storage.');
            }
            return;
        }

        try {
            if (fwrite($handle, $contents) !== strlen($contents)) {
                throw new \RuntimeException('Could not protect page archive storage.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function deleteFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        if (!is_writable($path)) {
            @chmod($path, 0600);
        }
        @unlink($path);
    }

    private function acquireLock(string $lockKey): bool
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !is_string($wpdb->options ?? null)) {
            return false;
        }

        $query = $wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
            $lockKey,
            (string) time(),
            'no',
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The unique option name is the lock boundary.
        $inserted = $wpdb->query($query);

        return (int) $inserted === 1;
    }

    private static function key(string $token): string
    {
        return self::TRANSIENT_PREFIX . hash('sha256', $token);
    }

    private static function lockKey(string $token): string
    {
        return self::LOCK_PREFIX . hash('sha256', $token);
    }
}

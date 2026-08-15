<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Filesystem\LocalFilesystemPortInterface;

/**
 * WordPress adapter for the application filesystem port.
 *
 * Native calls remain only where WordPress has no equivalent with the required
 * semantics, such as atomic rename and exclusive file creation.
 */
final class WordPressLocalFilesystem implements LocalFilesystemPortInterface
{
    private ?object $filesystem = null;

    public function read(string $path): string|false
    {
        return $this->filesystem()->get_contents($path);
    }

    public function moveAtomically(string $source, string $destination): bool
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- WP_Filesystem::move() can fall back to copy-and-delete, but this handoff must remain atomic.
        return @rename($source, $destination);
    }

    public function ensureDirectory(string $directory, int $mode): bool
    {
        $created = wp_mkdir_p($directory);
        if (!is_dir($directory) && !$created && !is_dir($directory)) {
            return false;
        }

        $this->chmod($directory, $mode);

        return true;
    }

    public function createExclusive(string $path, string $contents): bool
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- WP_Filesystem has no exclusive-create operation.
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            return false;
        }

        $complete = false;
        try {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- The exclusive handle must be written without reopening the path.
            $complete = fwrite($handle, $contents) === strlen($contents);
        } finally {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the exclusive native handle opened above.
            fclose($handle);
            if (!$complete) {
                $this->delete($path);
            }
        }

        return $complete;
    }

    public function writeBounded(string $path, mixed $source, int $maxBytes): int|false
    {
        if ($maxBytes < 0 || (!is_string($source) && !is_resource($source))) {
            return false;
        }

        if (is_string($source)) {
            $bytes = strlen($source);
            if ($bytes > $maxBytes) {
                return false;
            }

            return $this->filesystem()->put_contents($path, $source) ? $bytes : false;
        }

        // WP_Filesystem has no bounded streaming write; buffering the archive
        // entry would defeat the import limit this operation enforces.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $output = @fopen($path, 'wb');
        if ($output === false) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- This adapter owns the supplied stream.
            fclose($source);
            return false;
        }

        $written = 0;
        $complete = false;
        try {
            while (!feof($source)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Bounded streaming avoids loading an archive entry into memory.
                $chunk = fread($source, 1048576);
                if ($chunk === false || $written + strlen($chunk) > $maxBytes) {
                    return false;
                }

                $offset = 0;
                $length = strlen($chunk);
                while ($offset < $length) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Continue partial writes on the bounded native stream.
                    $chunkBytes = fwrite($output, substr($chunk, $offset));
                    if ($chunkBytes === false || $chunkBytes === 0) {
                        return false;
                    }
                    $offset += $chunkBytes;
                }
                $written += $length;
            }
            $complete = true;

            return $written;
        } finally {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the native streams owned by this operation.
            fclose($source);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the native streams owned by this operation.
            fclose($output);
            if (!$complete) {
                $this->delete($path);
            }
        }
    }

    public function isWritable(string $path): bool
    {
        return $this->filesystem()->is_writable($path);
    }

    public function chmod(string $path, int $mode): bool
    {
        return $this->filesystem()->chmod($path, $mode);
    }

    public function delete(string $path): bool
    {
        return $this->filesystem()->delete($path, false, 'f');
    }

    private function filesystem(): object
    {
        if ($this->filesystem !== null) {
            return $this->filesystem;
        }

        if (!class_exists('WP_Filesystem_Base', false)) {
            $file = ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            if (!is_file($file)) {
                throw new \RuntimeException('WordPress filesystem support is unavailable.');
            }
            require_once $file;
        }

        if (!class_exists('WP_Filesystem_Direct', false)) {
            $file = ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            if (!is_file($file)) {
                throw new \RuntimeException('WordPress direct filesystem support is unavailable.');
            }
            require_once $file;
        }

        $this->filesystem = new \WP_Filesystem_Direct(null);

        return $this->filesystem;
    }
}

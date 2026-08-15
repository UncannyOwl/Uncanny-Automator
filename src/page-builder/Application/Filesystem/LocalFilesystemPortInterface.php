<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Filesystem;

/**
 * Application-owned boundary for local filesystem capabilities.
 *
 * Consumers depend on the operations they require; WordPress supplies the
 * implementation without leaking WP_Filesystem into application or domain code.
 */
interface LocalFilesystemPortInterface extends LocalFileReaderInterface
{
    public function moveAtomically(string $source, string $destination): bool;

    public function ensureDirectory(string $directory, int $mode): bool;

    public function createExclusive(string $path, string $contents): bool;

    /**
     * Materialize a string or readable stream without exceeding the byte limit.
     * A supplied stream is consumed and closed by the implementation.
     *
     * @param mixed $source String contents or a readable stream resource.
     * @return int|false Bytes written, or false when the operation is incomplete.
     */
    public function writeBounded(string $path, mixed $source, int $maxBytes): int|false;

    public function isWritable(string $path): bool;

    public function chmod(string $path, int $mode): bool;

    public function delete(string $path): bool;
}

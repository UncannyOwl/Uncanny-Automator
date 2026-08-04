<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\SourcePackage\SourcePackageValidationException;
use UncannyPageBuilder\Application\SourcePackage\PageSourceImage;
use UncannyPageBuilder\Application\SourcePackage\UploadedPageSource;
use UncannyPageBuilder\Domain\SourcePackage\PageSourcePackage;

/**
 * Reads legacy JSON packages and portable ZIP archives from admin forms.
 *
 * ZIP entries are inspected before image data is streamed to temporary files.
 * The reader keeps traversal paths, symlinks, and undeclared files outside the
 * upload directory.
 */
final class SourcePackageUploadReader
{
    private const MAX_JSON_BYTES = PageSourcePackage::MAX_PAGE_SOURCE_BYTES;
    private const MAX_ARCHIVE_BYTES = PageSourcePackage::MAX_ARCHIVE_BYTES;
    private const MAX_ARCHIVE_ENTRIES = 110;
    private const MAX_MANIFEST_BYTES = PageSourcePackage::MAX_MANIFEST_BYTES;
    private const MAX_IMAGE_BYTES = PageSourcePackage::MAX_IMAGE_BYTES;
    private const MAX_TOTAL_UNCOMPRESSED_BYTES = PageSourcePackage::MAX_PAGE_SOURCE_BYTES
        + PageSourcePackage::MAX_TOTAL_IMAGE_BYTES
        + self::MAX_MANIFEST_BYTES;
    private const MAX_IMAGES = PageSourcePackage::MAX_IMAGES;
    private const MAX_COMPRESSION_RATIO = 200;
    private const INVALID_ARCHIVE_MESSAGE = 'This ZIP is not a valid Page Builder package. Export the page again and upload the downloaded ZIP file.';

    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public static function readPageSource(string $fieldName): UploadedPageSource
    {
        $file = self::uploadedFile($fieldName);
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

        if ($extension === 'json') {
            return new UploadedPageSource(
                self::readJsonFile((string) $file['tmp_name'], (int) $file['size']),
            );
        }

        if ($extension !== 'zip') {
            throw new SourcePackageValidationException('Upload the ZIP file downloaded from Page Builder, or a legacy JSON export.');
        }

        $actualArchiveSize = filesize((string) $file['tmp_name']);
        if (
            (int) $file['size'] > self::MAX_ARCHIVE_BYTES
            || !is_int($actualArchiveSize)
            || $actualArchiveSize > self::MAX_ARCHIVE_BYTES
        ) {
            throw new SourcePackageValidationException(
                'This page export is larger than the 100 MB limit. Export a smaller page or ask your site administrator for help.',
            );
        }

        if (!class_exists(\ZipArchive::class)) {
            throw new SourcePackageValidationException(
                'Page import needs ZIP support on this site. Ask your site administrator to enable it, then try again.',
            );
        }

        try {
            return self::readZipArchive((string) $file['tmp_name']);
        } catch (SourcePackageValidationException $e) {
            throw $e->withUserMessage(self::INVALID_ARCHIVE_MESSAGE);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function readJson(string $fieldName): array
    {
        $file = self::uploadedFile($fieldName);
        $name = (string) $file['name'];
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
            throw new SourcePackageValidationException('Upload a .json file.');
        }

        return self::readJsonFile((string) $file['tmp_name'], (int) $file['size']);
    }

    /**
     * @return array{name: string, tmp_name: string, size: int}
     */
    private static function uploadedFile(string $fieldName): array
    {
        $file = $_FILES[$fieldName] ?? null;
        if (!is_array($file)) {
            throw new SourcePackageValidationException('Choose a page source file to import.');
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new SourcePackageValidationException(
                'The upload is larger than this site allows. Use a smaller export or ask your site administrator to increase the upload limit.',
            );
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new SourcePackageValidationException('The file could not be uploaded. Try again.');
        }

        $name = is_string($file['name'] ?? null) ? $file['name'] : '';
        $tmpName = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
        if ($tmpName === '' || !is_readable($tmpName)) {
            throw new SourcePackageValidationException('The uploaded file could not be read.');
        }

        return [
            'name' => $name,
            'tmp_name' => $tmpName,
            'size' => max(0, (int) ($file['size'] ?? 0)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function readJsonFile(string $path, int $declaredSize): array
    {
        if ($declaredSize > self::MAX_JSON_BYTES || filesize($path) > self::MAX_JSON_BYTES) {
            throw new SourcePackageValidationException(
                'This JSON export is larger than the 5 MB limit. Export a smaller page and try again.',
            );
        }

        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            throw new SourcePackageValidationException('The uploaded file could not be read.');
        }

        return self::decodeObject(
            $raw,
            'This file is not a valid Page Builder export. Export the page again and upload the downloaded file.',
        );
    }

    private static function readZipArchive(string $path): UploadedPageSource
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new SourcePackageValidationException('ZIP support is not available on this server.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new SourcePackageValidationException('The file is not a readable ZIP archive.');
        }

        $temporaryFiles = [];
        $completed = false;

        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
                throw new SourcePackageValidationException('The page archive contains an invalid number of files.');
            }

            $entries = self::inspectEntries($zip);
            if (
                !isset($entries['manifest.json'])
                && isset($entries['index.html'], $entries['assets/page.css'])
            ) {
                throw new SourcePackageValidationException(
                    'The page archive is a static HTML export.',
                    userMessage: 'This ZIP is a static HTML export. It cannot be imported as an editable Page Builder page. Choose a ZIP created with Export page.',
                );
            }
            try {
                $manifestRaw = self::readEntry($zip, $entries, 'manifest.json', self::MAX_MANIFEST_BYTES);
                $manifest = self::decodeObject($manifestRaw, self::INVALID_ARCHIVE_MESSAGE);
                $validated = self::validateManifest($manifest);
            } catch (SourcePackageValidationException $e) {
                throw new SourcePackageValidationException(self::INVALID_ARCHIVE_MESSAGE, 0, $e);
            }

            $pageRaw = self::readEntry(
                $zip,
                $entries,
                ZipPageSourceArchiveWriter::PAGE_SOURCE_PATH,
                self::MAX_JSON_BYTES,
            );
            $payload = self::decodeObject($pageRaw, 'The archived page source is not valid JSON.');
            self::validatePageSourceIntegrity($manifest, $pageRaw);

            $declaredPaths = [
                'manifest.json' => true,
                ZipPageSourceArchiveWriter::PAGE_SOURCE_PATH => true,
            ];
            $images = [];
            $totalImageBytes = 0;

            foreach ($validated['images'] as $record) {
                $archivePath = $record['archive_path'];
                $declaredPaths[$archivePath] = true;
                $imagePath = self::extractEntryToTemporaryFile($zip, $entries, $archivePath, self::MAX_IMAGE_BYTES);
                $temporaryFiles[] = $imagePath;
                $actualBytes = filesize($imagePath);
                if (!is_int($actualBytes)) {
                    throw new SourcePackageValidationException('An archived image could not be inspected.');
                }
                $totalImageBytes += $actualBytes;
                if ($totalImageBytes > PageSourcePackage::MAX_TOTAL_IMAGE_BYTES) {
                    throw new SourcePackageValidationException('Archived images must total 100 MB or less.');
                }

                $actualSha256 = hash_file('sha256', $imagePath);
                if (
                    !is_int($actualBytes)
                    || !is_string($actualSha256)
                    || $actualBytes !== $record['bytes']
                    || !hash_equals($record['sha256'], $actualSha256)
                ) {
                    throw new SourcePackageValidationException('An archived image failed its size or checksum validation.');
                }

                $mime = self::mimeType($imagePath);
                $expectedExtension = self::MIME_EXTENSIONS[$mime] ?? null;
                if ($mime !== $record['mime_type'] || $expectedExtension !== $record['extension']) {
                    throw new SourcePackageValidationException('An archived image type does not match its manifest.');
                }

                $images[] = PageSourceImage::fromFile(
                    $record['source_urls'],
                    $mime,
                    $record['extension'],
                    $imagePath,
                    $actualBytes,
                    $actualSha256,
                );
            }

            foreach (array_keys($entries) as $entryPath) {
                if (!isset($declaredPaths[$entryPath])) {
                    throw new SourcePackageValidationException('The page archive contains an undeclared file.');
                }
            }

            $serializedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if (!is_string($serializedPayload)) {
                throw new SourcePackageValidationException('The archived page source could not be inspected.');
            }
            foreach ($images as $image) {
                foreach ($image->sourceUrls() as $sourceUrl) {
                    if (!str_contains($serializedPayload, $sourceUrl)) {
                        throw new SourcePackageValidationException('An archived image URL is not referenced by the page source.');
                    }
                }
            }

            foreach ($temporaryFiles as $temporaryFile) {
                self::deleteAtShutdown($temporaryFile);
            }
            $completed = true;

            return new UploadedPageSource($payload, $images, $validated['warnings'], 'zip');
        } finally {
            $zip->close();
            if (!$completed) {
                foreach ($temporaryFiles as $temporaryFile) {
                    if (is_file($temporaryFile)) {
                        @unlink($temporaryFile);
                    }
                }
            }
        }
    }

    /**
     * @return array<string, array{index: int, size: int, compressed_size: int}>
     */
    private static function inspectEntries(\ZipArchive $zip): array
    {
        $entries = [];
        $caseFolded = [];
        $totalUncompressed = 0;

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat) || !is_string($stat['name'] ?? null)) {
                throw new SourcePackageValidationException('The page archive contains an unreadable entry.');
            }

            $name = $stat['name'];
            if (!self::isSafeEntryPath($name)) {
                throw new SourcePackageValidationException('The page archive contains an unsafe file path.');
            }

            $folded = strtolower($name);
            if (isset($entries[$name]) || isset($caseFolded[$folded])) {
                throw new SourcePackageValidationException('The page archive contains duplicate file paths.');
            }

            $opsys = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
                $fileType = (($attributes >> 16) & 0170000);
                if ($fileType !== 0 && $fileType !== 0100000) {
                    throw new SourcePackageValidationException('The page archive may contain regular files only.');
                }
            }

            if ((int) ($stat['encryption_method'] ?? 0) !== 0) {
                throw new SourcePackageValidationException('Encrypted page archives are not supported.');
            }

            $size = max(0, (int) ($stat['size'] ?? 0));
            $compressedSize = max(0, (int) ($stat['comp_size'] ?? 0));
            $totalUncompressed += $size;
            if ($totalUncompressed > self::MAX_TOTAL_UNCOMPRESSED_BYTES) {
                throw new SourcePackageValidationException('The page archive expands beyond the allowed size.');
            }

            if ($size > 1048576 && $compressedSize > 0 && ($size / $compressedSize) > self::MAX_COMPRESSION_RATIO) {
                throw new SourcePackageValidationException('The page archive contains an unsafe compression ratio.');
            }

            $entries[$name] = [
                'index' => $index,
                'size' => $size,
                'compressed_size' => $compressedSize,
            ];
            $caseFolded[$folded] = true;
        }

        return $entries;
    }

    private static function isSafeEntryPath(string $path): bool
    {
        if ($path === '' || strlen($path) > 255 || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }

        if ($path[0] === '/' || str_ends_with($path, '/')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, array{index: int, size: int, compressed_size: int}> $entries
     */
    private static function readEntry(\ZipArchive $zip, array $entries, string $path, int $maxBytes): string
    {
        $entry = $entries[$path] ?? null;
        if (!is_array($entry)) {
            throw new SourcePackageValidationException("The page archive is missing {$path}.");
        }

        if ($entry['size'] <= 0 || $entry['size'] > $maxBytes) {
            throw new SourcePackageValidationException("The archived {$path} file has an invalid size.");
        }

        $bytes = $zip->getFromIndex($entry['index'], $maxBytes + 1);
        if (!is_string($bytes) || strlen($bytes) !== $entry['size']) {
            throw new SourcePackageValidationException("The archived {$path} file could not be read completely.");
        }

        return $bytes;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private static function validatePageSourceIntegrity(array $manifest, string $pageRaw): void
    {
        $bytes = $manifest['page_source_bytes'] ?? null;
        $sha256 = $manifest['page_source_sha256'] ?? null;
        if (
            !is_int($bytes)
            || $bytes <= 0
            || $bytes > self::MAX_JSON_BYTES
            || !is_string($sha256)
            || !preg_match('/^[a-f0-9]{64}$/', $sha256)
            || $bytes !== strlen($pageRaw)
            || !hash_equals($sha256, hash('sha256', $pageRaw))
        ) {
            throw new SourcePackageValidationException('The archived page source failed its integrity validation.');
        }
    }

    /**
     * @param array<string, array{index: int, size: int, compressed_size: int}> $entries
     */
    private static function extractEntryToTemporaryFile(
        \ZipArchive $zip,
        array $entries,
        string $path,
        int $maxBytes,
    ): string {
        $entry = $entries[$path] ?? null;
        if (!is_array($entry) || $entry['size'] <= 0 || $entry['size'] > $maxBytes) {
            throw new SourcePackageValidationException("The archived {$path} file has an invalid size.");
        }

        $temporaryPath = self::temporaryPath('uncanny-page-builder-source-image-');
        $output = fopen($temporaryPath, 'wb');
        if ($output === false) {
            @unlink($temporaryPath);
            throw new SourcePackageValidationException('The uploaded image could not be prepared for import.');
        }

        $stream = null;
        $written = 0;
        try {
            if (method_exists($zip, 'getStreamIndex')) {
                $stream = $zip->getStreamIndex($entry['index']);
                if (!is_resource($stream)) {
                    throw new SourcePackageValidationException("The archived {$path} file could not be read completely.");
                }

                while (!feof($stream)) {
                    $chunk = fread($stream, 1048576);
                    if ($chunk === false) {
                        throw new SourcePackageValidationException("The archived {$path} file could not be read completely.");
                    }
                    if ($chunk === '') {
                        continue;
                    }
                    if ($written + strlen($chunk) > $maxBytes) {
                        throw new SourcePackageValidationException("The archived {$path} file could not be read completely.");
                    }
                    self::writeChunk($output, $chunk);
                    $written += strlen($chunk);
                }
            } else {
                $bytes = $zip->getFromIndex($entry['index'], $maxBytes + 1);
                if (!is_string($bytes) || strlen($bytes) > $maxBytes) {
                    throw new SourcePackageValidationException("The archived {$path} file could not be read completely.");
                }
                self::writeChunk($output, $bytes);
                $written = strlen($bytes);
            }
        } catch (\Throwable $e) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            fclose($output);
            @unlink($temporaryPath);
            throw $e;
        }

        if (is_resource($stream)) {
            fclose($stream);
        }
        fclose($output);
        if ($written !== $entry['size']) {
            @unlink($temporaryPath);
            throw new SourcePackageValidationException("The archived {$path} file could not be read completely.");
        }

        return $temporaryPath;
    }

    /** @param resource $output */
    private static function writeChunk($output, string $chunk): void
    {
        $offset = 0;
        $length = strlen($chunk);
        while ($offset < $length) {
            $written = fwrite($output, substr($chunk, $offset));
            if ($written === false || $written === 0) {
                throw new SourcePackageValidationException('The uploaded image could not be prepared for import.');
            }
            $offset += $written;
        }
    }

    private static function temporaryPath(string $prefix): string
    {
        $path = function_exists(__NAMESPACE__ . '\\wp_tempnam') || function_exists('wp_tempnam')
            ? wp_tempnam($prefix)
            : tempnam(sys_get_temp_dir(), $prefix);
        if (!is_string($path) || $path === '') {
            throw new SourcePackageValidationException('The uploaded image could not be prepared for import.');
        }

        return $path;
    }

    private static function deleteAtShutdown(string $path): void
    {
        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                @unlink($path);
            }
        });
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{
     *   images: list<array{
     *     archive_path: string,
     *     source_urls: list<string>,
     *     sha256: string,
     *     bytes: int,
     *     mime_type: string,
     *     extension: string
     *   }>,
     *   warnings: list<string>
     * }
     */
    private static function validateManifest(array $manifest): array
    {
        if (
            ($manifest['schema_version'] ?? null) !== ZipPageSourceArchiveWriter::SCHEMA_VERSION
            || ($manifest['package_type'] ?? null) !== 'page'
            || ($manifest['page_source'] ?? null) !== ZipPageSourceArchiveWriter::PAGE_SOURCE_PATH
        ) {
            throw new SourcePackageValidationException('Upload a compatible Uncanny Page Builder page archive.');
        }

        if (
            !array_key_exists('page_source_bytes', $manifest)
            || !is_int($manifest['page_source_bytes'])
            || !array_key_exists('page_source_sha256', $manifest)
            || !is_string($manifest['page_source_sha256'])
        ) {
            throw new SourcePackageValidationException(self::INVALID_ARCHIVE_MESSAGE);
        }

        $compatibility = $manifest['compatibility'] ?? null;
        $features = is_array($compatibility) && is_array($compatibility['features'] ?? null)
            ? array_values($compatibility['features'])
            : null;
        if (
            !is_array($compatibility)
            || ($compatibility['minimum_reader_schema'] ?? null) !== ZipPageSourceArchiveWriter::SCHEMA_VERSION
            || $features !== ['images']
            || ($compatibility['omitted_features'] ?? null) !== ZipPageSourceArchiveWriter::OMITTED_FEATURES
        ) {
            throw new SourcePackageValidationException('The page archive requires unsupported features.');
        }

        $rawImages = $manifest['images'] ?? null;
        if (!is_array($rawImages) || count($rawImages) > self::MAX_IMAGES) {
            throw new SourcePackageValidationException('The page archive image manifest is invalid.');
        }

        $images = [];
        $seenPaths = [];
        $seenUrls = [];
        foreach ($rawImages as $rawImage) {
            if (!is_array($rawImage)) {
                throw new SourcePackageValidationException('The page archive contains an invalid image record.');
            }

            $path = is_string($rawImage['archive_path'] ?? null) ? $rawImage['archive_path'] : '';
            $hash = is_string($rawImage['sha256'] ?? null) ? strtolower($rawImage['sha256']) : '';
            $mime = is_string($rawImage['mime_type'] ?? null) ? strtolower($rawImage['mime_type']) : '';
            $extension = self::MIME_EXTENSIONS[$mime] ?? '';
            $bytes = is_int($rawImage['bytes'] ?? null) ? $rawImage['bytes'] : 0;
            $sourceUrls = $rawImage['source_urls'] ?? null;

            if (
                !preg_match('/^[a-f0-9]{64}$/', $hash)
                || $extension === ''
                || $path !== 'images/' . $hash . '.' . $extension
                || isset($seenPaths[$path])
                || $bytes <= 0
                || $bytes > self::MAX_IMAGE_BYTES
                || !is_array($sourceUrls)
                || $sourceUrls === []
                || count($sourceUrls) > PageSourceImage::MAX_SOURCE_URLS
            ) {
                throw new SourcePackageValidationException('The page archive contains an invalid image record.');
            }

            $urls = [];
            foreach ($sourceUrls as $sourceUrl) {
                if (
                    !is_string($sourceUrl)
                    || strlen($sourceUrl) > 2048
                    || preg_match('/[\x00-\x1F\x7F]/', $sourceUrl)
                    || !preg_match('~^(?:https?:)?//|^/~i', $sourceUrl)
                    || isset($seenUrls[$sourceUrl])
                ) {
                    throw new SourcePackageValidationException('The page archive contains an invalid image URL alias.');
                }
                $urls[] = $sourceUrl;
                $seenUrls[$sourceUrl] = true;
            }

            $seenPaths[$path] = true;
            $images[] = [
                'archive_path' => $path,
                'source_urls' => $urls,
                'sha256' => $hash,
                'bytes' => $bytes,
                'mime_type' => $mime,
                'extension' => $extension,
            ];
        }

        $warnings = [];
        if (isset($manifest['warnings'])) {
            if (!is_array($manifest['warnings']) || count($manifest['warnings']) > 20) {
                throw new SourcePackageValidationException('The page archive warnings are invalid.');
            }
            foreach ($manifest['warnings'] as $warning) {
                if (!is_string($warning) || strlen($warning) > 500) {
                    throw new SourcePackageValidationException('The page archive warnings are invalid.');
                }
                $warnings[] = $warning;
            }
        }

        return ['images' => $images, 'warnings' => $warnings];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeObject(string $raw, string $invalidMessage): array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new SourcePackageValidationException($invalidMessage);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new SourcePackageValidationException('The JSON file must contain a source package object.');
        }

        return $decoded;
    }

    private static function mimeType(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return is_string($mime) ? strtolower($mime) : '';
    }
}

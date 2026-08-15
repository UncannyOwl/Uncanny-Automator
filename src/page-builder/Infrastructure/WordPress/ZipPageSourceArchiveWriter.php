<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

// phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup targets only the temporary archive owned by the failed export.
// Archive verification hashes native ZIP streams without loading images into memory.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fread
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose

use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveArtifact;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveWriterInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceImage;
use UncannyPageBuilder\Domain\SourcePackage\PageSourcePackage;
use UncannyPageBuilder\Domain\SourcePackage\SourcePackageValidationException;

/**
 * Writes the portable page archive with an explicit, hash-addressed manifest.
 */
final class ZipPageSourceArchiveWriter implements PageSourceArchiveWriterInterface
{
    public const SCHEMA_VERSION = 'upb.page_archive.v2';
    public const PAGE_SOURCE_PATH = 'page.json';
    public const OMITTED_FEATURES = [
        'reusable_parts',
        'site_design_settings',
        'dynamic_data_sources',
        'internal_link_remapping',
    ];

    public function write(int $pageId, array $pageSource, array $images, array $warnings): PageSourceArchiveArtifact
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new SourcePackageValidationException(
                'Page export needs ZIP support on this site. Ask your site administrator to enable it, then try again.',
            );
        }

        $tempPath = function_exists('wp_tempnam')
            ? \wp_tempnam('uncanny-page-builder-page-source-' . $pageId . '.zip')
            : tempnam(
                function_exists('get_temp_dir') ? \get_temp_dir() : sys_get_temp_dir(),
                'uncanny-page-builder-page-source-' . $pageId . '-',
            );
        if (!is_string($tempPath) || $tempPath === '') {
            throw new \RuntimeException('Could not create a temporary page archive.');
        }

        $zip = null;
        try {
            if (count($images) > PageSourcePackage::MAX_IMAGES) {
                throw new SourcePackageValidationException(
                    'A page export can contain at most 100 images. Remove some images and try again.',
                );
            }

            $manifestImages = [];
            $totalImageBytes = 0;
            foreach ($images as $image) {
                if (!$image instanceof PageSourceImage) {
                    throw new \InvalidArgumentException('The page archive contains an invalid image value.');
                }

                $totalImageBytes += $image->byteCount();
                if ($totalImageBytes > PageSourcePackage::MAX_TOTAL_IMAGE_BYTES) {
                    throw new SourcePackageValidationException(
                        'The images in this page export exceed 100 MB. Remove some images and try again.',
                    );
                }

                $manifestImages[] = [
                    'archive_path' => $image->archivePath(),
                    'source_urls'  => $image->sourceUrls(),
                    'sha256'       => $image->sha256(),
                    'bytes'        => $image->byteCount(),
                    'mime_type'    => $image->mimeType(),
                ];
            }

            $pageSourceJson = $this->encodeJson($pageSource, 'Could not encode the page source.');
            if (strlen($pageSourceJson) > PageSourcePackage::MAX_PAGE_SOURCE_BYTES) {
                throw new SourcePackageValidationException(
                    'The page source must be 5 MB or smaller. Remove some source content and export again.',
                );
            }

            $manifest = [
                'schema_version' => self::SCHEMA_VERSION,
                'package_type'   => 'page',
                'producer'       => [
                    'name'    => 'Uncanny Page Builder',
                    'version' => defined('UNCANNY_PB_VERSION') ? (string) UNCANNY_PB_VERSION : 'unknown',
                ],
                'compatibility' => [
                    'minimum_reader_schema' => self::SCHEMA_VERSION,
                    'features' => ['images'],
                    'omitted_features' => self::OMITTED_FEATURES,
                ],
                'page_source' => self::PAGE_SOURCE_PATH,
                'page_source_bytes' => strlen($pageSourceJson),
                'page_source_sha256' => hash('sha256', $pageSourceJson),
                'images'      => $manifestImages,
                'warnings'    => array_values(array_unique([
                    ...$warnings,
                    'Reusable parts, site-wide design settings, dynamic data sources, and internal links are not bundled in this image-only archive.',
                ])),
            ];

            $manifestJson = $this->encodeJson($manifest, 'Could not encode the page archive manifest.');
            if (strlen($manifestJson) > PageSourcePackage::MAX_MANIFEST_BYTES) {
                throw new SourcePackageValidationException(
                    'The page export manifest is too large. Remove duplicate image references and try again.',
                );
            }
            $zip = new \ZipArchive();
            if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not open the temporary page archive.');
            }

            $this->addBytes($zip, 'manifest.json', $manifestJson);
            $this->addBytes($zip, self::PAGE_SOURCE_PATH, $pageSourceJson);
            foreach ($images as $image) {
                $this->addImage($zip, $image);
            }

            if (!$zip->close()) {
                throw new \RuntimeException('Could not finalize the page archive.');
            }
            $zip = null;
            clearstatcache(true, $tempPath);
            $archiveBytes = filesize($tempPath);
            if (!is_int($archiveBytes) || $archiveBytes <= 0) {
                throw new \RuntimeException('Could not inspect the completed page archive.');
            }
            if ($archiveBytes > PageSourcePackage::MAX_ARCHIVE_BYTES) {
                throw new SourcePackageValidationException(
                    'The complete page export exceeds 100 MB. Remove some images or source content and try again.',
                );
            }
            $this->verifyArchive($tempPath, $manifest, $manifestJson, $pageSourceJson);
        } catch (\Throwable $e) {
            if ($zip instanceof \ZipArchive) {
                $zip->close();
            }
            @unlink($tempPath);
            throw $e;
        }

        return new PageSourceArchiveArtifact(
            $tempPath,
            'uncanny-page-builder-page-source-' . $pageId . '.zip',
        );
    }

    /** @param array<string, mixed> $value */
    private function encodeJson(array $value, string $errorMessage): string
    {
        try {
            if (function_exists('wp_json_encode')) {
                return wp_json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone archive tests run without WordPress functions.
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new \RuntimeException($errorMessage, 0, $e);
        }
    }

    private function addBytes(\ZipArchive $zip, string $path, string $bytes): void
    {
        if (!$zip->addFromString($path, $bytes)) {
            throw new \RuntimeException('Could not add a file to the page archive.');
        }

        if (!$zip->setCompressionName($path, \ZipArchive::CM_STORE)) {
            throw new \RuntimeException('Could not configure a file in the page archive.');
        }

        $zip->setExternalAttributesName($path, \ZipArchive::OPSYS_UNIX, 0100644 << 16);
    }

    private function addImage(\ZipArchive $zip, PageSourceImage $image): void
    {
        $path = $image->filePath();
        if ($path !== null) {
            if (!is_readable($path) || !is_file($path)) {
                throw new \RuntimeException('The page archive image file is not readable.');
            }

            if (!$zip->addFile($path, $image->archivePath())) {
                throw new \RuntimeException('Could not add an image to the page archive.');
            }
            if (!$zip->setCompressionName($image->archivePath(), \ZipArchive::CM_STORE)) {
                throw new \RuntimeException('Could not configure an image in the page archive.');
            }
            $zip->setExternalAttributesName($image->archivePath(), \ZipArchive::OPSYS_UNIX, 0100644 << 16);

            return;
        }

        $this->addBytes($zip, $image->archivePath(), $image->bytes());
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function verifyArchive(
        string $path,
        array $manifest,
        string $manifestJson,
        string $pageSourceJson,
    ): void {
        $zip = new \ZipArchive();
        $flags = \ZipArchive::RDONLY;
        if (defined('\\ZipArchive::CHECKCONS')) {
            $flags |= \ZipArchive::CHECKCONS;
        }
        if ($zip->open($path, $flags) !== true) {
            throw new \RuntimeException('Could not reopen the page archive for validation.');
        }

        try {
            if ($zip->numFiles !== count($manifest['images']) + 2) {
                throw new \RuntimeException('The page archive contains an unexpected number of files.');
            }

            $storedManifest = $zip->getFromName('manifest.json');
            if (!is_string($storedManifest) || !hash_equals($manifestJson, $storedManifest)) {
                throw new \RuntimeException('The page archive manifest failed its integrity check.');
            }

            $storedPageSource = $zip->getFromName(self::PAGE_SOURCE_PATH);
            if (
                !is_string($storedPageSource)
                || !hash_equals($pageSourceJson, $storedPageSource)
                || strlen($storedPageSource) !== (int) $manifest['page_source_bytes']
                || !hash_equals((string) $manifest['page_source_sha256'], hash('sha256', $storedPageSource))
            ) {
                throw new \RuntimeException('The page archive source failed its integrity check.');
            }

            foreach ($manifest['images'] as $image) {
                $this->verifyImage($zip, $image['archive_path'], $image['bytes'], $image['sha256']);
            }
        } finally {
            $zip->close();
        }
    }

    private function verifyImage(object $zip, string $path, int $expectedBytes, string $expectedSha256): void
    {
        $stream = method_exists($zip, 'getStreamName')
            ? $zip->getStreamName($path)
            : $zip->getStream($path);
        if (!is_resource($stream)) {
            throw new \RuntimeException('The page archive image could not be read for validation.');
        }

        $hash = hash_init('sha256');
        $bytes = 0;
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 1048576);
                if ($chunk === false) {
                    throw new \RuntimeException('The page archive image could not be read for validation.');
                }
                if ($chunk === '') {
                    continue;
                }
                $bytes += strlen($chunk);
                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }

        if ($bytes !== $expectedBytes || !hash_equals($expectedSha256, hash_final($hash))) {
            throw new \RuntimeException('The page archive image failed its integrity check.');
        }
    }
}

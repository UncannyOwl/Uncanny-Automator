<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\SourcePackage\PageSourceImageCollection;
use UncannyPageBuilder\Application\SourcePackage\PageSourceImageCollectorInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceImage;
use UncannyPageBuilder\Domain\SourcePackage\PageSourcePackage;
use UncannyPageBuilder\Domain\SourcePackage\SourcePackageValidationException;

/**
 * Collects local WordPress upload images referenced by Page Builder source.
 *
 * Remote images are deliberately not fetched: archive export must not become
 * an SSRF surface or depend on another server being available.
 */
final class WordPressPageSourceImageCollector implements PageSourceImageCollectorInterface
{
    /** @var array<string, true> */
    private const IMAGE_EXTENSIONS = [
        'jpg'  => true,
        'jpeg' => true,
        'png'  => true,
        'gif'  => true,
        'webp' => true,
        'avif' => true,
    ];

    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public function collect(array $pageSource): PageSourceImageCollection
    {
        $uploads = wp_upload_dir();
        $baseUrl = is_array($uploads) && is_string($uploads['baseurl'] ?? null)
            ? rtrim($uploads['baseurl'], '/')
            : '';
        $baseDir = is_array($uploads) && is_string($uploads['basedir'] ?? null)
            ? rtrim($uploads['basedir'], DIRECTORY_SEPARATOR)
            : '';

        if ($baseUrl === '' || $baseDir === '') {
            throw new SourcePackageValidationException(
                'Page export needs the WordPress uploads folder. Ask your site administrator to check it, then try again.',
            );
        }

        $realBaseDir = realpath($baseDir);
        if (!is_string($realBaseDir) || !is_dir($realBaseDir)) {
            throw new SourcePackageValidationException(
                'Page export cannot read the WordPress uploads folder. Ask your site administrator to check its access, then try again.',
            );
        }

        // The archive manifest must record a MIME type detected from the file
        // bytes. A filename-based fallback can create an archive that no site
        // can import when the file extension does not match its content.
        if (!class_exists(\finfo::class)) {
            throw new SourcePackageValidationException(
                'Page export needs Fileinfo support on this site. Ask your site administrator to enable it, then try again.',
            );
        }

        $candidates = [];
        $this->collectCandidateUrls($pageSource, $candidates);

        /** @var array<string, PageSourceImage> $imagesByHash */
        $imagesByHash = [];
        $aliasesByHash = [];
        $externalCount = 0;
        $totalBytes = 0;

        foreach (array_keys($candidates) as $sourceUrl) {
            $extension = $this->imageExtension($sourceUrl);
            if ($extension === null) {
                continue;
            }

            $localPath = $this->localUploadPath($sourceUrl, $baseUrl, $realBaseDir);
            if ($localPath === null) {
                ++$externalCount;
                continue;
            }

            if ($extension === 'avif' && !$this->supportsAvif()) {
                ++$externalCount;
                continue;
            }

            if (is_link($localPath) || !is_file($localPath) || !is_readable($localPath)) {
                throw new SourcePackageValidationException(
                    'Page export cannot read one of the referenced images. Remove that image or ask your site administrator to check its file.',
                );
            }

            $size = filesize($localPath);
            if (!is_int($size) || $size <= 0 || $size > PageSourcePackage::MAX_IMAGE_BYTES) {
                throw new SourcePackageValidationException(
                    'One image is larger than the 20 MB limit. Replace it with a smaller image and try again.',
                );
            }

            $mimeType = $this->mimeType($localPath);
            $extension = self::MIME_EXTENSIONS[$mimeType] ?? null;
            if (!is_string($extension)) {
                throw new SourcePackageValidationException(
                    'Page export supports JPEG, PNG, GIF, WebP, and AVIF images. Replace the unsupported image and try again.',
                );
            }

            $hash = hash_file('sha256', $localPath);
            if (!is_string($hash)) {
                throw new SourcePackageValidationException(
                    'Page export cannot read one of the referenced images. Remove that image or ask your site administrator to check its file.',
                );
            }

            $aliasesByHash[$hash] ??= [];
            $aliasesByHash[$hash][] = $sourceUrl;

            if (!isset($imagesByHash[$hash])) {
                $totalBytes += $size;
                if ($totalBytes > PageSourcePackage::MAX_TOTAL_IMAGE_BYTES) {
                    throw new SourcePackageValidationException(
                        'The images in this page export exceed 100 MB. Remove some images and try again.',
                    );
                }
                if (count($imagesByHash) >= PageSourcePackage::MAX_IMAGES) {
                    throw new SourcePackageValidationException(
                        'A page export can contain at most 100 images. Remove some images and try again.',
                    );
                }

                $imagesByHash[$hash] = PageSourceImage::fromFile(
                    [$sourceUrl],
                    $mimeType,
                    $extension,
                    $localPath,
                    $size,
                    $hash,
                );
            }
        }

        $images = [];
        foreach ($imagesByHash as $hash => $image) {
            $aliases = array_values(array_unique($aliasesByHash[$hash]));
            if (count($aliases) > PageSourceImage::MAX_SOURCE_URLS) {
                throw new SourcePackageValidationException(
                    'One image has more than 50 source references. Remove duplicate image references and try again.',
                );
            }

            $images[] = $image->withSourceUrls($aliases);
        }

        $warnings = $externalCount > 0
            ? [sprintf(
                '%d external image reference(s) were preserved as URLs because only local WordPress uploads are embedded.',
                $externalCount,
            )]
            : [];

        return new PageSourceImageCollection($images, $warnings);
    }

    /**
     * @param array<mixed> $values
     * @param array<string, true> $urls
     */
    private function collectCandidateUrls(array $values, array &$urls): void
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $this->collectCandidateUrls($value, $urls);
                continue;
            }

            if (!is_string($value) || stripos($value, 'image') === false && !preg_match('/\.(?:jpe?g|png|gif|webp|avif)(?:[?#]|[\s"\')]|$)/i', $value)) {
                continue;
            }

            preg_match_all(
                '#(?:https?:)?//[^\s"\'<>()]+|/[A-Za-z0-9%_./~+\-]+\.(?:jpe?g|png|gif|webp|avif)(?:\?[^\s"\'<>()]*)?#i',
                $value,
                $matches,
            );

            foreach ($matches[0] ?? [] as $candidate) {
                $candidate = rtrim((string) $candidate, ',;');
                if ($candidate !== '') {
                    $urls[$candidate] = true;
                }
            }
        }
    }

    private function localUploadPath(string $sourceUrl, string $baseUrl, string $realBaseDir): ?string
    {
        $base = wp_parse_url($baseUrl);
        if (!is_array($base) || !is_string($base['host'] ?? null)) {
            return null;
        }

        $normalized = html_entity_decode($sourceUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($normalized, '//')) {
            $normalized = (is_string($base['scheme'] ?? null) ? $base['scheme'] : 'https') . ':' . $normalized;
        } elseif (str_starts_with($normalized, '/')) {
            $port = isset($base['port']) ? ':' . (int) $base['port'] : '';
            $normalized = (is_string($base['scheme'] ?? null) ? $base['scheme'] : 'https')
                . '://' . $base['host'] . $port . $normalized;
        }

        $url = wp_parse_url($normalized);
        if (
            !is_array($url)
            || strcasecmp((string) ($url['host'] ?? ''), (string) $base['host']) !== 0
            || (int) ($url['port'] ?? 0) !== (int) ($base['port'] ?? 0)
        ) {
            return null;
        }

        $basePath = rtrim((string) ($base['path'] ?? ''), '/');
        $urlPath = rawurldecode((string) ($url['path'] ?? ''));
        if ($basePath === '' || ($urlPath !== $basePath && !str_starts_with($urlPath, $basePath . '/'))) {
            return null;
        }

        $relative = ltrim(substr($urlPath, strlen($basePath)), '/');
        if ($relative === '' || str_contains($relative, "\0")) {
            return null;
        }

        foreach (explode('/', str_replace('\\', '/', $relative)) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        $candidate = $realBaseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $realPath = realpath($candidate);
        if (!is_string($realPath) || !str_starts_with($realPath, $realBaseDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }

    private function imageExtension(string $sourceUrl): ?string
    {
        $normalized = html_entity_decode($sourceUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $path = wp_parse_url($normalized, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return isset(self::IMAGE_EXTENSIONS[$extension]) ? $extension : null;
    }

    private function supportsAvif(): bool
    {
        if (!function_exists(__NAMESPACE__ . '\\wp_check_filetype') && !function_exists('wp_check_filetype')) {
            return false;
        }

        $fileType = wp_check_filetype('page-builder-image.avif');

        return is_array($fileType) && ($fileType['type'] ?? null) === 'image/avif';
    }

    private function mimeType(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return is_string($mime) ? strtolower($mime) : '';
    }
}

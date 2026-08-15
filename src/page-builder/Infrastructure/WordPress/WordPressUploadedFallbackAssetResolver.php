<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

// Content-addressed publication requires native locks, streams, and permission operations unavailable through WP_Filesystem.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fread
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_chmod

use UncannyPageBuilder\Application\Publishing\PageDeactivationFallbackAssetResolverInterface;
use UncannyPageBuilder\Application\Rendering\PublicRuntimeAssetCatalog;
use UncannyPageBuilder\Application\Rendering\PublishedPageAssets;
use UncannyPageBuilder\Application\Rendering\PublishedPageRuntimeUnavailable;
use UncannyPageBuilder\Domain\Publishing\PageDeactivationFallback;
use UncannyPageBuilder\Infrastructure\Rendering\PublishedPageAssetResolver;

/**
 * Copies approved fallback runtimes to immutable WordPress upload files.
 */
final class WordPressUploadedFallbackAssetResolver implements PageDeactivationFallbackAssetResolverInterface
{
    private const PRODUCT_DIRECTORY = 'uncanny-page-builder/fallback-assets/v1';
    private const LOCK_FILENAME = '.materialize.lock';
    private const LOCK_TIMEOUT_MICROSECONDS = 5_000_000;
    private const LOCK_RETRY_MICROSECONDS = 50_000;

    private ?object $filesystem = null;

    /**
     * @param null|\Closure(): array<string, mixed> $uploadsReader
     * @param null|\Closure(string): bool $directoryCreator
     * @param null|\Closure(resource, string): int|false $streamWriter
     * @param null|\Closure(string, string): bool $fileLinker
     * @param null|\Closure(): object $filesystemFactory
     * @param null|\Closure(string, int): bool $permissionApplier
     * @param null|\Closure(): string $homeUrlReader
     * @param null|\Closure(string, string, string): bool $externalBaseUrlPolicy
     * @param null|\Closure(resource, int): bool $lockAcquirer
     * @param null|\Closure(int): void $sleeper
     */
    public function __construct(
        private readonly PublishedPageAssetResolver $pluginAssets,
        private readonly string $pluginPath,
        private readonly ?\Closure $uploadsReader = null,
        private readonly ?\Closure $directoryCreator = null,
        private readonly ?\Closure $streamWriter = null,
        private readonly ?\Closure $fileLinker = null,
        private readonly ?\Closure $filesystemFactory = null,
        private readonly ?\Closure $permissionApplier = null,
        private readonly ?\Closure $homeUrlReader = null,
        private readonly ?\Closure $externalBaseUrlPolicy = null,
        private readonly ?\Closure $lockAcquirer = null,
        private readonly ?\Closure $sleeper = null,
    ) {}

    public function resolveFallback(PageDeactivationFallback $fallback): PublishedPageAssets
    {
        try {
            $pluginAssets = $this->pluginAssets->resolveFallback($fallback);
        } catch (PublishedPageRuntimeUnavailable $failure) {
            throw $this->failure(
                'fallback_asset_source_invalid',
                'The fallback asset source cannot be resolved.',
                $failure,
            );
        }
        [$uploadsUrl, $productRoot] = $this->prepareUploadRoot();
        $lock = $this->acquireLock($productRoot);

        try {
            $assets = [];
            foreach ($pluginAssets->all() as $name => $asset) {
                $assets[$name] = $this->materialize(
                    $name,
                    $asset,
                    $uploadsUrl,
                    $productRoot,
                );
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return new PublishedPageAssets(
            assets: $assets,
            googleFonts: $pluginAssets->googleFonts(),
            customFonts: $pluginAssets->customFonts(),
        );
    }

    /** @return array{0: string, 1: string} */
    private function prepareUploadRoot(): array
    {
        try {
            $uploads = $this->uploadsReader instanceof \Closure
                ? ($this->uploadsReader)()
                : wp_upload_dir(null, false);
        } catch (\Throwable $failure) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The WordPress uploads directory is unavailable.',
                $failure,
            );
        }

        $error = is_array($uploads) ? trim((string) ($uploads['error'] ?? '')) : '';
        $baseDirectory = is_array($uploads) ? (string) ($uploads['basedir'] ?? '') : '';
        $baseUrl = is_array($uploads) ? (string) ($uploads['baseurl'] ?? '') : '';

        if (
            $error !== ''
            || !$this->isSafeLocalPath($baseDirectory)
            || !$this->isPublicUrl($baseUrl)
            || is_link($baseDirectory)
        ) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The WordPress uploads directory is invalid.',
            );
        }

        $homeUrl = $this->homeUrl();
        if (!$this->sameUrlHost($baseUrl, $homeUrl) && !$this->allowsExternalBaseUrl($baseUrl, $baseDirectory, $homeUrl)) {
            throw $this->failure(
                'fallback_upload_url_unverified',
                'The WordPress uploads URL is not verified to serve fallback asset files.',
            );
        }

        $filesystem = $this->filesystem();
        if (!$filesystem->is_dir($baseDirectory) && !$this->createDirectory($baseDirectory)) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The WordPress uploads directory cannot be created.',
            );
        }

        $uploadsRoot = realpath($baseDirectory);
        if (!is_string($uploadsRoot) || !$this->isSafeLocalPath($uploadsRoot)) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The WordPress uploads directory cannot be verified.',
            );
        }

        $productRoot = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::PRODUCT_DIRECTORY);
        if (!$filesystem->is_dir($productRoot) && !$this->createDirectory($productRoot)) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The fallback asset directory cannot be created.',
            );
        }

        $realProductRoot = realpath($productRoot);
        if (
            !is_string($realProductRoot)
            || is_link($productRoot)
            || !$this->isPathInside($realProductRoot, $uploadsRoot)
            || $this->pathContainsSymlink($uploadsRoot, $productRoot)
        ) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The fallback asset directory cannot be verified.',
            );
        }

        $directoryMode = $this->directoryMode($uploadsRoot);
        $this->applyPermissions(
            $realProductRoot,
            $directoryMode,
            $directoryMode & 0555,
            'fallback_upload_dir_invalid',
        );

        return [
            rtrim($baseUrl, '/'),
            $realProductRoot,
        ];
    }

    /** @return resource */
    private function acquireLock(string $productRoot)
    {
        $lockPath = $productRoot . DIRECTORY_SEPARATOR . self::LOCK_FILENAME;
        if (is_link($lockPath) || is_dir($lockPath)) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The fallback asset lock is invalid.',
            );
        }

        $lock = @fopen($lockPath, 'c+b');
        if (!is_resource($lock)) {
            throw $this->failure(
                'fallback_asset_write_failed',
                'The fallback asset lock cannot be opened.',
            );
        }

        $stat = fstat($lock);
        if (
            is_link($lockPath)
            || !is_array($stat)
            || !is_int($stat['mode'] ?? null)
            || (($stat['mode'] & 0170000) !== 0100000)
        ) {
            fclose($lock);
            throw $this->failure(
                'fallback_asset_write_failed',
                'The fallback asset lock cannot be verified.',
            );
        }

        try {
            $acquired = $this->acquireExclusiveLock($lock);
        } catch (\Throwable $failure) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- WP_Filesystem has no advisory-lock stream to close.
            fclose($lock);
            throw $this->failure(
                'fallback_asset_write_failed',
                'The fallback asset lock cannot be checked.',
                $failure,
            );
        }

        if (!$acquired) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- WP_Filesystem has no advisory-lock stream to close.
            fclose($lock);
            throw $this->failure(
                'fallback_asset_lock_timeout',
                'The fallback asset lock wait timed out.',
            );
        }

        return $lock;
    }

    /** @param resource $lock */
    private function acquireExclusiveLock($lock): bool
    {
        $waitedMicroseconds = 0;

        while (true) {
            $acquired = $this->lockAcquirer instanceof \Closure
                ? ($this->lockAcquirer)($lock, LOCK_EX | LOCK_NB)
                : @flock($lock, LOCK_EX | LOCK_NB);

            if ($acquired) {
                return true;
            }

            if ($waitedMicroseconds >= self::LOCK_TIMEOUT_MICROSECONDS) {
                return false;
            }

            $pause = min(
                self::LOCK_RETRY_MICROSECONDS,
                self::LOCK_TIMEOUT_MICROSECONDS - $waitedMicroseconds,
            );

            if ($this->sleeper instanceof \Closure) {
                ($this->sleeper)($pause);
            } else {
                usleep($pause);
            }

            $waitedMicroseconds += $pause;
        }
    }

    /**
     * @param array{name: string, kind: string, path: string, url: string, sha256: string, reference: string} $asset
     * @return array{name: string, kind: string, path: string, url: string, sha256: string, reference: string}
     */
    private function materialize(
        string $name,
        array $asset,
        string $uploadsUrl,
        string $productRoot,
    ): array {
        $specification = PublicRuntimeAssetCatalog::get($name);
        if ($specification === null || $asset['name'] !== $name) {
            throw $this->failure(
                'fallback_asset_source_invalid',
                'The fallback asset catalog record is invalid.',
            );
        }

        $basename = basename($specification['plugin_path']);
        $expectedExtension = $specification['kind'] === 'style' ? 'css' : 'js';
        if (
            preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $basename) !== 1
            || strtolower((string) pathinfo($basename, PATHINFO_EXTENSION)) !== $expectedExtension
            || $asset['kind'] !== $specification['kind']
            || $asset['reference'] !== $specification['reference']
        ) {
            throw $this->failure(
                'fallback_asset_source_invalid',
                'The fallback asset catalog record is unsafe.',
            );
        }

        $source = $this->approvedSourcePath($specification['plugin_path']);
        $expectedHash = @hash_file('sha256', $source);
        if (!is_string($expectedHash) || !hash_equals($asset['sha256'], $expectedHash)) {
            throw $this->failure(
                'fallback_asset_source_invalid',
                'The fallback asset source cannot be verified.',
            );
        }

        $filesystem = $this->filesystem();
        $directoryMode = $this->directoryMode($productRoot);
        $fileMode = 0600 | ((($directoryMode & 0111) << 2) & 0444);
        $requiredFileMode = (($directoryMode & 0111) << 2) & 0444;

        $hashDirectory = $productRoot . DIRECTORY_SEPARATOR . $expectedHash;
        if (!$filesystem->is_dir($hashDirectory) && !$this->createHashDirectory($hashDirectory)) {
            throw $this->failure(
                'fallback_asset_write_failed',
                'The fallback asset hash directory cannot be created.',
            );
        }

        $realHashDirectory = realpath($hashDirectory);
        if (
            !is_string($realHashDirectory)
            || is_link($hashDirectory)
            || !$this->isPathInside($realHashDirectory, $productRoot)
            || $this->pathContainsSymlink($productRoot, $hashDirectory)
        ) {
            throw $this->failure(
                'fallback_asset_collision',
                'The fallback asset hash directory is invalid.',
            );
        }

        $this->applyPermissions(
            $realHashDirectory,
            $directoryMode,
            $directoryMode & 0555,
            'fallback_asset_write_failed',
        );

        $destination = $realHashDirectory . DIRECTORY_SEPARATOR . $basename;
        if ($filesystem->exists($destination) || is_link($destination)) {
            $this->verifyExistingDestination(
                $destination,
                $expectedHash,
                $realHashDirectory,
                $fileMode,
                $requiredFileMode,
            );
        } else {
            $this->copyWithoutClobber(
                $source,
                $destination,
                $expectedHash,
                $realHashDirectory,
                $fileMode,
                $requiredFileMode,
            );
        }

        $relativePath = self::PRODUCT_DIRECTORY . '/' . $expectedHash . '/' . $basename;

        return [
            'name' => $name,
            'kind' => $specification['kind'],
            'path' => $relativePath,
            'url' => $uploadsUrl . '/' . $relativePath,
            'sha256' => $expectedHash,
            'reference' => $specification['reference'],
        ];
    }

    private function approvedSourcePath(string $relativePath): string
    {
        if (!$this->isSafeLocalPath($this->pluginPath) || is_link($this->pluginPath)) {
            throw $this->failure(
                'fallback_asset_source_invalid',
                'The plugin asset directory is invalid.',
            );
        }

        $pluginRoot = realpath($this->pluginPath);
        $sourceCandidate = is_string($pluginRoot)
            ? $pluginRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath)
            : '';
        $source = realpath($sourceCandidate);
        if (
            !is_string($pluginRoot)
            || !is_string($source)
            || !$this->isPathInside($source, $pluginRoot)
            || $this->pathContainsSymlink($pluginRoot, $sourceCandidate)
            || is_link($source)
            || !$this->filesystem()->is_file($source)
            || !$this->filesystem()->is_readable($source)
        ) {
            throw $this->failure(
                'fallback_asset_source_invalid',
                'The fallback asset source is invalid.',
            );
        }

        return $source;
    }

    private function copyWithoutClobber(
        string $source,
        string $destination,
        string $expectedHash,
        string $destinationDirectory,
        int $fileMode,
        int $requiredFileMode,
    ): void {
        $temporary = $destinationDirectory
            . DIRECTORY_SEPARATOR
            . '.'
            . basename($destination)
            . '.'
            . bin2hex(random_bytes(16))
            . '.tmp';
        $input = null;
        $output = null;

        try {
            $input = @fopen($source, 'rb');
            $output = @fopen($temporary, 'x+b');
            if (!is_resource($input) || !is_resource($output) || is_link($temporary)) {
                throw $this->failure(
                    'fallback_asset_write_failed',
                    'The fallback asset temporary file cannot be opened.',
                );
            }

            $hashContext = hash_init('sha256');
            while (!feof($input)) {
                $chunk = fread($input, 1048576);
                if (!is_string($chunk)) {
                    throw $this->failure(
                        'fallback_asset_write_failed',
                        'The fallback asset source cannot be copied.',
                    );
                }
                if ($chunk === '') {
                    if (feof($input)) {
                        break;
                    }
                    throw $this->failure(
                        'fallback_asset_write_failed',
                        'The fallback asset source read was incomplete.',
                    );
                }

                hash_update($hashContext, $chunk);
                $this->writeAll($output, $chunk);
            }

            if (!fflush($output)) {
                throw $this->failure(
                    'fallback_asset_write_failed',
                    'The fallback asset temporary file cannot be flushed.',
                );
            }
            try {
                if (function_exists('fsync')) {
                    @fsync($output);
                }
            } catch (\Throwable) {
                // Hash checks verify the temporary file and each published destination.
            }

            $streamHash = hash_final($hashContext);
            fclose($input);
            $input = null;
            fclose($output);
            $output = null;

            $this->applyPermissions(
                $temporary,
                $fileMode,
                $requiredFileMode,
                'fallback_asset_write_failed',
            );

            $temporaryHash = @hash_file('sha256', $temporary);
            $sourceHash = @hash_file('sha256', $source);
            if (
                !is_string($temporaryHash)
                || !is_string($sourceHash)
                || !hash_equals($expectedHash, $streamHash)
                || !hash_equals($expectedHash, $temporaryHash)
                || !hash_equals($expectedHash, $sourceHash)
            ) {
                throw $this->failure(
                    'fallback_asset_verify_failed',
                    'The copied fallback asset does not match its source.',
                );
            }

            $linked = $this->fileLinker instanceof \Closure
                ? ($this->fileLinker)($temporary, $destination)
                : @link($temporary, $destination);
            if (!$linked) {
                if ($this->filesystem()->exists($destination) || is_link($destination)) {
                    $this->verifyExistingDestination(
                        $destination,
                        $expectedHash,
                        $destinationDirectory,
                        $fileMode,
                        $requiredFileMode,
                    );
                    return;
                }

                throw $this->failure(
                    'fallback_asset_write_failed',
                    'The fallback asset cannot be published.',
                );
            }

            $this->verifyExistingDestination(
                $destination,
                $expectedHash,
                $destinationDirectory,
                $fileMode,
                $requiredFileMode,
            );
        } catch (PublishedPageRuntimeUnavailable $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw $this->failure(
                'fallback_asset_write_failed',
                'The fallback asset cannot be written.',
                $failure,
            );
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            if ($this->filesystem()->exists($temporary) || is_link($temporary)) {
                $this->filesystem()->delete($temporary, false, 'f');
            }
        }
    }

    /** @param resource $stream */
    private function writeAll($stream, string $content): void
    {
        $offset = 0;
        $length = strlen($content);
        while ($offset < $length) {
            $written = $this->streamWriter instanceof \Closure
                ? ($this->streamWriter)($stream, substr($content, $offset))
                : fwrite($stream, substr($content, $offset));
            if (!is_int($written) || $written <= 0) {
                throw $this->failure(
                    'fallback_asset_write_failed',
                    'The fallback asset write was incomplete.',
                );
            }
            $offset += $written;
        }
    }

    private function verifyExistingDestination(
        string $destination,
        string $expectedHash,
        string $destinationDirectory,
        int $fileMode,
        int $requiredFileMode,
    ): void {
        $realDestination = realpath($destination);
        if (
            is_link($destination)
            || !is_string($realDestination)
            || !$this->isPathInside($realDestination, $destinationDirectory)
            || !$this->filesystem()->is_file($destination)
        ) {
            throw $this->failure(
                'fallback_asset_collision',
                'The fallback asset destination is invalid.',
            );
        }

        $actualHash = @hash_file('sha256', $destination);
        if (!is_string($actualHash)) {
            throw $this->failure(
                'fallback_asset_verify_failed',
                'The fallback asset destination cannot be verified.',
            );
        }
        if (!hash_equals($expectedHash, $actualHash)) {
            throw $this->failure(
                'fallback_asset_collision',
                'The fallback asset destination contains different bytes.',
            );
        }

        $this->applyPermissions(
            $destination,
            $fileMode,
            $requiredFileMode,
            'fallback_asset_verify_failed',
        );
    }

    private function createDirectory(string $directory): bool
    {
        try {
            return $this->directoryCreator instanceof \Closure
                ? ($this->directoryCreator)($directory)
                : wp_mkdir_p($directory);
        } catch (\Throwable $failure) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The fallback asset directory cannot be created.',
                $failure,
            );
        }
    }

    private function createHashDirectory(string $directory): bool
    {
        try {
            $created = $this->directoryCreator instanceof \Closure
                ? ($this->directoryCreator)($directory)
                : wp_mkdir_p($directory);

            return $created || $this->filesystem()->is_dir($directory);
        } catch (\Throwable $failure) {
            throw $this->failure(
                'fallback_asset_write_failed',
                'The fallback asset hash directory cannot be created.',
                $failure,
            );
        }
    }

    private function directoryMode(string $directory): int
    {
        $permissions = @fileperms($directory);
        if (!is_int($permissions)) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The fallback asset directory permissions cannot be read.',
            );
        }

        $mode = $permissions & 07777;
        if (($mode & 0500) !== 0500) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The fallback asset directory permissions are invalid.',
            );
        }

        return $mode;
    }

    private function applyPermissions(
        string $path,
        int $preferredMode,
        int $requiredMode,
        string $reasonCode,
    ): void {
        /*
         * WordPress core uses native chmod() after direct uploads. Keep that
         * behavior here because WP_Filesystem_Direct::chmod() can treat a
         * restrictive file mode as already writable after its compatibility
         * mask is applied.
         */
        clearstatcache(true, $path);
        $permissions = @fileperms($path);
        if (!is_int($permissions)) {
            throw $this->failure($reasonCode, 'The fallback asset permissions cannot be verified.');
        }

        if (($permissions & 07777) !== $preferredMode) {
            try {
                if ($this->permissionApplier instanceof \Closure) {
                    ($this->permissionApplier)($path, $preferredMode);
                } else {
                    @chmod($path, $preferredMode);
                }
            } catch (\Throwable) {
                // A mode difference is acceptable when required access remains.
            }
        }

        clearstatcache(true, $path);
        $permissions = @fileperms($path);
        if (
            !is_int($permissions)
            || (($permissions & $requiredMode) !== $requiredMode)
            || (($permissions & 0170000) === 0100000 && ($permissions & 0022) !== 0)
        ) {
            throw $this->failure($reasonCode, 'The fallback asset permissions cannot be verified.');
        }
    }

    private function filesystem(): object
    {
        if (is_object($this->filesystem)) {
            return $this->filesystem;
        }

        try {
            $filesystem = $this->filesystemFactory instanceof \Closure
                ? ($this->filesystemFactory)()
                : $this->loadWordPressFilesystem();
        } catch (PublishedPageRuntimeUnavailable $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The WordPress filesystem is unavailable.',
                $failure,
            );
        }

        if (!is_object($filesystem)) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The WordPress direct filesystem is unavailable.',
            );
        }

        foreach (['delete', 'exists', 'is_dir', 'is_file', 'is_readable', 'mkdir'] as $method) {
            if (!is_callable([$filesystem, $method])) {
                throw $this->failure(
                    'fallback_upload_dir_invalid',
                    'The WordPress direct filesystem is unavailable.',
                );
            }
        }

        $this->filesystem = $filesystem;

        return $this->filesystem;
    }

    private function loadWordPressFilesystem(): object
    {
        if (!defined('ABSPATH') || !is_string(ABSPATH) || ABSPATH === '') {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The WordPress filesystem bootstrap is unavailable.',
            );
        }

        $includes = rtrim(ABSPATH, '/\\') . '/wp-admin/includes/';
        if (!class_exists('WP_Filesystem_Base', false)) {
            $baseClass = $includes . 'class-wp-filesystem-base.php';
            if (!is_readable($baseClass)) {
                throw $this->failure(
                    'fallback_upload_dir_invalid',
                    'The WordPress filesystem base class is unavailable.',
                );
            }
            require_once $baseClass;
        }

        if (!class_exists('WP_Filesystem_Direct', false)) {
            $directClass = $includes . 'class-wp-filesystem-direct.php';
            if (!is_readable($directClass)) {
                throw $this->failure(
                    'fallback_upload_dir_invalid',
                    'The WordPress direct filesystem class is unavailable.',
                );
            }
            require_once $directClass;
        }

        if (!class_exists('WP_Filesystem_Direct', false) || !class_exists('WP_Error')) {
            throw $this->failure(
                'fallback_upload_dir_invalid',
                'The WordPress direct filesystem class cannot be loaded.',
            );
        }

        return new \WP_Filesystem_Direct(null);
    }

    private function isSafeLocalPath(string $path): bool
    {
        return $path !== ''
            && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1
            && preg_match('#^[A-Za-z][A-Za-z0-9+.-]*://#', $path) !== 1;
    }

    private function isPublicUrl(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return false;
        }

        $parts = self::parseUrl($url);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== '';
    }

    private function homeUrl(): string
    {
        try {
            $homeUrl = $this->homeUrlReader instanceof \Closure
                ? ($this->homeUrlReader)()
                : home_url('/');
        } catch (\Throwable $failure) {
            throw $this->failure(
                'fallback_upload_url_unverified',
                'The WordPress home URL cannot be verified.',
                $failure,
            );
        }

        if (!$this->isPublicUrl($homeUrl)) {
            throw $this->failure(
                'fallback_upload_url_unverified',
                'The WordPress home URL cannot be verified.',
            );
        }

        return $homeUrl;
    }

    private function sameUrlHost(string $firstUrl, string $secondUrl): bool
    {
        $firstHost = self::parseUrl($firstUrl, PHP_URL_HOST);
        $secondHost = self::parseUrl($secondUrl, PHP_URL_HOST);

        return is_string($firstHost)
            && is_string($secondHost)
            && strtolower($firstHost) === strtolower($secondHost);
    }

    private static function parseUrl(string $url, int $component = -1): array|string|int|false|null
    {
        if (function_exists('wp_parse_url')) {
            return wp_parse_url($url, $component);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Standalone resolver tests run without WordPress functions.
        return parse_url($url, $component);
    }

    private function allowsExternalBaseUrl(string $baseUrl, string $baseDirectory, string $homeUrl): bool
    {
        try {
            if ($this->externalBaseUrlPolicy instanceof \Closure) {
                return (bool) ($this->externalBaseUrlPolicy)($baseUrl, $baseDirectory, $homeUrl);
            }

            /**
             * Allow an external uploads URL only when it serves files written directly
             * to the local uploads directory without requiring attachment records.
             *
             * @param bool   $allowed       Whether the external URL is safe for fallback assets.
             * @param string $baseUrl       The filtered WordPress uploads base URL.
             * @param string $baseDirectory The local WordPress uploads base directory.
             * @param string $homeUrl       The current site's home URL.
             */
            return (bool) \apply_filters(
                'uncanny_page_builder_allow_external_fallback_asset_url',
                false,
                $baseUrl,
                $baseDirectory,
                $homeUrl,
            );
        } catch (\Throwable $failure) {
            throw $this->failure(
                'fallback_upload_url_unverified',
                'The external WordPress uploads URL cannot be verified.',
                $failure,
            );
        }
    }

    private function isPathInside(string $path, string $root): bool
    {
        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');

        if (PHP_OS_FAMILY === 'Windows') {
            $normalizedPath = strtolower($normalizedPath);
            $normalizedRoot = strtolower($normalizedRoot);
        }

        return str_starts_with($normalizedPath . '/', $normalizedRoot . '/');
    }

    private function pathContainsSymlink(string $root, string $path): bool
    {
        $relative = ltrim(substr($path, strlen(rtrim($root, '/\\'))), '/\\');
        $current = rtrim($root, '/\\');
        foreach (preg_split('#[/\\\\]+#', $relative) ?: [] as $component) {
            if ($component === '') {
                continue;
            }
            $current .= DIRECTORY_SEPARATOR . $component;
            if (is_link($current)) {
                return true;
            }
        }

        return false;
    }

    private function failure(
        string $reasonCode,
        string $message,
        ?\Throwable $previous = null,
    ): PublishedPageRuntimeUnavailable {
        return new PublishedPageRuntimeUnavailable($reasonCode, $message, $previous);
    }
}

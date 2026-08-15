<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveArtifactStoreInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Streams a prepared page archive only to an authorized page editor.
 */
final class PageSourceArchiveDownloadAction
{
    public const ACTION = 'uncanny_page_builder_export_page_archive';

    public function __construct(
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly SectionRepositoryInterface $pages,
        private readonly PageSourceArchiveArtifactStoreInterface $artifacts,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    public static function nonceAction(int $pageId): string
    {
        return self::ACTION . '_' . $pageId;
    }

    public function handle(): void
    {
        $pageId = absint($_GET['page_id'] ?? 0);
        check_admin_referer(self::nonceAction($pageId));
        $postType = $pageId > 0 ? get_post_type($pageId) : null;

        $isAuthorized = (bool) WordPressCallbackBoundary::valueOrDie(
            'page.export.authorize',
            fn (): bool => $pageId > 0
                && is_string($postType)
                && $this->supportsPostType->isSupported($postType)
                && $this->allowedCapabilities->currentUserHasAllowedCapability()
                && current_user_can('edit_post', $pageId)
                && $this->pages->isOwnedPage($pageId),
        );
        if (!$isAuthorized) {
            wp_die(
                esc_html_x("You don't have permission to export this Page Builder page.", 'Page Builder', 'uncanny-automator'),
                403,
            );
        }

        $token = is_string($_GET['artifact'] ?? null) ? (string) $_GET['artifact'] : '';
        $artifact = WordPressCallbackBoundary::valueOrDie(
            'page.export.artifact',
            fn () => $this->artifacts->take($pageId, $token),
        );
        if ($artifact === null) {
            wp_die(
                esc_html_x('This page export has expired. Export the page again and try again.', 'Page Builder', 'uncanny-automator'),
                404,
            );
        }

        $path = $artifact->path();
        $responseStarted = false;
        $downloadError = false;
        try {
            if (!is_readable($path)) {
                throw new \RuntimeException('The prepared page archive is not readable.');
            }
            clearstatcache(true, $path);
            $size = @filesize($path);
            if (!is_int($size) || $size <= 0) {
                throw new \RuntimeException('The prepared page archive size is invalid.');
            }

            while (ob_get_level() > 0) {
                if (!ob_end_clean()) {
                    throw new \RuntimeException('The page archive response buffer could not be cleared.');
                }
            }
            if (headers_sent()) {
                throw new \RuntimeException('The page archive response already sent output.');
            }

            // phpcs:ignore WordPress.PHP.IniSet.Risky -- ZIP downloads require identity encoding for Content-Length.
            @ini_set('zlib.output_compression', '0');
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            nocache_headers();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $artifact->filename()) . '"');
            header('Content-Length: ' . (string) $size);
            header('Content-Encoding: identity');
            header('X-Content-Type-Options: nosniff');
            $responseStarted = true;
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Stream the prepared local ZIP without loading it into PHP memory.
            $read = @readfile($path);
            if ($read !== $size) {
                throw new \RuntimeException('The page archive response ended before all bytes were sent.');
            }
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[Uncanny Page Builder] Page archive download failed for page %d: %s: %s',
                $pageId,
                $e::class,
                $e->getMessage(),
            ));
            $downloadError = !$responseStarted && !headers_sent();
        } finally {
            try {
                $this->artifacts->delete($artifact);
            } catch (\Throwable $cleanupFailure) {
                error_log(sprintf(
                    '[Uncanny Page Builder] Page archive cleanup failed for page %d (%s).',
                    $pageId,
                    $cleanupFailure::class,
                ));
            }
        }

        if ($downloadError) {
            wp_die(
                esc_html_x('The page export could not be downloaded. Export the page again and try again.', 'Page Builder', 'uncanny-automator'),
                500,
            );
        }

        exit;
    }
}

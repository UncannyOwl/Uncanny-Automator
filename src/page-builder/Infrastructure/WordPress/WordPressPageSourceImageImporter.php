<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\SourcePackage\PageSourceImageImporterInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceImageImportResult;
use UncannyPageBuilder\Application\SourcePackage\PageSourceImage;

/**
 * Creates WordPress attachments from already-validated archive image bytes.
 */
final class WordPressPageSourceImageImporter implements PageSourceImageImporterInterface
{
    public function import(int $pageId, array $images): PageSourceImageImportResult
    {
        if ($images === []) {
            return new PageSourceImageImportResult([], []);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $urlMap = [];
        $attachmentIds = [];

        try {
            foreach ($images as $image) {
                if (!$image instanceof PageSourceImage) {
                    throw new \InvalidArgumentException('The page archive contains an invalid image value.');
                }

                $filename = 'upb-import-' . substr($image->sha256(), 0, 16) . '.' . $image->extension();
                if ($image->filePath() !== null) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- This is a verified local import file, not a remote URL.
                    $bytes = @file_get_contents($image->filePath());
                } else {
                    $bytes = $image->bytes();
                }
                if (!is_string($bytes) || strlen($bytes) !== $image->byteCount()) {
                    throw new \RuntimeException('WordPress could not read an imported image.');
                }
                if (!hash_equals($image->sha256(), hash('sha256', $bytes))) {
                    throw new \RuntimeException('The imported image changed before it was stored. Upload the page export again.');
                }

                $upload = wp_upload_bits($filename, null, $bytes);
                if (!is_array($upload) || (string) ($upload['error'] ?? '') !== '') {
                    throw new \RuntimeException('WordPress could not store an imported image.');
                }

                $file = is_string($upload['file'] ?? null) ? $upload['file'] : '';
                $attachmentId = wp_insert_attachment([
                    'post_mime_type' => $image->mimeType(),
                    'post_title'     => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                    'post_parent'    => $pageId,
                ], $file, $pageId, true);

                if (is_wp_error($attachmentId) || (int) $attachmentId <= 0) {
                    if ($file !== '' && is_file($file)) {
                        wp_delete_file($file);
                    }
                    throw new \RuntimeException('WordPress could not register an imported image.');
                }

                $attachmentId = (int) $attachmentId;
                $attachmentIds[] = $attachmentId;
                $metadata = wp_generate_attachment_metadata($attachmentId, $file);
                if (is_array($metadata)) {
                    wp_update_attachment_metadata($attachmentId, $metadata);
                }

                $url = wp_get_attachment_url($attachmentId);
                if (!is_string($url) || $url === '') {
                    throw new \RuntimeException('WordPress could not resolve an imported image URL.');
                }

                foreach ($image->sourceUrls() as $sourceUrl) {
                    $urlMap[$sourceUrl] = $url;
                }
            }
        } catch (\Throwable $e) {
            $this->delete($attachmentIds);
            throw $e;
        }

        return new PageSourceImageImportResult($urlMap, $attachmentIds);
    }

    public function delete(array $attachmentIds): void
    {
        foreach (array_reverse($attachmentIds) as $attachmentId) {
            if ($attachmentId > 0) {
                wp_delete_attachment($attachmentId, true);
            }
        }
    }
}

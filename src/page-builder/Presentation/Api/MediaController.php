<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Domain\ErrorMessage;

final class MediaController
{
    private const ALLOWED_UPLOAD_MIMES = [
        'png'          => 'image/png',
        'jpe?g'        => 'image/jpeg',
        'gif'          => 'image/gif',
        'webp'         => 'image/webp',
    ];

    private const ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
    ];

    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/media/upload', [
            'methods'             => 'POST',
            'callback'            => [$this, 'upload'],
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);
    }

    public function upload(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        // Terminal boundary: no Throwable may escape the REST callback.
        try {
            $imageData = $request->get_param('image_data');

            if (!$this->permissions->canUploadFiles()) {
                return ApiResponse::error(ErrorMessage::MediaUploadForbidden);
            }

            if (empty($imageData) || !is_string($imageData)) {
                return ApiResponse::error(ErrorMessage::MediaImageDataRequired);
            }

            $normalizedImageData = $this->normalizeImageData($imageData);
            $uploadLimit = wp_max_upload_size();
            if ($this->estimateDecodedBytes($normalizedImageData) > $uploadLimit) {
                return ApiResponse::error(ErrorMessage::MediaTooLarge, [
                    'limit_bytes' => $uploadLimit,
                ]);
            }

            // Decode base64 to binary.
            $bytes = base64_decode($normalizedImageData, true);
            if ($bytes === false) {
                return ApiResponse::error(ErrorMessage::MediaBase64DecodeFailed);
            }

            if (strlen($bytes) > $uploadLimit) {
                return ApiResponse::error(ErrorMessage::MediaTooLarge, [
                    'limit_bytes' => $uploadLimit,
                ]);
            }

            // Resolve filename.
            $rawFilename = $request->get_param('filename');
            $filename = is_string($rawFilename) && $rawFilename !== ''
                ? sanitize_file_name($rawFilename)
                : wp_unique_id('upb-image-') . '.png';

            require_once ABSPATH . 'wp-admin/includes/file.php';

            $validatedFile = $this->validateFilePayload($filename, $bytes);
            if ($validatedFile === null) {
                return ApiResponse::error(ErrorMessage::MediaUnsupportedType);
            }

            // Write bytes to the uploads directory.
            $upload = wp_upload_bits($validatedFile['filename'], null, $bytes);

            if (!empty($upload['error'])) {
                return ApiResponse::error(ErrorMessage::MediaUploadFailed, [
                    'detail' => $upload['error'],
                ]);
            }

            $attachmentId = 0;

            try {
                // Create the attachment post.
                $attachmentData = [
                    'post_mime_type' => $validatedFile['mime_type'],
                    'post_title'     => pathinfo($validatedFile['filename'], PATHINFO_FILENAME),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ];

                $insertedAttachment = wp_insert_attachment($attachmentData, $upload['file']);

                if (is_wp_error($insertedAttachment) || (int) $insertedAttachment <= 0) {
                    if (!$this->cleanupFailedUpload(0, $upload['file'])) {
                        return $this->mediaCleanupFailure(0, 'attachment_insert');
                    }

                    if (is_wp_error($insertedAttachment)) {
                        return $this->mediaAttachmentFailure(
                            'attachment_insert',
                            $insertedAttachment->get_error_message(),
                            (string) $insertedAttachment->get_error_code(),
                        );
                    }

                    return $this->mediaAttachmentFailure(
                        'attachment_insert',
                        'WordPress did not return a valid attachment ID.',
                    );
                }

                $attachmentId = (int) $insertedAttachment;

                // Generate thumbnails and image metadata.
                require_once ABSPATH . 'wp-admin/includes/image.php';

                $metadata = wp_generate_attachment_metadata($attachmentId, $upload['file']);
                if (is_wp_error($metadata) || !is_array($metadata)) {
                    if (!$this->cleanupFailedUpload($attachmentId, $upload['file'])) {
                        return $this->mediaCleanupFailure($attachmentId, 'metadata_generation');
                    }

                    if (is_wp_error($metadata)) {
                        return $this->mediaAttachmentFailure(
                            'metadata_generation',
                            $metadata->get_error_message(),
                            (string) $metadata->get_error_code(),
                        );
                    }

                    return $this->mediaAttachmentFailure(
                        'metadata_generation',
                        'WordPress did not return valid attachment metadata.',
                    );
                }

                $metadataUpdated = wp_update_attachment_metadata($attachmentId, $metadata);
                $persistedMetadata = $metadataUpdated === false
                    ? wp_get_attachment_metadata($attachmentId, true)
                    : $metadata;

                // WordPress persists image metadata while generating each sub-size.
                // A final identical update returns false, which is a valid no-op.
                if (!is_array($persistedMetadata) || $persistedMetadata === []) {
                    if (!$this->cleanupFailedUpload($attachmentId, $upload['file'])) {
                        return $this->mediaCleanupFailure($attachmentId, 'metadata_persistence');
                    }

                    return $this->mediaAttachmentFailure(
                        'metadata_persistence',
                        'WordPress could not persist the generated attachment metadata.',
                    );
                }

                // Set alt text.
                $alt = sanitize_text_field($request->get_param('alt') ?? '');
                if ($alt !== '') {
                    update_post_meta($attachmentId, '_wp_attachment_alt_text', $alt);
                }
            } catch (\Throwable $exception) {
                $cleanupComplete = $this->cleanupFailedUpload($attachmentId, $upload['file']);
                $this->recordFailure('media upload', $attachmentId, 'attachment.unexpected', $exception);

                if (!$cleanupComplete) {
                    return $this->mediaCleanupFailure($attachmentId, 'unexpected_exception');
                }

                return $this->mediaAttachmentFailure(
                    'unexpected_exception',
                    'WordPress could not finish the media attachment.',
                );
            }

            return ApiResponse::created([
                'attachment_id' => $attachmentId,
                'url'           => $upload['url'],
                'alt'           => $alt,
            ])->toResponse();
        } catch (\Throwable $failure) {
            $this->recordFailure('media upload', 0, 'upload.unexpected', $failure);
            return ApiResponse::error(ErrorMessage::MediaUploadFailed, [
                'failure_stage' => 'unexpected_exception',
            ]);
        }
    }

    private function recordFailure(string $scope, int $ownerId, string $step, \Throwable $failure): void
    {
        try {
            $this->failureReporter?->report($scope, $ownerId, $step, $failure);
        } catch (\Throwable) {
            // A report failure cannot change the controlled REST response.
        }
    }

    /**
     * Preserve actionable WordPress diagnostics across the Agent boundary.
     * Values are plain text and bounded because this response is model-visible.
     */
    private function mediaAttachmentFailure(
        string $stage,
        string $detail,
        string $wordpressErrorCode = '',
    ): \WP_Error {
        $extra = [
            'failure_stage' => sanitize_text_field($stage),
            'detail'        => substr(sanitize_text_field($detail), 0, 500),
        ];

        $cleanErrorCode = sanitize_text_field($wordpressErrorCode);
        if ($cleanErrorCode !== '') {
            $extra['wordpress_error_code'] = substr($cleanErrorCode, 0, 120);
        }

        return ApiResponse::error(ErrorMessage::MediaAttachmentFailed, $extra);
    }

    private function mediaCleanupFailure(int $attachmentId, string $originalFailureStage): \WP_Error
    {
        $extra = [
            'failure_stage' => 'cleanup',
            'original_failure_stage' => sanitize_text_field($originalFailureStage),
            'retryable' => false,
            'requires_read' => true,
            'detail' => 'The upload result is uncertain. Read the Media Library before another upload.',
        ];

        if ($attachmentId > 0) {
            $extra['possible_attachment_id'] = $attachmentId;
        }

        return ApiResponse::error(ErrorMessage::MediaCleanupUncertain, $extra);
    }

    /**
     * Remove the durable side effect owned by this upload attempt. Once an
     * attachment exists WordPress owns all derived image files, so its delete
     * API is the only safe cleanup. Before that point only the uploaded file
     * exists and can be removed directly through WordPress' file hook.
     */
    private function cleanupFailedUpload(int $attachmentId, string $file): bool
    {
        if ($attachmentId > 0) {
            if (!function_exists('wp_delete_attachment')) {
                $this->recordFailure(
                    'media upload',
                    $attachmentId,
                    'attachment.cleanup',
                    new \RuntimeException('WordPress attachment cleanup is unavailable.'),
                );

                return false;
            }

            try {
                $deleted = wp_delete_attachment($attachmentId, true);
            } catch (\Throwable $failure) {
                $this->recordFailure('media upload', $attachmentId, 'attachment.cleanup', $failure);

                return false;
            }

            if ($deleted !== false && !is_wp_error($deleted)) {
                return true;
            }

            $this->recordFailure(
                'media upload',
                $attachmentId,
                'attachment.cleanup',
                new \RuntimeException('WordPress could not remove the incomplete attachment.'),
            );

            // WordPress still owns the attachment and its file. Keep both so
            // the Media Library does not point to a file that no longer exists.
            return false;
        }

        if ($file !== '' && function_exists('wp_delete_file')) {
            try {
                wp_delete_file($file);
            } catch (\Throwable $failure) {
                $this->recordFailure('media upload', 0, 'file.cleanup', $failure);

                return false;
            }
        }

        return true;
    }

    private function normalizeImageData(string $imageData): string
    {
        $payload = $imageData;
        if (preg_match('/^data:image\/[a-z0-9.+-]+;base64,(.*)$/is', $imageData, $matches) === 1) {
            $payload = $matches[1];
        }

        return preg_replace('/\s+/', '', $payload) ?? '';
    }

    private function estimateDecodedBytes(string $base64): int
    {
        $length = strlen($base64);
        if ($length === 0) {
            return 0;
        }

        $padding = 0;
        if (str_ends_with($base64, '==')) {
            $padding = 2;
        } elseif (str_ends_with($base64, '=')) {
            $padding = 1;
        }

        return (int) (($length * 3) / 4) - $padding;
    }

    /**
     * @return array{filename: string, mime_type: string}|null
     */
    private function validateFilePayload(string $filename, string $bytes): ?array
    {
        $tmpFile = wp_tempnam($filename);
        if ($tmpFile === false || $tmpFile === '') {
            return null;
        }

        try {
            if (file_put_contents($tmpFile, $bytes) === false) {
                return null;
            }

            $fileTypeInfo = wp_check_filetype_and_ext($tmpFile, $filename, self::ALLOWED_UPLOAD_MIMES);
            $mimeType = $fileTypeInfo['type'] ?? '';
            if ($mimeType === '' || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
                return null;
            }

            $resolvedFilename = $fileTypeInfo['proper_filename'] ?: $filename;

            return [
                'filename'  => sanitize_file_name($resolvedFilename),
                'mime_type' => $mimeType,
            ];
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }
}

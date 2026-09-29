<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Infrastructure\WordPress\ServerPathRedaction;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressSlashing;

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
        $uploadStarted = false;
        $uploadedFile = '';

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
            $uploadStarted = true;
            $fileWriteReached = true;
            $upload = $this->uploadBitsWithCapturedFile(
                $validatedFile['filename'],
                $bytes,
                $uploadedFile,
                $fileWriteReached,
            );

            if (!empty($upload['error'])) {
                // Core applies wp_handle_upload only after it writes the file.
                // When the capture filter never ran, no file was written.
                if ($fileWriteReached && ($uploadedFile === '' || !$this->cleanupFailedUpload(0, $uploadedFile))) {
                    return $this->mediaCleanupFailure(0, 'file_upload');
                }

                return ApiResponse::error(ErrorMessage::MediaUploadFailed, [
                    'detail' => $this->uploadErrorDetail($upload['error']),
                ]);
            }

            $attachmentId = 0;
            $requestedAlt = sanitize_text_field($request->get_param('alt') ?? '');
            $storedAlt = '';
            $altVerified = false;
            $warnings = [];
            $generatedMetadata = [];

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
                if (!$this->attachmentOwnsFile($attachmentId, (string) $upload['file'])) {
                    return $this->mediaCleanupFailure($attachmentId, 'attachment_verification');
                }

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
                $generatedMetadata = $metadata;

                $metadataUpdated = wp_update_attachment_metadata($attachmentId, $metadata);
                $persistedMetadata = $metadataUpdated === false
                    ? wp_get_attachment_metadata($attachmentId, true)
                    : $metadata;

                // WordPress persists image metadata while generating each sub-size.
                // A final identical update returns false, which is a valid no-op.
                if (!is_array($persistedMetadata) || $persistedMetadata === []) {
                    if (!$this->cleanupFailedUpload($attachmentId, $upload['file'], $generatedMetadata)) {
                        return $this->mediaCleanupFailure($attachmentId, 'metadata_persistence');
                    }

                    return $this->mediaAttachmentFailure(
                        'metadata_persistence',
                        'WordPress could not persist the generated attachment metadata.',
                    );
                }

                if ($requestedAlt !== '') {
                    try {
                        update_post_meta(
                            $attachmentId,
                            '_wp_attachment_alt_text',
                            WordPressSlashing::slash($requestedAlt),
                        );
                    } catch (\Throwable $failure) {
                        $this->recordFailure('media upload', $attachmentId, 'attachment.alt_write', $failure);
                    }
                }

                try {
                    $persistedAlt = get_post_meta($attachmentId, '_wp_attachment_alt_text', true);
                    $storedAlt = is_scalar($persistedAlt) ? (string) $persistedAlt : '';
                    $altVerified = true;
                } catch (\Throwable $failure) {
                    $this->recordFailure('media upload', $attachmentId, 'attachment.alt_read', $failure);
                }

                if (!$altVerified) {
                    $warnings[] = 'Stored alt text could not be verified. Read the attachment before relying on ALT.';
                } elseif ($requestedAlt !== '' && $storedAlt !== $requestedAlt) {
                    $warnings[] = 'Requested alt text was not saved. The ALT field reports the current stored value.';
                }
            } catch (\Throwable $exception) {
                $this->recordFailure('media upload', $attachmentId, 'attachment.unexpected', $exception);

                // WordPress can persist the attachment and its file metadata
                // before a third-party attachment hook interrupts the return.
                // Without the returned ID, deleting the file can break that
                // retained Media Library row.
                if ($attachmentId <= 0) {
                    return $this->mediaCleanupFailure(0, 'attachment_insert');
                }

                $cleanupComplete = $this->cleanupFailedUpload(
                    $attachmentId,
                    $upload['file'],
                    $generatedMetadata,
                );

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
                'alt'           => $storedAlt,
                'requested_alt' => $requestedAlt,
                'warnings'      => $warnings,
            ])->toResponse();
        } catch (\Throwable $failure) {
            $this->recordFailure('media upload', 0, 'upload.unexpected', $failure);

            if ($uploadStarted) {
                if ($uploadedFile !== '' && $this->cleanupFailedUpload(0, $uploadedFile)) {
                    return ApiResponse::error(ErrorMessage::MediaUploadFailed, [
                        'failure_stage' => 'file_upload',
                    ]);
                }

                return $this->mediaCleanupFailure(0, 'file_upload');
            }

            return ApiResponse::error(ErrorMessage::MediaUploadFailed, [
                'failure_stage' => 'unexpected_exception',
            ]);
        }
    }

    private function uploadBitsWithCapturedFile(
        string $filename,
        string $bytes,
        string &$capturedFile,
        bool &$fileWriteReached,
    ): array {
        if (!function_exists('add_filter') || !function_exists('remove_filter')) {
            // Without the capture filter, a written file cannot be ruled out.
            $fileWriteReached = true;

            return wp_upload_bits($filename, null, $bytes);
        }

        $fileWriteReached = false;
        $captureActive = true;
        $captureFile = static function ($upload = null) use (&$captureActive, &$capturedFile, &$fileWriteReached) {
            if ($captureActive) {
                $fileWriteReached = true;
            }
            try {
                if (
                    $captureActive
                    && is_array($upload)
                    && is_string($upload['file'] ?? null)
                    && $upload['file'] !== ''
                ) {
                    $capturedFile = $upload['file'];
                }
            } catch (\Throwable) {
                // A hostile filter value must pass through unchanged.
            }

            return $upload;
        };

        add_filter('wp_handle_upload', $captureFile, PHP_INT_MIN);

        try {
            return wp_upload_bits($filename, null, $bytes);
        } finally {
            // A failed filter removal must not leave this request-scoped
            // callback active for a later upload in the same process.
            $captureActive = false;

            try {
                remove_filter('wp_handle_upload', $captureFile, PHP_INT_MIN);
            } catch (\Throwable) {
                // Cleanup still uses the captured path at the REST boundary.
            }
        }
    }

    private function attachmentOwnsFile(int $attachmentId, string $file): bool
    {
        if (!function_exists('get_attached_file')) {
            return false;
        }

        try {
            $post = get_post($attachmentId);
            $attachedFile = get_attached_file($attachmentId, true);
        } catch (\Throwable $failure) {
            $this->recordFailure('media upload', $attachmentId, 'attachment.verification', $failure);

            return false;
        }

        if (!is_object($post) || (string) ($post->post_type ?? '') !== 'attachment' || !is_string($attachedFile)) {
            return false;
        }

        $expected = rtrim(str_replace('\\', '/', $file), '/');
        $stored = rtrim(str_replace('\\', '/', $attachedFile), '/');
        if ($expected === $stored) {
            return true;
        }

        $expectedRealPath = realpath($file);
        $storedRealPath = realpath($attachedFile);

        return is_string($expectedRealPath)
            && is_string($storedRealPath)
            && $expectedRealPath === $storedRealPath;
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

    /**
     * WordPress upload errors can name the absolute server path of the file.
     * Keep the reason and remove the path. A filter can also return WP_Error.
     */
    private function uploadErrorDetail(mixed $error): string
    {
        $message = is_wp_error($error) ? $error->get_error_message() : $error;

        return ServerPathRedaction::redact(is_scalar($message) ? (string) $message : 'unknown');
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
     *
     * @param array<string, mixed> $generatedMetadata
     */
    private function cleanupFailedUpload(int $attachmentId, string $file, array $generatedMetadata = []): bool
    {
        try {
            return $this->performFailedUploadCleanup($attachmentId, $file, $generatedMetadata);
        } catch (\Throwable $failure) {
            $this->recordFailure('media upload', $attachmentId, 'cleanup.unexpected', $failure);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $generatedMetadata
     */
    private function performFailedUploadCleanup(int $attachmentId, string $file, array $generatedMetadata): bool
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

            $inventory = $this->knownAttachmentFiles($attachmentId, $file, $generatedMetadata);

            try {
                $deleted = wp_delete_attachment($attachmentId, true);
            } catch (\Throwable $failure) {
                $this->recordFailure('media upload', $attachmentId, 'attachment.cleanup', $failure);

                return false;
            }

            try {
                if (
                    $deleted !== false
                    && !is_wp_error($deleted)
                    && get_post($attachmentId) === null
                    && $inventory['complete']
                    && $this->filesAreAbsent($inventory['files'])
                ) {
                    return true;
                }
            } catch (\Throwable $failure) {
                $this->recordFailure('media upload', $attachmentId, 'attachment.cleanup_verification', $failure);

                return false;
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

        if ($file === '' || !function_exists('wp_delete_file')) {
            $this->recordFailure(
                'media upload',
                0,
                'file.cleanup',
                new \RuntimeException('WordPress file cleanup is unavailable.'),
            );

            return false;
        }

        try {
            wp_delete_file($file);
        } catch (\Throwable $failure) {
            $this->recordFailure('media upload', 0, 'file.cleanup', $failure);

            return false;
        }

        try {
            $fileStillExists = file_exists($file);
        } catch (\Throwable $failure) {
            $this->recordFailure('media upload', 0, 'file.cleanup_verification', $failure);

            return false;
        }

        if ($fileStillExists) {
            $this->recordFailure(
                'media upload',
                0,
                'file.cleanup',
                new \RuntimeException('WordPress could not remove the uploaded file.'),
            );

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $generatedMetadata
     * @return array{files: list<string>, complete: bool}
     */
    private function knownAttachmentFiles(int $attachmentId, string $file, array $generatedMetadata): array
    {
        $known = $file !== '' ? [$file] : [];
        $metadataSets = [];
        $complete = true;
        if ($generatedMetadata !== []) {
            $metadataSets[] = $generatedMetadata;
        }

        try {
            $persistedMetadata = wp_get_attachment_metadata($attachmentId, true);
            if (is_array($persistedMetadata)) {
                $metadataSets[] = $persistedMetadata;
            }
        } catch (\Throwable $failure) {
            $complete = false;
            $this->recordFailure('media upload', $attachmentId, 'attachment.cleanup_inventory', $failure);
        }

        try {
            $backupSizes = get_post_meta($attachmentId, '_wp_attachment_backup_sizes', true);
            if (is_array($backupSizes)) {
                $metadataSets[] = $backupSizes;
            }
        } catch (\Throwable $failure) {
            $complete = false;
            $this->recordFailure('media upload', $attachmentId, 'attachment.cleanup_inventory', $failure);
        }

        $directory = dirname($file);
        foreach ($metadataSets as $metadata) {
            $this->appendMetadataFiles($metadata, $directory, $known);
        }

        return [
            'files' => array_values(array_unique($known)),
            'complete' => $complete,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<string> $known
     */
    private function appendMetadataFiles(array $metadata, string $directory, array &$known): void
    {
        foreach ($metadata as $key => $value) {
            if (
                is_string($value)
                && in_array((string) $key, [
                    'file',
                    'original_image',
                    'thumb',
                    'source_image',
                    'animated_video',
                    'animated_video_poster',
                ], true)
                && trim($value) !== ''
            ) {
                $known[] = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . basename($value);
                continue;
            }

            if (is_array($value)) {
                $this->appendMetadataFiles($value, $directory, $known);
            }
        }
    }

    /** @param list<string> $files */
    private function filesAreAbsent(array $files): bool
    {
        foreach ($files as $file) {
            if ($file !== '' && file_exists($file)) {
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
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- wp_check_filetype_and_ext() requires a local temporary file.
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
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- This private validation temp file must be removed without exposing cleanup to filters.
                unlink($tmpFile);
            }
        }
    }
}

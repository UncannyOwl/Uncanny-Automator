<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

use UncannyPageBuilder\Presentation\Api\MediaController;

final class AgentMediaController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly MediaController $uploads,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/agent/media', [
            'methods' => 'GET',
            'callback' => [$this, 'manageMedia'],
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args' => [
                'operation' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'mime_type' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'attachment_id' => [
                    'required' => false,
                    'sanitize_callback' => 'absint',
                ],
                'size' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/agent/media', [
            'methods' => 'POST',
            'callback' => [$this, 'manageMedia'],
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);
    }

    public function manageMedia(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $operation = trim((string) ($request->get_param('operation') ?? ''));

        try {
            return $this->manageMediaOperation($request, $operation);
        } catch (\Throwable $failure) {
            error_log(sprintf('[Uncanny Page Builder] manage_media "%s" failed (%s).', $operation, $failure::class));

            return $this->textError(500, 'media_operation_failed', [
                'OPERATION: ' . $operation,
                'NEXT STEP',
                'For search or read, retry manage_media. For upload, call manage_media with operation search first: if the file is present, do not retry the upload.',
            ]);
        }
    }

    private function manageMediaOperation(\WP_REST_Request $request, string $operation): \WP_REST_Response|\WP_Error
    {
        return match ($operation) {
            '', 'search' => $this->search(
                search: (string) ($request->get_param('search') ?? ''),
                mimeType: (string) ($request->get_param('mime_type') ?? ''),
                size: (string) ($request->get_param('size') ?? 'full'),
            ),
            'read' => $this->read(
                attachmentId: absint($request->get_param('attachment_id') ?? 0),
                size: (string) ($request->get_param('size') ?? 'full'),
            ),
            'upload' => $this->upload($request),
            default => $this->textError(400, 'invalid_operation', [
                'OPERATION: ' . $operation,
                'NEXT STEP',
                'Retry with operation search, read, or upload.',
            ]),
        };
    }

    private function search(string $search, string $mimeType, string $size): \WP_REST_Response
    {
        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $trimmedSearch = trim($search);
        if ($trimmedSearch !== '') {
            $args['s'] = $trimmedSearch;
        }

        $trimmedMimeType = trim($mimeType);
        if ($trimmedMimeType !== '') {
            $args['post_mime_type'] = $trimmedMimeType;
        }

        $posts = get_posts($args);
        $items = [];
        foreach (is_array($posts) ? $posts : [] as $post) {
            $item = $this->attachmentSummary($post, $size);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        $lines = [
            'TOOL: manage_media',
            'RESULT: success',
            'OPERATION: search',
            'SEARCH: ' . $trimmedSearch,
            'MIME_TYPE: ' . $trimmedMimeType,
            '',
            'ATTACHMENTS',
        ];

        if ($items === []) {
            $lines[] = 'none';
        }

        foreach ($items as $item) {
            $this->appendAttachmentLines($lines, $item);
        }

        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use read with an ATTACHMENT_ID to inspect one media item before selecting a size for image edits.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    private function read(int $attachmentId, string $size): \WP_REST_Response
    {
        if ($attachmentId <= 0) {
            return $this->textError(400, 'missing_attachment_id', [
                'NEXT STEP',
                'Retry with operation read and a valid attachment_id.',
            ]);
        }

        $post = get_post($attachmentId);
        $item = $this->attachmentSummary($post, $size);
        if ($item === null) {
            return $this->textError(404, 'attachment_not_found', [
                'ATTACHMENT_ID: ' . $attachmentId,
                'NEXT STEP',
                'Retry with a valid media attachment id from search.',
            ]);
        }

        $lines = [
            'TOOL: manage_media',
            'RESULT: success',
            'OPERATION: read',
        ];
        $this->appendAttachmentLines($lines, $item);
        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use ATTACHMENT_ID and the returned SIZE_URL for image selection or upload follow-up work.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    private function upload(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $response = $this->uploads->upload($request);
        if ($response instanceof \WP_Error) {
            return $response;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            return $this->textError(500, 'invalid_upload_response', [
                'NEXT STEP',
                'Retry the upload. If the error persists, inspect the underlying media upload response.',
            ]);
        }

        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: manage_media',
            'RESULT: success',
            'OPERATION: upload',
            'ATTACHMENT_ID: ' . (string) ($data['attachment_id'] ?? 0),
            'URL: ' . (string) ($data['url'] ?? ''),
            'ALT: ' . (string) ($data['alt'] ?? ''),
            '',
            'NEXT STEP',
            'Use ATTACHMENT_ID or URL from this response for image selection or follow-up image edits.',
        ]), $response->get_status());
    }

    /**
     * @return array{
     *   id: int,
     *   title: string,
     *   alt: string,
     *   mime_type: string,
     *   width: int,
     *   height: int,
     *   selected_size: string,
     *   selected_size_url: string,
     *   sizes: list<string>
     * }|null
     */
    private function attachmentSummary(mixed $post, string $size): ?array
    {
        if (!$post instanceof \WP_Post || $post->post_type !== 'attachment') {
            return null;
        }

        $attachmentId = (int) $post->ID;
        $metadata = wp_get_attachment_metadata($attachmentId);
        $sizes = [];
        if (is_array($metadata['sizes'] ?? null)) {
            $sizes = array_keys($metadata['sizes']);
        }

        $selectedSize = trim($size) !== '' ? trim($size) : 'full';
        $selectedUrl = wp_get_attachment_image_url($attachmentId, $selectedSize);
        if (!is_string($selectedUrl) || $selectedUrl === '') {
            $selectedSize = 'full';
            $selectedUrl = wp_get_attachment_image_url($attachmentId, 'full');
        }

        return [
            'id' => $attachmentId,
            'title' => (string) $post->post_title,
            'alt' => (string) get_post_meta($attachmentId, '_wp_attachment_alt_text', true),
            'mime_type' => (string) ($post->post_mime_type ?? ''),
            'width' => (int) ($metadata['width'] ?? 0),
            'height' => (int) ($metadata['height'] ?? 0),
            'selected_size' => $selectedSize,
            'selected_size_url' => is_string($selectedUrl) ? $selectedUrl : '',
            'sizes' => array_values(array_unique(array_merge(['full'], $sizes))),
        ];
    }

    /**
     * @param list<string> $lines
     * @param array{
     *   id: int,
     *   title: string,
     *   alt: string,
     *   mime_type: string,
     *   width: int,
     *   height: int,
     *   selected_size: string,
     *   selected_size_url: string,
     *   sizes: list<string>
     * } $item
     */
    private function appendAttachmentLines(array &$lines, array $item): void
    {
        $lines[] = '- ATTACHMENT_ID: ' . $item['id'];
        $lines[] = '  TITLE: ' . $item['title'];
        $lines[] = '  ALT: ' . $item['alt'];
        $lines[] = '  MIME_TYPE: ' . $item['mime_type'];
        $lines[] = '  DIMENSIONS: ' . $item['width'] . 'x' . $item['height'];
        $lines[] = '  SELECTED_SIZE: ' . $item['selected_size'];
        $lines[] = '  SIZE_URL: ' . $item['selected_size_url'];
        $lines[] = '  AVAILABLE_SIZES: ' . implode(', ', $item['sizes']);
    }

    /**
     * @param list<string> $lines
     */
    private function textError(int $status, string $code, array $lines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: manage_media',
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }
}

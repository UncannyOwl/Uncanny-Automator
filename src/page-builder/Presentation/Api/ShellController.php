<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\ShellImportService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\ShellHtmlTooLargeException;

final class ShellController
{
    private const NAMESPACE = 'uncanny-page-builder/v1';

    public function __construct(
        private readonly ShellImportService $shellImportService,
        private readonly PermissionChecker $permissions,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/shell/analyze', [
            'methods'             => 'POST',
            'callback'            => [$this, 'analyze'],
            'permission_callback' => [$this->permissions, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/shell/import', [
            'methods'             => 'POST',
            'callback'            => [$this, 'import'],
            'permission_callback' => [$this->permissions, 'canManage'],
        ]);
    }

    public function analyze(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $headerHtml = $request->get_param('header_html');
        $footerHtml = $request->get_param('footer_html');

        if (empty($headerHtml) && empty($footerHtml)) {
            return ApiResponse::error(ErrorMessage::ShellMissingHtml);
        }

        try {
            $result = $this->shellImportService->analyze(
                is_string($headerHtml) ? $headerHtml : null,
                is_string($footerHtml) ? $footerHtml : null,
            );
        } catch (ShellHtmlTooLargeException $e) {
            return ApiResponse::error(ErrorMessage::ShellHtmlTooLarge, [
                'field'    => $e->field(),
                'size'     => $e->size(),
                'max_size' => $e->maxSize(),
            ]);
        }

        return ApiResponse::ok([
            'header' => $result['header'] ?? null,
            'footer' => $result['footer'] ?? null,
        ])->toResponse();
    }

    public function import(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $header = $request->get_param('header');
        $footer = $request->get_param('footer');

        if (empty($header) && empty($footer)) {
            return ApiResponse::error(ErrorMessage::ShellMissingAnalysis);
        }

        $result = $this->shellImportService->import(
            is_array($header) ? $header : null,
            is_array($footer) ? $footer : null,
        );
        return ApiResponse::created([
            'header_id' => $result['header_id'] ?? null,
            'footer_id' => $result['footer_id'] ?? null,
            'warnings'  => $result['warnings'] ?? [],
        ])->toResponse();
    }
}

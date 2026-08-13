<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\ShellImportService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\ShellHtmlTooLargeException;

final class ShellController
{
    private const NAMESPACE = 'uncanny-page-builder/v1';

    public function __construct(
        private readonly ShellImportService $shellImportService,
        private readonly PermissionChecker $permissions,
        private readonly ?FailureReporterInterface $failureReporter = null,
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

        if (
            ($headerHtml !== null && !is_string($headerHtml))
            || ($footerHtml !== null && !is_string($footerHtml))
        ) {
            return ApiResponse::error(ErrorMessage::ShellMissingHtml);
        }

        if (trim($headerHtml ?? '') === '' && trim($footerHtml ?? '') === '') {
            return ApiResponse::error(ErrorMessage::ShellMissingHtml);
        }

        try {
            $result = $this->shellImportService->analyze(
                $headerHtml,
                $footerHtml,
            );
        } catch (ShellHtmlTooLargeException $e) {
            return ApiResponse::error(ErrorMessage::ShellHtmlTooLarge, [
                'field'    => $e->field(),
                'size'     => $e->size(),
                'max_size' => $e->maxSize(),
            ]);
        } catch (\Throwable $failure) {
            $this->recordUnexpectedFailure('analyze', $failure);
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed);
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

        if (
            ($header !== null && !is_array($header))
            || ($footer !== null && !is_array($footer))
        ) {
            return ApiResponse::error(ErrorMessage::ShellMissingAnalysis);
        }

        if (($header === null || $header === []) && ($footer === null || $footer === [])) {
            return ApiResponse::error(ErrorMessage::ShellMissingAnalysis);
        }

        try {
            $result = $this->shellImportService->import($header, $footer);
        } catch (\InvalidArgumentException) {
            return ApiResponse::error(ErrorMessage::ShellMissingAnalysis);
        } catch (\Throwable $failure) {
            $this->recordUnexpectedFailure('import', $failure);
            return new \WP_Error(
                'shell_import_failed',
                _x(
                    'Page Builder could not confirm the shell import. Inspect existing reusables before another import.',
                    'Page Builder',
                    'uncanny-automator',
                ),
                ['status' => 500, 'retryable' => false, 'requires_read' => true],
            );
        }

        return ApiResponse::created([
            'header_id' => $result['header_id'] ?? null,
            'footer_id' => $result['footer_id'] ?? null,
            'warnings'  => $result['warnings'] ?? [],
        ])->toResponse();
    }

    private function recordUnexpectedFailure(string $operation, \Throwable $failure): void
    {
        try {
            $this->failureReporter?->report('shell import', 0, $operation, $failure);
        } catch (\Throwable) {
            // A report failure cannot change the controlled REST response.
        }
    }
}

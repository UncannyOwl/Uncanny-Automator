<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\Reusable\PreviewReusableSection;
use UncannyPageBuilder\Application\SourcePackage\ReusableSourcePackageService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartCreationUncertainException;
use UncannyPageBuilder\Domain\SourcePackage\SourcePackageValidationException;

final class GlobalPartController
{
    private const REFRESH_WARNING = [
        'code' => 'working_canvas_refresh_failed',
        'message' => 'The site default was saved, but working canvases could not be queued for refresh.',
    ];

    private const DEFAULT_ASSIGNMENT_WARNING = [
        'code' => 'global_part_default_assignment_failed',
        'message' => 'The reusable was saved, but Page Builder could not assign it as the site default.',
    ];

    public function __construct(
        private readonly GlobalPartService $globalPartService,
        private readonly PermissionChecker $permissions,
        private readonly GlobalPartDefaultsService $defaultsService,
        private readonly ReusableSourcePackageService $sourcePackages,
        private readonly PreviewReusableSection $previewReusableSection,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/global-parts', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'create'],
                'permission_callback' => [$this->permissions, 'canManage'],
            ],
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'listAll'],
                'permission_callback' => [$this->permissions, 'canManage'],
            ],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/global-parts/ai', [
            'methods'             => 'POST',
            'callback'            => [$this, 'createFromAi'],
            'permission_callback' => [$this->permissions, 'canManage'],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/global-parts/import-section', [
            'methods'             => 'POST',
            'callback'            => [$this, 'importSection'],
            'permission_callback' => [$this->permissions, 'canManage'],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/global-parts/(?P<global_part_id>\d+)/section-source', [
            'methods'             => 'GET',
            'callback'            => [$this, 'readSectionSource'],
            'permission_callback' => [$this->permissions, 'canManage'],
            'args'                => ['global_part_id' => RequestId::routeArgument()],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/global-parts/(?P<global_part_id>\d+)/section-preview', [
            'methods'             => 'GET',
            'callback'            => [$this, 'previewSectionSource'],
            'permission_callback' => [$this, 'canPreviewSectionSource'],
            'args'                => [
                'global_part_id' => RequestId::routeArgument(),
                'page_id' => RequestId::routeArgument(),
                'preview_section_id' => RequestId::negativeRouteArgument(),
            ],
        ]);
    }

    /**
     * Authorize a reusable preview against its target page.
     *
     * The target page supplies the WordPress context for dynamic bindings.
     * Require both Page Builder management access and permission to edit that
     * page before the preview reads or renders reusable source.
     */
    public function canPreviewSectionSource(\WP_REST_Request $request): bool
    {
        $pageId = RequestId::positive($request->get_param('page_id'));

        return $pageId !== null
            && $this->permissions->canManage($request)
            && $this->permissions->canEditPage($pageId);
    }

    public function create(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $title   = sanitize_text_field($request->get_param('title') ?? '');
        $section = $request->get_param('section');
        $type    = sanitize_text_field($request->get_param('type') ?? 'section');

        if (empty($title)) {
            return ApiResponse::error(ErrorMessage::GpTitleRequired);
        }

        if (empty($section) || empty($section['content']['html'])) {
            return ApiResponse::error(ErrorMessage::GpSectionRequired);
        }

        try {
            $result = $this->globalPartService->create($title, $section, $type);
            return ApiResponse::created($result)->toResponse();
        } catch (SectionValidationException $e) {
            return ApiResponse::validationError($e);
        } catch (StaleSourceGenerationException $e) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $e->scope()]);
        } catch (GlobalPartCreationUncertainException $e) {
            return $this->creationUncertainError('create', $e);
        } catch (\RuntimeException $e) {
            $this->recordFailure('create', 0, $e);
            return ApiResponse::error(ErrorMessage::GpCreationFailed);
        } catch (\Throwable $failure) {
            $this->recordFailure('create', 0, $failure);
            return ApiResponse::error(ErrorMessage::GpCreationFailed);
        }
    }

    public function createFromAi(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $setAsDefaultValue = $request->get_param('set_as_default');
        if ($setAsDefaultValue !== null && !is_bool($setAsDefaultValue)) {
            return ApiResponse::error(ErrorMessage::InvalidBody);
        }

        $title   = sanitize_text_field($request->get_param('title') ?? '');
        $type    = sanitize_text_field($request->get_param('type') ?? '');
        $section = $request->get_param('section');
        $setAsDefault = $setAsDefaultValue === true;

        if (empty($title)) {
            return ApiResponse::error(ErrorMessage::GpTitleRequired);
        }

        $gpType = GlobalPartType::fromString($type);
        if ($gpType === GlobalPartType::Section) {
            return ApiResponse::error(ErrorMessage::GpAiTypeNotAllowed);
        }

        if (empty($section) || empty($section['content']['html'])) {
            return ApiResponse::error(ErrorMessage::GpSectionRequired);
        }

        try {
            $result = $this->globalPartService->createOrReplace($title, $section, $type);
        } catch (SectionValidationException $e) {
            return ApiResponse::validationError($e);
        } catch (StaleSourceGenerationException $e) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $e->scope()]);
        } catch (GlobalPartCreationUncertainException $e) {
            return $this->creationUncertainError('create_from_ai', $e);
        } catch (\RuntimeException $e) {
            $this->recordFailure('create_from_ai', 0, $e);
            return ApiResponse::error(ErrorMessage::GpCreationFailed);
        } catch (\Throwable $failure) {
            $this->recordFailure('create_from_ai', 0, $failure);
            return ApiResponse::error(ErrorMessage::GpCreationFailed);
        }

        $assignedAsDefault = false;
        $warnings = is_array($result['warnings'] ?? null)
            ? $this->normalizeCreateWarnings($result['warnings'])
            : [];
        if ($setAsDefault && $result['id'] > 0) {
            try {
                $defaultResult = $this->defaultsService->setDefaultIdWithRefreshStatus($gpType, $result['id']);
                $assignedAsDefault = $defaultResult['accepted'];
                if (!$defaultResult['refresh_queued']) {
                    $warnings[] = self::REFRESH_WARNING;
                }
            } catch (\Throwable $failure) {
                // The reusable exists. Do not report its creation as a failure.
                try {
                    $this->failureReporter?->report(
                        'global part',
                        (int) $result['id'],
                        'default_assignment',
                        $failure,
                    );
                } catch (\Throwable) {
                    // A report failure cannot change the completed create result.
                }
                $warnings[] = self::DEFAULT_ASSIGNMENT_WARNING;
            }
        }

        $response = [
            'id'         => $result['id'],
            'title'      => $result['title'],
            'type'       => $result['type'],
            'is_default' => $assignedAsDefault,
        ];
        if ($warnings !== []) {
            $response['warnings'] = $warnings;
        }

        return ApiResponse::created($response)->toResponse();
    }

    /**
     * @param array<int, mixed> $warnings
     * @return list<array{code: string, message: string}>
     */
    private function normalizeCreateWarnings(array $warnings): array
    {
        $normalized = [];
        foreach ($warnings as $warning) {
            if (
                is_array($warning)
                && is_string($warning['code'] ?? null)
                && is_string($warning['message'] ?? null)
            ) {
                $normalized[] = [
                    'code' => $warning['code'],
                    'message' => $warning['message'],
                ];
                continue;
            }

            if (!is_string($warning) || trim($warning) === '') {
                continue;
            }

            $normalized[] = [
                'code' => $warning === GlobalPartService::WORKING_CANVAS_REFRESH_WARNING
                    ? 'working_canvas_refresh_failed'
                    : 'global_part_source_warning',
                'message' => $warning,
            ];
        }

        return $normalized;
    }

    public function importSection(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $payload = $request->get_param('package');
        if (!is_array($payload)) {
            return new \WP_Error(
                'source_package_invalid',
                _x('Upload a valid Page Builder reusable JSON export.', 'Page Builder', 'uncanny-automator'),
                ['status' => 422],
            );
        }

        try {
            // Validate and authorize executable source before the create-only
            // import writes a new reusable or its runtime JavaScript.
            $package = $this->sourcePackages->validateReusable($payload, GlobalPartType::Section);
            if (trim($package->customJavaScript()) !== '' && !$this->permissions->canCapability('unfiltered_html')) {
                return new \WP_Error(
                    'source_package_javascript_forbidden',
                    _x('This reusable source contains custom JavaScript. Use an account that can publish unfiltered code to import it.', 'Page Builder', 'uncanny-automator'),
                    ['status' => 403],
                );
            }
            $result = $this->sourcePackages->importReusable($payload, GlobalPartType::Section);
        } catch (SourcePackageValidationException $e) {
            return new \WP_Error('source_package_invalid', $e->getMessage(), ['status' => 422]);
        } catch (SectionValidationException $e) {
            return ApiResponse::validationError($e);
        } catch (StaleSourceGenerationException $e) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $e->scope()]);
        } catch (GlobalPartCreationUncertainException $e) {
            return $this->creationUncertainError('import_section', $e);
        } catch (\Throwable $failure) {
            $this->recordFailure('import_section', 0, $failure);
            return ApiResponse::error(ErrorMessage::GpCreationFailed);
        }

        $response = [
            'global_part' => [
                'id'    => (int) $result['id'],
                'title' => (string) $result['title'],
                'type'  => (string) $result['type'],
            ],
        ];
        if (is_array($result['warnings'] ?? null) && $result['warnings'] !== []) {
            $response['warnings'] = array_values($result['warnings']);
        }

        return ApiResponse::created($response)->toResponse();
    }

    public function listAll(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $items = [];
            foreach (GlobalPartType::validValues() as $typeValue) {
                $type = GlobalPartType::fromString($typeValue);
                foreach ($this->globalPartService->listByType($type) as $part) {
                    $items[] = [
                        'id'    => (int) $part['post_id'],
                        'title' => (string) ($part['title'] ?? ''),
                        'type'  => $typeValue,
                    ];
                }
            }
        } catch (\Throwable $failure) {
            $this->recordFailure('list', 0, $failure);
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed, ['retryable' => true]);
        }

        return ApiResponse::ok(['global_parts' => $items])->toResponse();
    }

    public function readSectionSource(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $globalPartId = RequestId::fromUrl($request, 'global_part_id');
        if ($globalPartId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        try {
            $source = $this->globalPartService->resolveSourceContent($globalPartId);
        } catch (\Throwable $failure) {
            $this->recordFailure('read_source', $globalPartId, $failure);
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed, ['retryable' => true]);
        }
        if (!is_array($source) || !is_array($source['content'] ?? null)) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        }

        return ApiResponse::ok(['global_part' => $this->sourceResponse($globalPartId, $source)])->toResponse();
    }

    /**
     * Render reusable source for insertion into a target page canvas.
     *
     * The preview uses the target page's WordPress context and a negative
     * browser-only section ID. This resolves dynamic bindings and compiles
     * matching CSS without changing the stored reusable source.
     */
    public function previewSectionSource(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $globalPartId = RequestId::fromUrl($request, 'global_part_id');
        $pageId = RequestId::positive($request->get_param('page_id'));
        $previewSectionId = RequestId::negative($request->get_param('preview_section_id'));
        if ($globalPartId === null || $pageId === null || $previewSectionId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        try {
            $preview = $this->previewReusableSection->render($globalPartId, $pageId, $previewSectionId);
        } catch (\OutOfBoundsException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (\Throwable $failure) {
            $this->recordFailure('preview_source.render', $globalPartId, $failure);
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed, ['retryable' => true]);
        }

        return ApiResponse::ok([
            'global_part' => $this->sourceResponse($globalPartId, $preview['source'], $preview['content']) + [
                'rendered_html' => $preview['rendered_html'],
                'compiled_css' => $preview['compiled_css'],
            ],
        ])->toResponse();
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function sourceResponse(int $globalPartId, array $source, ?array $content = null): array
    {
        return [
            'id' => $globalPartId,
            'title' => (string) ($source['title'] ?? ''),
            'type' => GlobalPartType::Section->value,
            'content' => $content ?? $source['content'],
        ];
    }

    private function recordFailure(string $step, int $globalPartId, \Throwable $failure): void
    {
        try {
            $this->failureReporter?->report('global part', $globalPartId, $step, $failure);
        } catch (\Throwable) {
            // A report failure cannot change the controlled REST response.
        }
    }

    private function creationUncertainError(
        string $step,
        GlobalPartCreationUncertainException $failure,
    ): \WP_Error {
        $globalPartId = $failure->globalPartId();
        $this->recordFailure($step . '.uncertain', $globalPartId, $failure);

        return ApiResponse::error(ErrorMessage::GpCreationUncertain, [
            'retryable' => false,
            'requires_read' => true,
            'possible_global_part_id' => $globalPartId,
            'detail' => 'The creation result is uncertain. Read the reusable list before another create request.',
        ]);
    }
}

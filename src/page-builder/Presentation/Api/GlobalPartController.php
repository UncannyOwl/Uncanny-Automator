<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\SourcePackage\ReusableSourcePackageService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\SourcePackage\SourcePackageValidationException;

final class GlobalPartController
{
    public function __construct(
        private readonly GlobalPartService $globalPartService,
        private readonly PermissionChecker $permissions,
        private readonly GlobalPartDefaultsService $defaultsService,
        private readonly ReusableSourcePackageService $sourcePackages,
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
        } catch (\RuntimeException $e) {
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
        } catch (\RuntimeException $e) {
            return ApiResponse::error(ErrorMessage::GpCreationFailed);
        }

        $assignedAsDefault = false;
        if ($setAsDefault && $result['id'] > 0) {
            $assignedAsDefault = $this->defaultsService->setDefaultId($gpType, $result['id']);
        }

        return ApiResponse::created([
            'id'         => $result['id'],
            'title'      => $result['title'],
            'type'       => $result['type'],
            'is_default' => $assignedAsDefault,
        ])->toResponse();
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
        } catch (\Throwable) {
            return ApiResponse::error(ErrorMessage::GpCreationFailed);
        }

        return ApiResponse::created([
            'global_part' => [
                'id'    => (int) $result['id'],
                'title' => (string) $result['title'],
                'type'  => (string) $result['type'],
            ],
        ])->toResponse();
    }

    public function listAll(\WP_REST_Request $request): \WP_REST_Response
    {
        $items = [];
        foreach (GlobalPartType::validValues() as $typeValue) {
            $type = GlobalPartType::fromString($typeValue);
            foreach ($this->globalPartService->listByType($type) as $part) {
                $items[] = [
                    'id'    => (int) $part['post_id'], // Bubble the error.
                    'title' => (string) ($part['title'] ?? ''),
                    'type'  => $typeValue,
                ];
            }
        }

        return ApiResponse::ok(['global_parts' => $items])->toResponse();
    }

    public function readSectionSource(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $globalPartId = RequestId::fromUrl($request, 'global_part_id');
        if ($globalPartId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        $source = $this->globalPartService->resolveSourceContent($globalPartId);
        if (!is_array($source) || !is_array($source['content'] ?? null)) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        }

        return ApiResponse::ok([
            'global_part' => [
                'id' => $globalPartId,
                'title' => (string) ($source['title'] ?? ''),
                'type' => GlobalPartType::Section->value,
                'content' => $source['content'],
            ],
        ])->toResponse();
    }
}

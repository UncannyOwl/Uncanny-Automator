<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\Canvas\CanvasGlobalPartsProviderInterface;
use UncannyPageBuilder\Application\Canvas\CanvasRefreshRendererInterface;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Section\SectionCollection;

final class LayoutController
{
    public function __construct(
        private readonly SectionService $sectionService,
        private readonly PermissionChecker $permissions,
        private readonly ?GlobalPartService $globalPartService = null,
        private readonly ?CanvasGlobalPartsProviderInterface $globalParts = null,
        private readonly ?EditorLockWriteGuard $editorLock = null,
        private readonly ?SelectEditorPageSource $pageSources = null,
        private readonly ?ShadowCompiler $compiler = null,
        private readonly ?FailureReporterInterface $failureReporter = null,
        private readonly ?CanvasRefreshRendererInterface $refreshRenderer = null,
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/layout', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create'],
            'permission_callback' => [$this->permissions, 'canManage'],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/layout/(?P<page_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'read'],
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args'                => ['page_id' => RequestId::nonNegativeRouteArgument()],
        ]);
    }

    public function create(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = 0;
        $writeStarted = false;

        try {
            $pageId      = absint($request->get_param('page_id'));
            $content     = $request->get_param('content');
            $sectionName = sanitize_text_field($request->get_param('section_name') ?? '');
            $action      = sanitize_text_field($request->get_param('action') ?? '');
            $sectionId   = $request->get_param('section_id');

            // Support adding a section from a global part (reusable).
            $globalPartId = $request->get_param('global_part_id');
            if ($globalPartId !== null && $content === null && $this->globalPartService !== null) {
                $globalPartId = absint($globalPartId);
                if (!$this->permissions->canEditPost($globalPartId)) {
                    return ApiResponse::error(ErrorMessage::GlobalPartEditForbidden);
                }
                $resolved = $this->globalPartService->resolveSourceContent($globalPartId);
                if ($resolved === null) {
                    return ApiResponse::error(ErrorMessage::SectionNotFound);
                }
                $content = $resolved['content'];
                if ($sectionName === '') {
                    $sectionName = sanitize_text_field($resolved['title']);
                }
            }

            if (!$pageId || !is_array($content) || !$content) {
                return ApiResponse::error(ErrorMessage::LayoutParamsRequired);
            }

            if (!$this->permissions->canEditPage($pageId)) {
                return ApiResponse::error(ErrorMessage::PageEditForbidden);
            }

            if (!$this->sectionService->isPageOwned($pageId)) {
                return ApiResponse::error(ErrorMessage::PageNotOwned);
            }

            $ownershipError = $this->editorLock?->check($request, $pageId, 'layout.create');
            if ($ownershipError instanceof \WP_Error) {
                return $ownershipError;
            }

            try {
                $writeStarted = true;
                $result = $this->sectionService->create(
                    $pageId,
                    $sectionName,
                    $content,
                    $action ?: null,
                    $sectionId !== null ? absint($sectionId) : null,
                );
                return ApiResponse::ok($result)->toResponse();
            } catch (SectionValidationException $e) {
                return ApiResponse::validationError($e);
            } catch (PageNotFoundException $e) {
                return ApiResponse::error(ErrorMessage::PageNotFound);
            } catch (SectionNotFoundException $e) {
                return ApiResponse::error(ErrorMessage::SectionNotFound);
            } catch (StaleSourceGenerationException $e) {
                return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $e->scope()]);
            } catch (\Throwable $failure) {
                $this->recordFailure('page layout', $pageId, 'create.uncertain', $failure);
                return ApiResponse::error(ErrorMessage::WriteResultUncertain, [
                    'retryable' => false,
                    'requires_read' => true,
                    'detail' => 'The write result is uncertain. Read the current layout before another write.',
                ]);
            }
        } catch (\Throwable $failure) {
            $this->recordFailure('page layout', $pageId, $writeStarted ? 'create.response' : 'create.preflight', $failure);
            return ApiResponse::error($writeStarted ? ErrorMessage::WriteResultUncertain : ErrorMessage::ControlInvokeFailed, $writeStarted
                ? [
                    'retryable' => false,
                    'requires_read' => true,
                    'detail' => 'The write result is uncertain. Read the current layout before another write.',
                ]
                : ['retryable' => true]);
        }
    }

    public function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = RequestId::nonNegativeFromUrl($request, 'page_id');
        $globalPartId = absint($request->get_param('global_part_id'));

        if ($pageId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        // Terminal boundary: no Throwable may escape the REST callback.
        try {
            // Reusable canvases refresh through the same layout facade, but they
            // must read from the global-part lane before the page lane.
            if ($globalPartId > 0) {
                if (!$this->permissions->canEditPost($globalPartId)) {
                    return ApiResponse::error(ErrorMessage::GlobalPartEditForbidden);
                }

                if ($this->globalPartService === null) {
                    return ApiResponse::error(ErrorMessage::PageNotFoundGeneric);
                }

                $attempts = $this->sourceGenerations instanceof SourceGenerationStoreInterface ? 3 : 1;
                for ($attempt = 0; $attempt < $attempts; $attempt++) {
                    $globalGeneration = $this->sourceGenerations?->globalGeneration();
                    $projection = fn(): ?array => $this->buildGlobalPartLayout($globalPartId);
                    $layout = $this->refreshRenderer instanceof CanvasRefreshRendererInterface
                        ? $this->refreshRenderer->withOwnerRenderContext($globalPartId, $projection)
                        : $projection();

                    if (
                        !$this->sourceGenerations instanceof SourceGenerationStoreInterface
                        || $globalGeneration === $this->sourceGenerations->globalGeneration()
                    ) {
                        return $layout === null
                            ? ApiResponse::error(ErrorMessage::PageNotFoundGeneric)
                            : ApiResponse::ok($layout)->toResponse();
                    }
                }

                throw new \RuntimeException(
                    'The reusable source changed while the canvas refresh projection was being built.',
                );
            }

            if (!$this->permissions->canEditPage($pageId)) {
                return ApiResponse::error(ErrorMessage::PageEditForbidden);
            }

            if (!$this->sectionService->isPageOwned($pageId)) {
                return ApiResponse::error(ErrorMessage::PageNotOwned);
            }

            $loadWorking = trim((string) $request->get_param('source')) === 'working';
            $attempts = $this->sourceGenerations instanceof SourceGenerationStoreInterface ? 3 : 1;
            for ($attempt = 0; $attempt < $attempts; $attempt++) {
                $pageGeneration = $this->sourceGenerations?->pageGeneration($pageId);
                $globalGeneration = $this->sourceGenerations?->globalGeneration();
                $layout = $this->buildPageLayout($pageId, $loadWorking);

                if (
                    !$this->sourceGenerations instanceof SourceGenerationStoreInterface
                    || (
                        $pageGeneration === $this->sourceGenerations->pageGeneration($pageId)
                        && $globalGeneration === $this->sourceGenerations->globalGeneration()
                    )
                ) {
                    return ApiResponse::ok($layout)->toResponse();
                }
            }

            throw new \RuntimeException(
                'The page or reusable source changed while the canvas refresh projection was being built.',
            );
        } catch (\Throwable $failure) {
            $this->recordFailure('page layout', $pageId, 'read', $failure);
            return ApiResponse::error(ErrorMessage::ControlInvokeFailed, ['retryable' => true]);
        }
    }

    /** @return array<string, mixed>|null */
    private function buildGlobalPartLayout(int $globalPartId): ?array
    {
        $layout = $this->globalPartService?->getLayout($globalPartId);
        if ($layout === null) {
            return null;
        }

        foreach ($layout['sections'] as $i => $section) {
            $layout['sections'][$i]['editable_capabilities'] = $section['editable_capabilities'] ?? [];
        }

        if ($this->refreshRenderer instanceof CanvasRefreshRendererInterface) {
            $layout['rendered_sections'] = $this->refreshRenderer->renderSections($layout['sections'], $globalPartId);
            $layout['has_runtime_javascript'] = $this->refreshRenderer->hasCurrentJavaScript($globalPartId);
        }

        return $layout;
    }

    /** @return array<string, mixed> */
    private function buildPageLayout(int $pageId, bool $loadWorking): array
    {
        $sourceSelection = $this->pageSources?->forPage($pageId);
        $publishedSnapshot = !$loadWorking && $sourceSelection?->loadedSource() === 'published'
            ? $sourceSelection->publishedSnapshot()
            : null;
        if ($publishedSnapshot !== null && !$this->compiler instanceof ShadowCompiler) {
            throw new \RuntimeException('Published editor source compilation is unavailable.');
        }
        $publishedSource = $publishedSnapshot?->source();
        if (is_array($publishedSource)) {
            $sections = SectionCollection::fromArray(
                $publishedSource['sections'] ?? [],
                $pageId,
                $publishedSnapshot->pageGeneration(),
            );
            $layout = [
                'page_id' => $pageId,
                'sections' => $sections->toArray(),
                'compiled_css' => $this->compiler->compile($sections)->minifiedCss(),
                'warnings' => [],
            ];
        } else {
            $layout = $this->sectionService->getLayout($pageId);
        }
        $capabilitiesMap = is_array($publishedSource)
            ? $this->sectionService->buildEditableCapabilitiesMapForSource(
                $pageId,
                is_array($publishedSource['sections'] ?? null) ? $publishedSource['sections'] : [],
            )
            : $this->sectionService->buildEditableCapabilitiesMap($pageId);

        foreach ($layout['sections'] as $i => $section) {
            $sectionId = $section['id'] ?? 0;
            $layout['sections'][$i]['editable_capabilities'] = $capabilitiesMap[$sectionId] ?? [];
        }

        $projection = function () use (&$layout, $pageId, $publishedSource): void {
            if ($this->globalParts instanceof CanvasGlobalPartsProviderInterface) {
                $layout['global_parts']['header'] = is_array($publishedSource)
                    ? $this->globalParts->headerForPageSource($pageId, $publishedSource)
                    : $this->globalParts->headerForPage($pageId);
            }
            if (!$this->refreshRenderer instanceof CanvasRefreshRendererInterface) {
                if ($this->globalParts instanceof CanvasGlobalPartsProviderInterface) {
                    $layout['global_parts']['footer'] = is_array($publishedSource)
                        ? $this->globalParts->footerForPageSource($pageId, $publishedSource)
                        : $this->globalParts->footerForPage($pageId);
                }
                return;
            }

            $layout['rendered_sections'] = $this->refreshRenderer->renderSections($layout['sections'], $pageId);
            if ($this->globalParts instanceof CanvasGlobalPartsProviderInterface) {
                $layout['global_parts']['footer'] = is_array($publishedSource)
                    ? $this->globalParts->footerForPageSource($pageId, $publishedSource)
                    : $this->globalParts->footerForPage($pageId);
            }
            $header = is_array($layout['global_parts']['header'] ?? null)
                ? $layout['global_parts']['header']
                : null;
            $footer = is_array($layout['global_parts']['footer'] ?? null)
                ? $layout['global_parts']['footer']
                : null;
            $layout['has_runtime_javascript'] = is_array($publishedSource)
                ? $this->refreshRenderer->hasPageSourceJavaScript(
                    $pageId,
                    (string) ($publishedSource['custom_javascript'] ?? ''),
                    $header,
                    $footer,
                )
                : $this->refreshRenderer->hasCurrentJavaScript($pageId, $header, $footer);
        };

        if ($this->refreshRenderer instanceof CanvasRefreshRendererInterface) {
            $this->refreshRenderer->withOwnerRenderContext($pageId, $projection);
        } else {
            $projection();
        }

        $layout['source'] = $sourceSelection?->toArray();
        if ($loadWorking && is_array($layout['source'])) {
            $layout['source']['loaded_source'] = 'working';
            $layout['source']['loaded_working_generation'] = $sourceSelection?->workingGeneration();
            $layout['source']['loaded_snapshot_id'] = null;
        }

        return $layout;
    }

    private function recordFailure(string $scope, int $ownerId, string $step, \Throwable $failure): void
    {
        try {
            $this->failureReporter?->report($scope, $ownerId, $step, $failure);
        } catch (\Throwable) {
            // A report failure cannot change the controlled REST response.
        }
    }
}

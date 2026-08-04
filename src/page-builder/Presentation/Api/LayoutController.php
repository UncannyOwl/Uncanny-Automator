<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\Canvas\CanvasGlobalPartsProviderInterface;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
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
        ]);
    }

    public function create(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
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

        if (!$pageId || !$content) {
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
        }
    }

    public function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = absint($request->get_param('page_id'));
        $globalPartId = absint($request->get_param('global_part_id'));

        // Reusable canvases refresh through the same layout facade, but they
        // must read from the global-part lane before the page lane.
        if ($globalPartId > 0) {
            if (!$this->permissions->canEditPost($globalPartId)) {
                return ApiResponse::error(ErrorMessage::GlobalPartEditForbidden);
            }

            if ($this->globalPartService === null) {
                return ApiResponse::error(ErrorMessage::PageNotFoundGeneric);
            }

            $layout = $this->globalPartService->getLayout($globalPartId);
            if ($layout === null) {
                return ApiResponse::error(ErrorMessage::PageNotFoundGeneric);
            }

            foreach ($layout['sections'] as $i => $section) {
                $layout['sections'][$i]['editable_capabilities'] = $section['editable_capabilities'] ?? [];
            }

            return ApiResponse::ok($layout)->toResponse();
        }

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::PageNotOwned);
        }

        $sourceSelection = $this->pageSources?->forPage($pageId);
        $loadWorking = trim((string) $request->get_param('source')) === 'working';
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

        if ($this->globalParts instanceof CanvasGlobalPartsProviderInterface) {
            $layout['global_parts'] = is_array($publishedSource)
                ? $this->globalParts->forPageSource($pageId, $publishedSource)
                : $this->globalParts->forPage($pageId);
        }
        $layout['source'] = $sourceSelection?->toArray();
        if ($loadWorking && is_array($layout['source'])) {
            $layout['source']['loaded_source'] = 'working';
            $layout['source']['loaded_working_generation'] = $sourceSelection?->workingGeneration();
            $layout['source']['loaded_snapshot_id'] = null;
        }

        return ApiResponse::ok($layout)->toResponse();
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

use UncannyPageBuilder\Api\AgentPageController\BindingController;
use UncannyPageBuilder\Api\AgentPageController\CanvasController;
use UncannyPageBuilder\Api\AgentPageController\PageContextController;
use UncannyPageBuilder\Api\AgentPageController\PartEdit\PartEditController;
use UncannyPageBuilder\Api\AgentPageController\PartRead\PartReadController;
use UncannyPageBuilder\Api\AgentPageController\ReusableController;
use UncannyPageBuilder\Api\AgentPageController\RuntimeController;
use UncannyPageBuilder\Api\AgentPageController\Routes\AgentPageRouteRegistrar;
use UncannyPageBuilder\Api\AgentPageController\SectionCreate\SectionCreateController;
use UncannyPageBuilder\Api\AgentPageController\SectionManagementController;
use UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch\SectionSourcePatchController;

/**
 * Stable Agent API facade and map of page-builder responsibilities.
 *
 * Each public method preserves an Agent-facing entry point while delegating
 * its behavior to the focused controller that owns that responsibility. Keep
 * business logic out of this class: new behavior belongs in the appropriate
 * controller under AgentPageController/.
 *
 * Magic Bridge uses LayoutController and SectionController for its own flows.
 */
final class AgentPageController
{
    /**
     * Builds the facade from the controllers that own each Agent operation.
     */
    public function __construct(
        private readonly RuntimeController $runtimeController,
        private readonly SectionManagementController $sectionManagementController,
        private readonly ReusableController $reusableController,
        private readonly BindingController $bindingController,
        private readonly PageContextController $pageContextController,
        private readonly CanvasController $canvasController,
        private readonly SectionSourcePatchController $sectionSourcePatchController,
        private readonly PartReadController $partReadController,
        private readonly PartEditController $partEditController,
        private readonly SectionCreateController $sectionCreateController,
        private readonly AgentPageRouteRegistrar $routes,
    ) {}

    /**
     * Registers the stable Agent page-builder REST routes.
     */
    public function registerRoutes(): void
    {
        $this->routes->register($this);
    }

    // ═══════════════════════════════════════════════════════
    // PAGE AND PART CONTEXT
    // ═══════════════════════════════════════════════════════

    /**
     * Reads the consolidated page context used by Agent workflows.
     */
    public function readPageContext(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->pageContextController->readContext($request);
    }

    /**
     * Reads the requested section or global-part representation.
     */
    public function readPart(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->partReadController->read($request);
    }

    // ═══════════════════════════════════════════════════════
    // PART EDITING
    // ═══════════════════════════════════════════════════════

    /**
     * Dispatches a stable edit_part operation to its owning write controller.
     */
    public function editPart(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->partEditController->edit($request);
    }

    // ═══════════════════════════════════════════════════════
    // JAVASCRIPT RUNTIME
    // ═══════════════════════════════════════════════════════

    /**
     * Reads page or global-part JavaScript runtime source.
     */
    public function readRuntime(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->runtimeController->read($request);
    }

    /**
     * Applies a JavaScript runtime source operation.
     */
    public function editRuntime(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->runtimeController->edit($request);
    }

    /**
     * Previews a JavaScript runtime change without saving it.
     */
    public function previewRuntimeChange(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->runtimeController->preview($request);
    }

    // ═══════════════════════════════════════════════════════
    // SECTION AND SOURCE WRITES
    // ═══════════════════════════════════════════════════════

    /**
     * Creates a page section or bootstraps a reusable global-part source.
     */
    public function createSection(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->sectionCreateController->create($request);
    }

    /**
     * Previews an edit_part source change without saving it.
     */
    public function previewChange(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->sectionSourcePatchController->previewChange($request);
    }

    // ═══════════════════════════════════════════════════════
    // BINDINGS
    // ═══════════════════════════════════════════════════════

    /**
     * Dispatches binding search, guide, query, or template operations.
     */
    public function manageBinding(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->bindingController->manage($request);
    }

    // ═══════════════════════════════════════════════════════
    // CANVAS AND SECTION LIFECYCLE
    // ═══════════════════════════════════════════════════════

    /**
     * Dispatches page and global-part canvas lifecycle operations.
     */
    public function manageCanvas(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->canvasController->manage($request);
    }

    /**
     * Dispatches reusable-section lifecycle operations.
     */
    public function manageReusable(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->reusableController->manage($request);
    }

    /**
     * Dispatches page-section reorder and delete operations.
     */
    public function manageSections(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->sectionManagementController->manage($request);
    }
}

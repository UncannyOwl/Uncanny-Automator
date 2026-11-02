<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\Routes;

use UncannyPageBuilder\Api\AgentPageController;
use UncannyPageBuilder\Api\AgentPageController\AgentWrite\AgentWriteGuard;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\Agent\SharedRestOperationRegistryInterface;
use UncannyPageBuilder\Infrastructure\WordPress\DiagnosticLog;

/**
 * Registers the stable Agent page REST surface against the public facade.
 */
final class AgentPageRouteRegistrar
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly AgentWriteGuard $writes,
        private readonly ?SharedRestOperationRegistryInterface $sharedRest = null,
    ) {}

    public function register(AgentPageController $controller): void
    {
        $namespace = 'uncanny-page-builder/v1';
        $positiveId = static fn (): array => RequestId::routeArgument();
        $register = function (string $route, array $handler) use ($namespace): void {
            \register_rest_route($namespace, $route, $handler);
            $tool = match ($route) {
                '/agent/page/(?P<page_id>\\d+)/context' => 'read_page_context',
                '/agent/page/(?P<page_id>\\d+)/sections' => 'create_section',
                '/agent/part' => 'read_part',
                '/agent/part/edit' => 'edit_part',
                '/agent/canvas' => 'manage_canvas',
                '/agent/runtime' => 'read_runtime',
                '/agent/runtime/edit' => 'edit_runtime',
                '/agent/runtime/preview' => 'preview_runtime_change',
                '/agent/part/preview' => 'preview_change',
                '/agent/page/(?P<page_id>\\d+)/sections/manage' => 'manage_sections',
                '/agent/reusable' => 'manage_reusable',
                '/agent/binding' => 'manage_binding',
                default => null,
            };
            if ($tool !== null) {
                try {
                    $this->sharedRest?->remember(
                        '/' . $namespace . $route,
                        $handler['methods'],
                        $handler['callback'],
                        $handler['permission_callback'],
                        $tool,
                    );
                } catch (\Throwable $failure) {
                    DiagnosticLog::write(sprintf('[Uncanny Page Builder] Shared REST "%s" registration failed (%s).', $tool, $failure::class));
                }
            }
        };

        // ── Read facades ─────────────────────────────────────────────
        $register('/agent/page/(?P<page_id>\d+)/context', [
            'methods' => 'GET',
            'callback' => [$controller, 'readPageContext'],
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args' => [
                'page_id' => $positiveId(),
                'include' => [
                    'required' => false,
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        $register('/agent/part', [
            'methods' => 'GET',
            'callback' => [$controller, 'readPart'],
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args' => [
                'kind' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                'section_id' => ['required' => false, 'sanitize_callback' => 'absint'],
                'global_part_id' => ['required' => false, 'sanitize_callback' => 'absint'],
                'part_type' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                'page_id' => ['required' => false, 'sanitize_callback' => 'absint'],
                'include' => ['required' => false, 'default' => 'manifest', 'sanitize_callback' => 'sanitize_text_field'],
                'target_types' => ['required' => false],
                'include_css' => ['required' => false],
                'binding_id' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        $register('/agent/runtime', [
            'methods' => 'GET',
            'callback' => [$controller, 'readRuntime'],
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args' => [
                'scope' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'page_id' => ['required' => false, 'sanitize_callback' => 'absint'],
                'global_part_id' => ['required' => false, 'sanitize_callback' => 'absint'],
            ],
        ]);

        // ── Write facades ────────────────────────────────────────────
        $register('/agent/part/edit', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('edit_part', [$controller, 'editPart']),
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        $register('/agent/runtime/edit', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('edit_runtime', [$controller, 'editRuntime']),
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        $register('/agent/runtime/preview', [
            'methods' => 'POST',
            'callback' => [$controller, 'previewRuntimeChange'],
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        $register('/agent/part/preview', [
            'methods' => 'POST',
            'callback' => [$controller, 'previewChange'],
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        $register('/agent/page/(?P<page_id>\d+)/sections', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('create_section', [$controller, 'createSection']),
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args' => ['page_id' => $positiveId()],
        ]);

        $register('/agent/page/(?P<page_id>\d+)/sections/manage', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('manage_sections', [$controller, 'manageSections']),
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args' => ['page_id' => $positiveId()],
        ]);

        $register('/agent/canvas', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('manage_canvas', [$controller, 'manageCanvas']),
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        $register('/agent/reusable', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('manage_reusable', [$controller, 'manageReusable']),
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        $register('/agent/binding', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('manage_binding', [$controller, 'manageBinding']),
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);
    }
}

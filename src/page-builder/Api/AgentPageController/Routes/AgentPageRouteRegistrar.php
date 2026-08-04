<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\Routes;

use UncannyPageBuilder\Api\AgentPageController;
use UncannyPageBuilder\Api\AgentPageController\AgentWrite\AgentWriteGuard;
use UncannyPageBuilder\Api\PermissionChecker;

/**
 * Registers the stable Agent page REST surface against the public facade.
 */
final class AgentPageRouteRegistrar
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly AgentWriteGuard $writes,
    ) {}

    public function register(AgentPageController $controller): void
    {
        $namespace = 'uncanny-page-builder/v1';
        $positiveId = static fn (): array => [
            'required' => true,
            'validate_callback' => static fn (mixed $value): bool => is_numeric($value) && (int) $value > 0,
            'sanitize_callback' => 'absint',
        ];

        // ── Read facades ─────────────────────────────────────────────
        \register_rest_route($namespace, '/agent/page/(?P<page_id>\d+)/context', [
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

        \register_rest_route($namespace, '/agent/part', [
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

        \register_rest_route($namespace, '/agent/runtime', [
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
        \register_rest_route($namespace, '/agent/part/edit', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('edit_part', [$controller, 'editPart']),
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        \register_rest_route($namespace, '/agent/runtime/edit', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('edit_runtime', [$controller, 'editRuntime']),
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        \register_rest_route($namespace, '/agent/runtime/preview', [
            'methods' => 'POST',
            'callback' => [$controller, 'previewRuntimeChange'],
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        \register_rest_route($namespace, '/agent/part/preview', [
            'methods' => 'POST',
            'callback' => [$controller, 'previewChange'],
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        \register_rest_route($namespace, '/agent/page/(?P<page_id>\d+)/sections', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('create_section', [$controller, 'createSection']),
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args' => ['page_id' => $positiveId()],
        ]);

        \register_rest_route($namespace, '/agent/page/(?P<page_id>\d+)/sections/manage', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('manage_sections', [$controller, 'manageSections']),
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args' => ['page_id' => $positiveId()],
        ]);

        \register_rest_route($namespace, '/agent/canvas', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('manage_canvas', [$controller, 'manageCanvas']),
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        \register_rest_route($namespace, '/agent/reusable', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('manage_reusable', [$controller, 'manageReusable']),
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);

        \register_rest_route($namespace, '/agent/binding', [
            'methods' => 'POST',
            'callback' => $this->writes->guard('manage_binding', [$controller, 'manageBinding']),
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);
    }
}

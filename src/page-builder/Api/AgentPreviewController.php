<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

use UncannyPageBuilder\Application\Agent\SharedRestOperationRegistryInterface;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Preview\CreatePagePreview;
use UncannyPageBuilder\Application\Preview\PreviewSnapshotStoreInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Infrastructure\WordPress\DiagnosticLog;

final class AgentPreviewController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly SectionRepositoryInterface $sections,
        private readonly PageDetailsPortInterface $details,
        private readonly CreatePagePreview $previews,
        private readonly PreviewSnapshotStoreInterface $snapshots,
        private readonly SharedRestOperationRegistryInterface $sharedRest,
    ) {}

    public function registerRoutes(): void
    {
        $handler = [
            'methods' => 'POST',
            'callback' => [$this, 'create'],
            'permission_callback' => [$this->permissions, 'canManage'],
        ];
        register_rest_route('uncanny-page-builder/v1', '/agent/preview', $handler);
        try {
            $this->sharedRest->remember('/uncanny-page-builder/v1/agent/preview', 'POST', $handler['callback'], $handler['permission_callback'], 'create_preview_link');
        } catch (\Throwable $failure) {
            DiagnosticLog::write('[Uncanny Page Builder] Preview discovery registration failed (' . $failure::class . ').');
        }
        register_rest_route('uncanny-page-builder/v1', '/page-preview/(?P<page_id>\d+)/(?P<token>[a-f0-9]{64})', [
            'methods' => 'GET',
            'callback' => [$this, 'read'],
            // The unguessable, expiring token authorizes only this fixed HTML.
            'permission_callback' => '__return_true',
        ]);
    }

    public function create(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = RequestId::positive($request->get_param('page_id'));
        if ($pageId === null || array_diff(array_keys($request->get_params()), ['page_id']) !== []) {
            return new \WP_Error('invalid_preview_request', 'Provide one positive page_id.', ['status' => 400]);
        }
        if (!$this->permissions->canEditPage($pageId)) {
            return new \WP_Error('preview_forbidden', 'You cannot preview this page.', ['status' => 403]);
        }
        try {
            if (!$this->sections->isOwnedPage($pageId) || ($details = $this->details->find($pageId)) === null) {
                return new \WP_Error('preview_page_not_owned', 'Use a Page Builder-owned page.', ['status' => 404]);
            }
            return new \WP_REST_Response($this->previews->create($pageId, $details->title(), $details->permalink()) + [
                'read_only' => true,
                'live_page' => 'unchanged',
                'notice' => 'Anyone with this link can view this fixed snapshot for 15 minutes. A new link replaces the old link. This standalone preview includes enabled JavaScript, but does not test theme chrome, server forms, or network requests. Check the static rendering report for omitted bindings. Source edits need a new preview. Publishing still requires a human.',
            ], 200, ['Cache-Control' => 'private, no-store']);
        } catch (\Throwable $failure) {
            DiagnosticLog::write('[Uncanny Page Builder] Preview creation failed (' . $failure::class . ').');
            return new \WP_Error('preview_failed', 'The preview could not be created. No page was published.', ['status' => 500]);
        }
    }

    public function read(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $params = $request->get_url_params();
            $pageId = RequestId::positive($params['page_id'] ?? null);
            $html = $pageId === null ? null : $this->snapshots->read($pageId, (string) ($params['token'] ?? ''));
        } catch (\Throwable) {
            $html = null;
        }
        return new \WP_REST_Response($html ?? 'Preview unavailable or expired.', $html === null ? 404 : 200, [
            'X-UPB-Preview' => '1',
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox allow-scripts; base-uri 'none'; form-action 'none'; connect-src 'none'; script-src 'unsafe-inline' 'unsafe-eval' data:",
        ]);
    }
}

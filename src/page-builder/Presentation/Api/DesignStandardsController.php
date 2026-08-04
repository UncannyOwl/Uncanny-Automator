<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Presentation\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\DesignStandards\DesignStandardsProfile;
use UncannyPageBuilder\Domain\DesignStandards\PageDesignOverrides;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Publishing\PageSourceSnapshotRepositoryInterface;

/**
 * REST endpoints for design standards.
 *
 * GET  /uncanny-page-builder/v1/design-standards[?page_id=N]
 * PUT  /uncanny-page-builder/v1/design-standards
 * GET  /uncanny-page-builder/v1/design-standards/page/{page_id}
 * PUT  /uncanny-page-builder/v1/design-standards/page/{page_id}
 */
final class DesignStandardsController
{
    public function __construct(
        private readonly DesignStandardsService $service,
        private readonly SectionService $sectionService,
        private readonly PermissionChecker $permissions,
        private readonly ?WorkingCanvasRefresherInterface $workingCanvas = null,
        private readonly ?EditorLockWriteGuard $editorLock = null,
        private readonly ?PageSourceSnapshotRepositoryInterface $sourceSnapshots = null,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/design-standards', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'read'],
                'permission_callback' => [$this->permissions, 'canEdit'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this->permissions, 'canManage'],
            ],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/design-standards/page/(?P<page_id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'readPageOverrides'],
                'permission_callback' => [$this->permissions, 'canEdit'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'updatePageOverrides'],
                'permission_callback' => [$this->permissions, 'canEdit'],
            ],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/design-standards/section/(?P<section_id>\d+)/consumed', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'readSectionConsumed'],
                'permission_callback' => [$this->permissions, 'canEdit'],
            ],
        ]);
    }

    public function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = absint($request->get_param('page_id'));

        if ($pageId > 0) {
            if (!$this->permissions->canEditPage($pageId)) {
                return ApiResponse::error(ErrorMessage::PageEditForbidden);
            }
            if (!$this->sectionService->isPageOwned($pageId)) {
                return ApiResponse::error(ErrorMessage::NotEnginePage);
            }
            $overrides = $this->service->loadPageOverrides($pageId);
            $result = $this->service->resolveForPageWithAudit($pageId, $overrides);
            return ApiResponse::ok([
                'overrides'     => $overrides->toArray(),
                'resolved'      => $result->resolved()->toArray(),
                'applied_keys'  => $result->appliedKeys(),
                'rejected_keys' => $result->rejectedKeys(),
                'locked_keys'   => $result->lockedKeys(),
            ])->toResponse();
        }

        $profile = $this->service->resolve()->toArray();

        return ApiResponse::ok($profile)->toResponse();
    }

    /**
     * Caution for clients: the sitewide GET returns the profile AFTER the
     * `uncanny_engine_bootstrap_theme` runtime filter, while this PUT
     * persists the submitted values raw. On a site using that filter, a
     * blind GET→edit→PUT round-trip bakes the filter overlay into storage.
     * Build full-profile writes on the stored baseline where possible.
     */
    public function update(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $body = $request->get_json_params();

        if (empty($body) || !is_array($body)) {
            return ApiResponse::error(ErrorMessage::InvalidBody);
        }

        // Require tokens to prevent partial writes.
        if (!isset($body['tokens']) || !is_array($body['tokens'])) {
            return ApiResponse::error(ErrorMessage::IncompleteProfile);
        }

        $storedProfile = $this->service->loadProfile();
        $body = $this->preserveTransitionalProfileBuckets($body, $storedProfile);
        $storedLockedKeys = $storedProfile->lockedKeys();
        $body['locked_keys'] = $storedLockedKeys;

        try {
            $profile = DesignStandardsProfile::fromArray($body);
        } catch (\Throwable $e) {
            return ApiResponse::error(ErrorMessage::MalformedProfile, ['detail' => $e->getMessage()]);
        }

        $lockedChanges = $this->changedLockedKeys($storedProfile, $profile);
        if ($this->hasLockedChanges($lockedChanges)) {
            return ApiResponse::error(ErrorMessage::LockedDesignToken, [
                'locked_keys' => $lockedChanges,
            ]);
        }

        try {
            $artifactsQueued = $this->service->save($profile);
        } catch (StaleSourceGenerationException $e) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $e->scope()]);
        }

        $response = $this->service->loadProfile()->toArray();
        if (!$artifactsQueued) {
            $response['rebuild_warning'] = DesignStandardsService::workingCanvasRefreshWarning();
        }

        return ApiResponse::ok($response)->toResponse();
    }

    /**
     * GET /design-standards/page/{page_id}
     *
     * Returns raw page overrides plus the resolved profile with audit data.
     */
    public function readPageOverrides(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = absint($request['page_id']);

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::NotEnginePage);
        }

        $source = trim((string) $request->get_param('source'));
        $snapshotId = absint($request->get_param('snapshot_id'));
        $snapshot = $source === 'published' && $snapshotId > 0
            ? $this->sourceSnapshots?->findForPage($pageId, $snapshotId)
            : null;
        $overrides = $snapshot !== null
            ? PageDesignOverrides::fromArray(
                is_array($snapshot->source()['page_design_overrides'] ?? null)
                    ? $snapshot->source()['page_design_overrides']
                    : [],
            )
            : $this->service->loadPageOverrides($pageId);
        $result = $this->service->resolveForPageWithAudit($pageId, $overrides);

        return ApiResponse::ok([
            'overrides'     => $overrides->toArray(),
            'resolved'      => $result->resolved()->toArray(),
            'applied_keys'  => $result->appliedKeys(),
            'rejected_keys' => $result->rejectedKeys(),
            'locked_keys'   => $result->lockedKeys(),
        ])->toResponse();
    }

    /**
     * PUT /design-standards/page/{page_id}
     *
     * Saves page-level overrides. Returns resolved profile with audit data.
     */
    public function updatePageOverrides(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $pageId = absint($request['page_id']);

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::NotEnginePage);
        }

        $ownershipError = $this->editorLock?->check($request, $pageId, 'design_standards.page');
        if ($ownershipError instanceof \WP_Error) {
            return $ownershipError;
        }

        $body = $request->get_json_params();

        if (empty($body) || !is_array($body)) {
            return ApiResponse::error(ErrorMessage::InvalidOverridesBody);
        }

        $overridesBody = $this->pageOverridesPayload($body);
        if ($overridesBody === null) {
            return ApiResponse::error(ErrorMessage::MalformedOverrides, [
                'detail' => 'The overrides field must be an object when provided.',
            ]);
        }

        $persistedBefore = $this->service->loadPageOverrides($pageId);
        $overridesBody = $this->preserveTransitionalOverrideBuckets($overridesBody, $persistedBefore);

        try {
            $overrides = PageDesignOverrides::fromArray($overridesBody);
        } catch (\Throwable $e) {
            return ApiResponse::error(ErrorMessage::MalformedOverrides, ['detail' => $e->getMessage()]);
        }

        try {
            $result = $this->service->savePageOverrides($pageId, $overrides);
            $this->refreshWorkingCanvas($pageId);
        } catch (StaleSourceGenerationException $e) {
            return ApiResponse::error(ErrorMessage::StaleSourceGeneration, ['scope' => $e->scope()]);
        }
        $persistedOverrides = $this->service->loadPageOverrides($pageId);

        return ApiResponse::ok([
            'overrides'     => $persistedOverrides->toArray(),
            'resolved'      => $result->resolved()->toArray(),
            'applied_keys'  => $result->appliedKeys(),
            'rejected_keys' => $result->rejectedKeys(),
            'locked_keys'   => $result->lockedKeys(),
        ])->toResponse();
    }

    /**
     * Page overrides PUT accepts either the raw override payload or the same
     * wrapped {overrides, ...} document returned by GET.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null
     */
    private function pageOverridesPayload(array $body): ?array
    {
        if (!array_key_exists('overrides', $body)) {
            return $body;
        }

        return is_array($body['overrides']) ? $body['overrides'] : null;
    }

    private function refreshWorkingCanvas(int $pageId): void
    {
        if (!$this->workingCanvas instanceof WorkingCanvasRefresherInterface) {
            return;
        }

        $this->workingCanvas->refresh($pageId);
    }

    /**
     * GET /design-standards/section/{section_id}/consumed
     *
     * Inspects which design tokens a section's CSS consumes.
     */
    public function readSectionConsumed(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $sectionId = absint($request['section_id']);
        $pageId = absint($request->get_param('page_id'));

        if ($pageId <= 0) {
            return ApiResponse::error(ErrorMessage::MissingPageId);
        }

        if (!$this->permissions->canEditPage($pageId)) {
            return ApiResponse::error(ErrorMessage::PageEditForbidden);
        }

        if (!$this->sectionService->isPageOwned($pageId)) {
            return ApiResponse::error(ErrorMessage::NotEnginePage);
        }

        $section = $this->sectionService->findSection($pageId, $sectionId);
        if ($section === null) {
            return ApiResponse::error(ErrorMessage::SectionNotFoundOnPage);
        }

        $result = $this->service->getConsumedTokens(
            $section->content()->css(),
            $section->content()->html(),
            $pageId,
        );

        return ApiResponse::ok([
            'section_id'       => $sectionId,
            'page_id'          => $pageId,
            'consumed_tokens'  => $result['consumed_tokens'],
            'resolved_values'  => $result['resolved_values'],
        ])->toResponse();
    }

    /**
     * Full-profile REST writes may carry locked values through unchanged, but
     * lock management itself belongs to the admin branding form. This keeps a
     * generic profile PUT from clearing locks or overwriting a locked token.
     *
     * @return array{tokens: string[], typography: string[]}
     */
    private function changedLockedKeys(
        DesignStandardsProfile $storedProfile,
        DesignStandardsProfile $incomingProfile,
    ): array {
        $changed = ['tokens' => [], 'typography' => []];
        $lockedKeys = $storedProfile->lockedKeys();

        $storedTokens = $storedProfile->tokens()->toArray();
        $incomingTokens = $incomingProfile->tokens()->toArray();

        foreach ($lockedKeys['tokens'] ?? [] as $key) {
            if (
                !array_key_exists($key, $incomingTokens)
                || (string) ($incomingTokens[$key] ?? '') !== (string) ($storedTokens[$key] ?? '')
            ) {
                $changed['tokens'][] = (string) $key;
            }
        }

        $storedTypography = $storedProfile->typography()->toRoleArray();
        $incomingTypography = $incomingProfile->typography()->toRoleArray();

        foreach ($lockedKeys['typography'] ?? [] as $key) {
            $parts = explode('.', (string) $key, 2);
            $role = $parts[0] ?? '';
            $field = $parts[1] ?? '';

            if ($role === '' || $field === '') {
                continue;
            }

            $storedValue = (string) ($storedTypography[$role][$field] ?? '');
            $incomingValue = (string) ($incomingTypography[$role][$field] ?? '');

            if (
                !array_key_exists($role, $incomingTypography)
                || !array_key_exists($field, $incomingTypography[$role] ?? [])
                || $incomingValue !== $storedValue
            ) {
                $changed['typography'][] = (string) $key;
            }
        }

        return $changed;
    }

    /**
     * @param array{tokens: string[], typography: string[]} $lockedChanges
     */
    private function hasLockedChanges(array $lockedChanges): bool
    {
        return $lockedChanges['tokens'] !== [] || $lockedChanges['typography'] !== [];
    }

    /**
     * Until the typography settings UI owns full-profile writes, token-only
     * clients must not drop stored schema 3.0 buckets they do not know about.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function preserveTransitionalProfileBuckets(array $body, DesignStandardsProfile $storedProfile): array
    {
        if (!array_key_exists('breakpoints', $body)) {
            $body['breakpoints'] = $storedProfile->breakpoints()->toArray();
        }

        if (!array_key_exists('typography', $body)) {
            $body['typography'] = $storedProfile->typography()->toArray();
        }

        return $body;
    }

    /**
     * Transitional merge for sparse page overrides: legacy token-only clients
     * should not clear the new typography override bucket just by saving.
     *
     * @param array<string, mixed> $overridesBody
     * @return array<string, mixed>
     */
    private function preserveTransitionalOverrideBuckets(array $overridesBody, PageDesignOverrides $persisted): array
    {
        $stored = $persisted->toArray();

        if (!array_key_exists('tokens', $overridesBody) && array_key_exists('tokens', $stored)) {
            $overridesBody['tokens'] = $stored['tokens'];
        }

        if (!array_key_exists('typography', $overridesBody) && array_key_exists('typography', $stored)) {
            $overridesBody['typography'] = $stored['typography'];
        }

        return $overridesBody;
    }
}

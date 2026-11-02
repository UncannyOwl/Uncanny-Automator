<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

use UncannyPageBuilder\Api\AgentPageController\AgentWrite\AgentWriteGuard;
use UncannyPageBuilder\Application\Agent\SharedRestOperationRegistryInterface;
use UncannyPageBuilder\Application\ManagePageParts;
use UncannyPageBuilder\Domain\Exception\CanvasNotFoundException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Infrastructure\WordPress\DiagnosticLog;

/** Explicit Agent selection of page slots and site defaults. */
final class AgentPagePartsController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly ManagePageParts $parts,
        private readonly AgentWriteGuard $writes,
        private readonly ?SharedRestOperationRegistryInterface $sharedRest = null,
    ) {}

    public function registerRoutes(): void
    {
        $register = function (array $handler): void {
            register_rest_route('uncanny-page-builder/v1', '/agent/page-parts', $handler);
            try {
                $this->sharedRest?->remember(
                    '/uncanny-page-builder/v1/agent/page-parts',
                    $handler['methods'],
                    $handler['callback'],
                    $handler['permission_callback'],
                    'manage_page_parts',
                );
            } catch (\Throwable $failure) {
                DiagnosticLog::write('[Uncanny Page Builder] Shared page-parts registration failed (' . $failure::class . ').');
            }
        };
        $register([
            'methods' => 'GET',
            'callback' => [$this, 'read'],
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);
        $register([
            'methods' => 'POST',
            'callback' => $this->writes->guard('manage_page_parts', [$this, 'assign']),
            'permission_callback' => [$this->permissions, 'canManage'],
        ]);
    }

    public function read(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->invoke($request, false);
    }

    public function assign(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->invoke($request, true);
    }

    private function invoke(\WP_REST_Request $request, bool $write): \WP_REST_Response
    {
        try {
            $allowed = $write ? ['operation', 'scope', 'page_id', 'slot', 'selection', 'global_part_id', 'expected_generation'] : ['scope', 'page_id'];
            if (array_diff(array_keys($request->get_params()), $allowed) !== []) {
                throw new \InvalidArgumentException('The request contains unsupported fields.');
            }
            $scope = $request->get_param('scope');
            if (!is_string($scope) || !in_array($scope, ['page', 'site_defaults'], true)) {
                throw new \InvalidArgumentException('Use scope=page or site_defaults.');
            }
            $pageId = $request->has_param('page_id') ? RequestId::positive($request->get_param('page_id')) : 0;
            if ($pageId === null || ($scope === 'page' ? $pageId <= 0 : $request->has_param('page_id'))) {
                throw new \InvalidArgumentException('Provide an explicit page_id only for scope=page.');
            }
            if ($scope === 'page' && !$this->permissions->canEditPage($pageId)) {
                return $this->error(403, 'page_edit_forbidden');
            }
            if (!$write) {
                return $this->result('read', $this->parts->read($scope, $pageId));
            }
            if ($request->get_param('operation') !== 'assign') {
                throw new \InvalidArgumentException('Use operation=assign for POST.');
            }
            $slot = $request->get_param('slot');
            $selection = $request->get_param('selection');
            if (!is_string($slot) || !in_array($slot, ['header', 'footer'], true) || !is_string($selection)) {
                throw new \InvalidArgumentException('Provide slot=header or footer and an explicit selection.');
            }
            $globalPartId = $request->has_param('global_part_id') ? RequestId::positive($request->get_param('global_part_id')) : null;
            if (($selection === 'part' && $globalPartId === null) || ($selection !== 'part' && $request->has_param('global_part_id'))) {
                throw new \InvalidArgumentException('Provide a positive global_part_id only for selection=part.');
            }
            if ($globalPartId !== null && !$this->permissions->canEditPost($globalPartId)) {
                return $this->error(403, 'global_part_edit_forbidden');
            }
            $rawGeneration = $request->get_param('expected_generation');
            $generation = filter_var($rawGeneration, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ((!is_int($rawGeneration) && (!is_string($rawGeneration) || !preg_match('/^[0-9]+$/D', $rawGeneration))) || $generation === false) {
                throw new \InvalidArgumentException('Provide expected_generation from a fresh manage_page_parts read.');
            }
            return $this->result('assign', $this->parts->assign($scope, $pageId, GlobalPartType::from($slot), $selection, $globalPartId, $generation));
        } catch (CanvasNotFoundException) {
            return $this->error(404, 'canvas_not_found', 'Use an existing, owned, nontrashed page.');
        } catch (StaleSourceGenerationException) {
            return $this->error(409, 'stale_source_generation', 'Read manage_page_parts again before another write.');
        } catch (\InvalidArgumentException $failure) {
            return $this->error(400, 'invalid_page_parts_request', $failure->getMessage());
        } catch (\Throwable $failure) {
            DiagnosticLog::write('[Uncanny Page Builder] Page-parts request failed (' . $failure::class . ').');
            return $this->error(500, 'page_parts_request_failed', $write
                ? 'The write result is uncertain. Read manage_page_parts before another write.'
                : 'The read failed. Retry the read before a write.');
        }
    }

    private function result(string $operation, array $state): \WP_REST_Response
    {
        $lines = ['TOOL: manage_page_parts', 'RESULT: success', 'OPERATION: ' . $operation, 'SCOPE: ' . $state['scope'], 'EXPECTED_GENERATION: ' . $state['expected_generation'], 'GLOBAL_SOURCE_GENERATION: ' . $state['global_generation']];
        if ($state['scope'] === 'page') {
            $lines[] = 'PAGE_ID: ' . $state['page_id'];
            $lines[] = 'PAGE_MODE: ' . ($state['page_mode'] ?? 'none');
            $lines[] = 'SLOTS_RENDER_HERE: ' . ($state['page_mode'] === 'uncanny_native' ? 'yes' : 'no');
        }
        foreach (['header', 'footer'] as $slot) {
            $prefix = strtoupper($slot);
            $lines[] = $prefix . '_SITE_DEFAULT_ID: ' . ($state['site_defaults'][$slot] ?? 'none');
            if ($state['scope'] === 'page') {
                $lines[] = $prefix . '_PAGE_SELECTION: ' . match ($state['overrides'][$slot]) {
                    null => 'inherit', -1 => 'none', default => 'part ' . $state['overrides'][$slot],
                };
            }
            $lines[] = $prefix . '_EFFECTIVE_PART: ' . ($state['effective'][$slot] === null ? 'none' : wp_json_encode($state['effective'][$slot], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $lines[] = 'INHERIT: An empty site default can still resolve the first active part of the matching type.';
        $lines[] = 'PUBLICATION: These selections affect working source. A human must publish each page to change its visitor artifact.';
        if ($state['refresh_warning'] ?? false) {
            $lines[] = 'WARNING: The selection was saved, but the derived working canvas did not refresh. Reload the Manual editor or Preview; do not repeat the assignment.';
        }
        $lines[] = 'NEXT STEP';
        $lines[] = 'Read the effective parts before creating page-local header or footer sections. Use the fresh EXPECTED_GENERATION for assignment.';
        return AgentTextResponse::ok(implode("\n", $lines));
    }

    private function error(int $status, string $code, string $detail = ''): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(implode("\n", ['TOOL: manage_page_parts', 'RESULT: error', 'ERROR_CODE: ' . $code, 'DETAIL: ' . $detail]), $status);
    }
}

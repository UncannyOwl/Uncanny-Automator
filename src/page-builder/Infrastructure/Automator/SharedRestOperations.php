<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Automator;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Request_Scope;
use UncannyPageBuilder\Application\Agent\AgentToolRegistryAdapter;
use UncannyPageBuilder\Application\Agent\SharedRestOperationRegistryInterface;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Controls\ControlRegistry;
use UncannyPageBuilder\Application\Filesystem\LocalFileReaderInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/** Connects reviewed native authoring facades to Automator's checked REST transport. */
final class SharedRestOperations implements SharedRestOperationRegistryInterface
{
    /** @var array<string, array{callback: callable, permission: callable, tool: string}> */
    private array $operations = [];

    public function __construct(
        private readonly LocalFileReaderInterface $filesystem,
        private readonly ControlRegistry $registry,
        private readonly SectionRepositoryInterface $sections,
        private readonly SupportsPostTypeUseCase $supportsPostType,
    ) {}

    public function remember(string $route, string $method, callable $callback, callable $permission, string $tool): void
    {
        $this->operations[$method . ' ' . $route] = ['callback' => $callback, 'permission' => $permission, 'tool' => $tool];
    }

    public function register(): void
    {
        \add_filter('automator_mcp_rest_operation_policy_review', [$this, 'policy'], 10, 4);
        \add_filter('rest_request_before_callbacks', [$this, 'validateInvocation'], PHP_INT_MAX, 3);
    }

    public function policy($policy = null, $route = null, $method = null, $handler = null): mixed
    {
        try {
            if (!is_string($route) || !is_string($method) || !is_array($handler)) {
                return null;
            }
            return $this->reviewPolicy($policy, $route, $method, $handler);
        } catch (\Throwable) {
            return null;
        }
    }

    private function reviewPolicy(mixed $policy, string $route, string $method, array $handler): mixed
    {
        $operation = $this->operations[$method . ' ' . $route] ?? null;
        if (
            $operation === null || ($handler['callback'] ?? null) !== $operation['callback']
            || ($handler['permission_callback'] ?? null) !== $operation['permission']
        ) {
            return str_starts_with(strtolower($route), '/uncanny-page-builder/v1/') ? null : $policy;
        }

        try {
            $contract = (new AgentToolRegistryAdapter($this->registry, UNCANNY_PB_PATH . 'tools', $this->filesystem))->contract();
            foreach ($contract['tools'] as $tool) {
                if ($tool['name'] !== $operation['tool']) {
                    continue;
                }
                $readOnly = $method === 'GET' || in_array($tool['name'], ['preview_change', 'preview_runtime_change'], true);
                $reviewedPolicy = [
                    'read_only' => $readOnly,
                    'tools' => $readOnly ? ['get_posts', $tool['name']] : ['get_posts', 'post_apply_content_edit', $tool['name']],
                    'request_schema' => (new SharedRestInputSchema())->forTool($tool, $method),
                    'native_tool' => $tool,
                    // Freeze the complete JSON body, including nested element,
                    // menu, binding and assignment identities. Query IDs permit
                    // native integer sanitization through the scalar scope rule.
                    'target_fields' => ['body', 'query.operation', 'query.kind', 'query.page_id',
                        'query.section_id', 'query.global_part_id', 'query.scope', 'query.menu_id',
                        'query.binding_id', 'query.include', 'query.attachment_id', 'query.search',
                        'query.mime_type', 'query.size', 'query.query', 'query.limit'],
                ];
                if ($tool['name'] === 'manage_media' && $method === 'POST') {
                    $reviewedPolicy['max_input_bytes'] = 1572864;
                }
                return $reviewedPolicy;
            }
        } catch (\Throwable) {
            // A missing or invalid live contract must close exposure.
        }
        return null;
    }

    public function validateInvocation($response = null, $handler = null, $request = null): mixed
    {
        try {
            if (!is_array($handler) || !$request instanceof \WP_REST_Request) {
                return $response;
            }
            return $this->validateCheckedInvocation($response, $handler, $request);
        } catch (\Throwable) {
            return new \WP_Error('rest_forbidden', 'The Page Builder request authority could not be verified.', ['status' => 403]);
        }
    }

    private function validateCheckedInvocation(mixed $response, array $handler, \WP_REST_Request $request): mixed
    {
        if (
            $response !== null || !class_exists(Rest_Request_Scope::class)
            || Rest_Request_Scope::current_context_for_request($request) === null
        ) {
            return $response;
        }
        $tool = null;
        foreach ($this->operations as $operation) {
            if ($operation['callback'] === ($handler['callback'] ?? null)) {
                $tool = $operation['tool'];
                break;
            }
        }
        if ($tool === null) {
            return $response;
        }
        // Native route defaults are allowed. Hook-injected context or owner
        // defaults cannot add an identity that the reviewed input did not select.
        foreach ($request->get_default_params() as $name => $value) {
            $argument = $handler['args'][$name] ?? [];
            if (!array_key_exists('default', $argument) || $value !== $argument['default']) {
                return new \WP_Error('rest_forbidden', 'The reviewed operation defaults changed.', ['status' => 403]);
            }
        }
        if ($tool === 'manage_canvas' && in_array($request->get_param('operation'), ['update', 'attach_reusable'], true)) {
            $canvasId = (int) $request->get_param('canvas_id');
            $pageOnly = $request->get_param('operation') === 'attach_reusable' || $request->get_param('shell_mode') !== null;
            if (!$this->isSupportedCanvas($canvasId, !$pageOnly)) {
                return new \WP_Error(
                    'rest_invalid_parameters',
                    $pageOnly ? 'An explicit WordPress page canvas is required.' : 'An explicit Page Builder canvas is required.',
                    ['status' => 400]
                );
            }
        }
        $part = $request->get_param('part');
        $globalPartId = $tool === 'edit_part' && is_array($part) && ($part['kind'] ?? '') === 'global_part'
            ? (int) ($part['global_part_id'] ?? 0)
            : (int) ($request->get_param('global_part_id') ?? 0);
        if ($globalPartId > 0 && \get_post_type($globalPartId) !== 'upb_global_part') {
            return new \WP_Error('rest_invalid_parameters', 'An explicit reusable global part is required.', ['status' => 400]);
        }
        if (in_array($tool, ['read_page_context', 'create_section'], true)) {
            $ownerId = (int) $request->get_param('page_id');
            if (!$this->isSupportedCanvas($ownerId, true)) {
                return new \WP_Error('rest_invalid_parameters', 'An explicit Page Builder canvas target is required.', ['status' => 400]);
            }
        }
        $requiresPage = in_array($tool, ['manage_sections', 'preview_change'], true)
            || ($tool === 'read_part' && $request->get_param('kind') === 'section')
            || ($tool === 'edit_part' && is_array($part) && ($part['kind'] ?? '') === 'section')
            || ($tool === 'manage_binding' && $request->get_method() === 'POST')
            || ($tool === 'manage_reusable' && $request->get_param('operation') === 'convert')
            || (in_array($tool, ['read_runtime', 'edit_runtime', 'preview_runtime_change'], true) && $request->get_param('scope') === 'page');
        if ($requiresPage) {
            $pageId = (int) $request->get_param('page_id');
            if (!$this->isSupportedCanvas($pageId)) {
                return new \WP_Error('rest_invalid_parameters', 'An explicit WordPress page target is required.', ['status' => 400]);
            }
        }
        if ($tool === 'manage_reusable' && $request->get_param('operation') === 'convert') {
            try {
                $section = $this->sections->findById((int) $request->get_param('section_id'));
                if ($section->pageId() !== (int) $request->get_param('page_id')) {
                    return new \WP_Error('rest_invalid_parameters', 'The section does not belong to the explicit page.', ['status' => 400]);
                }
            } catch (\UncannyPageBuilder\Domain\Exception\SectionNotFoundException) {
                return new \WP_Error('rest_invalid_parameters', 'The section does not belong to the explicit page.', ['status' => 400]);
            }
        }
        return $response;
    }

    private function isSupportedCanvas(int $postId, bool $allowGlobalPart = false): bool
    {
        if ($postId <= 0) {
            return false;
        }
        $postType = \get_post_type($postId);
        if ($postType === 'upb_global_part') {
            return $allowGlobalPart;
        }
        // Match native authoring eligibility, including saved administrator settings.
        // Native callbacks still enforce permissions and Page Builder ownership.
        return is_string($postType) && $this->supportsPostType->isSupported($postType);
    }
}

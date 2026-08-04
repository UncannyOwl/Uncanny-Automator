<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Infrastructure\WordPress\AdminBrandingPage;

/**
 * Serves dynamic binding guides and design introspection for the agent.
 *
 * Reads from the BindingRegistry (populated from bindings directory)
 * and DesignStandardsService (site design custom properties).
 */
final class AgentGuideController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly BindingRegistry $registry,
        private readonly DesignStandardsService $designStandards,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/agent/binding', [
            'methods'             => 'GET',
            'callback'            => [$this, 'manageBinding'],
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args'                => [
                'operation' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'binding_id' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/agent/site-design', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getSiteDesign'],
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);
    }

    public function listBindings(\WP_REST_Request $request): \WP_REST_Response
    {
        $search = trim((string) ($request->get_param('search') ?? ''));

        // With search: return matched bindings ranked by relevance.
        if ($search !== '') {
            $matched = $this->registry->search($search, 10);
            $lines = [
                'TOOL: manage_binding',
                'RESULT: success',
                'OPERATION: search',
                'SEARCH: ' . $search,
                '',
                'BINDINGS',
            ];
            foreach ($matched as $id => $decl) {
                $lines[] = '- ID: ' . $id;
                $lines[] = '  TITLE: ' . $decl->title;
                $lines[] = '  SUMMARY: ' . ($decl->summary !== '' ? $decl->summary : 'none');
                $lines[] = '  TAGS: ' . ($decl->tags !== [] ? implode(', ', $decl->tags) : 'none');
            }
            if ($matched === []) {
                $lines[] = 'none';
            }
            $lines[] = '';
            $lines[] = 'NEXT STEP';
            $lines[] = $matched === []
                ? 'Search again with a broader user intent, such as posts, menu, user, or logo.'
                : 'Call manage_binding with operation guide and the exact binding ID before using a binding.';

            return AgentTextResponse::ok(implode("\n", $lines));
        }

        // No search: return category summary so the agent knows what to search for.
        $tagCounts = $this->registry->tagCounts();
        $total = count($this->registry->all());

        $lines = [
            'TOOL: manage_binding',
            'RESULT: success',
            'OPERATION: search',
            'TOTAL: ' . $total,
            '',
            'CATEGORIES',
        ];
        foreach ($tagCounts as $tag => $count) {
            $lines[] = (string) $tag . ': ' . (int) $count;
        }
        if ($tagCounts === []) {
            $lines[] = 'none';
        }
        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = 'Call manage_binding with operation search and a term that matches the user request, then call operation guide.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    public function manageBinding(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $operation = trim((string) ($request->get_param('operation') ?? ''));

        if ($operation === '' || $operation === 'search') {
            return $this->listBindings($request);
        }

        if ($operation === 'guide') {
            return $this->getBindingGuide($request);
        }

        return AgentTextResponse::error(
            'manage_binding',
            400,
            'invalid_operation',
            [
                'OPERATION: ' . $operation,
                'NEXT STEP',
                'Retry with operation search or guide.',
            ],
        );
    }

    public function getBindingGuide(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $bindingId = $request->get_param('binding_id');
        $guideRegistry = $this->registry->guideRegistry();

        if (!isset($guideRegistry[$bindingId])) {
            return new \WP_Error(
                'binding_not_found',
                'Binding not found. Call manage_binding with operation search to see available bindings.',
                ['status' => 404],
            );
        }

        $meta = $guideRegistry[$bindingId];

        // Validate file path is within the plugin directory to prevent path traversal.
        $realPath = realpath($meta['file']);
        $pluginDir = realpath(UNCANNY_PB_PATH);
        if ($realPath === false || $pluginDir === false || !str_starts_with($realPath, $pluginDir)) {
            return new \WP_Error('guide_read_failed', 'Could not read guide file.', ['status' => 500]);
        }

        $content = file_get_contents($realPath);

        if ($content === false) {
            return new \WP_Error('guide_read_failed', 'Could not read guide file.', ['status' => 500]);
        }

        $declaration = $this->registry->get((string) $bindingId);

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_binding',
            'RESULT: success',
            'OPERATION: guide',
            'BINDING_ID: ' . $bindingId,
            '',
            '--- CONTRACT ---',
            ...$this->bindingContractLines($declaration),
            '',
            '--- GUIDE ---',
            $content,
            '',
            'NEXT STEP',
            'Use the guide exactly when creating or updating binding markup.',
        ]));
    }

    /**
     * @return list<string>
     */
    private function bindingContractLines(?\UncannyPageBuilder\Domain\Binding\BindingDeclaration $declaration): array
    {
        if ($declaration === null) {
            return ['none'];
        }

        $lines = [
            'TYPE: ' . $declaration->type,
            'STATIC_SAFETY: ' . $declaration->staticSafety->value,
            'OUTPUT_SHAPE: ' . $declaration->outputShape,
            'REGION_CONTRACT: ' . self::regionContractLine($declaration),
            'BIND_KEYS: ' . ($declaration->bindKeys === [] ? 'none' : implode('|', $declaration->bindKeys)),
            'META_BINDINGS: ' . ($declaration->metaBindings ? 'true' : 'false'),
            'TERMS_BINDINGS: ' . ($declaration->termsBindings ? 'true' : 'false'),
        ];

        if ($declaration->queryAttributes === []) {
            $lines[] = 'QUERY_ATTRIBUTES: none';
            return $lines;
        }

        $lines[] = 'QUERY_ATTRIBUTES:';
        foreach ($declaration->queryAttributes as $attribute => $config) {
            $parts = [
                (string) $attribute,
                'required=' . (!empty($config['required']) ? 'true' : 'false'),
            ];
            if (array_key_exists('default', $config)) {
                $parts[] = 'default=' . (string) $config['default'];
            }
            if (isset($config['cast'])) {
                $parts[] = 'cast=' . (string) $config['cast'];
            }
            if (isset($config['allowed_values']) && is_array($config['allowed_values'])) {
                $parts[] = 'allowed=' . implode('|', array_map('strval', $config['allowed_values']));
            }
            $lines[] = '- ' . implode(' ', $parts);
        }

        return $lines;
    }

    private static function regionContractLine(\UncannyPageBuilder\Domain\Binding\BindingDeclaration $declaration): string
    {
        $contract = $declaration->regionContract();

        return sprintf(
            'replaces=%s template=%s fully_projected=%s',
            $contract->replaces->value,
            $contract->template->value,
            $contract->isFullyProjected() ? 'true' : 'false',
        );
    }

    /**
     * GET /agent/branding
     *
     * Returns the full resolved design settings map, breakpoints,
     * and brand logo URL.
     */
    public function getBranding(\WP_REST_Request $request): \WP_REST_Response
    {
        $snapshot = $this->brandingSnapshot();
        $tokens = $snapshot['tokens'];
        $breakpoints = $snapshot['breakpoints'];
        $lockedKeys = $snapshot['locked_keys'];

        $lines = [
            'TOOL: get_branding',
            'RESULT: success',
            '',
            'BRAND LOGO',
            $snapshot['logo'],
            '',
            'DESIGN SETTINGS',
        ];
        foreach ($tokens as $name => $value) {
            $lock = in_array($name, $lockedKeys['tokens'] ?? [], true) ? ' protected' : '';
            $lines[] = (string) $name . ': ' . (string) $value . $lock;
        }
        if ($tokens === []) {
            $lines[] = 'none';
        }

        $lines[] = '';
        $lines[] = 'BREAKPOINTS';
        foreach ($breakpoints as $name => $value) {
            $lines[] = (string) $name . ': ' . (string) $value . 'px';
        }
        if ($breakpoints === []) {
            $lines[] = 'none';
        }

        $lines[] = '';
        $lines[] = 'GUIDANCE';
        $lines[] = 'Use site design settings for broad design consistency. Do not change site or page design settings for one selected element.';
        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use proper section CSS first. Only edit durable element style after inspection proves it already owns the property.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    public function getSiteDesign(\WP_REST_Request $request): \WP_REST_Response
    {
        unset($request);

        $snapshot = $this->brandingSnapshot();
        $tokens = $snapshot['tokens'];
        $lockedKeys = $snapshot['locked_keys'];

        $lines = [
            'TOOL: get_site_design',
            'RESULT: success',
            '',
            'LOGO',
            $snapshot['logo'],
            '',
            'COLORS',
        ];
        $this->appendSection($lines, $this->filterTokens($tokens, static fn (string $name): bool => str_contains($name, 'primary') || str_contains($name, 'secondary') || str_contains($name, 'success') || str_contains($name, 'info') || str_contains($name, 'warning') || str_contains($name, 'danger') || str_contains($name, 'body-color') || str_contains($name, 'body-bg') || str_contains($name, 'link-color')));

        $lines[] = '';
        $lines[] = 'FONTS';
        $this->appendSection($lines, $this->filterTokens($tokens, static fn (string $name): bool => str_contains($name, 'font') || str_contains($name, 'line-height') || str_contains($name, 'heading')));

        $lines[] = '';
        $lines[] = 'TEXT STYLES';
        $this->appendTypographyRoles($lines, $snapshot['typography']);

        $lines[] = '';
        $lines[] = 'SPACING';
        $this->appendSection($lines, $this->filterTokens($tokens, static fn (string $name): bool => str_contains($name, 'padding') || str_contains($name, 'spacer') || str_contains($name, 'margin')));

        $lines[] = '';
        $lines[] = 'BREAKPOINTS';
        foreach ($snapshot['breakpoints'] as $name => $value) {
            $lines[] = (string) $name . ': ' . (string) $value . 'px';
        }
        if ($snapshot['breakpoints'] === []) {
            $lines[] = 'none';
        }

        $lines[] = '';
        $lines[] = 'BUTTON STYLE';
        $this->appendSection($lines, $this->filterTokens($tokens, static fn (string $name): bool => str_contains($name, '--bs-btn-') || str_contains($name, 'border-radius')));

        $lines[] = '';
        $lines[] = 'LOCKED SETTINGS';
        $this->appendLockedSettings($lines, $lockedKeys);

        $lines[] = '';
        $lines[] = 'CSS/SOURCE NOTE';
        $lines[] = 'These settings describe the site design system. Mention Bootstrap only when working directly in CSS or source-level custom properties.';
        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use section CSS for one-off styling. Use durable style work only after inspection proves the element already owns the property.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    /**
     * @return array{
     *   logo: string,
     *   tokens: array<string, string>,
     *   typography: array<string, array<string, string>>,
     *   breakpoints: array<string, int>,
     *   locked_keys: array{tokens?: array<int, string>, typography?: array<int, string>}
     * }
     */
    private function brandingSnapshot(): array
    {
        $profile = $this->designStandards->resolve();

        return [
            'logo' => AdminBrandingPage::resolveLogoUrl() ?: 'none',
            'tokens' => $profile->tokens()->toArray(),
            'typography' => $profile->typography()->toRoleArray(),
            'breakpoints' => $profile->breakpoints()->toArray(),
            'locked_keys' => $profile->lockedKeys(),
        ];
    }

    /**
     * @param array<string, string> $tokens
     * @return array<string, string>
     */
    private function filterTokens(array $tokens, callable $predicate): array
    {
        $filtered = [];
        foreach ($tokens as $name => $value) {
            if ($predicate((string) $name)) {
                $filtered[(string) $name] = (string) $value;
            }
        }

        return $filtered;
    }

    /**
     * @param list<string> $lines
     * @param array<string, string> $tokens
     */
    private function appendSection(array &$lines, array $tokens): void
    {
        if ($tokens === []) {
            $lines[] = 'none';
            return;
        }

        foreach ($tokens as $name => $value) {
            $lines[] = (string) $name . ': ' . (string) $value;
        }
    }

    /**
     * @param list<string> $lines
     * @param array<string, array<string, string>> $roles
     */
    private function appendTypographyRoles(array &$lines, array $roles): void
    {
        if ($roles === []) {
            $lines[] = 'none';
            return;
        }

        foreach ($roles as $role => $fields) {
            foreach ($fields as $field => $value) {
                $lines[] = $role . '.' . $field . ': ' . $value;
            }
        }
    }

    /**
     * @param list<string> $lines
     * @param array{tokens?: array<int, string>, typography?: array<int, string>} $lockedKeys
     */
    private function appendLockedSettings(array &$lines, array $lockedKeys): void
    {
        $locked = [];

        foreach (array_values(array_unique((array) ($lockedKeys['tokens'] ?? []))) as $name) {
            $locked[] = 'design setting ' . (string) $name;
        }

        foreach (array_values(array_unique((array) ($lockedKeys['typography'] ?? []))) as $name) {
            $locked[] = 'text style ' . (string) $name;
        }

        if ($locked === []) {
            $lines[] = 'none';
            return;
        }

        foreach ($locked as $name) {
            $lines[] = $name;
        }
    }
}

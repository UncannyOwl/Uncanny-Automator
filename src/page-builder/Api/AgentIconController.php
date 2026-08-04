<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

use UncannyPageBuilder\Infrastructure\Section\LucideIconFinder;

final class AgentIconController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly LucideIconFinder $finder,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/agent/lucide-icons', [
            'methods'             => 'GET',
            'callback'            => [$this, 'findLucideIcons'],
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args'                => [
                'query' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public function findLucideIcons(\WP_REST_Request $request): \WP_REST_Response
    {
        $rawQuery = trim((string) ($request->get_param('query') ?? ''));
        if ($rawQuery === '') {
            return $this->textError(400, 'missing_query', [
                'NEXT STEP',
                'Use this tool only after a write returns a Lucide icon WARNING, then pass the invalid icon name in query.',
            ]);
        }

        $limit = (int) ($request->get_param('limit') ?? 8);
        $limit = max(1, min(12, $limit));
        $queries = $this->queries($rawQuery);

        $lines = [
            'TOOL: find_lucide_icons',
            'RESULT: success',
            'MODE: lucide_warning_recovery',
            'QUERY: ' . $rawQuery,
            '',
            'USAGE',
            'Use this only after create_section or edit_part returns a WARNING about unsupported Lucide icon names.',
            'Use one exact ICON value in data-lucide; do not call this as a general icon-planning step.',
            '',
            'RESULTS',
        ];

        foreach ($queries as $query) {
            $lines[] = 'SEARCH: ' . $query;
            $icons = $this->finder->search($query, $limit);
            if ($icons === []) {
                $lines[] = '- none';
                $lines[] = '';
                continue;
            }

            foreach ($icons as $icon) {
                $lines[] = '- ICON: ' . $icon;
            }
            $lines[] = '';
        }

        $first = $queries !== [] ? ($this->finder->search($queries[0], 1)[0] ?? '') : '';
        $lines[] = 'NEXT STEP';
        $lines[] = $first !== ''
            ? 'Patch only the invalid data-lucide value, for example data-lucide="' . $first . '".'
            : 'Patch only the invalid data-lucide value with one exact ICON from the results.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    /**
     * @return string[]
     */
    private function queries(string $rawQuery): array
    {
        $queries = [];
        foreach (explode('|', $rawQuery) as $query) {
            $query = trim($query);
            if ($query !== '') {
                $queries[$query] = true;
            }
        }

        return array_keys($queries);
    }

    /**
     * @param string[] $lines
     */
    private function textError(int $status, string $code, array $lines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: find_lucide_icons',
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }
}

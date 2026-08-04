<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\PartRead;

use UncannyPageBuilder\Api\AgentTextResponse;

/**
 * Routes unified read_part requests to section or global-part readers.
 */
final class PartReadController
{
    public function __construct(
        private readonly SectionPartReader $sections,
        private readonly GlobalPartReader $globalParts,
        private readonly PartDetailPresenter $details,
    ) {}

    public function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $kind = trim((string) ($request->get_param('kind') ?? ''));
        if (!in_array($kind, ['section', 'global_part'], true)) {
            return $this->textToolError('read_part', 400, 'invalid_part_kind', [
                'KIND: ' . ($kind !== '' ? $kind : 'missing'),
                'NEXT STEP',
                'Retry with kind=section or kind=global_part.',
            ]);
        }

        $includes = $this->details->includes($request);
        if ($includes === []) {
            return $this->textToolError('read_part', 400, 'unsupported_include', [
                'KIND: ' . $kind,
                'NEXT STEP',
                'Retry with include=manifest,source,content_targets,design_targets, or bindings.',
            ]);
        }

        return $kind === 'section'
            ? $this->sections->readPart($request, $includes)
            : $this->globalParts->readPart($request, $includes);
    }

    /**
     * @param list<string> $lines
     */
    private function textToolError(string $toolName, int $status, string $code, array $lines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }
}

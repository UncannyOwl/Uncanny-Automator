<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\PartRead;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\Section;

/**
 * Resolves reusable source aggregates for unified read_part requests.
 */
final class GlobalPartReader
{
    public function __construct(
        private readonly GlobalPartDefaultsService $globalPartDefaults,
        private readonly GlobalPartService $globalParts,
        private readonly PermissionChecker $permissions,
        private readonly PartDetailPresenter $details,
    ) {}

    /**
     * @param list<string> $includes
     */
    public function readPart(\WP_REST_Request $request, array $includes): \WP_REST_Response|\WP_Error
    {
        $partTypeValue = $this->assignedPartTypeValue($request);
        $requestedPartType = $this->parseAssignedPartType($partTypeValue);
        if ($requestedPartType === false) {
            return $this->invalidPartTypeError('read_part', 'part_type', $partTypeValue);
        }

        $partType = $requestedPartType instanceof GlobalPartType ? $requestedPartType->value : '';
        $globalPartId = $this->requestGlobalPartId($request, $partType === '');
        if ($globalPartId <= 0 && $partType === '') {
            return $this->textToolError('read_part', 400, 'missing_part_type', [
                'KIND: global_part',
                'NEXT STEP',
                'Retry with global_part_id from the current reusable canvas, or part_type=header or footer.',
            ]);
        }

        [$resolved, $section, $error] = $this->resolveForPartRead($request, $partType, $globalPartId);
        if ($error instanceof \WP_REST_Response || !is_array($resolved) || !$section instanceof Section) {
            return $error;
        }

        $partType = $this->resolvedPartType($resolved, $partType);

        return AgentTextResponse::ok(implode(
            "\n",
            $this->details->globalPartLines($partType, $resolved, $section, $includes, $request),
        ));
    }

    /**
     * @return array{0: ?array<string, mixed>, 1: ?Section, 2: ?\WP_REST_Response}
     */
    private function resolveForPartRead(
        \WP_REST_Request $request,
        string $partType,
        int $globalPartId,
    ): array {
        $resolved = $this->resolveRequested($request, $partType, $globalPartId);
        if ($resolved === null) {
            return [null, null, $this->textToolError('read_part', 404, 'no_active_global_part', [
                'KIND: global_part',
                'PART_TYPE: ' . $partType,
                'NEXT STEP',
                'Create or assign an active ' . $partType . ' global part before reading it.',
            ])];
        }

        $postId = (int) ($resolved['post_id'] ?? 0);
        if (!$this->permissions->canEditPost($postId)) {
            return [null, null, $this->textToolError('read_part', 403, 'global_part_edit_forbidden', [
                'KIND: global_part',
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'NEXT STEP',
                'Use an account that can edit this global part.',
            ])];
        }

        $section = $this->sourceSection($resolved);
        if (!$section instanceof Section) {
            return [null, null, $this->textToolError('read_part', 404, 'no_global_part_source', [
                'KIND: global_part',
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'NEXT STEP',
                'Create source content for this global part before editing it.',
            ])];
        }

        return [$resolved, $section, null];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveRequested(
        \WP_REST_Request $request,
        string $partType,
        int $globalPartId = 0,
    ): ?array {
        if ($globalPartId > 0) {
            return $this->globalParts->findById($globalPartId);
        }

        if ($partType === '') {
            $globalPartId = $this->requestGlobalPartId($request);
            if ($globalPartId > 0) {
                return $this->globalParts->findById($globalPartId);
            }

            return null;
        }

        return $this->globalPartDefaults->resolveAssignedForType(GlobalPartType::fromString($partType));
    }

    /**
     * @param array<string, mixed> $globalPart
     */
    private function sourceSection(array $globalPart): ?Section
    {
        $sections = $globalPart['sections'] ?? [];
        if (!is_array($sections) || $sections === []) {
            return null;
        }

        $sectionData = $sections[0] ?? null;
        if (!is_array($sectionData)) {
            return null;
        }

        if (!isset($sectionData['content']) && (isset($sectionData['html']) || isset($sectionData['css']))) {
            $sectionData['content'] = [
                'html' => (string) ($sectionData['html'] ?? ''),
                'css' => (string) ($sectionData['css'] ?? ''),
            ];
        }

        return Section::fromStoredArray(
            $sectionData,
            (int) ($globalPart['post_id'] ?? 0),
            (int) ($sectionData['position'] ?? 0),
        );
    }

    private function assignedPartTypeValue(\WP_REST_Request $request): string
    {
        $typeValue = $request->get_param('part_type');

        return is_string($typeValue) ? trim($typeValue) : '';
    }

    private function parseAssignedPartType(string $typeValue): GlobalPartType|false|null
    {
        if ($typeValue === '') {
            return null;
        }

        $type = GlobalPartType::tryFrom($typeValue);

        return $type instanceof GlobalPartType && $type !== GlobalPartType::Section
            ? $type
            : false;
    }

    private function requestGlobalPartId(\WP_REST_Request $request, bool $allowContextFallback = true): int
    {
        $requestId = \absint($request->get_param('global_part_id'));
        if ($requestId > 0) {
            return $requestId;
        }

        if (!$allowContextFallback) {
            return 0;
        }

        $context = $request->get_param('page_builder_context');
        if (is_array($context)) {
            return \absint($context['global_part_id'] ?? 0);
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $resolved
     */
    private function resolvedPartType(array $resolved, string $fallback): string
    {
        $resolvedType = trim((string) ($resolved['type'] ?? ''));

        return $resolvedType !== '' ? $resolvedType : $fallback;
    }

    private function invalidPartTypeError(
        string $toolName,
        string $fieldName,
        string $typeValue,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 400, 'invalid_part_type', [
            'KIND: global_part',
            'PART_TYPE: ' . ($typeValue !== '' ? $typeValue : 'missing'),
            'NEXT STEP',
            'Retry with global_part_id from the current reusable canvas, or ' . $fieldName . '=header or footer for assigned site defaults.',
        ]);
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

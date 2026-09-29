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

        $sourceSections = $this->sourceSections($resolved);
        $lines = $this->details->globalPartLines($partType, $resolved, $section, $includes, $request);
        $lines[] = 'GLOBAL SOURCE ROWS';
        $lines[] = 'SOURCE_ROW_COUNT: ' . count($sourceSections);
        $lines[] = 'CANONICAL_SOURCE_ROW: 1';

        if (count($sourceSections) > 1) {
            $lines[] = 'NOTICE: This legacy global part stores multiple source rows. No row was merged or omitted.';
            $lines[] = 'ADDITIONAL_ROWS_EDITABLE: no';
            $lines[] = 'NOTICE: Additional rows are read-only legacy evidence. edit_part always changes canonical source row 1. Ask an administrator to consolidate legacy rows.';
            foreach (array_slice($sourceSections, 1, null, true) as $index => $additionalSection) {
                $rowNumber = (int) $index + 1;
                if (in_array('source', $includes, true)) {
                    array_push($lines, ...$this->details->additionalGlobalSourceLines($additionalSection, $rowNumber));
                    continue;
                }

                $lines[] = 'ADDITIONAL SOURCE ROW ' . $rowNumber;
                $lines[] = 'SOURCE_SECTION_ID: ' . (string) ($additionalSection->id() ?? 0);
                $lines[] = 'SOURCE_SECTION_NAME: ' . $additionalSection->name();
                $lines[] = 'POSITION: ' . $additionalSection->position();
            }
        }

        array_push($lines, ...$this->details->globalPartNextStepLines(
            $includes,
            (int) ($resolved['post_id'] ?? 0),
        ));

        return AgentTextResponse::ok(implode("\n", $lines));
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
            $lines = ['KIND: global_part'];
            if ($globalPartId > 0) {
                $lines[] = 'GLOBAL_PART_ID: ' . $globalPartId;
                $lines[] = 'NEXT STEP';
                $lines[] = 'Call manage_reusable operation=list and retry with a current REUSABLE_ID.';
            } else {
                $lines[] = 'PART_TYPE: ' . $partType;
                $lines[] = 'NEXT STEP';
                $lines[] = 'Create or assign an active ' . $partType . ' global part before reading it.';
            }

            return [null, null, $this->textToolError('read_part', 404, 'no_active_global_part', $lines)];
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
        return $this->sourceSections($globalPart)[0] ?? null;
    }

    /**
     * @param array<string, mixed> $globalPart
     * @return list<Section>
     */
    private function sourceSections(array $globalPart): array
    {
        $sections = $globalPart['sections'] ?? [];
        if (!is_array($sections) || $sections === []) {
            return [];
        }

        $resolvedSections = [];
        foreach ($sections as $sectionData) {
            if (!is_array($sectionData)) {
                continue;
            }

            if (!isset($sectionData['content']) && (isset($sectionData['html']) || isset($sectionData['css']))) {
                $sectionData['content'] = [
                    'html' => (string) ($sectionData['html'] ?? ''),
                    'css' => (string) ($sectionData['css'] ?? ''),
                ];
            }

            $resolvedSections[] = Section::fromStoredArray(
                $sectionData,
                (int) ($globalPart['post_id'] ?? 0),
                (int) ($sectionData['position'] ?? count($resolvedSections)),
            );
        }

        return $resolvedSections;
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

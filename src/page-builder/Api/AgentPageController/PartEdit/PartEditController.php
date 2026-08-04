<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\PartEdit;

use UncannyPageBuilder\Api\AgentPageController\ContentTargetController;
use UncannyPageBuilder\Api\AgentPageController\DesignStyleController;
use UncannyPageBuilder\Api\AgentPageController\ElementController;
use UncannyPageBuilder\Api\AgentPageController\GlobalPartSource\GlobalPartSourceController;
use UncannyPageBuilder\Api\AgentPageController\GlobalPartSource\GlobalPartSourceResolver;
use UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch\SectionSourcePatchController;
use UncannyPageBuilder\Api\AgentPageController\SectionSourceReplaceController;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

/**
 * Dispatches the stable edit_part contract to focused source-write
 * controllers.
 */
final class PartEditController
{
    public function __construct(
        private readonly ContentTargetController $contentTargets,
        private readonly DesignStyleController $designStyles,
        private readonly ElementController $elements,
        private readonly SectionSourcePatchController $sectionPatches,
        private readonly SectionSourceReplaceController $sectionReplacements,
        private readonly GlobalPartSourceController $globalPartSources,
        private readonly GlobalPartSourceResolver $globalParts,
        private readonly PartEditRequestAdapter $requests,
        private readonly PartEditResponseFormatter $responses,
    ) {}

    public function edit(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $part = $request->get_param('part');
        if (!is_array($part)) {
            return $this->responses->error(400, 'invalid_part', [
                'NEXT STEP',
                'Retry with part.kind and the required part identifier.',
            ]);
        }

        $kind = trim((string) ($part['kind'] ?? ''));
        if (!in_array($kind, ['section', 'global_part'], true)) {
            return $this->responses->error(400, 'invalid_part_kind', [
                'KIND: ' . ($kind !== '' ? $kind : 'missing'),
                'NEXT STEP',
                'Retry with part.kind=section or part.kind=global_part.',
            ]);
        }

        $operation = $request->get_param('operation');
        if (!is_array($operation)) {
            return $this->responses->error(400, 'invalid_operation', [
                'KIND: ' . $kind,
                'NEXT STEP',
                'Retry with operation.mode and mode-specific fields.',
            ]);
        }

        $mode = trim((string) ($operation['mode'] ?? ''));
        if ($mode === '') {
            return $this->responses->error(400, 'missing_operation_mode', [
                'KIND: ' . $kind,
                'NEXT STEP',
                'Retry with operation.mode.',
            ]);
        }

        return $kind === 'section'
            ? $this->editSection($request, $part, $mode, $operation)
            : $this->editGlobalPart($request, $part, $mode, $operation);
    }

    /**
     * @param array<string, mixed> $part
     * @param array<string, mixed> $operation
     */
    private function editSection(
        \WP_REST_Request $request,
        array $part,
        string $mode,
        array $operation,
    ): \WP_REST_Response|\WP_Error {
        $sectionId = absint($part['section_id'] ?? 0);
        if ($sectionId <= 0) {
            return $this->responses->error(400, 'missing_section_id', [
                'KIND: section',
                'NEXT STEP',
                'Retry with part.section_id.',
            ]);
        }

        $pageId = absint($request->get_param('page_id') ?? 0);
        $base = ['section_id' => $sectionId];
        if ($pageId > 0) {
            $base['page_id'] = $pageId;
        }

        return match ($mode) {
            'text' => $this->responses->facade($this->contentTargets->updateText($this->requests->withOverrides($request, $base + [
                'target' => $operation['target'] ?? null,
                'text' => $operation['text'] ?? null,
                'format' => $operation['format'] ?? 'plain',
            ])), 'text'),
            'link' => $this->responses->facade($this->contentTargets->updateLink($this->requests->withOverrides($request, $base + [
                'target' => $operation['target'] ?? null,
                'href' => $operation['href'] ?? null,
                'text' => $operation['text'] ?? null,
            ])), 'link'),
            'image' => $this->responses->facade($this->contentTargets->updateImage($this->requests->withOverrides($request, $base + [
                'target' => $operation['target'] ?? null,
                'src' => $operation['src'] ?? null,
                'alt' => $operation['alt'] ?? null,
                'loading' => $operation['loading'] ?? null,
                'decoding' => $operation['decoding'] ?? null,
                'width' => $operation['width'] ?? null,
                'height' => $operation['height'] ?? null,
            ])), 'image'),
            'durable_style' => $this->responses->facade($this->designStyles->update($this->requests->withOverrides($request, $base + [
                'changes' => $this->requests->durableStyleChanges($operation),
            ])), 'durable_style'),
            'insert_element' => $this->responses->facade($this->elements->insert($this->requests->withOverrides($request, $base + [
                'target' => $operation['target'] ?? null,
                'placement' => $operation['placement'] ?? null,
                'html' => $operation['html'] ?? null,
            ])), 'insert_element'),
            'move_element' => $this->responses->facade($this->elements->move($this->requests->withOverrides($request, $base + [
                'source' => $operation['source'] ?? null,
                'destination' => $operation['destination'] ?? null,
                'placement' => $operation['placement'] ?? null,
            ])), 'move_element'),
            'delete_element' => $this->responses->facade($this->elements->delete($this->requests->withOverrides($request, $base + [
                'target' => $operation['target'] ?? null,
            ])), 'delete_element'),
            'css_rule' => $this->responses->facade($this->sectionPatches->patchSource($this->requests->withOverrides($request, $base + [
                'css_rules' => $operation['css_rules'] ?? [],
            ])), 'css_rule'),
            'source_patch' => $this->responses->facade($this->sectionPatches->patchSource($this->requests->withOverrides($request, $base + [
                'html_patches' => $operation['html_patches'] ?? [],
                'css_patches' => $operation['css_patches'] ?? [],
                'css_rules' => $operation['css_rules'] ?? [],
            ])), 'source_patch'),
            'source_replace' => $this->responses->facade($this->sectionReplacements->rewriteSource($this->requests->withOverrides($request, $base + [
                'reason' => $operation['reason'] ?? null,
                'name' => $operation['name'] ?? null,
                'html' => $operation['html'] ?? null,
                'css' => $operation['css'] ?? '',
            ])), 'source_replace'),
            default => $this->responses->error(400, 'unsupported_operation_mode', [
                'KIND: section',
                'MODE: ' . $mode,
                'NEXT STEP',
                'Retry with text, link, image, css_rule, durable_style, insert_element, move_element, delete_element, source_patch, or source_replace.',
            ]),
        };
    }

    /**
     * @param array<string, mixed> $part
     * @param array<string, mixed> $operation
     */
    private function editGlobalPart(
        \WP_REST_Request $request,
        array $part,
        string $mode,
        array $operation,
    ): \WP_REST_Response|\WP_Error {
        $partTypeValue = $this->globalParts->assignedTypeValue($request, $part);
        $requestedPartType = $this->globalParts->parseAssignedType($partTypeValue);
        if ($requestedPartType === false) {
            return $this->responses->error(400, 'invalid_part_type', [
                'KIND: global_part',
                'PART_TYPE: ' . ($partTypeValue !== '' ? $partTypeValue : 'missing'),
                'NEXT STEP',
                'Retry with global_part_id from the current reusable canvas, or part.part_type=header or footer for assigned site defaults.',
            ]);
        }

        $partType = $requestedPartType instanceof GlobalPartType ? $requestedPartType->value : '';
        $globalPartId = $this->globalParts->requestId($request, $part, $partType === '');
        if ($globalPartId <= 0 && $partType === '') {
            return $this->responses->error(400, 'missing_part_type', [
                'KIND: global_part',
                'NEXT STEP',
                'Retry with part.global_part_id from the current reusable canvas, or part.part_type=header or footer.',
            ]);
        }

        $base = [
            'part_type' => $partType,
            'global_part_id' => $globalPartId > 0 ? $globalPartId : null,
        ];

        return match ($mode) {
            'css_rule' => $this->responses->facade($this->globalPartSources->patchSource($this->requests->withOverrides($request, $base + [
                'css_rules' => $operation['css_rules'] ?? [],
            ])), 'css_rule'),
            'source_patch' => $this->responses->facade($this->globalPartSources->patchSource($this->requests->withOverrides($request, $base + [
                'html_patches' => $operation['html_patches'] ?? [],
                'css_patches' => $operation['css_patches'] ?? [],
                'css_rules' => $operation['css_rules'] ?? [],
            ])), 'source_patch'),
            'source_replace' => $this->responses->facade($this->globalPartSources->update($this->requests->withOverrides($request, $base + [
                'name' => $operation['name'] ?? null,
                'html' => $operation['html'] ?? null,
                'css' => $operation['css'] ?? '',
            ])), 'source_replace'),
            default => $this->responses->error(400, 'unsupported_operation_mode', [
                'KIND: global_part',
                'MODE: ' . $mode,
                'NEXT STEP',
                'Retry with css_rule, source_patch, or source_replace.',
            ]),
        };
    }
}

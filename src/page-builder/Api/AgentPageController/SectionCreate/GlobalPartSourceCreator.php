<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\SectionCreate;

use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\Section;

/**
 * Bootstraps the single canonical source section of a blank reusable canvas.
 */
final class GlobalPartSourceCreator
{
    public function __construct(
        private readonly GlobalPartService $globalParts,
        private readonly SectionCreateResponseFormatter $responses,
    ) {}

    public function create(
        int $globalPartId,
        string $name,
        string $html,
        string $css,
    ): \WP_REST_Response {
        $existing = $this->globalParts->findById($globalPartId);
        if ($existing === null) {
            return $this->responses->error('create_section', 404, 'no_active_global_part', [
                'KIND: global_part',
                'GLOBAL_PART_ID: ' . $globalPartId,
                'NEXT STEP',
                'Retry from the current reusable canvas or use a valid global_part_id.',
            ]);
        }

        $partType = $this->resolvedGlobalPartType($existing, GlobalPartType::Section->value);
        if ($this->sourceSection($existing) instanceof Section) {
            return $this->responses->error('create_section', 422, 'global_part_source_exists', [
                'KIND: global_part',
                'GLOBAL_PART_ID: ' . $globalPartId,
                'PART_TYPE: ' . $partType,
                'NEXT STEP',
                'This reusable already has source content. Use edit_part kind=global_part mode=source_patch or mode=source_replace instead.',
            ]);
        }

        $title = \trim((string) ($existing['title'] ?? ''));
        if ($title === '') {
            $title = $name !== '' ? $name : 'Reusable section';
        }

        try {
            $result = $this->globalParts->replaceExisting(
                $globalPartId,
                $title,
                [
                    'name' => $name !== '' ? $name : $title,
                    'content' => ['html' => $html, 'css' => $css],
                ],
                GlobalPartType::fromString($partType),
            );
        } catch (SectionValidationException $exception) {
            return $this->responses->error('create_section', 422, 'global_part_validation_failed', [
                'KIND: global_part',
                'GLOBAL_PART_ID: ' . $globalPartId,
                'PART_TYPE: ' . $partType,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Fix the section source and retry.',
            ]);
        } catch (StaleSourceGenerationException $exception) {
            return $this->responses->stale('create_section', $exception);
        } catch (\RuntimeException $exception) {
            return $this->responses->globalPartWriteError(
                'create_section',
                $partType,
                $globalPartId,
                $exception,
            );
        }

        $section = $this->globalParts->sourceSection($globalPartId);
        if (!$section instanceof Section) {
            return $this->responses->error(
                'create_section',
                500,
                'global_part_source_bootstrap_failed',
                [
                    'KIND: global_part',
                    'GLOBAL_PART_ID: ' . $globalPartId,
                    'PART_TYPE: ' . $partType,
                    'NEXT STEP',
                    'Refresh the reusable canvas and retry once.',
                ],
            );
        }

        return $this->responses->globalPartSuccess(
            $globalPartId,
            $partType,
            $section,
            $result,
        );
    }

    /**
     * @param array<string, mixed> $globalPart
     */
    private function sourceSection(array $globalPart): ?Section
    {
        $sections = $globalPart['sections'] ?? [];
        if (!\is_array($sections) || $sections === []) {
            return null;
        }

        $sectionData = $sections[0] ?? null;
        if (!\is_array($sectionData)) {
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

    /**
     * @param array<string, mixed> $resolved
     */
    private function resolvedGlobalPartType(array $resolved, string $fallback): string
    {
        $resolvedType = \trim((string) ($resolved['type'] ?? ''));

        return $resolvedType !== '' ? $resolvedType : $fallback;
    }
}

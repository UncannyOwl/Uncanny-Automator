<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\AgentWrite;

use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Resolves the Page Builder page whose working source a tool will mutate.
 */
final class AgentWritePageOwnerResolver
{
    private const GLOBAL_PART_POST_TYPE = 'upb_global_part';

    private ?\WP_REST_Request $reusableConversionRequest = null;
    private ?Section $reusableConversionSection = null;

    public function __construct(private readonly SectionRepositoryInterface $sections) {}

    public function resolve(string $toolName, \WP_REST_Request $request): int
    {
        return match ($toolName) {
            'create_section', 'manage_sections' => $this->pageId($request->get_param('page_id')),
            'edit_runtime' => $request->get_param('scope') === 'page'
                ? $this->runtimePageId($request)
                : 0,
            'edit_part' => $this->editPartPageId($request),
            'manage_binding' => $this->sectionOwnerId($request->get_param('section_id')),
            'manage_canvas' => $this->canvasWritePageId($request),
            'manage_reusable' => $this->reusableConversionPageId($request),
            default => 0,
        };
    }

    public function reusableConversionSection(\WP_REST_Request $request): ?Section
    {
        if ($this->reusableConversionRequest === $request) {
            return $this->reusableConversionSection;
        }

        $this->reusableConversionRequest = $request;
        $this->reusableConversionSection = null;

        if (trim((string) ($request->get_param('operation') ?? '')) !== 'convert') {
            return null;
        }

        $sectionId = RequestId::positive($request->get_param('section_id'));
        if ($sectionId === null) {
            return null;
        }

        try {
            return $this->reusableConversionSection = $this->sections->findById($sectionId);
        } catch (SectionNotFoundException) {
            return null;
        }
    }

    private function editPartPageId(\WP_REST_Request $request): int
    {
        $part = $request->get_param('part');
        if (is_array($part)) {
            $kind = trim((string) ($part['kind'] ?? ''));
            if ($kind === 'global_part') {
                return 0;
            }
            if (array_key_exists('section_id', $part)) {
                return $this->sectionOwnerId($part['section_id']);
            }
        }

        return $this->sectionOwnerId($request->get_param('section_id'));
    }

    private function canvasWritePageId(\WP_REST_Request $request): int
    {
        $operation = trim((string) ($request->get_param('operation') ?? ''));
        if (!in_array($operation, ['update', 'attach_reusable'], true)) {
            return 0;
        }

        foreach (['canvas_id', 'page_id', 'global_part_id'] as $key) {
            $value = $request->get_param($key);
            if ($value !== null) {
                return $this->pageId($value);
            }
        }

        $context = $request->get_param('page_builder_context');
        if (!is_array($context)) {
            return 0;
        }
        if (array_key_exists('global_part_id', $context)) {
            return 0;
        }

        return $this->pageId($context['page_id'] ?? null);
    }

    private function runtimePageId(\WP_REST_Request $request): int
    {
        $value = $request->get_param('page_id');
        if ($value !== null) {
            return $this->pageId($value);
        }

        $context = $request->get_param('page_builder_context');
        if (!is_array($context) || array_key_exists('global_part_id', $context)) {
            return 0;
        }

        return $this->pageId($context['page_id'] ?? null);
    }

    private function reusableConversionPageId(\WP_REST_Request $request): int
    {
        $section = $this->reusableConversionSection($request);

        return $section instanceof Section ? $this->pageId($section->pageId()) : 0;
    }

    private function sectionOwnerId(mixed $value): int
    {
        $sectionId = RequestId::positive($value);
        if ($sectionId === null) {
            return 0;
        }

        try {
            return $this->pageId($this->sections->findById($sectionId)->pageId());
        } catch (SectionNotFoundException) {
            return 0;
        }
    }

    private function pageId(mixed $value): int
    {
        $pageId = RequestId::positive($value);
        if ($pageId === null) {
            return 0;
        }

        return \get_post_type($pageId) === self::GLOBAL_PART_POST_TYPE ? 0 : $pageId;
    }
}

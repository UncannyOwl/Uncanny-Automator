<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\GlobalPartSource;

use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\Section;

/**
 * Resolves explicit reusable IDs and assigned header/footer source aggregates.
 *
 * Context fallback is deliberately opt-in so an explicit assigned-part request
 * can never drift onto the reusable section currently open in the editor.
 */
final class GlobalPartSourceResolver
{
    public function __construct(
        private readonly GlobalPartDefaultsService $globalPartDefaults,
        private readonly GlobalPartService $globalPartService,
    ) {}

    public function requestId(
        \WP_REST_Request $request,
        ?array $part = null,
        bool $allowContextFallback = true,
    ): int {
        $partId = absint($part['global_part_id'] ?? 0);
        if ($partId > 0) {
            return $partId;
        }

        $requestId = absint($request->get_param('global_part_id'));
        if ($requestId > 0) {
            return $requestId;
        }

        if (!$allowContextFallback) {
            return 0;
        }

        $context = $request->get_param('page_builder_context');
        if (is_array($context)) {
            return absint($context['global_part_id'] ?? 0);
        }

        return 0;
    }

    public function assignedTypeValue(\WP_REST_Request $request, ?array $part = null): string
    {
        $typeValue = $part['part_type'] ?? null;
        if (!is_string($typeValue) || trim($typeValue) === '') {
            $typeValue = $request->get_param('part_type');
        }

        return is_string($typeValue) ? trim($typeValue) : '';
    }

    public function parseAssignedType(string $typeValue): GlobalPartType|false|null
    {
        if ($typeValue === '') {
            return null;
        }

        $type = GlobalPartType::tryFrom($typeValue);

        return $type instanceof GlobalPartType && $type !== GlobalPartType::Section
            ? $type
            : false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(
        \WP_REST_Request $request,
        string $partType,
        int $globalPartId = 0,
    ): ?array {
        if ($globalPartId > 0) {
            return $this->globalPartService->findById($globalPartId);
        }

        if ($partType === '') {
            $globalPartId = $this->requestId($request);
            if ($globalPartId > 0) {
                return $this->globalPartService->findById($globalPartId);
            }

            return null;
        }

        return $this->globalPartDefaults->resolveAssignedForType(GlobalPartType::fromString($partType));
    }

    /**
     * @param array<string, mixed> $resolved
     */
    public function resolvedType(array $resolved, string $fallback): string
    {
        $resolvedType = trim((string) ($resolved['type'] ?? ''));

        return $resolvedType !== '' ? $resolvedType : $fallback;
    }

    /**
     * Hydrate the canonical source unit from an active global part.
     *
     * @param array<string, mixed> $globalPart
     */
    public function sourceSection(array $globalPart): ?Section
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
}

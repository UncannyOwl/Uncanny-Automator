<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\PartRead;

use UncannyPageBuilder\Domain\Section\ComponentCategory;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Infrastructure\Section\ComponentCategoryClassifier;
use UncannyPageBuilder\Infrastructure\Section\DomSectionBindingContractInspector;
use UncannyPageBuilder\Infrastructure\Section\DomSectionManifestExtractor;
use UncannyPageBuilder\Infrastructure\Section\DomSectionTargetInspector;

/**
 * Formats the composable detail blocks returned by read_part.
 */
final class PartDetailPresenter
{
    public function __construct(
        private readonly ComponentCategoryClassifier $categoryClassifier,
        private readonly DomSectionManifestExtractor $manifestExtractor,
        private readonly DomSectionTargetInspector $targetInspector,
        private readonly DomSectionBindingContractInspector $bindingInspector,
        private readonly PartSourcePresenter $sourcePresenter,
    ) {}

    /**
     * @return list<string>
     */
    public function includes(\WP_REST_Request $request): array
    {
        $requested = array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) ($request->get_param('include') ?? 'manifest')),
        )));

        if ($requested === []) {
            $requested = ['manifest'];
        }

        $allowed = ['manifest', 'source', 'content_targets', 'design_targets', 'bindings'];
        foreach ($requested as $value) {
            if (!in_array($value, $allowed, true)) {
                return [];
            }
        }

        return array_values(array_unique($requested));
    }

    /**
     * @param list<string> $includes
     * @return list<string>
     */
    public function sectionLines(Section $section, array $includes, \WP_REST_Request $request): array
    {
        $lines = [
            'TOOL: read_part',
            'RESULT: success',
            'KIND: section',
            'PAGE_ID: ' . $section->pageId(),
            'SECTION_ID: ' . (string) $section->id(),
            'SECTION_NAME: ' . $section->name(),
            '',
        ];

        $this->appendDetails($lines, $section, $includes, $request);

        return $lines;
    }

    /**
     * @param array<string, mixed> $resolved
     * @param list<string> $includes
     * @return list<string>
     */
    public function globalPartLines(
        string $partType,
        array $resolved,
        Section $section,
        array $includes,
        \WP_REST_Request $request,
    ): array {
        $lines = [
            'TOOL: read_part',
            'RESULT: success',
            'KIND: global_part',
            'PART_TYPE: ' . $partType,
            'POST_ID: ' . (int) ($resolved['post_id'] ?? 0),
            'TITLE: ' . (string) ($resolved['title'] ?? $partType),
            '',
        ];

        $this->appendDetails($lines, $section, $includes, $request);

        return $lines;
    }


    private function classify(Section $section): ComponentCategory
    {
        try {
            $manifest = $this->manifestExtractor->extract($section);

            return $this->categoryClassifier->classifyWithManifest($section->name(), $manifest);
        } catch (\Throwable) {
            $nameLower = strtolower(trim($section->name()));
            foreach (ComponentCategory::cases() as $case) {
                if ($case !== ComponentCategory::Generic && str_contains($nameLower, $case->value)) {
                    return $case;
                }
            }

            return ComponentCategory::Generic;
        }
    }




    /**
     * @param list<string> $lines
     * @param list<string> $includes
     */
    private function appendDetails(array &$lines, Section $section, array $includes, \WP_REST_Request $request): void
    {
        foreach ($includes as $include) {
            if ($include === 'manifest') {
                $this->appendManifest($lines, $section);
                continue;
            }

            if ($include === 'source') {
                array_push($lines, ...$this->sourcePresenter->detailLines($section));
                continue;
            }

            if ($include === 'content_targets') {
                $this->appendContentTargets($lines, $section, $request);
                continue;
            }

            if ($include === 'design_targets') {
                $this->appendDesignTargets($lines, $section, $request);
                continue;
            }

            if ($include === 'bindings') {
                $this->appendBindings($lines, $section, $request);
            }
        }
    }

    /**
     * @param list<string> $lines
     */
    private function appendManifest(array &$lines, Section $section): void
    {
        $category = $this->classify($section);
        $root = $this->targetInspector->rootMetadata($section);
        $targetCounts = $this->targetInspector->contentTargetCounts($section);
        $bindings = [];

        try {
            foreach ($this->bindingInspector->inspect($section) as $contract) {
                $bindings[] = $contract->toArray();
            }
        } catch (\Throwable) {
        }

        $lines[] = 'MANIFEST';
        $lines[] = 'CATEGORY: ' . $category->value;
        $lines[] = '';
        $lines[] = 'ROOT';
        $lines[] = 'TAG: ' . $root['tag'];
        $lines[] = 'CLASSES: ' . ($root['classes'] !== [] ? implode(' ', $root['classes']) : 'none');
        $lines[] = 'SOURCE_PATH: ' . $root['source_path'];
        $lines[] = '';
        $lines[] = 'CONTENT TARGETS SUMMARY';
        $lines[] = 'TEXT_TARGETS: ' . $targetCounts['text'];
        $lines[] = 'IMAGE_TARGETS: ' . $targetCounts['image'];
        $lines[] = 'LINK_TARGETS: ' . $targetCounts['link'];
        $lines[] = '';
        $lines[] = 'BINDINGS';
        if ($bindings === []) {
            $lines[] = 'none';
        } else {
            foreach ($bindings as $binding) {
                $lines[] = '- ' . (string) ($binding['binding_id'] ?? $binding['source'] ?? 'unknown');
            }
        }
        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = 'For copy/image/link edits, call read_part include=content_targets.';
        $lines[] = 'For visual styling, call read_part include=design_targets.';
        $lines[] = 'For raw source surgery, call read_part include=source.';
        $lines[] = '';
    }

    /**
     * @param list<string> $lines
     */
    private function appendContentTargets(array &$lines, Section $section, \WP_REST_Request $request): void
    {
        $targets = $this->targetInspector->contentTargets($section, $this->targetTypes($request));
        $lines[] = 'CONTENT TARGETS';
        $this->appendContentTargetLines($lines, 'TEXT TARGETS', $targets['text']);
        $this->appendContentTargetLines($lines, 'IMAGE TARGETS', $targets['image']);
        $this->appendContentTargetLines($lines, 'LINK TARGETS', $targets['link']);
        $this->appendContentTargetLines($lines, 'BUTTON TARGETS', $targets['button']);
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use the target SOURCE_PATH with edit_part mode=text, mode=link, or mode=image.';
        $lines[] = '';
    }

    /**
     * @param list<string> $lines
     */
    private function appendDesignTargets(array &$lines, Section $section, \WP_REST_Request $request): void
    {
        $targets = $this->targetInspector->designTargets($section, $this->truthyParam($request->get_param('include_css')));
        $lines[] = 'DESIGN TARGETS';

        if ($targets === []) {
            $lines[] = 'TARGETS: none';
            $lines[] = '';
        }

        foreach ($targets as $index => $target) {
            $lines[] = 'TARGET ' . ((int) $index + 1);
            $lines[] = 'LABEL: ' . (string) ($target['label'] ?? '');
            $lines[] = 'TAG: ' . (string) ($target['tag'] ?? '');
            $lines[] = 'SOURCE_PATH: ' . (string) ($target['source_path'] ?? '');
            $lines[] = 'ID: ' . ((string) ($target['id'] ?? '') !== '' ? (string) $target['id'] : 'none');
            $lines[] = 'ELEMENT_ID: ' . ((string) ($target['element_id'] ?? '') !== '' ? (string) $target['element_id'] : 'none');
            $lines[] = 'COMPILED_SELECTOR: ' . ((string) ($target['compiled_selector'] ?? '') !== '' ? (string) $target['compiled_selector'] : 'none');
            $classes = is_array($target['classes'] ?? null) ? implode(' ', $target['classes']) : '';
            $lines[] = 'CLASSES: ' . ($classes !== '' ? $classes : 'none');
            $lines[] = 'TEXT: ' . (string) ($target['text'] ?? '');
            $lines[] = 'STYLE_OWNERSHIP: ' . (string) ($target['style_ownership'] ?? 'unstyled');
            $lines[] = 'RECOMMENDED_WRITE: ' . (string) ($target['recommended_write'] ?? 'edit_part mode=durable_style');
            if (isset($target['inline_style'])) {
                $lines[] = 'INLINE_STYLE: ' . (string) $target['inline_style'];
            }
            $this->appendCssCandidateLines($lines, 'CURRENT ELEMENT STYLES', $target['element_styles'] ?? null);
            $this->appendCssCandidateLines($lines, 'GENERATED CSS CANDIDATES', $target['generated_css_candidates'] ?? null);
            $lines[] = '';
        }

        $lines[] = 'NEXT STEP';
        $lines[] = 'Use edit_part mode=durable_style for user-owned visual changes. Generated source CSS is a lower layer, not the element customization workflow.';
        $lines[] = 'For durable_style, use target+styles: {mode:"durable_style", target:{source_path:"...", tag:"...", element_id:"..."}, styles:{color:"#111"}}. If ELEMENT_ID is none, send source_path and tag so Page Builder can materialize a stable id.';
        $lines[] = '';
    }

    /**
     * @param list<string> $lines
     */
    private function appendBindings(array &$lines, Section $section, \WP_REST_Request $request): void
    {
        $contracts = $this->bindingInspector->inspect($section);
        $bindings = array_map(static fn ($contract) => $contract->toArray(), $contracts);

        $bindingId = $request->get_param('binding_id');
        if ($bindingId !== null && $bindingId !== '') {
            $bindings = array_values(array_filter(
                $bindings,
                static fn ($binding) => $binding['binding_id'] === $bindingId,
            ));
        }

        $lines[] = 'BINDINGS DETAIL';
        if ($bindings === []) {
            $lines[] = 'none';
        }
        foreach ($bindings as $binding) {
            $lines[] = '- BINDING_ID: ' . (string) ($binding['binding_id'] ?? '');
            $lines[] = '  SOURCE: ' . (string) ($binding['source'] ?? '');
            $lines[] = '  PATH: ' . (string) ($binding['path'] ?? '');
            $lines[] = '  CONTRACT_HASH: ' . (string) ($binding['contract_hash'] ?? '');
            $lines[] = '  QUERY_ATTRIBUTES: ' . (json_encode($binding['query_attributes'] ?? [], JSON_UNESCAPED_SLASHES) ?: '{}');
            $lines[] = '  BIND_KEYS: ' . implode(', ', array_map('strval', (array) ($binding['bind_keys'] ?? [])));
            $lines[] = '  TEMPLATE_HTML:';
            $lines[] = (string) ($binding['template_html'] ?? '');
        }
        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = $bindings === []
            ? 'Use manage_binding operation=search and operation=guide before adding a dynamic binding.'
            : 'Use manage_binding operation=update_query or operation=update_template with a BINDING_ID copied exactly from this response.';
        $lines[] = '';
    }

    /**
     * @return list<string>
     */
    private function targetTypes(\WP_REST_Request $request): array
    {
        $raw = $request->get_param('target_types');
        if (is_array($raw)) {
            return array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $raw,
            )));
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return ['all'];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * @param list<string> $lines
     * @param list<array<string, string>> $targets
     */
    private function appendContentTargetLines(array &$lines, string $heading, array $targets): void
    {
        $lines[] = $heading;
        if ($targets === []) {
            $lines[] = 'none';
            $lines[] = '';
            return;
        }

        foreach ($targets as $index => $target) {
            $lines[] = ((int) $index + 1) . '. TARGET_ID: ' . ($target['target_id'] ?? '');
            $lines[] = '   LABEL: ' . ($target['label'] ?? '');
            $lines[] = '   TAG: ' . ($target['tag'] ?? '');
            $lines[] = '   SOURCE_PATH: ' . ($target['source_path'] ?? '');
            foreach (['text' => 'TEXT', 'src' => 'SRC', 'alt' => 'ALT', 'href' => 'HREF'] as $key => $label) {
                if (array_key_exists($key, $target)) {
                    $lines[] = '   ' . $label . ': ' . $target[$key];
                }
            }
            $lines[] = '   RECOMMENDED_TOOL: ' . ($target['recommended_tool'] ?? '');
            $lines[] = '';
        }
    }

    private function truthyParam(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param list<string> $lines
     */
    private function appendCssCandidateLines(array &$lines, string $heading, mixed $rules): void
    {
        if (!is_array($rules)) {
            return;
        }

        $lines[] = $heading;
        if ($rules === []) {
            $lines[] = 'none';
            return;
        }

        foreach ($rules as $rule) {
            $lines[] = (string) $rule;
        }
    }
}

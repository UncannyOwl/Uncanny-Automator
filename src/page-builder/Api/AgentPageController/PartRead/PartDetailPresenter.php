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

        $this->appendDetails($lines, $section, $includes, $request, false, true);

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
        $globalPartId = (int) ($resolved['post_id'] ?? 0);
        $lines = [
            'TOOL: read_part',
            'RESULT: success',
            'KIND: global_part',
            'PART_TYPE: ' . $partType,
            'GLOBAL_PART_ID: ' . $globalPartId,
            'POST_ID: ' . $globalPartId,
            'TITLE: ' . (string) ($resolved['title'] ?? $partType),
            '',
        ];

        $this->appendDetails($lines, $section, $includes, $request, true, false);

        return $lines;
    }

    /**
     * @return list<string>
     */
    public function additionalGlobalSourceLines(Section $section, int $rowNumber): array
    {
        return [
            'ADDITIONAL SOURCE ROW ' . $rowNumber,
            'SOURCE_SECTION_ID: ' . (string) ($section->id() ?? 0),
            'SOURCE_SECTION_NAME: ' . $section->name(),
            'POSITION: ' . $section->position(),
            ...$this->sourcePresenter->detailLines($section, false),
        ];
    }

    /**
     * @param list<string> $includes
     * @return list<string>
     */
    public function globalPartNextStepLines(array $includes, int $globalPartId): array
    {
        $hasSource = in_array('source', $includes, true);
        $part = 'part={kind:"global_part",global_part_id:' . $globalPartId . '}';

        return [
            'NEXT STEP',
            $hasSource
                ? 'Call edit_part with ' . $part . ' and operation={mode:"source_patch",...} or operation={mode:"source_replace",...} for global-part content.'
                : 'Call read_part with kind=global_part, global_part_id=' . $globalPartId . ', and include=source before editing global-part content.',
            $hasSource
                ? 'Call edit_part with ' . $part . ' and operation={mode:"css_rule",...} for global-part CSS.'
                : 'Then call edit_part with ' . $part . ' and a supported operation object.',
            '',
        ];
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
    private function appendDetails(
        array &$lines,
        Section $section,
        array $includes,
        \WP_REST_Request $request,
        bool $isGlobalPart,
        bool $includeNextStep,
    ): void {
        foreach ($includes as $include) {
            if ($include === 'manifest') {
                $this->appendManifest($lines, $section, $isGlobalPart, $includeNextStep);
                continue;
            }

            if ($include === 'source') {
                array_push($lines, ...$this->sourcePresenter->detailLines($section, $includeNextStep));
                continue;
            }

            if ($include === 'content_targets') {
                $this->appendContentTargets($lines, $section, $request, $isGlobalPart, $includeNextStep);
                continue;
            }

            if ($include === 'design_targets') {
                $this->appendDesignTargets($lines, $section, $request, $isGlobalPart, $includeNextStep);
                continue;
            }

            if ($include === 'bindings') {
                $this->appendBindings($lines, $section, $request, $isGlobalPart, $includeNextStep);
            }
        }
    }

    /**
     * @param list<string> $lines
     */
    private function appendManifest(
        array &$lines,
        Section $section,
        bool $isGlobalPart,
        bool $includeNextStep,
    ): void {
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
        if ($includeNextStep) {
            $lines[] = 'NEXT STEP';
            if ($isGlobalPart) {
                $lines[] = 'For global-part content, call read_part include=source before edit_part mode=source_patch or mode=source_replace.';
                $lines[] = 'For global-part CSS, call read_part include=source before edit_part mode=css_rule.';
            } else {
                $lines[] = 'For copy/image/link edits, call read_part include=content_targets.';
                $lines[] = 'For visual styling, call read_part include=design_targets.';
                $lines[] = 'For raw source surgery, call read_part include=source.';
            }
            $lines[] = '';
        }
    }

    /**
     * @param list<string> $lines
     */
    private function appendContentTargets(
        array &$lines,
        Section $section,
        \WP_REST_Request $request,
        bool $isGlobalPart,
        bool $includeNextStep,
    ): void {
        $targets = $this->targetInspector->contentTargets($section, $this->targetTypes($request));
        $lines[] = 'CONTENT TARGETS';
        $this->appendContentTargetLines($lines, 'TEXT TARGETS', $targets['text'], $isGlobalPart);
        $this->appendContentTargetLines($lines, 'IMAGE TARGETS', $targets['image'], $isGlobalPart);
        $this->appendContentTargetLines($lines, 'LINK TARGETS', $targets['link'], $isGlobalPart);
        $this->appendContentTargetLines($lines, 'BUTTON TARGETS', $targets['button'], $isGlobalPart);
        $hasBindingOwnedTargets = count(array_filter(
            array_merge(...array_values($targets)),
            static fn (array $target): bool => ($target['binding_ownership'] ?? '') === 'binding_owned',
        )) > 0;
        $hasFieldOwnedTargets = count(array_filter(
            array_merge(...array_values($targets)),
            static fn (array $target): bool => ($target['binding_ownership'] ?? '') === 'field_owned',
        )) > 0;
        if ($includeNextStep) {
            $lines[] = 'NEXT STEP';
            if ($isGlobalPart) {
                $lines[] = 'Use read_part include=source, then edit_part mode=source_patch or mode=source_replace for global-part content.';
            } else {
                $lines[] = 'Use the target SOURCE_PATH with edit_part mode=text, mode=link, or mode=image for direct_source targets.';
                if ($hasBindingOwnedTargets) {
                    $lines[] = 'A binding_owned value is stored template source, not a rendered WordPress value. Use its BINDING_ID with manage_binding operation=update_template.';
                }
                if ($hasFieldOwnedTargets) {
                    $lines[] = 'A field_owned target mixes a dynamic attribute with stored source. Edit only the STORED_SOURCE_FIELDS and do not replace the BINDING_OWNED_FIELD.';
                }
            }
            $lines[] = '';
        }
    }

    /**
     * @param list<string> $lines
     */
    private function appendDesignTargets(
        array &$lines,
        Section $section,
        \WP_REST_Request $request,
        bool $isGlobalPart,
        bool $includeNextStep,
    ): void {
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
            $this->appendTextPreviewMetadata($lines, $target);
            $lines[] = 'STYLE_OWNERSHIP: ' . (string) ($target['style_ownership'] ?? 'unstyled');
            $lines[] = 'RECOMMENDED_WRITE: ' . ($isGlobalPart
                ? 'edit_part mode=css_rule'
                : (string) ($target['recommended_write'] ?? 'edit_part mode=css_rule'));
            if (isset($target['inline_style'])) {
                $lines[] = 'INLINE_STYLE: ' . (string) $target['inline_style'];
            }
            $this->appendCssCandidateLines($lines, 'CURRENT ELEMENT STYLES', $target['element_styles'] ?? null);
            $this->appendCssCandidateLines($lines, 'GENERATED CSS CANDIDATES', $target['generated_css_candidates'] ?? null);
            $lines[] = '';
        }

        if ($includeNextStep) {
            $lines[] = 'NEXT STEP';
            if ($isGlobalPart) {
                $lines[] = 'Use read_part include=source, then edit_part mode=css_rule for global-part CSS or source_patch/source_replace for source changes.';
            } else {
                $lines[] = 'Use edit_part mode=css_rule for one-off section styling. Use durable_style only when STYLE_OWNERSHIP is element_style for the property. Use source_patch for an inline_attribute.';
                $lines[] = 'For an existing element_style, use target+styles: {mode:"durable_style", target:{source_path:"...", tag:"...", element_id:"..."}, styles:{color:"#111"}}. If ELEMENT_ID is none, omit element_id.';
            }
            $lines[] = '';
        }
    }

    /**
     * @param list<string> $lines
     */
    private function appendBindings(
        array &$lines,
        Section $section,
        \WP_REST_Request $request,
        bool $isGlobalPart,
        bool $includeNextStep,
    ): void {
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
            $lines[] = '  QUERY_ATTRIBUTES: ' . (self::encodeJson($binding['query_attributes'] ?? [], JSON_UNESCAPED_SLASHES) ?: '{}');
            $lines[] = '  BIND_KEYS: ' . implode(', ', array_map('strval', (array) ($binding['bind_keys'] ?? [])));
            $lines[] = '  TEMPLATE_HTML:';
            $lines[] = (string) ($binding['template_html'] ?? '');
        }
        $lines[] = '';
        if ($includeNextStep) {
            $lines[] = 'NEXT STEP';
            if ($isGlobalPart) {
                $lines[] = 'Use read_part include=source, then edit_part mode=source_patch or mode=source_replace for global-part binding markup.';
            } else {
                $lines[] = $bindings === []
                    ? 'Use manage_binding operation=search and operation=guide before adding a dynamic binding.'
                    : 'Use manage_binding operation=update_query or operation=update_template with a BINDING_ID copied exactly from this response.';
            }
            $lines[] = '';
        }
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
    private function appendContentTargetLines(
        array &$lines,
        string $heading,
        array $targets,
        bool $isGlobalPart,
    ): void {
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
            $this->appendTextPreviewMetadata($lines, $target, '   ');
            foreach (
                [
                    'binding_ownership' => 'BINDING_OWNERSHIP',
                    'value_source' => 'VALUE_SOURCE',
                    'binding_source' => 'BINDING_SOURCE',
                    'binding_id' => 'BINDING_ID',
                    'binding_owned_field' => 'BINDING_OWNED_FIELD',
                    'stored_source_fields' => 'STORED_SOURCE_FIELDS',
                ] as $key => $label
            ) {
                if (array_key_exists($key, $target)) {
                    $lines[] = '   ' . $label . ': ' . $target[$key];
                }
            }
            $lines[] = '   RECOMMENDED_TOOL: ' . ($isGlobalPart
                ? 'edit_part mode=source_patch after read_part include=source'
                : ($target['recommended_tool'] ?? ''));
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

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $target
     */
    private function appendTextPreviewMetadata(array &$lines, array $target, string $prefix = ''): void
    {
        if (($target['text_preview_truncated'] ?? '') !== 'yes') {
            return;
        }

        $lines[] = $prefix . 'TEXT_PREVIEW_TRUNCATED: yes';
        $lines[] = $prefix . 'TEXT_PREVIEW_BYTES: ' . (string) ($target['text_preview_bytes'] ?? 0);
        $lines[] = $prefix . 'TEXT_SOURCE_BYTES: ' . (string) ($target['text_source_bytes'] ?? 0);
    }

    private static function encodeJson(mixed $value, int $flags = 0): string|false
    {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($value, $flags);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone API contract tests run without WordPress functions.
        return json_encode($value, $flags);
    }
}

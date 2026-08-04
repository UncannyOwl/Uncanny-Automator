<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

use UncannyPageBuilder\Domain\DesignStyles\ElementStyleRule;
use UncannyPageBuilder\Domain\Section\Section;

/**
 * Inspects stored section HTML for production agent target discovery.
 */
final class DomSectionTargetInspector
{
    private const PREVIEW_LIMIT = 160;

    /**
     * @return array{tag: string, classes: list<string>, source_path: string}
     */
    public function rootMetadata(Section $section): array
    {
        $root = $this->rootElement($section->content()->html());

        if (!$root instanceof \DOMElement) {
            return [
                'tag' => '',
                'classes' => [],
                'source_path' => '',
            ];
        }

        return [
            'tag' => strtolower($root->tagName),
            'classes' => $this->classList($root),
            'source_path' => '0',
        ];
    }

    /**
     * @return array{text: int, image: int, link: int}
     */
    public function contentTargetCounts(Section $section): array
    {
        $root = $this->rootElement($section->content()->html());
        if (!$root instanceof \DOMElement) {
            return ['text' => 0, 'image' => 0, 'link' => 0];
        }

        return [
            'text' => $this->countTextTargets($root),
            'image' => $this->countElements($root, 'img'),
            'link' => $this->countElements($root, 'a'),
        ];
    }

    /**
     * @param list<string> $types
     * @return array{text: list<array<string, string>>, image: list<array<string, string>>, link: list<array<string, string>>, button: list<array<string, string>>}
     */
    public function contentTargets(Section $section, array $types = ['all']): array
    {
        $root = $this->rootElement($section->content()->html());
        if (!$root instanceof \DOMElement) {
            return ['text' => [], 'image' => [], 'link' => [], 'button' => []];
        }

        $wanted = $this->normalizeTypes($types);

        return [
            'text' => $this->wants($wanted, 'text') ? $this->textTargets($root) : [],
            'image' => $this->wants($wanted, 'image') ? $this->elementTargets($root, 'img', 'image') : [],
            'link' => $this->wants($wanted, 'link') ? $this->elementTargets($root, 'a', 'link') : [],
            'button' => $this->wants($wanted, 'button') ? $this->buttonTargets($root) : [],
        ];
    }

    public function textForTarget(Section $section, string $sourcePath, string $expectedTag): ?string
    {
        $root = $this->rootElement($section->content()->html());
        if (!$root instanceof \DOMElement) {
            return null;
        }

        $target = $this->locateElementByPath($root, $sourcePath);
        if (!$target instanceof \DOMElement) {
            return null;
        }

        if ($expectedTag !== '' && strtolower($target->tagName) !== strtolower($expectedTag)) {
            return null;
        }

        return $this->preview($target->textContent ?? '');
    }

    public function attributeForTarget(Section $section, string $sourcePath, string $expectedTag, string $attribute): ?string
    {
        $root = $this->rootElement($section->content()->html());
        if (!$root instanceof \DOMElement) {
            return null;
        }

        $target = $this->locateElementByPath($root, $sourcePath);
        if (!$target instanceof \DOMElement) {
            return null;
        }

        if ($expectedTag !== '' && strtolower($target->tagName) !== strtolower($expectedTag)) {
            return null;
        }

        return $target->getAttribute($attribute);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function designTargets(Section $section, bool $includeCss = false): array
    {
        $root = $this->rootElement($section->content()->html());
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $sectionId = (int) ($section->id() ?? 0);
        $css = $includeCss ? $section->content()->css() : '';
        $elementStyles = $section->content()->elementStyles();
        $targets = [];
        $this->walkElements($root, function (\DOMElement $element, string $path) use (&$targets, $css, $includeCss, $sectionId, $elementStyles): void {
            if ($this->isNonContentTag($element)) {
                return;
            }

            $id = trim($element->getAttribute('id'));
            $classes = $this->classList($element);
            $elementStyleRules = $id !== '' ? $elementStyles->rulesForElementId($id) : [];
            $generatedRules = $includeCss ? $this->generatedCssCandidates($css, $id, $classes) : [];
            $hasInlineStyle = trim($element->getAttribute('style')) !== '';
            $styleOwnership = $elementStyleRules !== [] ? 'element_style' : ($hasInlineStyle ? 'inline_attribute' : 'unstyled');

            $target = [
                'label' => $this->label($element),
                'tag' => strtolower($element->tagName),
                'source_path' => $path,
                'id' => $id,
                'element_id' => $id,
                'compiled_selector' => $this->compiledElementSelector($sectionId, $id),
                'classes' => $classes,
                'text' => $this->preview($element->textContent ?? ''),
                'style_ownership' => $styleOwnership,
                'recommended_write' => 'edit_part mode=durable_style',
            ];

            if ($hasInlineStyle) {
                $target['inline_style'] = trim($element->getAttribute('style'));
            }
            if ($includeCss) {
                $target['element_styles'] = $this->elementStyleLines($elementStyleRules);
                $target['generated_css_candidates'] = $generatedRules;
            }

            $targets[] = $target;
        });

        return $targets;
    }

    private function rootElement(string $html): ?\DOMElement
    {
        $doc = new \DOMDocument();
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div id="__upb_target_root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        $container = $doc->getElementById('__upb_target_root');
        if (!$container instanceof \DOMElement) {
            return null;
        }

        foreach ($container->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function classList(\DOMElement $element): array
    {
        $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];

        return array_values(array_filter($classes, static fn (string $class): bool => $class !== ''));
    }

    private function countElements(\DOMElement $root, string $tag): int
    {
        $count = strtolower($root->tagName) === $tag ? 1 : 0;

        foreach ($root->getElementsByTagName($tag) as $node) {
            if ($node instanceof \DOMElement) {
                ++$count;
            }
        }

        return $count;
    }

    private function countTextTargets(\DOMElement $root): int
    {
        $count = $this->elementHasDirectText($root) && !$this->isNonContentTag($root) ? 1 : 0;

        foreach ($root->getElementsByTagName('*') as $node) {
            if (!$node instanceof \DOMElement || $this->isNonContentTag($node)) {
                continue;
            }

            if ($this->elementHasDirectText($node)) {
                ++$count;
            }
        }

        return $count;
    }

    private function elementHasDirectText(\DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) !== '') {
                return true;
            }
        }

        return false;
    }

    private function isNonContentTag(\DOMElement $element): bool
    {
        return in_array(strtolower($element->tagName), ['script', 'style', 'template'], true);
    }

    /**
     * @param list<string> $types
     * @return list<string>
     */
    private function normalizeTypes(array $types): array
    {
        $normalized = [];
        foreach ($types as $type) {
            $value = strtolower(trim($type));
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized !== [] ? array_values(array_unique($normalized)) : ['all'];
    }

    /**
     * @param list<string> $types
     */
    private function wants(array $types, string $type): bool
    {
        return in_array('all', $types, true) || in_array($type, $types, true);
    }

    /**
     * @return list<array<string, string>>
     */
    private function textTargets(\DOMElement $root): array
    {
        $targets = [];
        $this->walkElements($root, function (\DOMElement $element, string $path) use (&$targets): void {
            $tag = strtolower($element->tagName);
            if ($this->isNonContentTag($element) || in_array($tag, ['a', 'button', 'img'], true)) {
                return;
            }

            if (!$this->elementHasDirectText($element)) {
                return;
            }

            $targets[] = [
                'target_id' => 'text-' . (count($targets) + 1),
                'label' => $this->label($element),
                'tag' => $tag,
                'source_path' => $path,
                'text' => $this->preview($element->textContent ?? ''),
                'recommended_tool' => 'edit_part mode=text',
            ];
        });

        return $targets;
    }

    /**
     * @return list<array<string, string>>
     */
    private function elementTargets(\DOMElement $root, string $tag, string $targetType): array
    {
        $targets = [];
        $this->walkElements($root, function (\DOMElement $element, string $path) use (&$targets, $tag, $targetType): void {
            if (strtolower($element->tagName) !== $tag) {
                return;
            }

            $target = [
                'target_id' => $targetType . '-' . (count($targets) + 1),
                'label' => $this->label($element),
                'tag' => $tag,
                'source_path' => $path,
                'recommended_tool' => $targetType === 'image' ? 'edit_part mode=image' : 'edit_part mode=link',
            ];

            if ($targetType === 'image') {
                $target['src'] = $element->getAttribute('src');
                $target['alt'] = $element->getAttribute('alt');
            } else {
                $target['href'] = $element->getAttribute('href');
                $target['text'] = $this->preview($element->textContent ?? '');
            }

            $targets[] = $target;
        });

        return $targets;
    }

    /**
     * @return list<array<string, string>>
     */
    private function buttonTargets(\DOMElement $root): array
    {
        $targets = [];
        $this->walkElements($root, function (\DOMElement $element, string $path) use (&$targets): void {
            $tag = strtolower($element->tagName);
            $classes = $this->classList($element);
            $looksLikeButton = $tag === 'button'
                || $element->getAttribute('role') === 'button'
                || count(array_filter($classes, static fn (string $class): bool => str_contains(strtolower($class), 'button') || str_contains(strtolower($class), 'btn'))) > 0;

            if (!$looksLikeButton) {
                return;
            }

            $targets[] = [
                'target_id' => 'button-' . (count($targets) + 1),
                'label' => $this->label($element),
                'tag' => $tag,
                'source_path' => $path,
                'text' => $this->preview($element->textContent ?? ''),
                'href' => $element->getAttribute('href'),
                'recommended_tool' => $tag === 'a' ? 'edit_part mode=link' : 'edit_part mode=text',
            ];
        });

        return $targets;
    }

    /**
     * @param callable(\DOMElement, string): void $visit
     */
    private function walkElements(\DOMElement $root, callable $visit): void
    {
        $this->walkElement($root, '0', $visit);
    }

    /**
     * @param callable(\DOMElement, string): void $visit
     */
    private function walkElement(\DOMElement $element, string $path, callable $visit): void
    {
        $visit($element, $path);

        foreach ($this->treeChildren($element) as $index => $child) {
            if ($child instanceof \DOMElement) {
                $this->walkElement($child, $path . '.' . $index, $visit);
            }
        }
    }

    /**
     * @return list<\DOMNode>
     */
    private function treeChildren(\DOMNode $node): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
                continue;
            }

            if ($child instanceof \DOMText && trim($child->wholeText) !== '') {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function locateElementByPath(\DOMElement $root, string $path): ?\DOMElement
    {
        $segments = explode('.', trim($path));
        if (($segments[0] ?? '') !== '0') {
            return null;
        }

        $current = $root;
        foreach (array_slice($segments, 1) as $segment) {
            if ($segment === '' || !ctype_digit($segment)) {
                return null;
            }

            $children = $this->treeChildren($current);
            $child = $children[(int) $segment] ?? null;
            if (!$child instanceof \DOMElement) {
                return null;
            }

            $current = $child;
        }

        return $current;
    }

    private function label(\DOMElement $element): string
    {
        $label = strtolower($element->tagName);
        $id = trim($element->getAttribute('id'));
        if ($id !== '') {
            return $label . '#' . $id;
        }

        $classes = array_slice($this->classList($element), 0, 3);
        if ($classes !== []) {
            return $label . '.' . implode('.', $classes);
        }

        return $label;
    }

    private function preview(string $text): string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $text));
        if (strlen($normalized) <= self::PREVIEW_LIMIT) {
            return $normalized;
        }

        return rtrim(substr($normalized, 0, self::PREVIEW_LIMIT - 3)) . '...';
    }

    /**
     * @param list<string> $classes
     */
    /**
     * @return list<string>
     */
    private function generatedCssCandidates(string $css, string $id, array $classes): array
    {
        $candidates = [];
        if ($id !== '') {
            $candidates = array_merge($candidates, $this->cssRulesForSelector($css, '#' . $id));
        }
        foreach (array_slice($classes, 0, 3) as $class) {
            $candidates = array_merge($candidates, $this->cssRulesForSelector($css, '.' . $class));
        }

        return array_values(array_unique($candidates));
    }

    private function compiledElementSelector(int $sectionId, string $elementId): string
    {
        if ($sectionId <= 0 || $elementId === '') {
            return 'none';
        }

        $sectionSelector = '#upb-section-' . $sectionId;

        return $elementId === 'upb-section-' . $sectionId
            ? $sectionSelector
            : $sectionSelector . ' #' . $elementId;
    }

    /**
     * @param ElementStyleRule[] $rules
     * @return list<string>
     */
    private function elementStyleLines(array $rules): array
    {
        $lines = [];
        foreach ($rules as $rule) {
            foreach ($rule->declarations() as $property => $value) {
                $lines[] = sprintf('%s %s %s %s: %s', $rule->kind(), $rule->viewport(), $rule->state(), $property, $value);
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function cssRulesForSelector(string $css, string $selector): array
    {
        if ($selector === '' || $selector === 'none') {
            return [];
        }

        $rules = [];
        $quotedSelector = preg_quote($selector, '/');
        if (preg_match_all('/([^{}]*' . $quotedSelector . '[^{]*)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $ruleSelector = trim((string) $match[1]);
            $block = trim((string) preg_replace('/\s+/', ' ', $match[2]));
            if ($ruleSelector !== '' && $block !== '') {
                $rules[] = $ruleSelector . ' { ' . $block . ' }';
            }
        }

        return $rules;
    }
}

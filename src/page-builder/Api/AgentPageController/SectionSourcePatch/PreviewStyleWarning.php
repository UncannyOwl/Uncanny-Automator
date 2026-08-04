<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\SectionSourcePatch;

use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Infrastructure\Section\DomSectionTargetInspector;

/**
 * Warns when previewed normal CSS targets nodes with stronger style ownership.
 */
final class PreviewStyleWarning
{
    public function __construct(
        private readonly DomSectionTargetInspector $targets,
    ) {}

    /**
     * @param list<string> $lines
     * @param array<int, mixed> $cssRules
     */
    public function append(
        array &$lines,
        Section $section,
        array $cssRules,
        string $toolName,
    ): void {
        if ($toolName !== 'preview_change' || $cssRules === []) {
            return;
        }

        foreach ($this->targets->designTargets($section) as $target) {
            $styleOwnership = (string) ($target['style_ownership'] ?? 'unstyled');
            if (!\in_array($styleOwnership, ['element_style', 'inline_attribute'], true)) {
                continue;
            }

            foreach ($cssRules as $rule) {
                $selector = \trim((string) (($rule['selector'] ?? '') ?: ''));
                if ($selector === '' || !$this->selectorMatchesTarget($selector, $target)) {
                    continue;
                }

                $lines[] = 'WARNING: proposed normal CSS may not win against durable element styles.';
                $lines[] = 'NEXT STEP: use read_part include=design_targets, then edit_part mode=durable_style if appropriate.';
                $lines[] = '';
                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $target
     */
    private function selectorMatchesTarget(string $selector, array $target): bool
    {
        foreach (\array_map('trim', \explode(',', $selector)) as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $simpleSelectors = \preg_split('/\s+|>|\+|~/', $candidate) ?: [];
            foreach ($simpleSelectors as $simpleSelector) {
                $simpleSelector = \trim((string) $simpleSelector);
                if ($simpleSelector !== '' && $this->simpleSelectorMatchesTarget($simpleSelector, $target)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $target
     */
    private function simpleSelectorMatchesTarget(string $selector, array $target): bool
    {
        $id = \trim((string) ($target['id'] ?? ''));
        if ($id !== '' && \preg_match('/#' . \preg_quote($id, '/') . '(?![A-Za-z0-9_-])/', $selector) === 1) {
            return true;
        }

        foreach ((array) ($target['classes'] ?? []) as $class) {
            $class = \trim((string) $class);
            if ($class !== '' && \preg_match('/\.' . \preg_quote($class, '/') . '(?![A-Za-z0-9_-])/', $selector) === 1) {
                return true;
            }
        }

        $tag = \trim((string) ($target['tag'] ?? ''));

        return $tag !== ''
            && \preg_match(
                '/^' . \preg_quote(\strtolower($tag), '/') . '(?=$|[.#[:])/',
                \strtolower($selector),
            ) === 1;
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

use DOMDocument;
use DOMElement;
use DOMXPath;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;

final class LucideIconValidator
{
    public function __construct(
        private readonly LucideIconCatalogInterface $catalog,
    ) {}

    public function assertValidHtml(string $html): void
    {
        $invalid = $this->invalidIconNames($html);

        if ($invalid !== []) {
            throw SectionValidationException::invalidLucideIcons($invalid);
        }
    }

    /**
     * @return string[]
     */
    public function warningsForHtml(string $html): array
    {
        $invalid = $this->invalidIconNames($html);
        if ($invalid === []) {
            return [];
        }

        return [
            'Unsupported Lucide icon name(s): ' . $this->formatIconNames($invalid)
            . '. The content was saved, but those icon(s) may render blank. Patch only the icon attribute if needed.',
        ];
    }

    public static function normalizeName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $normalized) ?? $normalized;
        $normalized = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $normalized) ?? $normalized;
        $normalized = preg_replace('/[\s_]+/', '-', $normalized) ?? $normalized;
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/-+/', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-');

        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $normalized) === 1
            ? $normalized
            : '';
    }

    /**
     * @return string[]
     */
    private function invalidIconNames(string $html): array
    {
        if (stripos($html, 'lucide') === false) {
            return [];
        }

        $invalid = [];
        foreach ($this->lucideIconNames($html) as $name) {
            $normalized = self::normalizeName($name);
            if ($normalized === '' || !$this->catalog->contains($normalized)) {
                $invalid[] = $name;
            }
        }

        return array_values(array_unique($invalid));
    }

    /**
     * @param string[] $names
     */
    private function formatIconNames(array $names): string
    {
        return implode(
            ', ',
            array_map(
                static fn (string $name): string => trim($name) === '' ? '[blank]' : "'{$name}'",
                $names,
            ),
        );
    }

    /**
     * @return string[]
     */
    private function lucideIconNames(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><html><body><div data-upb-lucide-root="1">' . $html . '</div></body></html>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[@data-lucide or @lucide]');
        if ($nodes === false) {
            return [];
        }

        $names = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            foreach (['data-lucide', 'lucide'] as $attribute) {
                if ($node->hasAttribute($attribute)) {
                    $names[] = trim($node->getAttribute($attribute));
                }
            }
        }

        return $names;
    }
}

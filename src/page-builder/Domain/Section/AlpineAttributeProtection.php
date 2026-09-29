<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Protects Alpine shorthand event names while HTML4 DOMDocument parses HTML.
 */
final class AlpineAttributeProtection
{
    private string $placeholderPrefix;

    /** @var array<string, string> */
    private array $originalNames = [];

    private int $nextPlaceholder = 0;

    public function __construct(string $completeSource)
    {
        $prefix = 'data-upb-alpine-' . substr(hash('sha256', $completeSource), 0, 12) . '-';
        while (str_contains($completeSource, $prefix)) {
            $prefix .= 'x';
        }

        $this->placeholderPrefix = $prefix;
    }

    public function protect(string $html): string
    {
        return (string) preg_replace_callback(
            '/(\s)@([A-Za-z_:][A-Za-z0-9_.:+-]*)(?=\s*(?:=|\/?>))/',
            function (array $match): string {
                $placeholder = $this->placeholderPrefix . $this->nextPlaceholder++;
                $this->originalNames[$placeholder] = '@' . $match[2];

                return $match[1] . $placeholder;
            },
            $html,
        );
    }

    public function restore(string $html): string
    {
        return strtr($html, $this->originalNames);
    }
}

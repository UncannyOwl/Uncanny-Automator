<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Compiler;

final class CssContractWarningDetector
{
    private const DOCUMENT_SELECTOR_PATTERN = '/(?:^|[,{])\s*(?:(?:body|html)\b|:root\b)(?=\s|[.#:\[>+~,{])/i';

    /**
     * @return string[]
     */
    public static function warningsForCss(string $css): array
    {
        if (trim($css) === '') {
            return [];
        }

        if (preg_match(self::DOCUMENT_SELECTOR_PATTERN, $css) !== 1) {
            return [];
        }

        return [
            'Document-level CSS selector detected: avoid body, html, and :root in generated Page Builder section CSS. The content was saved, but compiled canvas CSS may filter those selectors. Prefer section-local classes, :target, and local state classes instead.',
        ];
    }
}

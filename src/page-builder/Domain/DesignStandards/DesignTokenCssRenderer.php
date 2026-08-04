<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\DesignStyles\DesignStyleValue;

/**
 * Renders resolved design standards as scoped CSS.
 */
final class DesignTokenCssRenderer
{
    public const CANVAS_SELECTOR = ShadowCompiler::CANVAS_SCOPE;

    public static function renderProfile(
        DesignStandardsProfile $profile,
        string $selector = self::CANVAS_SELECTOR,
    ): string {
        $tokens = $profile->tokens()->toArray();
        $css = self::renderRootVariables($tokens, $selector);
        $css .= self::renderCanvasBodyRules($selector);
        $css .= self::renderRoleSelectors($selector);
        $css .= self::renderHeadingSizeRules($tokens, $selector);

        return $css;
    }

    /**
     * @param array<string, string|int|float> $tokens
     */
    public static function renderRootVariables(array $tokens, string $selector = self::CANVAS_SELECTOR): string
    {
        $css = $selector . '{';

        foreach ($tokens as $prop => $value) {
            $safeName = self::sanitizeCustomPropertyName((string) $prop);
            $safeValue = self::sanitizeCustomPropertyValue($value);

            if ($safeName === null || $safeValue === null) {
                continue;
            }

            $css .= $safeName . ':' . $safeValue . ';';
        }

        return $css . '}';
    }

    public static function renderCanvasBodyRules(string $selector = self::CANVAS_SELECTOR): string
    {
        return $selector . '{'
            . 'font-family:var(--bs-body-font-family);'
            . 'font-size:var(--bs-body-font-size);'
            . 'font-weight:var(--bs-body-font-weight);'
            . 'line-height:var(--bs-body-line-height);'
            . 'color:var(--bs-body-color);'
            . 'background-color:var(--bs-body-bg);'
            . '}';
    }

    public static function renderRoleSelectors(string $selector = self::CANVAS_SELECTOR): string
    {
        $rules = [
            "{$selector} p{"
                . 'font-family:var(--upb-paragraph-font-family,var(--bs-body-font-family));'
                . 'font-size:var(--upb-paragraph-font-size,var(--bs-body-font-size));'
                . 'font-weight:var(--upb-paragraph-font-weight,var(--bs-body-font-weight));'
                . 'line-height:var(--upb-paragraph-line-height,var(--bs-body-line-height));'
                . '}',
            "{$selector} h1,{$selector} h2,{$selector} h3,{$selector} h4,{$selector} h5,{$selector} h6{"
                . 'font-family:var(--bs-heading-font-family,inherit);'
                . 'font-weight:var(--bs-heading-font-weight,500);'
                . 'line-height:var(--bs-heading-line-height,1.2);'
                . 'color:var(--bs-heading-color,inherit);'
                . '}',
            "{$selector} button,{$selector} .btn,{$selector} input[type=\"button\"],{$selector} input[type=\"submit\"],{$selector} input[type=\"reset\"]{"
                . 'font-family:var(--bs-btn-font-family,var(--bs-body-font-family));'
                . 'font-size:var(--bs-btn-font-size,var(--bs-body-font-size));'
                . 'font-weight:var(--bs-btn-font-weight,var(--bs-body-font-weight));'
                . '}',
            "{$selector} nav,{$selector} nav a,{$selector} .navbar-brand,{$selector} .navbar-nav .nav-link{"
                . 'font-family:var(--upb-nav-font-family,var(--bs-body-font-family));'
                . 'font-size:var(--upb-nav-font-size,var(--bs-body-font-size));'
                . 'font-weight:var(--upb-nav-font-weight,600);'
                . 'line-height:var(--upb-nav-line-height,var(--bs-body-line-height));'
                . 'letter-spacing:var(--upb-nav-letter-spacing,normal);'
                . 'text-transform:var(--upb-nav-text-transform,none);'
                . '}',
            "{$selector} blockquote{"
                . 'font-family:var(--upb-blockquote-font-family,var(--bs-body-font-family));'
                . 'font-size:var(--upb-blockquote-font-size,var(--bs-body-font-size));'
                . 'font-weight:var(--upb-blockquote-font-weight,var(--bs-body-font-weight));'
                . 'line-height:var(--upb-blockquote-line-height,var(--bs-body-line-height));'
                . 'font-style:var(--upb-blockquote-font-style,italic);'
                . '}',
            "{$selector} code,{$selector} pre,{$selector} kbd,{$selector} samp{"
                . 'font-family:var(--bs-font-monospace);'
                . 'font-size:var(--upb-code-font-size,inherit);'
                . 'font-weight:var(--upb-code-font-weight,inherit);'
                . 'line-height:var(--upb-code-line-height,inherit);'
                . '}',
            "{$selector} small,{$selector} .small,{$selector} figcaption,{$selector} .form-text{"
                . 'font-family:var(--upb-small-font-family,var(--bs-body-font-family));'
                . 'font-size:var(--upb-small-font-size,0.875rem);'
                . 'font-weight:var(--upb-small-font-weight,inherit);'
                . 'line-height:var(--upb-small-line-height,inherit);'
                . 'letter-spacing:var(--upb-small-letter-spacing,inherit);'
                . 'text-transform:var(--upb-small-text-transform,inherit);'
                . '}',
        ];

        return implode('', $rules);
    }

    /**
     * @param array<string, string> $tokens
     */
    public static function renderHeadingSizeRules(array $tokens, string $selector = self::CANVAS_SELECTOR): string
    {
        $headingTokenKeys = BootstrapTokenProfile::headingSizeTokenKeys();
        $hasHeadingTokens = false;

        foreach ($headingTokenKeys as $key) {
            if (isset($tokens[$key])) {
                $hasHeadingTokens = true;
                break;
            }
        }

        if (!$hasHeadingTokens) {
            return '';
        }

        $css = '';
        for ($i = 1; $i <= 6; $i++) {
            $key = "--bs-heading-h{$i}-font-size";
            if (isset($tokens[$key])) {
                $safe = self::sanitizeCustomPropertyValue($tokens[$key]);
                if ($safe !== null) {
                    $css .= "{$selector} h{$i}{font-size:var({$key},{$safe});}";
                }
            }
        }

        return $css;
    }

    private static function sanitizeCustomPropertyName(string $name): ?string
    {
        return preg_match('/^--[a-zA-Z0-9_-]+$/', $name) === 1 ? $name : null;
    }

    private static function sanitizeCustomPropertyValue(string|int|float $value): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return DesignStyleValue::isSafeValue($text) ? $text : null;
    }
}

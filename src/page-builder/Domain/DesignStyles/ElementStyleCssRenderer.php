<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStyles;

/**
 * Compiles structured element style rules into deterministic section CSS.
 */
final class ElementStyleCssRenderer
{
    private const VIEWPORT_MEDIA = [
        'tablet' => '@media (max-width: 768px)',
        'mobile' => '@media (max-width: 375px)',
    ];
    private const VIEWPORT_ORDER = ['desktop', 'tablet', 'mobile'];

    private const STATE_PSEUDO = [
        'hover' => ':hover',
        'focus' => ':focus',
        'active' => ':active',
    ];

    /** @var array<string, true> */
    private const INHERITED_TYPOGRAPHY_PROPERTIES = [
        'font-family'               => true,
        'font-size'                 => true,
        'font-weight'               => true,
        'font-style'                => true,
        'font-variant'              => true,
        'line-height'               => true,
        'letter-spacing'            => true,
        'word-spacing'              => true,
        'text-align'                => true,
        'text-transform'            => true,
        'text-decoration'           => true,
        'text-decoration-line'      => true,
        'text-decoration-color'     => true,
        'text-decoration-thickness' => true,
        'color'                     => true,
        'white-space'               => true,
        'word-break'                => true,
        'text-shadow'               => true,
    ];

    public static function renderForSection(int $sectionId, ElementStyleSheet $sheet): string
    {
        if ($sectionId <= 0 || $sheet->isEmpty()) {
            return '';
        }

        $blockRules = [];
        $inlineRules = [];
        foreach ($sheet->all() as $rule) {
            if ($rule->kind() === 'inline') {
                $inlineRules[] = $rule;
                continue;
            }

            $blockRules[] = $rule;
        }

        $css = [];
        foreach (self::VIEWPORT_ORDER as $viewport) {
            $rules = [];
            foreach ($blockRules as $rule) {
                if ($rule->viewport() === $viewport) {
                    $rules[] = self::renderDescendantRule($sectionId, $rule);
                }
            }
            foreach ($blockRules as $rule) {
                if ($rule->viewport() === $viewport) {
                    $rules[] = self::renderDirectRule($sectionId, $rule);
                }
            }
            foreach ($inlineRules as $rule) {
                if ($rule->viewport() === $viewport) {
                    $rules[] = self::renderDirectRule($sectionId, $rule);
                }
            }

            $css[] = self::wrapViewport($viewport, implode("\n", array_filter($rules)));
        }

        return implode("\n", array_filter($css));
    }

    private static function renderDirectRule(int $sectionId, ElementStyleRule $rule): string
    {
        $body = self::renderDeclarations($rule->declarations());
        if ($body === '') {
            return '';
        }

        return self::selector($sectionId, $rule) . self::pseudo($rule) . ' {' . $body . '}';
    }

    private static function renderDescendantRule(int $sectionId, ElementStyleRule $rule): string
    {
        $body = self::renderDeclarations(self::inheritedTypographyDeclarations($rule->declarations()));
        if ($body === '') {
            return '';
        }

        return self::selector($sectionId, $rule) . self::pseudo($rule) . ' * {' . $body . '}';
    }

    private static function selector(int $sectionId, ElementStyleRule $rule): string
    {
        $sectionSelector = sprintf('#upb-section-%d', $sectionId);

        return $rule->elementId() === 'upb-section-' . $sectionId
            ? $sectionSelector
            : sprintf('%s #%s', $sectionSelector, $rule->elementId());
    }

    private static function pseudo(ElementStyleRule $rule): string
    {
        return self::STATE_PSEUDO[$rule->state()] ?? '';
    }

    private static function wrapViewport(string $viewport, string $css): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }

        $media = self::VIEWPORT_MEDIA[$viewport] ?? '';

        return $media !== '' ? $media . ' {' . $css . '}' : $css;
    }

    /**
     * @param array<string, string> $declarations
     */
    private static function renderDeclarations(array $declarations): string
    {
        $out = [];
        foreach (self::orderDeclarations($declarations) as $property => $value) {
            if (!DesignStyleProperty::isRenderable($property) || !DesignStyleValue::isSafeValue($value)) {
                continue;
            }

            $out[] = $property . ': ' . self::normalValue($value) . ';';
        }

        return implode(' ', $out);
    }

    /**
     * Shorthands must paint before their structured longhand overrides. Keep
     * every other declaration in its original insertion order.
     *
     * @param array<string, string> $declarations
     * @return array<string, string>
     */
    private static function orderDeclarations(array $declarations): array
    {
        $order = array_flip(array_keys($declarations));
        uksort($declarations, static function (string $left, string $right) use ($order): int {
            $weight = static function (string $property): int {
                return match ($property) {
                    'font' => -20,
                    'text-decoration' => -10,
                    default => 0,
                };
            };
            $weightComparison = $weight($left) <=> $weight($right);

            return $weightComparison !== 0
                ? $weightComparison
                : (($order[$left] ?? 0) <=> ($order[$right] ?? 0));
        });

        return $declarations;
    }

    private static function normalValue(string $value): string
    {
        return trim(preg_replace('/\s*!important\s*$/i', '', trim($value)) ?? $value);
    }

    /**
     * @param array<string, string> $declarations
     * @return array<string, string>
     */
    private static function inheritedTypographyDeclarations(array $declarations): array
    {
        $out = [];
        foreach ($declarations as $property => $value) {
            if (isset(self::INHERITED_TYPOGRAPHY_PROPERTIES[$property])) {
                $out[$property] = $value;
            }
        }

        return $out;
    }
}

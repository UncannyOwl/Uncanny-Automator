<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStyles;

/**
 * Allowlist of CSS properties element commits may write.
 *
 * Element commits accept only the properties Page Builder intentionally
 * supports. Anything outside this list is rejected (not silently dropped).
 */
final class DesignStyleProperty
{
    /** @var string[] */
    private const ALLOWED = [
        // Box
        'display', 'box-sizing', 'width', 'min-width', 'max-width',
        'height', 'min-height', 'max-height', 'overflow', 'overflow-x',
        'overflow-y', 'opacity', 'visibility',
        // Flex and grid layout
        'flex-direction', 'flex-wrap', 'justify-content', 'align-items',
        'column-gap', 'row-gap', 'grid-template-columns', 'grid-template-rows',
        'grid-auto-flow', 'justify-items', 'align-self', 'justify-self',
        'order', 'flex-grow', 'flex-shrink', 'flex-basis',
        'grid-column-start', 'grid-column-end', 'grid-row-start', 'grid-row-end',
        // Position
        'position', 'top', 'right', 'bottom', 'left', 'z-index', 'inset',
        // Spacing
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        // Background
        'background', 'background-color', 'background-image', 'background-size',
        'background-repeat', 'background-position', 'background-position-x',
        'background-position-y', 'background-origin', 'background-clip',
        'background-attachment', 'background-blend-mode',
        // Typography
        'font-family', 'font-size', 'font-weight', 'font-style', 'font-variant',
        'line-height', 'letter-spacing', 'word-spacing', 'text-align',
        'text-transform', 'text-decoration', 'text-decoration-line',
        'text-decoration-color', 'text-decoration-thickness',
        'color', 'white-space', 'word-break',
        // Border
        'border', 'border-width', 'border-style', 'border-color',
        'border-top', 'border-right', 'border-bottom', 'border-left',
        'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
        'border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style',
        'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color',
        'border-radius', 'border-top-left-radius', 'border-top-right-radius',
        'border-bottom-right-radius', 'border-bottom-left-radius',
        'outline', 'outline-width', 'outline-style', 'outline-color', 'outline-offset',
        // Shadows and effects
        'box-shadow', 'text-shadow', 'filter', 'backdrop-filter',
        'mix-blend-mode', 'isolation', 'transform', 'transform-origin', 'clip-path',
    ];

    /** @var array<string, true>|null */
    private static ?array $lookup = null;

    public static function isAllowed(string $property): bool
    {
        if (self::$lookup === null) {
            self::$lookup = array_fill_keys(self::ALLOWED, true);
        }

        return isset(self::$lookup[strtolower(trim($property))]);
    }

    /**
     * Migration may retain a legacy `font` shorthand so editing one longhand
     * does not erase the shorthand's family, size, style, or line-height. It is
     * renderable internal state but remains rejected from public write payloads.
     */
    public static function isRenderable(string $property): bool
    {
        $property = strtolower(trim($property));

        return $property === 'font' || self::isAllowed($property);
    }

    /** @return string[] */
    public static function all(): array
    {
        return self::ALLOWED;
    }
}

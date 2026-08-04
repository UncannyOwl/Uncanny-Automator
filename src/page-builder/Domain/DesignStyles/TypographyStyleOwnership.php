<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStyles;

/**
 * Typography declarations owned by structured element styles.
 *
 * Legacy host inline declarations outrank compiled element CSS. When one of
 * these properties is edited, its inline longhand and any owning shorthand
 * must move into the structured baseline before the new value is applied.
 */
final class TypographyStyleOwnership
{
    /** @var array<string, string[]> */
    private const CONFLICTS = [
        'font-family'               => ['font', 'font-family'],
        'font-size'                 => ['font', 'font-size'],
        'font-weight'               => ['font', 'font-weight'],
        'font-style'                => ['font', 'font-style'],
        'font-variant'              => ['font', 'font-variant'],
        'line-height'               => ['font', 'line-height'],
        'letter-spacing'            => ['letter-spacing'],
        'word-spacing'              => ['word-spacing'],
        'text-align'                => ['text-align'],
        'text-transform'            => ['text-transform'],
        'text-decoration'           => ['text-decoration'],
        'text-decoration-line'      => ['text-decoration', 'text-decoration-line'],
        'text-decoration-color'     => ['text-decoration', 'text-decoration-color'],
        'text-decoration-thickness' => ['text-decoration', 'text-decoration-thickness'],
        'color'                     => ['color'],
        'fill'                      => ['fill'],
        'stroke'                    => ['stroke'],
        'white-space'               => ['white-space'],
        'word-break'                => ['word-break'],
        'text-shadow'               => ['text-shadow'],
    ];

    /**
     * @param string[] $editedProperties
     * @return string[]
     */
    public static function inlineConflicts(array $editedProperties): array
    {
        $conflicts = [];
        foreach ($editedProperties as $property) {
            $property = strtolower(trim($property));
            foreach (self::CONFLICTS[$property] ?? [] as $conflict) {
                $conflicts[$conflict] = true;
            }
        }

        return array_keys($conflicts);
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Finite vocabulary of section component categories.
 *
 * In Phase 7 categories are derived from section name heuristics,
 * not persisted. The `generic` case is the default fallback.
 */
enum ComponentCategory: string
{
    case Generic      = 'generic';
    case Hero         = 'hero';
    case Navigation   = 'navigation';
    case Header       = 'header';
    case Features     = 'features';
    case LogoCloud    = 'logo_cloud';
    case Testimonials = 'testimonials';
    case Pricing      = 'pricing';
    case Faq          = 'faq';
    case Cta          = 'cta';
    case Newsletter   = 'newsletter';
    case Resources    = 'resources';
    case BlogFeed     = 'blog_feed';
    case Team         = 'team';
    case Footer       = 'footer';

    /** @return string[] All category values. */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }

    /** Safe parse with fallback to Generic. */
    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        foreach (self::cases() as $case) {
            if ($case->value === $normalized) {
                return $case;
            }
        }
        return self::Generic;
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

use UncannyPageBuilder\Domain\Section\ComponentCategory;
use UncannyPageBuilder\Domain\Section\SectionManifest;

/**
 * Derives a ComponentCategory from section name and structural signals.
 *
 * Phase 7: name-based heuristic + dynamic region detection. Not persisted.
 * Wrong classifications have zero runtime impact — the category is metadata,
 * not identity.
 */
final class ComponentCategoryClassifier
{
    /**
     * @var array<string, ComponentCategory> Keyword → category mapping.
     *      First match wins, so more specific keywords come first.
     */
    private const KEYWORD_MAP = [
        'hero'        => ComponentCategory::Hero,
        'navigation'  => ComponentCategory::Navigation,
        'navbar'      => ComponentCategory::Navigation,
        'nav bar'     => ComponentCategory::Navigation,
        'header'      => ComponentCategory::Header,
        'footer'      => ComponentCategory::Footer,
        'feature'     => ComponentCategory::Features,
        'testimonial' => ComponentCategory::Testimonials,
        'review'      => ComponentCategory::Testimonials,
        'pricing'     => ComponentCategory::Pricing,
        'faq'         => ComponentCategory::Faq,
        'cta'         => ComponentCategory::Cta,
        'call to action' => ComponentCategory::Cta,
        'newsletter'  => ComponentCategory::Newsletter,
        'subscribe'   => ComponentCategory::Newsletter,
        'blog'        => ComponentCategory::BlogFeed,
        'resource'    => ComponentCategory::Resources,
        'team'        => ComponentCategory::Team,
        'logo'        => ComponentCategory::LogoCloud,
    ];

    /**
     * Classify using section name + manifest structural signals.
     *
     * Name match takes priority over structural signals.
     * Falls back to dynamic-region detection if name is generic.
     */
    public function classifyWithManifest(string $sectionName, SectionManifest $manifest): ComponentCategory
    {
        // Name-based heuristic (most reliable signal).
        $name = strtolower(trim($sectionName));
        foreach (self::KEYWORD_MAP as $keyword => $category) {
            if (str_contains($name, $keyword)) {
                return $category;
            }
        }

        $data = $manifest->toArray();
        $structuralCategory = $this->classifyDynamicRegions($data['dynamic_regions'] ?? []);
        if ($structuralCategory !== null) {
            return $structuralCategory;
        }

        return ComponentCategory::Generic;
    }

    /**
     * @param array<int, array<string, mixed>> $dynamicRegions
     */
    private function classifyDynamicRegions(array $dynamicRegions): ?ComponentCategory
    {
        foreach ($dynamicRegions as $region) {
            if (($region['source'] ?? null) === 'wp_menu') {
                return ComponentCategory::Navigation;
            }
        }

        if ($dynamicRegions !== []) {
            return ComponentCategory::BlogFeed;
        }

        return null;
    }
}

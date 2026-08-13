<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

use UncannyPageBuilder\Domain\DesignStyles\StableSelector;

/**
 * Gives a copied section new element IDs when its source IDs already exist.
 *
 * The HTML ID and its durable style-rule target are one identity contract.
 * They must change together before the copy joins the page aggregate.
 */
final class CopiedSectionIdentityRemapper
{
    private const ID_ATTRIBUTE = '/\bid\s*=\s*(["\'])(upb-el-[A-Za-z0-9_-]+)\1/i';

    public static function remapCollisions(
        SectionContent $content,
        SectionCollection $existingSections,
        string $copyKey,
    ): SectionContent {
        $occupied = self::elementIdsInSections($existingSections);
        $copiedIds = self::elementIdsInHtml($content->html());
        $remapped = [];

        foreach ($copiedIds as $elementId) {
            if (!isset($occupied[$elementId]) || isset($remapped[$elementId])) {
                continue;
            }

            $attempt = 0;
            do {
                $candidate = StableSelector::generateId(
                    'copied-section:' . $copyKey . ':' . $elementId . ':' . $attempt,
                );
                $attempt++;
            } while (isset($occupied[$candidate]) || in_array($candidate, $remapped, true));

            $remapped[$elementId] = $candidate;
            $occupied[$candidate] = true;
        }

        if ($remapped === []) {
            return $content;
        }

        $html = preg_replace_callback(
            self::ID_ATTRIBUTE,
            static function (array $match) use ($remapped): string {
                $elementId = (string) ($match[2] ?? '');

                return isset($remapped[$elementId])
                    ? str_replace($elementId, $remapped[$elementId], (string) $match[0])
                    : (string) $match[0];
            },
            $content->html(),
        );

        $css = $content->css();
        foreach ($remapped as $oldId => $newId) {
            $css = (string) preg_replace(
                '/#' . preg_quote($oldId, '/') . '(?![A-Za-z0-9_-])/',
                '#' . $newId,
                $css,
            );
        }

        return new SectionContent(
            $html ?? $content->html(),
            $css,
            $content->elementStyles()->remapElementIds($remapped),
        );
    }

    /** @return array<string, true> */
    private static function elementIdsInSections(SectionCollection $sections): array
    {
        $elementIds = [];
        foreach ($sections->all() as $section) {
            foreach (self::elementIdsInHtml($section->content()->html()) as $elementId) {
                $elementIds[$elementId] = true;
            }
        }

        return $elementIds;
    }

    /** @return string[] */
    private static function elementIdsInHtml(string $html): array
    {
        if (preg_match_all(self::ID_ATTRIBUTE, $html, $matches) !== false) {
            return array_values(array_unique(array_map('strval', $matches[2] ?? [])));
        }

        return [];
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Domain\DesignStyles\DesignStyleProperty;
use UncannyPageBuilder\Domain\DesignStyles\DesignStyleValue;
use UncannyPageBuilder\Domain\DesignStyles\ElementStyleSheet;
use UncannyPageBuilder\Domain\DesignStyles\TypographyStyleOwnership;

/**
 * Moves conflicting host inline typography into the desktop/normal structured
 * baseline before the requested element declaration is applied.
 */
final class InlineTypographyMigrator
{
    public function __construct(
        private readonly InlineStylePatcherInterface $inlineStyles,
    ) {}

    /**
     * @param string[] $editedProperties
     */
    public function migrate(
        string $html,
        ElementStyleSheet $elementStyles,
        string $elementId,
        string $kind,
        array $editedProperties,
    ): InlineTypographyMigration {
        $conflicts = TypographyStyleOwnership::inlineConflicts($editedProperties);
        if ($conflicts === []) {
            return InlineTypographyMigration::success($html, $elementStyles);
        }

        $patch = $this->inlineStyles->removeFromElement($html, $elementId, $conflicts);
        if (!$patch->isSafe()) {
            return InlineTypographyMigration::unsafe($html, $elementStyles, $patch->reason());
        }

        $baseline = $patch->removedDeclarations();
        foreach ($baseline as $property => $value) {
            if (!DesignStyleProperty::isRenderable($property) || !DesignStyleValue::isSafeValue($value)) {
                return InlineTypographyMigration::unsafe($html, $elementStyles, 'unsafe_inline_declaration');
            }

            $baseline[$property] = trim(preg_replace('/\s*!important\s*$/i', '', $value) ?? $value);
        }

        if ($baseline !== []) {
            $elementStyles = $elementStyles->withRule(
                $elementId,
                $kind,
                'desktop',
                'normal',
                $baseline,
            );
        }

        return InlineTypographyMigration::success($patch->html(), $elementStyles);
    }
}

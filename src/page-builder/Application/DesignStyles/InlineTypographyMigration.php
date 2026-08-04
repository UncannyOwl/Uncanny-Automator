<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Domain\DesignStyles\ElementStyleSheet;

/** Prepared legacy-inline typography migration for one stable element. */
final class InlineTypographyMigration
{
    public function __construct(
        private readonly string $html,
        private readonly ElementStyleSheet $elementStyles,
        private readonly bool $safe = true,
        private readonly string $reason = '',
    ) {}

    public static function success(string $html, ElementStyleSheet $elementStyles): self
    {
        return new self($html, $elementStyles);
    }

    public static function unsafe(string $html, ElementStyleSheet $elementStyles, string $reason): self
    {
        return new self($html, $elementStyles, false, $reason);
    }

    public function html(): string
    {
        return $this->html;
    }

    public function elementStyles(): ElementStyleSheet
    {
        return $this->elementStyles;
    }

    public function isSafe(): bool
    {
        return $this->safe;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}

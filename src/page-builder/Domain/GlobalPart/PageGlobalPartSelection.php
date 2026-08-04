<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\GlobalPart;

/**
 * Header and footer overrides owned by one Page Builder page.
 *
 * Null inherits the site default, -1 explicitly renders no part, and a
 * positive ID selects one reusable global part.
 */
final class PageGlobalPartSelection
{
    public function __construct(
        private readonly ?int $headerOverrideId,
        private readonly ?int $footerOverrideId,
    ) {
        $this->assertValidOverride($headerOverrideId);
        $this->assertValidOverride($footerOverrideId);
    }

    public static function siteDefaults(): self
    {
        return new self(null, null);
    }

    public static function noParts(): self
    {
        return new self(-1, -1);
    }

    public function headerOverrideId(): ?int
    {
        return $this->headerOverrideId;
    }

    public function footerOverrideId(): ?int
    {
        return $this->footerOverrideId;
    }

    public function equals(self $other): bool
    {
        return $this->headerOverrideId === $other->headerOverrideId
            && $this->footerOverrideId === $other->footerOverrideId;
    }

    private function assertValidOverride(?int $overrideId): void
    {
        if ($overrideId === null || $overrideId === -1 || $overrideId > 0) {
            return;
        }

        throw new \InvalidArgumentException('A page global-part override must be null, -1, or a positive post ID.');
    }
}

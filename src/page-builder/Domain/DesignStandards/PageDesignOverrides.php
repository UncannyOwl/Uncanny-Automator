<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

/**
 * Per-page design token overrides.
 *
 * Stored in `_uncanny_engine_theme_overrides` postmeta.
 * Sparse token and typography buckets. Only fields the page explicitly
 * overrides need to be present.
 *
 * Immutable value object.
 */
final class PageDesignOverrides
{
    /**
     * @param array<string, string> $tokens --bs-* overrides for the page scope
     */
    public function __construct(
        private readonly array $tokens = [],
        ?TypographyProfile $typography = null,
    ) {
        $this->typography = $typography ?? new TypographyProfile();
    }

    private readonly TypographyProfile $typography;

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $tokens = is_array($data['tokens'] ?? null) ? $data['tokens'] : [];
        $typography = is_array($data['typography'] ?? null)
            ? TypographyProfile::fromArray($data['typography'])
            : new TypographyProfile();

        return new self(
            DesignTokenValidator::normalizeBucket($tokens, 'Token'),
            $typography,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = ['tokens' => $this->tokens];

        if ($this->typography->roles() !== []) {
            $data['typography'] = $this->typography->toArray();
        }

        return $data;
    }

    /** @return array<string, string> */
    public function tokens(): array { return $this->tokens; }
    public function typography(): TypographyProfile { return $this->typography; }
}

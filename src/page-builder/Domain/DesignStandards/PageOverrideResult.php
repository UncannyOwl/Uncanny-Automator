<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

/**
 * Result of resolving page-level design overrides against the sitewide profile.
 *
 * Carries the resolved profile plus audit data: which override keys were
 * accepted, rejected (not in sitewide), or locked (admin-locked from overrides).
 *
 * Immutable value object.
 */
final class PageOverrideResult
{
    private const EMPTY_AUDIT = ['tokens' => [], 'typography' => []];

    /**
     * @param array{tokens: string[], typography: string[]} $appliedKeys
     * @param array{tokens: string[], typography: string[]} $rejectedKeys
     * @param array{tokens: string[], typography: string[]} $lockedKeys
     */
    public function __construct(
        private readonly DesignStandardsProfile $resolved,
        private readonly array $appliedKeys,
        private readonly array $rejectedKeys,
        private readonly array $lockedKeys = self::EMPTY_AUDIT,
    ) {}

    public function resolved(): DesignStandardsProfile { return $this->resolved; }

    /** @return array{tokens: string[], typography: string[]} */
    public function appliedKeys(): array { return $this->appliedKeys; }

    /** @return array{tokens: string[], typography: string[]} */
    public function rejectedKeys(): array { return $this->rejectedKeys; }

    /** @return array{tokens: string[], typography: string[]} */
    public function lockedKeys(): array { return $this->lockedKeys; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'resolved'      => $this->resolved->toArray(),
            'applied_keys'  => $this->appliedKeys,
            'rejected_keys' => $this->rejectedKeys,
            'locked_keys'   => $this->lockedKeys,
        ];
    }
}

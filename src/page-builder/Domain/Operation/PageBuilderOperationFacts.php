<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Operation;

/**
 * The five independent answers required for one Page Builder operation.
 *
 * Application code resolves their operation-specific meaning through existing
 * ports. For example, required authority means an unowned post for adoption,
 * but persisted Page Builder ownership for editing or recovery.
 */
final class PageBuilderOperationFacts
{
    public function __construct(
        private readonly bool $administratorEnabledPostType,
        private readonly bool $runtimeSupportsPostType,
        private readonly bool $hasRequiredAuthority,
        private readonly bool $actorIsAllowed,
        private readonly bool $isSafeNow,
    ) {}

    public function administratorEnabledPostType(): bool
    {
        return $this->administratorEnabledPostType;
    }

    public function runtimeSupportsPostType(): bool
    {
        return $this->runtimeSupportsPostType;
    }

    public function hasRequiredAuthority(): bool
    {
        return $this->hasRequiredAuthority;
    }

    public function actorIsAllowed(): bool
    {
        return $this->actorIsAllowed;
    }

    public function isSafeNow(): bool
    {
        return $this->isSafeNow;
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Binding;

/**
 * Selects which dynamic bindings may be resolved while HTML is rendered.
 */
enum DynamicBindingRenderMode
{
    case ResolveAll;
    case FreezeOnly;

    public function resolves(BindingStaticSafety $safety): bool
    {
        return $this === self::ResolveAll || $safety->canFreeze();
    }
}

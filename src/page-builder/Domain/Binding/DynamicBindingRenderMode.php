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
    case RemoveAll;

    public function resolves(BindingStaticSafety $safety): bool
    {
        return match ($this) {
            self::ResolveAll => true,
            self::FreezeOnly => $safety->canFreeze(),
            self::RemoveAll => false,
        };
    }
}

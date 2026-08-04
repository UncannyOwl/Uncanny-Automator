<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Binding;

/**
 * What the renderer replaces when it resolves a dynamic region.
 */
enum RegionReplaces: string
{
    case Children = 'children';
    case HostAttribute = 'attribute';
    case SelfElement = 'self';
}

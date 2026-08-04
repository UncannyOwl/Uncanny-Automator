<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Canvas;

enum CanvasKind: string
{
    // Public canvas contract for ordinary supported content, independent of
    // the host's concrete post-type slug.
    case Page = 'page';
    case GlobalPart = 'global_part';
}

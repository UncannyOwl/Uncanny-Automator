<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\EditorLock;

enum EditorOwnershipStatus: string
{
    case Available = 'available';
    case Owned = 'owned';
    case Blocked = 'blocked';
    case Unavailable = 'unavailable';
}

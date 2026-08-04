<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Publishing;

/** Human-facing relationship between the working draft and public output. */
enum PageLiveState: string
{
    case Draft = 'draft';
    case Live = 'live';
    case ChangesNotLive = 'changes_not_live';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft — not live',
            self::Live => 'Live',
            self::ChangesNotLive => 'Changes not live',
        };
    }
}

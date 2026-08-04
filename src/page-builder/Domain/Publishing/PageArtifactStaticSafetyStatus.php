<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Publishing;

enum PageArtifactStaticSafetyStatus: string
{
    case Safe = 'safe';
    case Failed = 'failed';
    case NotChecked = 'not_checked';

    public static function fromStorage(string $status): self
    {
        return self::tryFrom($status) ?? self::NotChecked;
    }
}

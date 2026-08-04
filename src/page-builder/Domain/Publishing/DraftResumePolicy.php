<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Publishing;

/**
 * Decides which durable source a published page opens after a fresh load.
 */
enum DraftResumePolicy: string
{
    /** Agent-created working changes reopen immediately. */
    case Active = 'active';

    /** Human-saved working changes wait behind the published-source prompt. */
    case Parked = 'parked';

    public static function fromStorage(mixed $value): self
    {
        return is_string($value) ? self::tryFrom($value) ?? self::Active : self::Active;
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\GlobalPart;

/** Resolves effective shell slots without repairing persisted defaults. */
interface ReadOnlyPageGlobalPartResolverInterface
{
    /** @return array<string, mixed>|null */
    public function resolveForPageReadOnly(int $pageId, GlobalPartType $type): ?array;
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

use UncannyPageBuilder\Domain\Editing\CompactSourceDiffer;

/**
 * Builds compact line diffs for source preview and post-write tool output.
 *
 * This is intentionally small and deterministic. It is not a shell `diff`
 * clone; it gives agents enough source context to verify what changed without
 * flooding tool output on large sections.
 */
final class SourceDiffer
{
    public function __construct(
        private readonly ?CompactSourceDiffer $sourceDiffer = null,
    ) {}

    public function diff(string $label, string $before, string $after, int $maxChangedLines = 300): SourceDiff
    {
        $diff = ($this->sourceDiffer ?? new CompactSourceDiffer())->diff($label, $before, $after, $maxChangedLines);

        return new SourceDiff($diff->label(), $diff->body(), $diff->changed(), $diff->truncated());
    }
}

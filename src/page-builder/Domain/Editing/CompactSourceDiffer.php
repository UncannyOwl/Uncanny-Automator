<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Editing;

/**
 * Builds compact line diffs for preview and post-write tool output.
 *
 * This stays small and deterministic so agents can verify source changes
 * without flooding tool output on large targets.
 */
final class CompactSourceDiffer
{
    public function diff(string $label, string $before, string $after, int $maxChangedLines = 300): CompactSourceDiff
    {
        if ($before === $after) {
            return new CompactSourceDiff($label, 'no changes', false, false);
        }

        $beforeLines = $this->lines($before);
        $afterLines = $this->lines($after);
        $beforeCount = count($beforeLines);
        $afterCount = count($afterLines);

        $prefix = 0;
        while (
            $prefix < $beforeCount
            && $prefix < $afterCount
            && $beforeLines[$prefix] === $afterLines[$prefix]
        ) {
            $prefix++;
        }

        $suffix = 0;
        while (
            $suffix < ($beforeCount - $prefix)
            && $suffix < ($afterCount - $prefix)
            && $beforeLines[$beforeCount - 1 - $suffix] === $afterLines[$afterCount - 1 - $suffix]
        ) {
            $suffix++;
        }

        $removed = array_slice($beforeLines, $prefix, $beforeCount - $prefix - $suffix);
        $added = array_slice($afterLines, $prefix, $afterCount - $prefix - $suffix);

        $lines = ['@@ line ' . ((int) $prefix + 1) . ' @@'];
        $changedLines = 0;
        $truncated = false;

        foreach ($removed as $line) {
            if ($changedLines >= $maxChangedLines) {
                $truncated = true;
                break;
            }

            $lines[] = '- ' . $line;
            $changedLines++;
        }

        if (!$truncated) {
            foreach ($added as $line) {
                if ($changedLines >= $maxChangedLines) {
                    $truncated = true;
                    break;
                }

                $lines[] = '+ ' . $line;
                $changedLines++;
            }
        }

        if ($truncated) {
            $lines[] = '... diff truncated; use a smaller patch for full detail.';
        }

        return new CompactSourceDiff($label, implode("\n", $lines), true, $truncated);
    }

    /**
     * @return list<string>
     */
    private function lines(string $source): array
    {
        if ($source === '') {
            return [''];
        }

        return explode("\n", str_replace(["\r\n", "\r"], "\n", $source));
    }
}

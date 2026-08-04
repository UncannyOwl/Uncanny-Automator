<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Editing;

/**
 * Shared exact-match patcher for agent-facing source surgery.
 *
 * The public contract is search/replace driven. Legacy alias keys stay
 * accepted here for model compatibility, but the contract documentation should
 * describe only the canonical search/replace shape.
 */
final class ExactSourcePatcher
{
    // ─────────────────────────────────────────────────────────────────────────
    // Public patch application
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array<int, mixed> $patches
     * @return array{0: string, 1: ?string} [result, errorMessage]
     */
    public function apply(string $subject, array $patches, string $field): array
    {
        $result = $subject;

        foreach ($patches as $index => $patch) {
            if (!is_array($patch)) {
                return [$subject, "{$field}_patches[{$index}]: patch must be an object."];
            }

            $action = strtolower(trim((string) ($patch['action'] ?? 'replace')));
            $search = (string) ($patch['search'] ?? $patch['old'] ?? $patch['old_string'] ?? '');
            $search = str_replace(['\n', '\t'], ["\n", "\t"], $search);

            if ($search === '') {
                return [$subject, "{$field}_patches[{$index}]: empty 'search' string."];
            }

            [$offset, $matched, $matchError] = $this->findUniquePatchMatch($result, $search);
            if ($matchError !== null) {
                return [$subject, "{$field}_patches[{$index}]: {$matchError}"];
            }

            [$replacement, $replacementError] = $this->resolvePatchReplacement(
                $patch,
                $action,
                $matched,
                $field,
                $index,
            );
            if ($replacementError !== null) {
                return [$subject, $replacementError];
            }

            $result = substr_replace($result, $replacement, $offset, strlen($matched));
        }

        return [$result, null];
    }

    /**
     * @return array{0: int, 1: string, 2: ?string}
     */
    private function findUniquePatchMatch(string $subject, string $search): array
    {
        $count = substr_count($subject, $search);
        if ($count === 1) {
            return [(int) strpos($subject, $search), $search, null];
        }

        // Models often preserve intent but mangle internal multi-line whitespace.
        // Keep boundary whitespace exact so fuzzy matching cannot silently absorb
        // surrounding lines outside the quoted source.
        if ($count === 0) {
            $normalizedSearch = $this->normalizedWhitespacePattern($search);
            if (preg_match_all('/' . $normalizedSearch . '/s', $subject, $matches, PREG_OFFSET_CAPTURE) !== false) {
                $count = count($matches[0] ?? []);
                if ($count === 1) {
                    return [$matches[0][0][1], $matches[0][0][0], null];
                }
            }
        }

        if ($count === 0) {
            return [0, '', 'search string not found.'];
        }

        return [0, '', "search string matches {$count} times — must be unique."];
    }

    private function normalizedWhitespacePattern(string $search): string
    {
        $segments = preg_split('/(\s+)/u', $search, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($segments) || $segments === []) {
            return preg_quote($search, '/');
        }

        $pattern = '';
        $nonEmptyIndexes = array_keys(array_filter($segments, static fn (string $segment): bool => $segment !== ''));
        $firstIndex = $nonEmptyIndexes[0] ?? 0;
        $lastIndex = $nonEmptyIndexes[count($nonEmptyIndexes) - 1] ?? 0;

        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                continue;
            }

            if (preg_match('/^\s+$/u', $segment) === 1) {
                $pattern .= ($index === $firstIndex || $index === $lastIndex)
                    ? preg_quote($segment, '/')
                    : '\s+';
                continue;
            }

            $pattern .= preg_quote($segment, '/');
        }

        return $pattern;
    }

    /**
     * @param array<string, mixed> $patch
     * @return array{0: string, 1: ?string}
     */
    private function resolvePatchReplacement(
        array $patch,
        string $action,
        string $matched,
        string $field,
        int $index,
    ): array {
        if (!in_array($action, ['replace', 'insert_after', 'insert_before', 'delete'], true)) {
            return ['', "{$field}_patches[{$index}]: unsupported action '{$action}'."];
        }

        if ($action === 'delete') {
            return ['', null];
        }

        if ($action === 'replace') {
            if (!$this->hasPatchValue($patch, ['replace', 'new', 'new_string'])) {
                return ['', "{$field}_patches[{$index}]: missing 'replace' string."];
            }

            return [
                $this->normalizePatchText($this->firstPatchValue($patch, ['replace', 'new', 'new_string'])),
                null,
            ];
        }

        if (!$this->hasPatchValue($patch, ['content'])) {
            return ['', "{$field}_patches[{$index}]: action '{$action}' requires 'content'."];
        }

        $content = $this->normalizePatchText($this->firstPatchValue($patch, ['content']));
        if ($action === 'insert_before') {
            return [$content . $matched, null];
        }

        return [$matched . $content, null];
    }

    /**
     * @param array<string, mixed> $patch
     * @param list<string> $keys
     */
    private function hasPatchValue(array $patch, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $patch)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $patch
     * @param list<string> $keys
     */
    private function firstPatchValue(array $patch, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $patch)) {
                return (string) $patch[$key];
            }
        }

        return '';
    }

    private function normalizePatchText(string $value): string
    {
        return str_replace(['\n', '\t'], ["\n", "\t"], $value);
    }
}

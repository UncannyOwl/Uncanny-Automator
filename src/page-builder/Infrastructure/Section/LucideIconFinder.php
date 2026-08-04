<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

use UncannyPageBuilder\Domain\Section\LucideIconCatalogInterface;
use UncannyPageBuilder\Domain\Section\LucideIconValidator;

/**
 * Finds valid Lucide icon names from loose model/user language.
 *
 * This stays infrastructure-owned because the vocabulary is an external Lucide
 * asset list, not a Page Builder domain rule.
 */
final class LucideIconFinder
{
    /** @var array<string, string[]> */
    private const SYNONYMS = [
        'add' => ['plus'],
        'back' => ['arrow', 'left', 'chevron'],
        'close' => ['x'],
        'delete' => ['trash', 'x'],
        'document' => ['file'],
        'forward' => ['arrow', 'right', 'chevron'],
        'next' => ['arrow', 'right', 'chevron'],
        'previous' => ['arrow', 'left', 'chevron'],
        'protected' => ['lock', 'shield'],
        'remove' => ['trash', 'x'],
        'secure' => ['lock', 'shield'],
        'security' => ['lock', 'shield'],
    ];

    /** @var array<string, array{name: string, parts: string[], part_count: int}>|null */
    private ?array $rows = null;

    public function __construct(
        private readonly LucideIconCatalogInterface $catalog,
    ) {}

    /**
     * @return string[]
     */
    public function search(string $query, int $limit = 12): array
    {
        $limit = max(1, min(25, $limit));
        $originalTokens = $this->queryTokens($query);
        if ($originalTokens === []) {
            return [];
        }

        $expandedTokens = $this->expandedTokens($originalTokens);
        $normalizedQuery = LucideIconValidator::normalizeName($query);

        $scored = [];
        foreach ($this->rows() as $row) {
            $score = $this->score($row, $normalizedQuery, $originalTokens, $expandedTokens);
            if ($score <= 0) {
                continue;
            }

            $scored[] = [
                'name' => $row['name'],
                'score' => $score,
                'parts' => $row['part_count'],
            ];
        }

        usort($scored, static function (array $a, array $b): int {
            $score = $b['score'] <=> $a['score'];
            if ($score !== 0) {
                return $score;
            }

            $parts = $a['parts'] <=> $b['parts'];
            if ($parts !== 0) {
                return $parts;
            }

            return strcmp($a['name'], $b['name']);
        });

        return array_slice(array_column($scored, 'name'), 0, $limit);
    }

    /**
     * @return array<string, array{name: string, parts: string[], part_count: int}>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $names = $this->catalog->allNames();
        sort($names);

        $rows = [];
        foreach ($names as $name) {
            $parts = explode('-', $name);
            $rows[$name] = [
                'name' => $name,
                'parts' => $parts,
                'part_count' => count($parts),
            ];
        }

        $this->rows = $rows;
        return $this->rows;
    }

    /**
     * @return string[]
     */
    private function queryTokens(string $query): array
    {
        $query = strtolower($query);
        $query = preg_replace('/\b(?:icon|icons|lucide|svg|symbol|symbols)\b/', ' ', $query) ?? $query;
        $query = preg_replace('/[^a-z0-9]+/', ' ', $query) ?? $query;

        return array_values(array_unique(array_filter(preg_split('/\s+/', trim($query)) ?: [])));
    }

    /**
     * @param string[] $tokens
     * @return string[]
     */
    private function expandedTokens(array $tokens): array
    {
        $expanded = [];
        foreach ($tokens as $token) {
            $expanded[] = $token;
            foreach (self::SYNONYMS[$token] ?? [] as $synonym) {
                $expanded[] = $synonym;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @param string[] $queryTokens
     * @param array{name: string, parts: string[], part_count: int} $row
     */
    private function score(array $row, string $normalizedQuery, array $originalTokens, array $queryTokens): int
    {
        $name = $row['name'];
        $nameParts = $row['parts'];
        $score = 0;

        if ($normalizedQuery !== '' && $name === $normalizedQuery) {
            $score += 1000;
        } elseif ($normalizedQuery !== '' && $this->hasToken($name, $normalizedQuery)) {
            $score += 180;
        }

        $matchedOriginal = 0;
        foreach ($queryTokens as $token) {
            $isOriginal = in_array($token, $originalTokens, true);
            $tokenScore = $isOriginal ? 80 : 40;

            if ($token === $name) {
                $score += $tokenScore + 80;
                $matchedOriginal += $isOriginal ? 1 : 0;
                continue;
            }

            if (in_array($token, $nameParts, true)) {
                $score += $tokenScore;
                $matchedOriginal += $isOriginal ? 1 : 0;
                continue;
            }

            if ($this->hasTokenPrefix($nameParts, $token)) {
                $score += (int) floor($tokenScore / 3);
                $matchedOriginal += $isOriginal ? 1 : 0;
            }
        }

        if ($matchedOriginal >= count($originalTokens)) {
            $score += 160;
        }

        // Keep exact/simple icon names ahead of distant compound matches.
        return $score - max(0, $row['part_count'] - 1);
    }

    private function hasToken(string $name, string $token): bool
    {
        return $name === $token
            || str_starts_with($name, $token . '-')
            || str_ends_with($name, '-' . $token)
            || str_contains($name, '-' . $token . '-');
    }

    /**
     * @param string[] $nameParts
     */
    private function hasTokenPrefix(array $nameParts, string $token): bool
    {
        foreach ($nameParts as $part) {
            if (str_starts_with($part, $token)) {
                return true;
            }
        }

        return false;
    }
}

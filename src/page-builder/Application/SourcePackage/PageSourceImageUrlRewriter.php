<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

/**
 * Rewrites only exact image URL aliases declared by a validated archive.
 *
 * Traversing scalar strings keeps HTML attributes, srcset candidates, CSS
 * url() values, and structured element-style declarations on one deterministic
 * path without parsing or reserializing user markup.
 */
final class PageSourceImageUrlRewriter
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $urlMap
     * @return array<string, mixed>
     */
    public function rewrite(array $payload, array $urlMap): array
    {
        if ($urlMap === []) {
            return $payload;
        }

        return $this->rewriteArray($payload, $urlMap);
    }

    /**
     * @param array<mixed> $values
     * @param array<string, string> $urlMap
     * @return array<mixed>
     */
    private function rewriteArray(array $values, array $urlMap): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = $this->rewriteString($value, $urlMap);
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->rewriteArray($value, $urlMap);
            }
        }

        return $values;
    }

    /** @param array<string, string> $urlMap */
    private function rewriteString(string $value, array $urlMap): string
    {
        uksort($urlMap, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        foreach ($urlMap as $sourceUrl => $targetUrl) {
            $pattern = '#' . preg_quote($sourceUrl, '#')
                . '(?![A-Za-z0-9._~:/?\#\[\]@!$&\'()*+,;=%-])#';
            $rewritten = preg_replace($pattern, addcslashes($targetUrl, '\\$'), $value);
            if (is_string($rewritten)) {
                $value = $rewritten;
            }
        }

        return $value;
    }
}

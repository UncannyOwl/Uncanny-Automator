<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

use UncannyPageBuilder\Domain\DesignStyles\DesignStyleProperty;
use UncannyPageBuilder\Domain\DesignStyles\DesignStyleValue;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;

/**
 * Applies allowlisted declaration changes without rebuilding user CSS.
 *
 * Source CSS is durable user data. Only the requested declaration value may be
 * replaced; comments, custom properties, vendor declarations, duplicate
 * fallbacks, formatting, and unrelated rules must remain byte-for-byte intact.
 */
final class CssRulePatcher
{
    /**
     * @param array<int, array{selector: string, set: array<string, string>, media?: string|null}> $rules
     */
    public function apply(string $css, array $rules): string
    {
        $patches = [];
        foreach ($rules as $rule) {
            $selector = is_string($rule['selector'] ?? null) ? trim($rule['selector']) : '';
            $properties = $this->sanitizePatchProperties($rule['set'] ?? []);
            $media = is_string($rule['media'] ?? null) ? trim($rule['media']) : '';

            if (!$this->isSafeSelector($selector) || $properties === []) {
                continue;
            }

            if ($media !== '') {
                if (!$this->isSafeMediaPrelude($media)) {
                    continue;
                }
            }

            $patches[] = [
                'selector' => $selector,
                'properties' => $properties,
                'media' => $media,
            ];
        }

        if ($patches === []) {
            return $css;
        }

        // A failed structural scan must never be treated as a missing selector.
        // Appending in that state creates another override on every retry.
        if (!$this->hasBalancedStructure($css) || !$this->hasCompleteRuleList($css)) {
            throw CssRuleIntegrityException::malformedSource();
        }

        foreach ($patches as $patch) {
            if ($patch['media'] !== '') {
                $css = $this->patchRuleInMedia($css, $patch['media'], $patch['selector'], $patch['properties']);
                continue;
            }

            $css = $this->patchRule($css, $patch['selector'], $patch['properties']);
        }

        return $css;
    }

    /**
     * @param array<string, string> $properties
     */
    private function patchRule(string $css, string $selector, array $properties): string
    {
        $block = $this->findBlock($css, $selector);
        if ($block !== null) {
            return $this->patchDeclarations($css, $block, $properties);
        }

        return $this->appendRule($css, $selector, $properties);
    }

    /**
     * @param array<string, string> $properties
     */
    private function patchRuleInMedia(string $css, string $media, string $selector, array $properties): string
    {
        $mediaBlock = $this->findBlock($css, $media);
        if ($mediaBlock === null) {
            $innerRule = trim($this->appendRule('', $selector, $properties));

            return $this->appendRawBlock($css, $media, $innerRule);
        }

        $innerStart = $mediaBlock['open_brace'] + 1;
        $innerLength = $mediaBlock['close_brace'] - $innerStart;
        $innerCss = substr($css, $innerStart, $innerLength);
        if (!$this->hasCompleteRuleList($innerCss)) {
            throw CssRuleIntegrityException::malformedSource();
        }
        $patchedInnerCss = $this->patchRule($innerCss, $selector, $properties);

        return substr_replace($css, $patchedInnerCss, $innerStart, $innerLength);
    }

    /**
     * Locate the last equivalent block at the current stylesheet level.
     *
     * The scanner ignores structural characters inside strings, comments,
     * functions, and attribute selectors. Nested blocks are skipped as opaque
     * units, so a base rule never aliases a responsive rule.
     *
     * @return array{open_brace: int, close_brace: int}|null
     */
    private function findBlock(string $css, string $expectedPrelude): ?array
    {
        $cursor = 0;
        $end = strlen($css);
        $match = null;
        $expectedPrelude = trim($expectedPrelude);
        $expectedKey = $this->preludeComparisonKey($expectedPrelude);

        while ($cursor < $end) {
            $cursor = $this->skipTrivia($css, $cursor, $end);
            if ($cursor >= $end) {
                break;
            }

            $boundary = $this->findStructuralBoundary($css, $cursor, $end);
            if ($boundary === null) {
                break;
            }

            if ($boundary['type'] === ';') {
                $cursor = $boundary['offset'] + 1;
                continue;
            }

            if ($boundary['type'] !== '{') {
                break;
            }

            $closeBrace = $this->findMatchingBrace($css, $boundary['offset'], $end);
            if ($closeBrace === null) {
                break;
            }

            $candidate = trim(substr($css, $cursor, $boundary['offset'] - $cursor));
            $candidateKey = $this->preludeComparisonKey($candidate);
            if (
                $candidate === $expectedPrelude
                || ($expectedKey !== null && $candidateKey !== null && $candidateKey === $expectedKey)
            ) {
                $match = [
                    'open_brace' => $boundary['offset'],
                    'close_brace' => $closeBrace,
                ];
            }

            $cursor = $closeBrace + 1;
        }

        return $match;
    }

    /**
     * Build a comparison-only key without rewriting authored selector bytes.
     *
     * Descendant whitespace stays significant, while whitespace around
     * explicit combinators and media punctuation is not. Commented or escaped
     * preludes deliberately fall back to exact matching because normalizing
     * either form without a full CSS token stream could alias another selector.
     */
    private function preludeComparisonKey(string $prelude): ?string
    {
        $prelude = trim($prelude);
        $isMedia = preg_match('/^@media\b/i', $prelude) === 1;
        $result = '';
        $pendingWhitespace = false;
        $previousSuppressesWhitespace = false;
        $parentheses = 0;
        $brackets = 0;
        $functionNames = [];
        $selectorFunctionContexts = [];
        $end = strlen($prelude);

        for ($index = 0; $index < $end; $index++) {
            $char = $prelude[$index];

            if (ctype_space($char)) {
                $pendingWhitespace = true;
                continue;
            }

            if ($char === '/' && $index + 1 < $end && $prelude[$index + 1] === '*') {
                return null;
            }

            if ($char === '\\') {
                return null;
            }

            if ($char === '"' || $char === "'") {
                if ($pendingWhitespace && $result !== '' && !$previousSuppressesWhitespace) {
                    $result .= ' ';
                }
                $pendingWhitespace = false;
                $previousSuppressesWhitespace = false;
                $quote = $char;
                $result .= $char;

                for ($index++; $index < $end; $index++) {
                    $char = $prelude[$index];
                    $result .= $char;
                    if ($char === '\\') {
                        if ($index + 1 >= $end) {
                            return null;
                        }
                        $result .= $prelude[++$index];
                        continue;
                    }
                    if ($char === $quote) {
                        break;
                    }
                }
                continue;
            }

            $token = $char;
            if (
                !$isMedia
                && $brackets > 0
                && in_array($char, ['~', '|', '^', '$', '*'], true)
                && $index + 1 < $end
                && $prelude[$index + 1] === '='
            ) {
                $token = $char . '=';
                $index++;
            } elseif ($char === '|' && $index + 1 < $end && $prelude[$index + 1] === '|') {
                $token = '||';
                $index++;
            }

            $currentSelectorFunction = $selectorFunctionContexts !== []
                ? (bool) $selectorFunctionContexts[array_key_last($selectorFunctionContexts)]
                : false;
            $currentFunctionName = $functionNames !== []
                ? $functionNames[array_key_last($functionNames)]
                : null;
            $openingFunctionName = null;
            if ($token === '(' && !$pendingWhitespace && preg_match('/([A-Za-z-]+)$/', $result, $matches) === 1) {
                $openingFunctionName = strtolower($matches[1]);
                if ($isMedia && $parentheses === 0 && $openingFunctionName === 'media') {
                    $openingFunctionName = null;
                }
            }
            $opensSelectorFunction = !$isMedia
                && in_array($openingFunctionName, ['has', 'host', 'host-context', 'is', 'not', 'slotted', 'where'], true);
            $selectorCombinator = !$isMedia
                && $brackets === 0
                && ($parentheses === 0 || $currentSelectorFunction)
                && in_array($token, ['>', '+', '~', '||'], true);
            $attributeOperator = !$isMedia
                && $brackets > 0
                && in_array($token, ['=', '~=', '|=', '^=', '$=', '*='], true);
            $attributeOpen = !$isMedia && $token === '[';
            $attributeClose = !$isMedia && $token === ']' && $brackets > 0;
            $separator = $token === ',';
            $mediaPunctuation = $isMedia && in_array($token, [':', '<', '>', '='], true);
            $mediaRatioSeparator = $isMedia
                && $token === '/'
                && $parentheses > 0
                && $currentFunctionName === null;
            $removeBefore = $selectorCombinator
                || $attributeOperator
                || $attributeClose
                || $separator
                || $mediaPunctuation
                || $mediaRatioSeparator
                || (!$isMedia && $token === ')' && $currentSelectorFunction)
                || ($isMedia && $token === ')');
            $removeAfter = $selectorCombinator
                || $attributeOperator
                || $attributeOpen
                || $separator
                || $mediaPunctuation
                || $mediaRatioSeparator
                || $opensSelectorFunction
                || ($isMedia && $token === '(');

            // Whitespace before the opening media condition is also trivia,
            // but whitespace between `and`/`not` and `(` remains significant.
            if ($isMedia && $token === '(' && preg_match('/@media\s*$/i', $result) === 1) {
                $removeBefore = true;
            }

            if ($removeBefore) {
                $result = rtrim($result);
            } elseif ($pendingWhitespace && $result !== '' && !$previousSuppressesWhitespace) {
                $result .= ' ';
            }

            $result .= $token;
            $pendingWhitespace = false;
            $previousSuppressesWhitespace = $removeAfter;

            if ($token === '(') {
                $parentheses++;
                $functionNames[] = $openingFunctionName;
                $selectorFunctionContexts[] = $opensSelectorFunction;
            } elseif ($token === ')') {
                $parentheses = max(0, $parentheses - 1);
                array_pop($functionNames);
                array_pop($selectorFunctionContexts);
            } elseif ($token === '[') {
                $brackets++;
            } elseif ($token === ']') {
                $brackets = max(0, $brackets - 1);
            }
        }

        if ($isMedia) {
            $result = preg_replace('/^@media/i', '@media', $result) ?? $result;
        }

        return $result;
    }

    /**
     * @param array{open_brace: int, close_brace: int} $block
     * @param array<string, string> $properties
     */
    private function patchDeclarations(string $css, array $block, array $properties): string
    {
        $innerStart = $block['open_brace'] + 1;
        $innerEnd = $block['close_brace'];
        $targetProperties = array_fill_keys(array_map('strtolower', array_keys($properties)), true);
        $spans = $this->declarationValueSpans($css, $innerStart, $innerEnd, $targetProperties);
        $edits = [];
        $missing = [];

        foreach ($properties as $property => $value) {
            $key = strtolower($property);
            if (isset($spans[$key])) {
                $edits[] = [
                    'start' => $spans[$key]['start'],
                    'length' => $spans[$key]['length'],
                    'replacement' => $this->replacementValue($value, $spans[$key]['important']),
                ];
                continue;
            }

            $missing[$property] = $value;
        }

        if ($missing !== []) {
            $insertionOffset = $this->trailingWhitespaceStart($css, $innerStart, $innerEnd);
            $edits[] = [
                'start' => $insertionOffset,
                'length' => 0,
                'replacement' => $this->buildMissingDeclarations(
                    substr($css, $innerStart, $insertionOffset - $innerStart),
                    substr($css, $insertionOffset, $innerEnd - $insertionOffset),
                    $missing,
                ),
            ];
        }

        // Apply from right to left so original byte offsets remain valid.
        usort($edits, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($edits as $edit) {
            $css = substr_replace($css, $edit['replacement'], $edit['start'], $edit['length']);
        }

        return $css;
    }

    /**
     * Find direct declaration values within one rule block.
     *
     * The last duplicate declaration wins and is therefore the only duplicate
     * that gets updated. Earlier fallback declarations remain untouched.
     *
     * @param array<string, true> $targetProperties
     * @return array<string, array{start: int, length: int, important: bool}>
     */
    private function declarationValueSpans(string $css, int $start, int $end, array $targetProperties): array
    {
        $spans = [];
        $statementStart = $start;
        $cursor = $start;
        $customPropertyStatement = false;

        while ($cursor < $end) {
            $boundary = $this->findStructuralBoundary($css, $cursor, $end);
            if ($boundary === null) {
                $this->recordDeclarationSpan($css, $statementStart, $end, $targetProperties, $spans);
                break;
            }

            if ($boundary['type'] === ';') {
                $this->recordDeclarationSpan($css, $statementStart, $boundary['offset'], $targetProperties, $spans);
                $cursor = $boundary['offset'] + 1;
                $statementStart = $cursor;
                $customPropertyStatement = false;
                continue;
            }

            if ($boundary['type'] === '{') {
                $closeBrace = $this->findMatchingBrace($css, $boundary['offset'], $end);
                if ($closeBrace === null) {
                    break;
                }

                if (!$customPropertyStatement) {
                    $prelude = substr($css, $statementStart, $boundary['offset'] - $statementStart);
                    if ($this->isCustomPropertyBlockPrelude($prelude)) {
                        $customPropertyStatement = true;
                        $cursor = $closeBrace + 1;
                        continue;
                    }
                } else {
                    // Additional balanced blocks remain components of the
                    // same custom-property value until a real semicolon.
                    $cursor = $closeBrace + 1;
                    continue;
                }

                // A nested rule is an opaque complete statement. Direct
                // declarations may resume after its closing brace.
                $cursor = $closeBrace + 1;
                $statementStart = $cursor;
                continue;
            }

            break;
        }

        return $spans;
    }

    /**
     * @param array<string, true> $targetProperties
     * @param array<string, array{start: int, length: int, important: bool}> $spans
     */
    private function recordDeclarationSpan(
        string $css,
        int $start,
        int $end,
        array $targetProperties,
        array &$spans,
    ): void {
        $start = $this->skipTrivia($css, $start, $end);
        if ($start >= $end) {
            return;
        }

        $colon = $this->findDeclarationColon($css, $start, $end);
        if ($colon === null) {
            return;
        }

        $property = trim(substr($css, $start, $colon - $start));
        if (preg_match('/^(?:--|-?[A-Za-z_])[A-Za-z0-9_-]*$/', $property) !== 1) {
            return;
        }

        $key = strtolower($property);
        if (!isset($targetProperties[$key])) {
            return;
        }

        // A second direct colon usually means the prior declaration is missing
        // its semicolon. Treating the entire statement as one value would erase
        // the following property while reporting a successful surgical edit.
        if ($this->findDeclarationColon($css, $colon + 1, $end) !== null) {
            throw CssRuleIntegrityException::ambiguousDeclarationBoundary();
        }

        $candidate = $this->declarationReplacementSpan($css, $colon + 1, $end);
        if ($candidate === null) {
            return;
        }

        $existing = $spans[$key] ?? null;
        if ($existing !== null && $existing['important'] && !$candidate['important']) {
            return;
        }

        $spans[$key] = $candidate;
    }

    /**
     * Locate only the authored value tokens, leaving comments, surrounding
     * trivia, and an existing !important priority exactly where they are.
     *
     * @return array{start: int, length: int, important: bool}|null
     */
    private function declarationReplacementSpan(string $css, int $start, int $end): ?array
    {
        $maskedValue = $this->maskCommentsAndStrings(substr($css, $start, $end - $start));
        $importantOffset = $this->trailingImportantOffset($maskedValue);
        $important = $importantOffset !== null;
        $valueLimit = $important ? $start + $importantOffset : $end;

        $valueStart = $this->skipTrivia($css, $start, $valueLimit);
        $valueEnd = $this->trailingValueTriviaStart($css, $valueStart, $valueLimit);
        if ($valueStart >= $valueEnd) {
            return null;
        }

        // Moving an inline comment to a guessed position would also be a source
        // rewrite. Edge comments stay in place; interleaved comments fail closed.
        if ($this->containsCssComment($css, $valueStart, $valueEnd)) {
            throw CssRuleIntegrityException::ambiguousComment();
        }

        return [
            'start' => $valueStart,
            'length' => $valueEnd - $valueStart,
            'important' => $important,
        ];
    }

    private function replacementValue(string $value, bool $preserveImportant): string
    {
        if (!$preserveImportant) {
            return $value;
        }

        $maskedValue = $this->maskCommentsAndStrings($value);
        $importantOffset = $this->trailingImportantOffset($maskedValue);
        if ($importantOffset === null) {
            return $value;
        }

        $withoutPriority = rtrim(substr($value, 0, $importantOffset));

        return $withoutPriority !== '' ? $withoutPriority : $value;
    }

    private function trailingImportantOffset(string $maskedValue): ?int
    {
        $trimmed = rtrim($maskedValue);
        $bang = strrpos($trimmed, '!');
        if ($bang === false) {
            return null;
        }
        if ($this->isEscapedCharacter($trimmed, $bang)) {
            return null;
        }

        $identifier = ltrim(substr($trimmed, $bang + 1));
        if (strtolower($this->decodeCssIdentifier($identifier)) !== 'important') {
            return null;
        }

        return $bang;
    }

    /**
     * CSS escapes are parity-sensitive: an odd run escapes the current
     * character, while an even run ends in a literal backslash and leaves the
     * current character structural.
     */
    private function isEscapedCharacter(string $source, int $offset): bool
    {
        $backslashes = 0;
        for ($index = $offset - 1; $index >= 0 && $source[$index] === '\\'; $index--) {
            $backslashes++;
        }

        return $backslashes % 2 === 1;
    }

    /**
     * Decode the identifier escapes needed to recognize CSS-wide priority.
     * The original bytes are never rewritten; this is comparison-only.
     */
    private function decodeCssIdentifier(string $identifier): string
    {
        $decoded = '';
        $end = strlen($identifier);

        for ($index = 0; $index < $end; $index++) {
            $char = $identifier[$index];
            if ($char !== '\\') {
                $decoded .= $char;
                continue;
            }

            if ($index + 1 >= $end) {
                return $decoded . '\\';
            }

            $cursor = $index + 1;
            $hex = '';
            while ($cursor < $end && strlen($hex) < 6 && ctype_xdigit($identifier[$cursor])) {
                $hex .= $identifier[$cursor];
                $cursor++;
            }

            if ($hex === '') {
                $decoded .= $identifier[$cursor];
                $index = $cursor;
                continue;
            }

            $codepoint = hexdec($hex);
            $decoded .= $this->utf8Codepoint($codepoint);
            if ($cursor < $end && ctype_space($identifier[$cursor])) {
                $cursor++;
            }
            $index = $cursor - 1;
        }

        return $decoded;
    }

    private function utf8Codepoint(int $codepoint): string
    {
        if ($codepoint <= 0 || $codepoint > 0x10FFFF || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF)) {
            return "\u{FFFD}";
        }
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7FF) {
            return chr(0xC0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3F));
        }
        if ($codepoint <= 0xFFFF) {
            return chr(0xE0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3F))
                . chr(0x80 | ($codepoint & 0x3F));
        }

        return chr(0xF0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3F))
            . chr(0x80 | (($codepoint >> 6) & 0x3F))
            . chr(0x80 | ($codepoint & 0x3F));
    }

    /**
     * Strip trailing whitespace and complete comments without changing bytes.
     */
    private function trailingValueTriviaStart(string $css, int $start, int $end): int
    {
        $cursor = $end;
        while ($cursor > $start) {
            while ($cursor > $start && ctype_space($css[$cursor - 1])) {
                $cursor--;
            }

            if ($cursor - 2 < $start || substr($css, $cursor - 2, 2) !== '*/') {
                break;
            }

            $commentStart = strrpos(substr($css, $start, $cursor - $start - 2), '/*');
            if ($commentStart === false) {
                break;
            }

            $cursor = $start + $commentStart;
        }

        return $cursor;
    }

    private function containsCssComment(string $css, int $start, int $end): bool
    {
        $quote = null;
        for ($index = $start; $index < $end; $index++) {
            $char = $css[$index];
            if ($quote !== null) {
                if ($char === '\\' && $index + 1 < $end) {
                    $index++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '\\' && $index + 1 < $end) {
                $index++;
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '/' && $index + 1 < $end && $css[$index + 1] === '*') {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep offsets stable while hiding comment and string contents from suffix
     * matching such as !important detection.
     */
    private function maskCommentsAndStrings(string $value): string
    {
        $masked = $value;
        $quote = null;
        $end = strlen($value);

        for ($index = 0; $index < $end; $index++) {
            $char = $value[$index];
            if ($quote !== null) {
                $masked[$index] = ' ';
                if ($char === '\\' && $index + 1 < $end) {
                    $masked[++$index] = ' ';
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '\\' && $index + 1 < $end) {
                $index++;
                continue;
            }
            if ($char === '/' && $index + 1 < $end && $value[$index + 1] === '*') {
                $commentEnd = strpos($value, '*/', $index + 2);
                if ($commentEnd === false) {
                    return str_repeat(' ', $end);
                }
                for (; $index <= $commentEnd + 1; $index++) {
                    $masked[$index] = ' ';
                }
                $index--;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $masked[$index] = ' ';
            }
        }

        return $masked;
    }

    private function findDeclarationColon(string $css, int $start, int $end): ?int
    {
        $quote = null;
        $parentheses = 0;
        $brackets = 0;

        for ($index = $start; $index < $end; $index++) {
            $char = $css[$index];

            if ($quote !== null) {
                if ($char === '\\' && $index + 1 < $end) {
                    $index++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '/' && $index + 1 < $end && $css[$index + 1] === '*') {
                $commentEnd = strpos($css, '*/', $index + 2);
                if ($commentEnd === false || $commentEnd >= $end) {
                    return null;
                }
                $index = $commentEnd + 1;
                continue;
            }

            if ($char === '\\' && $index + 1 < $end) {
                $index++;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $parentheses++;
                continue;
            }
            if ($char === ')') {
                $parentheses = max(0, $parentheses - 1);
                continue;
            }
            if ($char === '[') {
                $brackets++;
                continue;
            }
            if ($char === ']') {
                $brackets = max(0, $brackets - 1);
                continue;
            }

            if ($char === ':' && $parentheses === 0 && $brackets === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Find the next statement boundary outside quoted and functional content.
     *
     * @return array{type: string, offset: int}|null
     */
    private function findStructuralBoundary(string $css, int $start, int $end): ?array
    {
        $quote = null;
        $parentheses = 0;
        $brackets = 0;

        for ($index = $start; $index < $end; $index++) {
            $char = $css[$index];

            if ($quote !== null) {
                if ($char === '\\' && $index + 1 < $end) {
                    $index++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '/' && $index + 1 < $end && $css[$index + 1] === '*') {
                $commentEnd = strpos($css, '*/', $index + 2);
                if ($commentEnd === false || $commentEnd >= $end) {
                    return null;
                }
                $index = $commentEnd + 1;
                continue;
            }

            if ($char === '\\' && $index + 1 < $end) {
                $index++;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $parentheses++;
                continue;
            }
            if ($char === ')') {
                $parentheses = max(0, $parentheses - 1);
                continue;
            }
            if ($char === '[') {
                $brackets++;
                continue;
            }
            if ($char === ']') {
                $brackets = max(0, $brackets - 1);
                continue;
            }

            if ($parentheses === 0 && $brackets === 0 && in_array($char, [';', '{', '}'], true)) {
                return ['type' => $char, 'offset' => $index];
            }
        }

        return null;
    }

    private function findMatchingBrace(string $css, int $openBrace, int $end): ?int
    {
        $depth = 0;
        $quote = null;
        $parentheses = 0;
        $brackets = 0;

        for ($index = $openBrace; $index < $end; $index++) {
            $char = $css[$index];

            if ($quote !== null) {
                if ($char === '\\' && $index + 1 < $end) {
                    $index++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '/' && $index + 1 < $end && $css[$index + 1] === '*') {
                $commentEnd = strpos($css, '*/', $index + 2);
                if ($commentEnd === false || $commentEnd >= $end) {
                    return null;
                }
                $index = $commentEnd + 1;
                continue;
            }

            if ($char === '\\' && $index + 1 < $end) {
                $index++;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '(') {
                $parentheses++;
                continue;
            }
            if ($char === ')') {
                $parentheses = max(0, $parentheses - 1);
                continue;
            }
            if ($char === '[') {
                $brackets++;
                continue;
            }
            if ($char === ']') {
                $brackets = max(0, $brackets - 1);
                continue;
            }

            if ($parentheses > 0 || $brackets > 0) {
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function skipTrivia(string $css, int $start, int $end): int
    {
        $cursor = $start;
        while ($cursor < $end) {
            if (ctype_space($css[$cursor])) {
                $cursor++;
                continue;
            }

            if ($css[$cursor] === '/' && $cursor + 1 < $end && $css[$cursor + 1] === '*') {
                $commentEnd = strpos($css, '*/', $cursor + 2);
                if ($commentEnd === false || $commentEnd >= $end) {
                    return $end;
                }
                $cursor = $commentEnd + 2;
                continue;
            }

            break;
        }

        return $cursor;
    }

    /**
     * @param array<string, string> $properties
     */
    private function buildMissingDeclarations(
        string $contentBeforeInsertion,
        string $trailingWhitespace,
        array $properties,
    ): string {
        $lines = [];
        foreach ($properties as $property => $value) {
            $lines[] = "  {$property}: {$value};";
        }

        if ($lines === []) {
            return '';
        }

        $separator = '';
        $lastStructuralCharacter = $this->lastSignificantCharacter($contentBeforeInsertion);
        if (
            $lastStructuralCharacter !== null
            && $lastStructuralCharacter !== ';'
            && (
                $lastStructuralCharacter !== '}'
                || $this->endsWithUnterminatedCustomPropertyBlock($contentBeforeInsertion)
            )
        ) {
            $separator = ';';
        }

        $separator .= "\n";

        $suffix = preg_match('/(?:\r\n|\r|\n)/', $trailingWhitespace) === 1 ? '' : "\n";

        return $separator . implode("\n", $lines) . $suffix;
    }

    /**
     * A custom-property block may omit its final semicolon at the end of a
     * rule. A following declaration needs that separator; a nested rule does
     * not. Inspect only the final direct block prelude to distinguish them.
     */
    private function endsWithUnterminatedCustomPropertyBlock(string $content): bool
    {
        $cursor = 0;
        $statementStart = 0;
        $end = strlen($content);
        $customPropertyStatement = false;
        $lastCustomPropertyBlockEnd = null;

        while ($cursor < $end) {
            $boundary = $this->findStructuralBoundary($content, $cursor, $end);
            if ($boundary === null) {
                break;
            }
            if ($boundary['type'] === ';') {
                $cursor = $boundary['offset'] + 1;
                $statementStart = $cursor;
                $customPropertyStatement = false;
                $lastCustomPropertyBlockEnd = null;
                continue;
            }
            if ($boundary['type'] !== '{') {
                break;
            }

            $closeBrace = $this->findMatchingBrace($content, $boundary['offset'], $end);
            if ($closeBrace === null) {
                return false;
            }

            if (!$customPropertyStatement) {
                $prelude = substr($content, $statementStart, $boundary['offset'] - $statementStart);
                if (!$this->isCustomPropertyBlockPrelude($prelude)) {
                    // A nested rule is a complete statement at its closing
                    // brace. A following declaration starts independently.
                    $cursor = $closeBrace + 1;
                    $statementStart = $cursor;
                    $lastCustomPropertyBlockEnd = null;
                    continue;
                }

                $customPropertyStatement = true;
            }

            // A custom-property value may contain more than one balanced
            // component block. Keep its original declaration prelude until a
            // semicolon ends the whole value.
            $lastCustomPropertyBlockEnd = $closeBrace + 1;
            $cursor = $lastCustomPropertyBlockEnd;
        }

        return $customPropertyStatement
            && $lastCustomPropertyBlockEnd !== null
            && $this->skipTrivia($content, $lastCustomPropertyBlockEnd, $end) >= $end;
    }

    private function isCustomPropertyBlockPrelude(string $prelude): bool
    {
        $preludeEnd = strlen($prelude);
        $preludeStart = $this->skipTrivia($prelude, 0, $preludeEnd);
        $colon = $this->findDeclarationColon($prelude, $preludeStart, $preludeEnd);
        if ($colon === null) {
            return false;
        }

        $property = $this->removeCssTrivia(substr($prelude, $preludeStart, $colon - $preludeStart));

        return str_starts_with($property, '--') && strlen($property) > 2;
    }

    private function removeCssTrivia(string $source): string
    {
        $result = '';
        $end = strlen($source);

        for ($index = 0; $index < $end; $index++) {
            $char = $source[$index];
            if ($char === '\\' && $index + 1 < $end) {
                $result .= $char . $source[++$index];
                continue;
            }
            if ($char === '/' && $index + 1 < $end && $source[$index + 1] === '*') {
                $commentEnd = strpos($source, '*/', $index + 2);
                if ($commentEnd === false) {
                    return $result;
                }
                $index = $commentEnd + 1;
                continue;
            }
            if (!ctype_space($char)) {
                $result .= $char;
            }
        }

        return $result;
    }

    private function trailingWhitespaceStart(string $css, int $start, int $end): int
    {
        while ($end > $start && ctype_space($css[$end - 1])) {
            $end--;
        }

        return $end;
    }

    private function lastSignificantCharacter(string $css): ?string
    {
        $last = null;
        $quote = null;
        $end = strlen($css);

        for ($index = 0; $index < $end; $index++) {
            $char = $css[$index];

            if ($quote !== null) {
                if (!ctype_space($char)) {
                    $last = $char;
                }
                if ($char === '\\' && $index + 1 < $end) {
                    $last = $css[++$index];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '\\' && $index + 1 < $end) {
                // The escaped byte is value content, never a real statement
                // or block terminator. Pairwise consumption preserves escape
                // parity when multiple backslashes are authored.
                $last = '\\';
                $index++;
                continue;
            }
            if ($char === '/' && $index + 1 < $end && $css[$index + 1] === '*') {
                $commentEnd = strpos($css, '*/', $index + 2);
                if ($commentEnd === false) {
                    break;
                }
                $index = $commentEnd + 1;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            }

            if (!ctype_space($char)) {
                $last = $char;
            }
        }

        return $last;
    }

    /**
     * @param array<string, string> $properties
     */
    private function appendRule(string $css, string $selector, array $properties): string
    {
        $declarations = [];
        foreach ($properties as $property => $value) {
            $declarations[] = "  {$property}: {$value};";
        }

        if ($declarations === []) {
            return $css;
        }

        return $this->appendRawBlock($css, $selector, implode("\n", $declarations));
    }

    private function appendRawBlock(string $css, string $prelude, string $body): string
    {
        $prefix = $css === '' || preg_match('/(?:\r\n|\r|\n)$/', $css) === 1 ? '' : "\n";

        return $css . $prefix . $prelude . " {\n" . $body . "\n}";
    }

    /**
     * Validate only structural delimiters. This is intentionally not a CSS
     * normalizer: authored tokens remain untouched and are parsed only far
     * enough to distinguish "missing" from "unsafe to locate".
     */
    private function hasBalancedStructure(string $css): bool
    {
        $quote = null;
        $parentheses = 0;
        $brackets = 0;
        $braces = 0;
        $end = strlen($css);

        for ($index = 0; $index < $end; $index++) {
            $char = $css[$index];

            if ($quote !== null) {
                if ($char === '\\') {
                    if ($index + 1 >= $end) {
                        return false;
                    }
                    $index++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '/' && $index + 1 < $end && $css[$index + 1] === '*') {
                $commentEnd = strpos($css, '*/', $index + 2);
                if ($commentEnd === false) {
                    return false;
                }
                $index = $commentEnd + 1;
                continue;
            }

            if ($char === '\\') {
                if ($index + 1 >= $end) {
                    return false;
                }
                $index++;
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $parentheses++;
                continue;
            }
            if ($char === ')') {
                if ($parentheses === 0) {
                    return false;
                }
                $parentheses--;
                continue;
            }
            if ($char === '[') {
                $brackets++;
                continue;
            }
            if ($char === ']') {
                if ($brackets === 0) {
                    return false;
                }
                $brackets--;
                continue;
            }

            if ($parentheses > 0 || $brackets > 0) {
                continue;
            }
            if ($char === '{') {
                $braces++;
                continue;
            }
            if ($char === '}') {
                if ($braces === 0) {
                    return false;
                }
                $braces--;
            }
        }

        return $quote === null
            && $parentheses === 0
            && $brackets === 0
            && $braces === 0;
    }

    /**
     * A balanced prefix can still be incomplete (for example `@charset "x"`
     * without `;`, or stray text after a complete rule). Require every
     * top-level token stream to terminate as an at-rule statement or block.
     */
    private function hasCompleteRuleList(string $css): bool
    {
        $cursor = 0;
        $end = strlen($css);

        while ($cursor < $end) {
            $cursor = $this->skipTrivia($css, $cursor, $end);
            if ($cursor >= $end) {
                return true;
            }

            $boundary = $this->findStructuralBoundary($css, $cursor, $end);
            if ($boundary === null || $boundary['type'] === '}') {
                return false;
            }

            $prelude = trim(substr($css, $cursor, $boundary['offset'] - $cursor));
            if ($boundary['type'] === ';') {
                // Empty statements are harmless CSS trivia.
                $cursor = $boundary['offset'] + 1;
                continue;
            }

            if ($prelude === '') {
                return false;
            }

            $closeBrace = $this->findMatchingBrace($css, $boundary['offset'], $end);
            if ($closeBrace === null) {
                return false;
            }
            $cursor = $closeBrace + 1;
        }

        return true;
    }

    /**
     * @param mixed $properties
     * @return array<string, string>
     */
    private function sanitizePatchProperties(mixed $properties): array
    {
        if (!is_array($properties)) {
            return [];
        }

        $safe = [];
        foreach ($properties as $property => $value) {
            if (!is_string($property) || !is_string($value)) {
                continue;
            }

            $property = strtolower(trim($property));
            $value = trim($value);
            if (!DesignStyleProperty::isAllowed($property) || !$this->isSafeDeclarationValue($value)) {
                continue;
            }

            $safe[$property] = $value;
        }

        return $safe;
    }

    public function isSafeDeclarationValue(string $value): bool
    {
        return DesignStyleValue::isSafeValue($value)
            && $this->hasBalancedStructure($value);
    }

    public function isSafeSelector(string $selector): bool
    {
        return $selector !== ''
            && !str_starts_with($selector, '@')
            && preg_match('/[{};]/', $selector) !== 1
            && !str_contains($selector, '/*')
            && !str_contains($selector, '*/')
            && $this->hasBalancedStructure($selector);
    }

    public function isSafeMediaPrelude(string $media): bool
    {
        return preg_match('/^@media\b/i', $media) === 1
            && preg_match('/[{};]/', $media) !== 1
            && !str_contains($media, '/*')
            && !str_contains($media, '*/')
            && $this->hasBalancedStructure($media);
    }
}

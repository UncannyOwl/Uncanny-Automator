<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Compiler;

final class CssMinifier
{
    public function minify(string $css): string
    {
        $output = '';
        $length = strlen($css);
        $pendingSpace = false;
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($quote !== null) {
                $output .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $css[++$i];
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $output = $this->flushPendingSpace($output, $pendingSpace, $char);
                $output .= $char;
                $quote = $char;
                continue;
            }

            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $i += 2;
                while ($i < $length && !($css[$i] === '*' && ($css[$i + 1] ?? '') === '/')) {
                    $i++;
                }
                $i++;
                continue;
            }

            if ($this->startsWithUrlFunction($css, $i)) {
                $output = $this->flushPendingSpace($output, $pendingSpace, 'u');
                $end = $this->findUrlFunctionEnd($css, $i + 4);
                $output .= substr($css, $i, $end - $i + 1);
                $i = $end;
                continue;
            }

            if (ctype_space($char)) {
                $pendingSpace = true;
                continue;
            }

            $output = $this->flushPendingSpace($output, $pendingSpace, $char);

            if ($this->isCompactPunctuation($char)) {
                $output = rtrim($output);
            }

            $output .= $char;
        }

        return trim($output);
    }

    private function flushPendingSpace(string $output, bool &$pendingSpace, string $nextChar): string
    {
        if (!$pendingSpace) {
            return $output;
        }

        $pendingSpace = false;
        $previousChar = $output !== '' ? $output[strlen($output) - 1] : '';

        if ($previousChar === '' || $this->isCompactPunctuation($previousChar) || $this->isCompactPunctuation($nextChar)) {
            return $output;
        }

        return $output . ' ';
    }

    private function isCompactPunctuation(string $char): bool
    {
        return $char === '{' || $char === '}' || $char === ':' || $char === ';';
    }

    private function startsWithUrlFunction(string $css, int $offset): bool
    {
        return strncasecmp(substr($css, $offset, 4), 'url(', 4) === 0;
    }

    private function findUrlFunctionEnd(string $css, int $offset): int
    {
        $length = strlen($css);
        $quote = null;

        for ($i = $offset; $i < $length; $i++) {
            $char = $css[$i];

            if ($quote !== null) {
                if ($char === '\\' && $i + 1 < $length) {
                    $i++;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === ')') {
                return $i;
            }
        }

        return $length - 1;
    }
}

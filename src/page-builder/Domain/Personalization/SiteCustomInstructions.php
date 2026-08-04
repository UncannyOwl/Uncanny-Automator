<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Personalization;

/**
 * Site-wide custom instructions sent to the Page Builder Agent.
 */
final class SiteCustomInstructions
{
    public const MAX_CHARACTERS = 2000;

    private function __construct(private readonly string $text) {}

    public static function fromString(string $value): self
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));
        $clean = self::isValidUtf8($normalized) ? $normalized : self::stripNonAsciiBytes($normalized);

        return new self(self::limitCharacters($clean, self::MAX_CHARACTERS));
    }

    public function text(): string
    {
        return $this->text;
    }

    public function isEmpty(): bool
    {
        return $this->text === '';
    }

    private static function limitCharacters(string $value, int $limit): string
    {
        if (self::length($value) <= $limit) {
            return $value;
        }

        if (function_exists('mb_substr')) {
            $limited = mb_substr($value, 0, $limit, 'UTF-8');
            if (is_string($limited)) {
                return $limited;
            }
        }

        $characters = self::characters($value);
        if ($characters !== null) {
            return implode('', array_slice($characters, 0, $limit));
        }

        return substr(self::stripNonAsciiBytes($value), 0, $limit);
    }

    private static function length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            $length = mb_strlen($value, 'UTF-8');
            if (is_int($length)) {
                return $length;
            }
        }

        $characters = self::characters($value);
        if ($characters !== null) {
            return count($characters);
        }

        return strlen(self::stripNonAsciiBytes($value));
    }

    /**
     * @return list<string>|null
     */
    private static function characters(string $value): ?array
    {
        if (preg_match_all('/./us', $value, $matches) !== false) {
            return $matches[0];
        }

        return null;
    }

    private static function stripNonAsciiBytes(string $value): string
    {
        return (string) preg_replace('/[\x80-\xFF]+/', '', $value);
    }

    private static function isValidUtf8(string $value): bool
    {
        return preg_match('//u', $value) === 1;
    }
}

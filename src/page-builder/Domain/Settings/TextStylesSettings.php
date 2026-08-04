<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

use UncannyPageBuilder\Domain\DesignStandards\TypographyProfile;

/**
 * Stored typography configuration inside the brand styles settings.
 */
final class TextStylesSettings
{
    /**
     * @param list<string> $lockedKeys
     */
    public function __construct(
        private readonly TypographyProfile $typography,
        private readonly array $lockedKeys = [],
    ) {}

    public static function defaults(): self
    {
        return new self(TypographyProfile::defaults(), []);
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data)) {
            return self::defaults();
        }

        $typography = is_array($data['typography'] ?? null)
            ? TypographyProfile::fromArray(['roles' => $data['typography']])
            : TypographyProfile::defaults();

        return new self(
            $typography,
            self::normalizeLockedKeys($data['locked_keys'] ?? []),
        );
    }

    /**
     * @return array{
     *     typography: array<string, mixed>,
     *     locked_keys: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'typography' => $this->typography->toRoleArray(),
            'locked_keys' => $this->lockedKeys,
        ];
    }

    public function typography(): TypographyProfile
    {
        return $this->typography;
    }

    /**
     * @return list<string>
     */
    public function lockedKeys(): array
    {
        return $this->lockedKeys;
    }

    /**
     * @return list<string>
     */
    private static function normalizeLockedKeys(mixed $keys): array
    {
        if (!is_array($keys)) {
            return [];
        }

        $normalized = [];
        foreach ($keys as $key) {
            $text = trim((string) $key);
            if ($text === '') {
                continue;
            }

            $normalized[] = $text;
        }

        return array_values(array_unique($normalized));
    }
}

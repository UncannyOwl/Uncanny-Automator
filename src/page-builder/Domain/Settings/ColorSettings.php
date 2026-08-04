<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

use UncannyPageBuilder\Domain\DesignStandards\BootstrapTokenProfile;

/**
 * Stored color and token configuration inside the brand styles settings.
 */
final class ColorSettings
{
    /**
     * @param list<string> $lockedKeys
     */
    public function __construct(
        private readonly BootstrapTokenProfile $tokens,
        private readonly array $lockedKeys = [],
    ) {}

    public static function defaults(): self
    {
        return new self(BootstrapTokenProfile::defaults(), []);
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data)) {
            return self::defaults();
        }

        return new self(
            BootstrapTokenProfile::fromArray(is_array($data['tokens'] ?? null) ? $data['tokens'] : []),
            self::normalizeLockedKeys($data['locked_keys'] ?? []),
        );
    }

    /**
     * @return array{
     *     tokens: array<string, string>,
     *     locked_keys: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'tokens' => $this->tokens->toArray(),
            'locked_keys' => $this->lockedKeys,
        ];
    }

    public function tokens(): BootstrapTokenProfile
    {
        return $this->tokens;
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

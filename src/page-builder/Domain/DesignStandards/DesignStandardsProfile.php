<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

/**
 * Top-level sitewide design standards profile — Bootstrap 5 native.
 *
 * Aggregates a token map for existing CSS consumers, a role-first typography
 * model for schema 3.0, Bootstrap breakpoints (metadata), and per-key lockout
 * governance.
 *
 * Immutable value object. Schema version 3.0 adds typography roles while
 * keeping token output for backwards-compatible rendering and tooling.
 */
final class DesignStandardsProfile
{
    private const SCHEMA_VERSION = '3.0';

    /**
     * @param array{tokens: string[], typography: string[]} $lockedKeys
     */
    public function __construct(
        BootstrapTokenProfile $tokens,
        BootstrapBreakpoints $breakpoints,
        ?TypographyProfile $typography = null,
        array $lockedKeys = ['tokens' => [], 'typography' => []],
    ) {
        $this->typography = $typography ?? TypographyProfile::fromTokens($tokens->toArray());
        $this->tokens = BootstrapTokenProfile::fromArray($this->typography->applyToTokens($tokens->toArray()));
        $this->breakpoints = $breakpoints;
        $this->lockedKeys = self::normalizeLockedKeys($lockedKeys);
    }

    private readonly BootstrapTokenProfile $tokens;
    private readonly BootstrapBreakpoints $breakpoints;
    private readonly TypographyProfile $typography;

    /** @var array{tokens: string[], typography: string[]} */
    private readonly array $lockedKeys;

    public static function defaults(): self
    {
        return new self(
            BootstrapTokenProfile::defaults(),
            BootstrapBreakpoints::defaults(),
            TypographyProfile::defaults(),
            ['tokens' => [], 'typography' => []],
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $rawLocked = $data['locked_keys'] ?? [];
        $tokens = BootstrapTokenProfile::fromArray(is_array($data['tokens'] ?? null) ? $data['tokens'] : []);
        $typography = is_array($data['typography'] ?? null)
            ? TypographyProfile::fromArray($data['typography'])
            : TypographyProfile::fromTokens($tokens->toArray());

        return new self(
            $tokens,
            BootstrapBreakpoints::fromArray(is_array($data['breakpoints'] ?? null) ? $data['breakpoints'] : []),
            $typography,
            [
                'tokens' => is_array($rawLocked['tokens'] ?? null) ? array_values($rawLocked['tokens']) : [],
                'typography' => is_array($rawLocked['typography'] ?? null) ? array_values($rawLocked['typography']) : [],
            ],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $tokens = $this->tokens->toArray();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'tokens' => $tokens ?: new \stdClass(),
            'typography' => $this->typography->toArray(),
            'breakpoints' => $this->breakpoints->toArray(),
            'locked_keys' => $this->lockedKeys,
        ];
    }

    public function tokens(): BootstrapTokenProfile { return $this->tokens; }
    public function breakpoints(): BootstrapBreakpoints { return $this->breakpoints; }
    public function typography(): TypographyProfile { return $this->typography; }

    /** @return array{tokens: string[], typography: string[]} */
    public function lockedKeys(): array { return $this->lockedKeys; }

    /**
     * @param array<string, mixed> $lockedKeys
     * @return array{tokens: string[], typography: string[]}
     */
    private static function normalizeLockedKeys(array $lockedKeys): array
    {
        $normalize = static function (mixed $keys): array {
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
        };

        return [
            'tokens' => $normalize($lockedKeys['tokens'] ?? []),
            'typography' => $normalize($lockedKeys['typography'] ?? []),
        ];
    }
}

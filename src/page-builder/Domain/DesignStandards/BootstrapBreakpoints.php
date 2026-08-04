<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

/**
 * Bootstrap 5 responsive breakpoints — read-only metadata.
 *
 * Breakpoints are NOT injected as CSS custom properties (Bootstrap uses
 * Sass-compiled media queries). They are stored alongside the token profile
 * so the AI agent knows the responsive contract.
 *
 * Immutable value object.
 */
final class BootstrapBreakpoints
{
    /** @var array<string, int> Breakpoint name → min-width in px */
    private const DEFAULTS = [
        'sm'  => 576,
        'md'  => 768,
        'lg'  => 992,
        'xl'  => 1200,
        'xxl' => 1400,
    ];

    /** @param array<string, int> $breakpoints */
    public function __construct(
        private readonly array $breakpoints,
    ) {}

    public static function defaults(): self
    {
        return new self(self::DEFAULTS);
    }

    /** @param array<string, int|string> $data */
    public static function fromArray(array $data): self
    {
        $breakpoints = [];
        foreach ($data as $name => $value) {
            if (is_string($name) && (is_int($value) || is_numeric($value))) {
                $breakpoints[$name] = (int) $value;
            }
        }

        return new self($breakpoints ?: self::DEFAULTS);
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return $this->breakpoints;
    }

    /** @return array<string, int> */
    public function all(): array
    {
        return $this->breakpoints;
    }

    public function get(string $name): ?int
    {
        return $this->breakpoints[$name] ?? null;
    }
}

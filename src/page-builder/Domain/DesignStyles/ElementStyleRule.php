<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStyles;

/**
 * One Page Builder-owned element style rule.
 *
 * Element customization is stored as structured target identity plus
 * declarations. CSS selectors are compiled later from the owning section id.
 */
final class ElementStyleRule
{
    /** @var string[] */
    public const VALID_KINDS = ['block', 'inline'];

    /** @var string[] */
    public const VALID_VIEWPORTS = ['desktop', 'tablet', 'mobile'];

    /** @var string[] */
    public const VALID_STATES = ['normal', 'hover', 'focus', 'active'];

    /**
     * @param array<string, string> $declarations
     */
    public function __construct(
        private readonly string $elementId,
        private readonly string $kind,
        private readonly string $viewport,
        private readonly string $state,
        private readonly array $declarations,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $elementId = self::readString($data['element_id'] ?? null);
        $kind = self::readString($data['kind'] ?? null) ?: 'block';
        $viewport = self::readString($data['viewport'] ?? null) ?: 'desktop';
        $state = self::readString($data['state'] ?? null) ?: 'normal';
        $declarations = is_array($data['declarations'] ?? null) ? $data['declarations'] : [];

        if (
            !self::isValidElementId($elementId)
            || !in_array($kind, self::VALID_KINDS, true)
            || !in_array($viewport, self::VALID_VIEWPORTS, true)
            || !in_array($state, self::VALID_STATES, true)
        ) {
            return null;
        }

        $cleanDeclarations = [];
        foreach ($declarations as $property => $value) {
            if (!is_string($property) || !is_string($value)) {
                continue;
            }

            $property = strtolower(trim($property));
            if ($property === '') {
                continue;
            }

            $cleanDeclarations[$property] = $value;
        }

        return new self($elementId, $kind, $viewport, $state, $cleanDeclarations);
    }

    public static function isValidElementId(string $id): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $id) === 1;
    }

    public function elementId(): string { return $this->elementId; }
    public function kind(): string { return $this->kind; }
    public function viewport(): string { return $this->viewport; }
    public function state(): string { return $this->state; }

    /**
     * @return array<string, string>
     */
    public function declarations(): array
    {
        return $this->declarations;
    }

    /**
     * @param array<string, string> $declarations
     */
    public function withDeclarations(array $declarations): self
    {
        return new self($this->elementId, $this->kind, $this->viewport, $this->state, $declarations);
    }

    public function withElementId(string $elementId): self
    {
        if (!self::isValidElementId($elementId)) {
            throw new \InvalidArgumentException('Element style ID is invalid.');
        }

        return new self($elementId, $this->kind, $this->viewport, $this->state, $this->declarations);
    }

    /**
     * @return array{element_id: string, kind: string, viewport: string, state: string, declarations: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'element_id'   => $this->elementId,
            'kind'         => $this->kind,
            'viewport'     => $this->viewport,
            'state'        => $this->state,
            'declarations' => $this->declarations,
        ];
    }

    public function key(): string
    {
        return implode('|', [$this->kind, $this->elementId, $this->viewport, $this->state]);
    }

    private static function readString(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        return is_int($value) || is_float($value) ? trim((string) $value) : '';
    }
}

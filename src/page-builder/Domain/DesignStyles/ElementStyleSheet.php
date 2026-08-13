<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStyles;

/**
 * Section-owned user customization rules.
 *
 * This is the durable element style source. Generated section CSS stays in
 * SectionContent::css(); user element customization lives here.
 */
final class ElementStyleSheet
{
    /** @var ElementStyleRule[] */
    private array $rules;

    /**
     * @param ElementStyleRule[] $rules
     */
    public function __construct(array $rules = [])
    {
        $this->rules = [];
        foreach ($rules as $rule) {
            if ($rule instanceof ElementStyleRule && $rule->declarations() !== []) {
                $this->rules[$rule->key()] = $rule;
            }
        }
    }

    public static function empty(): self
    {
        return new self();
    }

    public static function fromJson(?string $json): self
    {
        $json = is_string($json) ? trim($json) : '';
        if ($json === '') {
            return self::empty();
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return self::empty();
        }

        return self::fromArray($decoded);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawRules = is_array($data['rules'] ?? null) ? $data['rules'] : [];
        $rules = [];

        foreach ($rawRules as $rawRule) {
            if (!is_array($rawRule)) {
                continue;
            }

            $rule = ElementStyleRule::fromArray($rawRule);
            if ($rule instanceof ElementStyleRule) {
                $rules[] = $rule;
            }
        }

        return new self($rules);
    }

    /**
     * @param array<string, string> $declarations
     */
    public function withRule(
        string $elementId,
        string $kind,
        string $viewport,
        string $state,
        array $declarations,
    ): self {
        $next = new self($this->all());
        $rule = ElementStyleRule::fromArray([
            'element_id'   => $elementId,
            'kind'         => $kind,
            'viewport'     => $viewport,
            'state'        => $state,
            'declarations' => $declarations,
        ]);

        if (!$rule instanceof ElementStyleRule) {
            return $next;
        }

        $existing = $next->rules[$rule->key()] ?? null;
        $merged = $existing instanceof ElementStyleRule
            ? array_merge($existing->declarations(), $rule->declarations())
            : $rule->declarations();

        $next->rules[$rule->key()] = $rule->withDeclarations($merged);

        return $next;
    }

    /**
     * @param array<string, true> $elementIds
     */
    public function pruneMissingElementIds(array $elementIds, string $html): self
    {
        /*
         * Empty inventories are ambiguous: the section may truly have no ids, or
         * the repair pass may have failed to prove which ids still exist. Keep
         * harmless orphan selectors rather than deleting user-owned style source.
         */
        if ($elementIds === []) {
            return new self($this->all());
        }

        $rules = [];
        foreach ($this->rules as $rule) {
            if (
                isset($elementIds[$rule->elementId()])
                || self::htmlContainsElementId($html, $rule->elementId())
            ) {
                $rules[] = $rule;
            }
        }

        return new self($rules);
    }

    /**
     * @return ElementStyleRule[]
     */
    public function all(): array
    {
        return array_values($this->rules);
    }

    /**
     * @return ElementStyleRule[]
     */
    public function rulesForElementId(string $elementId): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (ElementStyleRule $rule): bool => $rule->elementId() === $elementId,
        ));
    }

    /**
     * Keep durable style targets aligned when copied HTML receives new IDs.
     *
     * @param array<string, string> $elementIds old ID => new ID
     */
    public function remapElementIds(array $elementIds): self
    {
        if ($elementIds === []) {
            return new self($this->all());
        }

        return new self(array_map(
            static fn (ElementStyleRule $rule): ElementStyleRule => isset($elementIds[$rule->elementId()])
                ? $rule->withElementId($elementIds[$rule->elementId()])
                : $rule,
            $this->all(),
        ));
    }

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    /**
     * @return array{version: int, rules: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'version' => 1,
            'rules'   => array_map(static fn(ElementStyleRule $rule): array => $rule->toArray(), $this->all()),
        ];
    }

    public function toJson(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        return (string) json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
    }

    private static function htmlContainsElementId(string $html, string $elementId): bool
    {
        return str_contains($html, 'id="' . $elementId . '"')
            || str_contains($html, "id='" . $elementId . "'");
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Binding;

/**
 * Explicit contract for which part of a dynamic region is really dynamic.
 *
 * Derived from a declaration's existing fields (see fromDeclarationData()),
 * with an optional per-declaration override via the `region_contract` key in
 * declaration.json. Consumers (renderer, normalizers, code-editor tokenizer,
 * agent guides) read this instead of re-encoding per-binding special cases.
 */
final class RegionContract
{
    /** @param list<string> $wrapperAttributes Query attributes living on the region element. */
    public function __construct(
        public readonly RegionReplaces $replaces,
        public readonly RegionTemplate $template,
        public readonly array $wrapperAttributes = [],
    ) {}

    /**
     * Derive the contract from declaration fields, honoring an optional
     * explicit override array (the parsed `region_contract` JSON key).
     *
     * @param array<string, array> $queryAttributes
     * @param array<string, mixed> $override
     */
    public static function derive(
        string $type,
        string $outputShape,
        array $queryAttributes,
        array $override = [],
    ): self {
        if ($type === 'card') {
            $replaces = RegionReplaces::Children;
            $template = RegionTemplate::FirstChild;
        } elseif ($outputShape === 'conditional') {
            $replaces = RegionReplaces::SelfElement;
            $template = RegionTemplate::None;
        } elseif ($outputShape === 'url') {
            $replaces = RegionReplaces::HostAttribute;
            $template = RegionTemplate::None;
        } else {
            $replaces = RegionReplaces::Children;
            $template = RegionTemplate::None;
        }

        if (is_string($override['replaces'] ?? null)) {
            $replaces = RegionReplaces::tryFrom($override['replaces']) ?? $replaces;
        }
        if (is_string($override['template'] ?? null)) {
            $template = RegionTemplate::tryFrom($override['template']) ?? $template;
        }

        return new self($replaces, $template, array_values(array_map('strval', array_keys($queryAttributes))));
    }

    /**
     * True when a bare region carries nothing beyond the binding id: the
     * renderer replaces all children, there is no authored template, and no
 * wrapper attributes. Only these regions may collapse to a code-editor
     * token losslessly.
     */
    public function isFullyProjected(): bool
    {
        return $this->replaces === RegionReplaces::Children
            && $this->template === RegionTemplate::None
            && $this->wrapperAttributes === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'replaces' => $this->replaces->value,
            'template' => $this->template->value,
            'wrapper_attributes' => $this->wrapperAttributes,
            'fully_projected' => $this->isFullyProjected(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Binding;

/**
 * Immutable value object describing a single dynamic binding.
 *
 * Each binding folder contains a declaration.json that maps to one instance.
 * The registry holds all loaded declarations for the request lifecycle.
 */
final class BindingDeclaration
{
    /**
     * @param string               $id              Unique binding identifier (e.g. "wp_query").
     * @param string               $title           Human-readable title for agent guide listing.
     * @param string               $summary         One-line summary for agent guide listing.
     * @param string               $type            "card" (looping) or "self_rendering" (singular).
     * @param string               $rendererClass   FQCN of the renderer class.
     * @param array<string, array>  $queryAttributes Keyed by data-* attribute name. Each value: {required, default, cast}.
     * @param list<string>          $bindKeys        Allowed data-ai-bind keys (empty for self-rendering).
     * @param bool                 $metaBindings    Whether meta.* bind keys are allowed.
     * @param string               $guidePath       Absolute path to the guide.md file.
     * @param list<string>          $tags            Searchable keyword tags for discovery.
     * @param string               $description     Extended description (optional).
     * @param bool                 $termsBindings   Whether terms.<taxonomy> bind keys are allowed.
     * @param BindingStaticSafety   $staticSafety    Whether this binding can be frozen into a saved artifact.
     * @param string                $outputShape     Machine-readable output contract for self-rendering bindings.
     * @param array<string, mixed>  $regionContractOverride Raw `region_contract` override from declaration.json.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $type,
        public readonly string $rendererClass,
        public readonly array $queryAttributes,
        public readonly array $bindKeys,
        public readonly bool $metaBindings,
        public readonly string $guidePath,
        public readonly array $tags = [],
        public readonly string $description = '',
        public readonly bool $termsBindings = false,
        public readonly BindingStaticSafety $staticSafety = BindingStaticSafety::NotStatic,
        public readonly string $outputShape = 'html',
        /** @var array<string, mixed> Raw `region_contract` override from declaration.json. */
        public readonly array $regionContractOverride = [],
    ) {}

    public function regionContract(): RegionContract
    {
        return RegionContract::derive(
            $this->type,
            $this->outputShape,
            $this->queryAttributes,
            $this->regionContractOverride,
        );
    }

    public function isCard(): bool
    {
        return $this->type === 'card';
    }

    public function isSelfRendering(): bool
    {
        return $this->type === 'self_rendering';
    }

    /**
     * Get required query attribute names.
     *
     * @return list<string>
     */
    public function requiredQueryAttributes(): array
    {
        $required = [];
        foreach ($this->queryAttributes as $attr => $config) {
            if (!empty($config['required'])) {
                $required[] = $attr;
            }
        }
        return $required;
    }

    public function canFreezeIntoArtifact(): bool
    {
        return $this->staticSafety->canFreeze();
    }
}

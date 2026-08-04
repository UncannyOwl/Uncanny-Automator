<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Immutable binding-contract snapshot for one dynamic region in a section.
 */
final class SectionBindingContract
{
    /**
     * @param array<string, bool|int|string> $queryAttributes
     * @param string[] $bindKeys
     * @param array<int, array<string, mixed>> $bindings
     */
    public function __construct(
        private readonly string $bindingId,
        private readonly string $source,
        private readonly string $path,
        private readonly array $queryAttributes,
        private readonly array $bindKeys,
        private readonly array $bindings,
        private readonly string $templateHtml,
        private readonly string $contractHash,
    ) {}

    public function bindingId(): string
    {
        return $this->bindingId;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function contractHash(): string
    {
        return $this->contractHash;
    }

    /** @return array<string, bool|int|string> */
    public function queryAttributes(): array
    {
        return $this->queryAttributes;
    }

    /** @return string[] */
    public function bindKeys(): array
    {
        return $this->bindKeys;
    }

    /** @return array<int, array<string, mixed>> */
    public function bindings(): array
    {
        return $this->bindings;
    }

    public function templateHtml(): string
    {
        return $this->templateHtml;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'binding_id' => $this->bindingId,
            'source' => $this->source,
            'path' => $this->path,
            'query_attributes' => $this->queryAttributes,
            'bind_keys' => $this->bindKeys,
            'bindings' => $this->bindings,
            'template_html' => $this->templateHtml,
            'contract_hash' => $this->contractHash,
        ];
    }
}

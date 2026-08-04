<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * A single editable field update within an update_editables proposal.
 */
final class EditableUpdate
{
    private function __construct(
        private readonly string $key,
        private readonly string $type,
        private readonly ?string $textValue,
        private readonly ?string $urlValue,
        private readonly ?string $srcValue,
        private readonly ?string $altValue,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $key = $data['key'] ?? '';
        if (!is_string($key) || trim($key) === '') {
            throw new \InvalidArgumentException('Each editable update must have a non-empty "key".');
        }
        $key = trim($key);

        $type = $data['type'] ?? '';
        if (!is_string($type) || trim($type) === '') {
            throw new \InvalidArgumentException('Each editable update must have a non-empty "type".');
        }

        $allowedTypes = ['text', 'textarea', 'link', 'image', 'bg-image'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new \InvalidArgumentException("Editable type \"{$type}\" is not allowed. Must be one of: " . implode(', ', $allowedTypes) . '.');
        }

        $textValue = isset($data['text_value']) && is_string($data['text_value']) ? $data['text_value'] : null;
        $urlValue  = isset($data['url_value']) && is_string($data['url_value']) ? $data['url_value'] : null;
        $srcValue  = isset($data['src_value']) && is_string($data['src_value']) ? $data['src_value'] : null;
        $altValue  = isset($data['alt_value']) && is_string($data['alt_value']) ? $data['alt_value'] : null;

        return new self(
            key: trim($key),
            type: $type,
            textValue: $textValue,
            urlValue: $urlValue,
            srcValue: $srcValue,
            altValue: $altValue,
        );
    }

    public function key(): string       { return $this->key; }
    public function type(): string      { return $this->type; }
    public function textValue(): ?string { return $this->textValue; }
    public function urlValue(): ?string  { return $this->urlValue; }
    public function srcValue(): ?string  { return $this->srcValue; }
    public function altValue(): ?string  { return $this->altValue; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = ['key' => $this->key, 'type' => $this->type];
        if ($this->textValue !== null) {
            $data['text_value'] = $this->textValue; }
        if ($this->urlValue !== null) {
            $data['url_value'] = $this->urlValue; }
        if ($this->srcValue !== null) {
            $data['src_value'] = $this->srcValue; }
        if ($this->altValue !== null) {
            $data['alt_value'] = $this->altValue; }
        return $data;
    }
}

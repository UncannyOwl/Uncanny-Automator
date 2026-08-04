<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

/**
 * One pending design style change to commit.
 *
 * A token change (page/global) carries a property name. Element changes
 * additionally carry a stable selector and source locator. Boring data holder;
 * validation lives in the commit handlers.
 */
final class DesignStyleChange
{
    public function __construct(
        private readonly string $property,
        private readonly string $value,
        private readonly string $bucket = 'tokens',
        private readonly ?string $selector = null,
        private readonly ?string $sourcePath = null,
        private readonly ?string $identity = null,
        private readonly ?string $tag = null,
        private readonly ?string $elementId = null,
        private readonly string $kind = 'block',
        private readonly string $viewport = 'desktop',
        private readonly string $state = 'normal',
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $token = is_array($data['token'] ?? null) ? $data['token'] : [];
        $target = is_array($data['target'] ?? null) ? $data['target'] : [];
        $bucket = self::readString($token['bucket'] ?? null);

        $property = self::readString($data['property'] ?? ($token['token_name'] ?? null));
        return new self(
            property: $property,
            value: self::readString($data['value'] ?? null),
            bucket: $bucket !== '' ? $bucket : 'tokens',
            selector: self::readNullableString($target['selector'] ?? null),
            sourcePath: self::readNullableString($target['source_path'] ?? ($target['sourcePath'] ?? null)),
            identity: self::readNullableString($target['identity'] ?? null),
            tag: self::readNullableString($target['tag'] ?? null),
            elementId: self::readNullableString($target['element_id'] ?? ($target['elementId'] ?? null)),
            kind: self::readString($target['kind'] ?? null) ?: 'block',
            viewport: self::readString($data['viewport'] ?? null) ?: 'desktop',
            state: self::readString($data['state'] ?? null) ?: 'normal',
        );
    }

    public function property(): string
    {
        return $this->property;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function bucket(): string
    {
        return $this->bucket;
    }

    public function selector(): ?string
    {
        return $this->selector;
    }

    public function sourcePath(): ?string
    {
        return $this->sourcePath;
    }

    public function identity(): ?string
    {
        return $this->identity;
    }

    public function tag(): ?string
    {
        return $this->tag;
    }

    public function elementId(): ?string
    {
        return $this->elementId;
    }

    public function stableSelector(): ?string
    {
        if (is_string($this->elementId) && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $this->elementId) === 1) {
            return '#' . $this->elementId;
        }

        return $this->selector;
    }

    public function kind(): string
    {
        return $this->kind === 'inline' ? 'inline' : 'block';
    }

    public function viewport(): string
    {
        return $this->viewport;
    }

    public function state(): string
    {
        return $this->state;
    }

    private static function readString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : '';
    }

    private static function readNullableString(mixed $value): ?string
    {
        $string = self::readString($value);

        return $string === '' ? null : $string;
    }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class EditableUpdateException extends \RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $editableKey,
        private readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public static function keyNotFound(string $key): self
    {
        return new self(
            "Editable key '{$key}' does not exist in the stored section.",
            editableKey: $key,
            reason: 'key_not_found',
        );
    }

    public static function typeMismatch(string $key, string $expected, string $actual): self
    {
        return new self(
            "Editable '{$key}' has type '{$expected}' in stored HTML but proposal sent type '{$actual}'.",
            editableKey: $key,
            reason: 'type_mismatch',
        );
    }

    public static function duplicateKey(string $key): self
    {
        return new self(
            "Editable key '{$key}' matches multiple elements in the stored section. Use replace_source instead.",
            editableKey: $key,
            reason: 'duplicate_key',
        );
    }

    public static function hasNestedMarkup(string $key): self
    {
        return new self(
            "Editable '{$key}' contains nested HTML elements. Use replace_source to update text in elements with inner markup.",
            editableKey: $key,
            reason: 'nested_markup',
        );
    }

    public function editableKey(): string { return $this->editableKey; }
    public function reason(): string      { return $this->reason; }
}

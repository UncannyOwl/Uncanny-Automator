<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class SectionValidationException extends \RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $rule,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function multipleRoots(int $count): self
    {
        return new self(
            "Section HTML has {$count} root elements; exactly one is required.",
            rule: 'multiple_roots',
            context: ['root_count' => $count],
        );
    }

    public static function noRoot(): self
    {
        return new self(
            'Section HTML has no root element.',
            rule: 'no_root',
        );
    }

    public static function forbiddenTag(string $tag): self
    {
        return new self(
            "Section HTML contains a forbidden tag: <{$tag}>.",
            rule: 'forbidden_tag',
            context: ['tag' => $tag],
        );
    }

    public static function forbiddenAttribute(string $attribute, string $tag): self
    {
        return new self(
            "Element <{$tag}> has a forbidden inline event handler: '{$attribute}'.",
            rule: 'forbidden_attribute',
            context: ['attribute' => $attribute, 'tag' => $tag],
        );
    }

    public static function dynamicSourceInvalid(string $source): self
    {
        return new self(
            "Dynamic region has unsupported source: '{$source}'. "
            . "Call list_bindings to search for the correct binding ID, "
            . "then call get_binding_guide to learn its required attributes before using it.",
            rule: 'dynamic_source_invalid',
            context: ['source' => $source],
        );
    }

    public static function dynamicMissingTemplate(string $path): self
    {
        return new self(
            "Dynamic region at '{$path}' has no direct element child (card template).",
            rule: 'dynamic_missing_template',
            context: ['path' => $path],
        );
    }

    public static function dynamicExtraTemplates(string $path, int $count): self
    {
        return new self(
            "Dynamic region at '{$path}' has {$count} direct element children; exactly one is required.",
            rule: 'dynamic_extra_templates',
            context: ['path' => $path, 'count' => $count],
        );
    }

    public static function dynamicMissingAttribute(string $path, string $attribute): self
    {
        return new self(
            "Dynamic region at '{$path}' is missing required attribute '{$attribute}'.",
            rule: 'dynamic_missing_attribute',
            context: ['path' => $path, 'attribute' => $attribute],
        );
    }

    public static function dynamicInvalidAttribute(string $path, string $attribute, string $value): self
    {
        return new self(
            "Dynamic region at '{$path}' has invalid attribute '{$attribute}' with value '{$value}'.",
            rule: 'dynamic_invalid_attribute',
            context: ['path' => $path, 'attribute' => $attribute, 'value' => $value],
        );
    }

    public static function bindKeyInvalid(string $key): self
    {
        return new self(
            "Bind key '{$key}' is not in the supported vocabulary. "
            . "Call list_bindings to find the correct binding, "
            . "then call get_binding_guide to see the allowed bind keys.",
            rule: 'bind_key_invalid',
            context: ['key' => $key],
        );
    }

    public static function htmlParseError(string $detail): self
    {
        return new self(
            "Section HTML failed parse validation: {$detail}",
            rule: 'html_parse_error',
            context: ['detail' => $detail],
        );
    }

    /** @param string[] $names */
    public static function invalidLucideIcons(array $names): self
    {
        $names = array_values(array_unique($names));
        $label = implode(
            ', ',
            array_map(
                static fn (string $name): string => trim($name) === '' ? '[blank]' : "'{$name}'",
                $names,
            ),
        );

        return new self(
            "Section HTML references unsupported Lucide icon name(s): {$label}. "
            . 'Use valid Lucide icon names such as arrow-right, sparkles, check, star, or heart.',
            rule: 'invalid_lucide_icon',
            context: ['icons' => $names],
        );
    }

    public static function manifestExtractionFailed(string $reason): self
    {
        return new self(
            "Cannot re-extract manifest from result: {$reason}",
            rule: 'manifest_extraction_failed',
            context: ['reason' => $reason],
        );
    }

    public function rule(): string { return $this->rule; }
    public function context(): array { return $this->context; }
}

<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

final class BindingContractUpdateException extends \InvalidArgumentException
{
    private function __construct(
        private readonly string $reason,
        private readonly string $detail,
    ) {
        parent::__construct($detail);
    }

    public static function bindingNotFound(string $bindingId): self
    {
        return new self(
            'binding_not_found',
            sprintf('The binding contract "%s" does not exist in this section.', $bindingId),
        );
    }

    public static function contractHashMismatch(string $bindingId): self
    {
        return new self(
            'contract_hash_mismatch',
            sprintf('The binding contract "%s" changed since it was inspected. Fetch the latest contract and retry.', $bindingId),
        );
    }

    public static function invalidTemplateSyntax(): self
    {
        return new self(
            'invalid_template_syntax',
            'Replacement template HTML cannot contain {{...}} template syntax.',
        );
    }

    public static function invalidRoot(): self
    {
        return new self(
            'invalid_root',
            'Replacement template HTML must contain exactly one root element.',
        );
    }

    public static function forbiddenTag(string $tag): self
    {
        return new self(
            'forbidden_tag',
            sprintf('Replacement template HTML contains forbidden <%s> markup.', $tag),
        );
    }

    public static function nestedDynamic(): self
    {
        return new self(
            'nested_dynamic',
            'Replacement template HTML must not contain nested data-ai-dynamic regions.',
        );
    }

    public static function forbiddenDynamicAttribute(string $attribute): self
    {
        return new self(
            'forbidden_dynamic_attribute',
            sprintf('Replacement template HTML must not set dynamic wrapper attribute "%s".', $attribute),
        );
    }

    public static function forbiddenEventHandler(string $attribute, string $tag): self
    {
        return new self(
            'forbidden_event_handler',
            sprintf('Replacement template HTML contains forbidden inline event handler "%s" on <%s>.', $attribute, $tag),
        );
    }

    public static function missingBindings(): self
    {
        return new self(
            'missing_bindings',
            'Replacement template HTML must include at least one node with data-ai-bind on the root or a descendant.',
        );
    }

    public static function invalidBindKey(string $key, string $source): self
    {
        return new self(
            'invalid_bind_key',
            sprintf(
                'Bind key "%s" is not valid for dynamic source "%s". '
                . 'Call list_bindings to find the correct binding, '
                . 'then call get_binding_guide to see the allowed bind keys.',
                $key,
                $source,
            ),
        );
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function detail(): string
    {
        return $this->detail;
    }
}
